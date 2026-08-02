/**
 * Shared Amz CVR Audit modal logic (same APIs/tables as /amz-cvr-issues).
 *
 * Set window.AmzCvrAuditBridge before DOM ready:
 *   {
 *     getTable: () => tabulatorInstance,
 *     rowSku: (row) => string,
 *     rowParent: (row) => string,
 *     rowInv: (row) => number,
 *     rowViews: (row) => number,
 *     rowCvr: (row) => number,   // percent
 *     rowPrice: (row) => number,
 *     getSelectedSkus: () => string[]  // optional bulk
 *   }
 */
(function () {
    'use strict';

    function bridge() {
        return window.AmzCvrAuditBridge || {};
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return (window.AMZ_CVR_CSRF || (meta && meta.getAttribute('content')) || '');
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    var TASK_STORE_URL = '/tasks';
    var HISTORY_STORE_URL = '/amz-cvr-issues/audit-history';
    var ISSUE_TYPES_URL = '/amz-cvr-issues/issue-types';
    var ISSUE_TYPES_STORE_URL = '/amz-cvr-issues/issue-types';
    var ISSUE_TYPES_DESTROY_URL = '/amz-cvr-issues/issue-types';

    var ISSUE_ASSIGNEES = {};
    var customIssues = [];
    var auditUsers = null;

    function sortHistoryLatestFirst(hist) {
        return (Array.isArray(hist) ? hist.slice() : []).sort(function (a, b) {
            return (parseInt(b.sort_ts, 10) || 0) - (parseInt(a.sort_ts, 10) || 0)
                || (parseInt(b.id, 10) || 0) - (parseInt(a.id, 10) || 0);
        });
    }

    function rowSku(row) {
        var b = bridge();
        if (typeof b.rowSku === 'function') return String(b.rowSku(row) || '').trim();
        return String((row && (row.sku || row['(Child) sku'])) || '').trim();
    }

    function applyHistoryToRow(sku, history) {
        var table = typeof bridge().getTable === 'function' ? bridge().getTable() : null;
        if (!table || !history || !sku) return;
        var rows = table.getRows() || [];
        for (var i = 0; i < rows.length; i++) {
            var rd = rows[i].getData();
            if (rowSku(rd) !== sku) continue;
            var hist = Array.isArray(rd.audit_history) ? rd.audit_history.slice() : [];
            hist.unshift(history);
            var sorted = sortHistoryLatestFirst(hist).slice(0, 10);
            var latest = sorted[0] || history;
            var dates = [];
            sorted.forEach(function (h) {
                if (h.date_key && dates.indexOf(h.date_key) === -1) dates.push(h.date_key);
            });
            rows[i].update({
                audit_history: sorted,
                audit_history_latest: latest,
                audit_history_ts: parseInt(latest.sort_ts, 10) || 0,
                audit_history_dates: dates
            });
            break;
        }
    }

    function getSkuCvr(sku) {
        var table = typeof bridge().getTable === 'function' ? bridge().getTable() : null;
        if (!table || !sku) return null;
        try {
            var rows = table.getRows() || [];
            for (var i = 0; i < rows.length; i++) {
                var rd = rows[i].getData();
                if (rowSku(rd) !== sku) continue;
                var b = bridge();
                var cvr = typeof b.rowCvr === 'function' ? b.rowCvr(rd) : (rd.avg_cvr || rd.CVR_L30);
                cvr = parseFloat(cvr);
                return isFinite(cvr) ? Math.round(cvr * 100) / 100 : null;
            }
        } catch (e) { /* ignore */ }
        return null;
    }

    function storeAuditHistory(sku, taskCount) {
        var body = new FormData();
        body.append('_token', csrf());
        body.append('sku', sku);
        body.append('task_count', String(taskCount));
        var cvr = getSkuCvr(sku);
        if (cvr !== null && isFinite(cvr)) body.append('cvr_l30', String(cvr));
        return fetch(HISTORY_STORE_URL, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf()
            },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json().catch(function () { return null; }); })
            .then(function (data) {
                if (data && data.history) applyHistoryToRow(sku, data.history);
                return data;
            })
            .catch(function () { return null; });
    }

    function getAuditUsers() {
        if (Array.isArray(auditUsers)) return auditUsers;
        try {
            var el = document.getElementById('quick-assignee-users-data');
            if (el && el.textContent) {
                auditUsers = JSON.parse(el.textContent) || [];
                return auditUsers;
            }
        } catch (e) { /* ignore */ }
        auditUsers = [];
        return auditUsers;
    }

    function defaultTaskTitle(sku, label) {
        return (sku || 'SKU') + ' — ' + (label || 'Issue');
    }

    function collectTaskRowState() {
        var prev = {};
        document.querySelectorAll('.amz-cvr-task-row').forEach(function (row) {
            var key = row.getAttribute('data-issue');
            if (!key) return;
            prev[key] = {
                title: (row.querySelector('.amz-cvr-task-title') || {}).value || '',
                group: (row.querySelector('.amz-cvr-task-group') || {}).value || '',
                assigneeId: (row.querySelector('.amz-cvr-task-assignee-id') || {}).value || '',
                assigneeName: (row.querySelector('.amz-cvr-task-assignee-search') || {}).value || ''
            };
        });
        return prev;
    }

    function resetIssueOptions() {
        document.querySelectorAll('.amz-cvr-issue-opt').forEach(function (cb) { cb.checked = false; });
        var otherText = document.getElementById('amzCvrAuditIssueOtherText');
        if (otherText) otherText.value = '';
        var otherWrap = document.getElementById('amzCvrAuditIssueOtherWrap');
        if (otherWrap) otherWrap.classList.add('d-none');
        var wrap = document.getElementById('amzCvrAuditTaskRows');
        if (wrap) wrap.innerHTML = '<div class="small text-muted">Select one or more issues to create task inputs.</div>';
    }

    function syncModalHeight() {
        var modalEl = document.getElementById('amzCvrAuditModal');
        if (!modalEl) return;
        var dialog = modalEl.querySelector('.modal-dialog');
        if (!dialog) return;
        requestAnimationFrame(function () {
            var needsScroll = dialog.scrollHeight > (window.innerHeight - 2);
            modalEl.classList.toggle('amz-cvr-audit-tall', needsScroll);
        });
    }

    function selectedIssueKeys() {
        var keys = [];
        document.querySelectorAll('.amz-cvr-issue-opt:checked').forEach(function (cb) {
            keys.push(cb.value);
        });
        return keys;
    }

    function hideAssigneeDropdowns() {
        document.querySelectorAll('.amz-cvr-task-assignee-dd').forEach(function (dd) {
            dd.classList.add('d-none');
            dd.innerHTML = '';
        });
    }

    function renderAssigneeDropdownForRow(key, query) {
        var dd = document.querySelector('.amz-cvr-task-assignee-dd[data-issue="' + key + '"]');
        if (!dd) return;
        var q = String(query || '').toLowerCase().trim();
        var users = getAuditUsers().filter(function (u) {
            if (!q) return true;
            return String(u.name || '').toLowerCase().indexOf(q) !== -1;
        }).slice(0, 25);
        if (!users.length) {
            dd.innerHTML = '<div class="list-group-item small text-muted">No users</div>';
            dd.classList.remove('d-none');
            return;
        }
        dd.innerHTML = users.map(function (u) {
            return '<button type="button" class="list-group-item list-group-item-action amz-cvr-assignee-opt py-1 px-2"'
                + ' data-issue="' + esc(key) + '" data-id="' + esc(u.id) + '" data-name="' + esc(u.name) + '">'
                + esc(u.name) + '</button>';
        }).join('');
        dd.classList.remove('d-none');
    }

    function renderTaskRows() {
        var wrap = document.getElementById('amzCvrAuditTaskRows');
        if (!wrap) return;
        var sku = (document.getElementById('amzCvrAuditSkuInput') || {}).value || '';
        sku = String(sku).trim();
        var prev = collectTaskRowState();
        var keys = selectedIssueKeys();
        if (!keys.length) {
            wrap.innerHTML = '<div class="small text-muted">Select one or more issues to create task inputs.</div>';
            return;
        }
        wrap.innerHTML = keys.map(function (key) {
            var isOther = key === 'other';
            var meta = isOther
                ? { label: 'Other Issue', email: '', user_id: null, name: null }
                : (ISSUE_ASSIGNEES[key] || { label: key, email: '', user_id: null, name: null });
            var label = meta.label || key;
            var saved = prev[key] || {};
            var title = saved.title || defaultTaskTitle(sku, label);
            var group = saved.group || 'Amazon';
            var assigneeId = isOther ? (saved.assigneeId || '') : (meta.user_id ? String(meta.user_id) : '');
            var assigneeDisplay = isOther
                ? (saved.assigneeName || '')
                : (meta.name ? (meta.name + ' (' + meta.email + ')') : (meta.email || 'User not found'));
            var assigneeReadonly = !isOther ? ' readonly' : '';
            var missingUser = !isOther && !meta.user_id;
            return ''
                + '<div class="border rounded px-2 py-1 amz-cvr-task-row" data-issue="' + esc(key) + '">'
                +   '<div class="row g-2 align-items-center">'
                +     '<div class="col-12 col-md-2">'
                +       '<div class="fw-semibold small text-truncate" title="' + esc(label) + '">' + esc(label) + '</div>'
                +       (missingUser ? '<small class="text-danger">User not found</small>' : '')
                +     '</div>'
                +     '<div class="col-12 col-md-4">'
                +       '<input type="text" class="form-control form-control-sm amz-cvr-task-title" data-issue="' + esc(key) + '"'
                +         ' value="' + esc(title) + '" maxlength="1000" autocomplete="off" placeholder="Task">'
                +     '</div>'
                +     '<div class="col-12 col-md-2">'
                +       '<input type="text" class="form-control form-control-sm amz-cvr-task-group" data-issue="' + esc(key) + '"'
                +         ' value="' + esc(group) + '" maxlength="100" autocomplete="off" placeholder="Group">'
                +     '</div>'
                +     '<div class="col-12 col-md-4">'
                +       '<div class="position-relative amz-cvr-task-assignee-wrap" data-issue="' + esc(key) + '">'
                +         '<input type="text" class="form-control form-control-sm amz-cvr-task-assignee-search" data-issue="' + esc(key) + '"'
                +           ' value="' + esc(assigneeDisplay) + '" placeholder="' + (isOther ? 'Quick Search assignee...' : 'Auto-assigned') + '"'
                +           assigneeReadonly + ' autocomplete="off">'
                +         '<input type="hidden" class="amz-cvr-task-assignee-id" data-issue="' + esc(key) + '" value="' + esc(assigneeId) + '">'
                +         '<div class="list-group position-absolute w-100 shadow-sm d-none amz-cvr-task-assignee-dd" data-issue="' + esc(key) + '"'
                +           ' style="z-index:1080;max-height:220px;overflow-y:auto;top:100%;left:0;"></div>'
                +       '</div>'
                +     '</div>'
                +   '</div>'
                + '</div>';
        }).join('');
    }

    function syncIssueUi() {
        var otherCb = document.getElementById('amzCvrIssueOther');
        var otherWrap = document.getElementById('amzCvrAuditIssueOtherWrap');
        if (otherWrap) otherWrap.classList.toggle('d-none', !(otherCb && otherCb.checked));
        renderTaskRows();
        syncModalHeight();
    }

    function buildTaskJobs() {
        var jobs = [];
        document.querySelectorAll('.amz-cvr-task-row').forEach(function (row) {
            var key = row.getAttribute('data-issue');
            if (!key) return;
            var title = (row.querySelector('.amz-cvr-task-title') || {}).value || '';
            title = String(title).trim();
            var group = ((row.querySelector('.amz-cvr-task-group') || {}).value || '').trim() || 'Amazon';
            var assigneeId = ((row.querySelector('.amz-cvr-task-assignee-id') || {}).value || '').trim();
            var assigneeLabel = ((row.querySelector('.amz-cvr-task-assignee-search') || {}).value || '').trim();
            if (key === 'other') {
                var otherText = ((document.getElementById('amzCvrAuditIssueOtherText') || {}).value || '').trim();
                jobs.push({
                    key: 'other',
                    label: 'Other Issue',
                    issueText: otherText ? ('Other Issue: ' + otherText) : 'Other Issue',
                    title: title,
                    group: group,
                    assigneeId: assigneeId,
                    assigneeLabel: assigneeLabel || 'manual',
                    manual: true
                });
                return;
            }
            var meta = ISSUE_ASSIGNEES[key];
            if (!meta) return;
            jobs.push({
                key: key,
                label: meta.label,
                issueText: meta.label,
                title: title,
                group: group,
                assigneeId: assigneeId || (meta.user_id ? String(meta.user_id) : ''),
                assigneeLabel: assigneeLabel || meta.email,
                manual: false,
                email: meta.email
            });
        });
        return jobs;
    }

    function createTask(payload) {
        var body = new FormData();
        body.append('_token', csrf());
        body.append('title', payload.title);
        body.append('description', payload.description);
        body.append('group', payload.group || 'Amazon');
        body.append('priority', 'normal');
        body.append('assignee_id', payload.assigneeId);
        body.append('etc_minutes', '10');
        body.append('tid', new Date().toISOString().slice(0, 16));
        return fetch(TASK_STORE_URL, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf()
            },
            body: body,
            credentials: 'same-origin'
        }).then(function (res) {
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            }).catch(function () {
                return { ok: res.ok, status: res.status, data: null };
            });
        });
    }

    function getTargetSkus() {
        var b = bridge();
        if (typeof b.getSelectedSkus === 'function') {
            var sel = b.getSelectedSkus() || [];
            if (sel.length) return sel.map(function (s) { return String(s).trim(); }).filter(Boolean);
        }
        var current = ((document.getElementById('amzCvrAuditSkuInput') || {}).value || '').trim();
        return current ? [current] : [];
    }

    function syncBulkUi() {
        var targets = getTargetSkus();
        var note = document.getElementById('amzCvrAuditBulkNote');
        var label = document.getElementById('amzCvrAuditSubmitLabel');
        var current = ((document.getElementById('amzCvrAuditSkuInput') || {}).value || '').trim();
        if (note) {
            if (targets.length > 1) {
                note.textContent = 'Bulk: applying to ' + targets.length + ' SKUs'
                    + (current ? (' (opened on ' + current + ')') : '') + '.';
                note.classList.remove('d-none');
            } else {
                note.classList.add('d-none');
            }
        }
        if (label) label.textContent = targets.length > 1 ? ('Submit ×' + targets.length) : 'Submit';
    }

    function renderCustomIssueCheckboxes() {
        var host = document.getElementById('amzCvrCustomIssueOptions');
        if (!host) return;
        host.innerHTML = customIssues.map(function (issue) {
            var id = 'amzCvrIssueCustom_' + issue.id;
            return '<div class="form-check mb-0 amz-cvr-custom-issue-check" data-issue-id="' + esc(issue.id) + '">'
                + '<input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="' + esc(issue.key) + '" id="' + esc(id) + '">'
                + '<label class="form-check-label" for="' + esc(id) + '">' + esc(issue.label) + '</label>'
                + '</div>';
        }).join('');
    }

    function renderCustomIssueManageList() {
        var list = document.getElementById('amzCvrCustomIssueList');
        if (!list) return;
        if (!customIssues.length) {
            list.innerHTML = '<div class="small text-muted">No custom issues yet.</div>';
            return;
        }
        list.innerHTML = customIssues.map(function (issue) {
            var who = issue.name
                ? (esc(issue.name) + ' &lt;' + esc(issue.email || '') + '&gt;')
                : esc(issue.email || 'No assignee');
            return '<div class="d-flex align-items-center justify-content-between border rounded px-2 py-1 mb-1">'
                + '<div><div class="fw-semibold">' + esc(issue.label) + '</div><div class="small text-muted">' + who + '</div></div>'
                + '<button type="button" class="btn btn-outline-danger btn-sm amz-cvr-del-issue" data-id="' + esc(issue.id)
                + '" data-key="' + esc(issue.key) + '" title="Remove"><i class="fa fa-trash"></i></button></div>';
        }).join('');
    }

    function registerCustomIssue(issue) {
        if (!issue || !issue.key) return;
        customIssues = customIssues.filter(function (i) { return i.key !== issue.key; });
        customIssues.push(issue);
        ISSUE_ASSIGNEES[issue.key] = {
            id: issue.id || 0,
            label: issue.label || issue.key,
            email: issue.email || '',
            user_id: issue.user_id || null,
            name: issue.name || null,
            custom: true
        };
        renderCustomIssueCheckboxes();
        renderCustomIssueManageList();
    }

    function unregisterCustomIssue(issueKey) {
        if (!issueKey) return;
        customIssues = customIssues.filter(function (i) { return i.key !== issueKey; });
        delete ISSUE_ASSIGNEES[issueKey];
        renderCustomIssueCheckboxes();
        renderCustomIssueManageList();
        syncIssueUi();
    }

    function loadIssueTypes() {
        return fetch(ISSUE_TYPES_URL, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                customIssues = Array.isArray(res.data) ? res.data : [];
                customIssues.forEach(function (issue) {
                    if (!issue || !issue.key) return;
                    ISSUE_ASSIGNEES[issue.key] = {
                        id: issue.id || 0,
                        label: issue.label || issue.key,
                        email: issue.email || '',
                        user_id: issue.user_id || null,
                        name: issue.name || null,
                        custom: true
                    };
                });
                renderCustomIssueCheckboxes();
                renderCustomIssueManageList();
            })
            .catch(function () { /* ignore */ });
    }

    function openAuditModal(row) {
        row = row || {};
        var b = bridge();
        var sku = typeof b.rowSku === 'function' ? b.rowSku(row) : rowSku(row);
        var parent = typeof b.rowParent === 'function' ? b.rowParent(row) : (row.parent || row.Parent || '');
        var inv = typeof b.rowInv === 'function' ? b.rowInv(row) : (row.inventory || row.INV || 0);
        var views = typeof b.rowViews === 'function' ? b.rowViews(row) : (row.total_views || row.Sess30 || 0);
        var cvr = typeof b.rowCvr === 'function' ? b.rowCvr(row) : (row.avg_cvr || row.CVR_L30 || 0);
        var price = typeof b.rowPrice === 'function' ? b.rowPrice(row) : (row.amazon_price || row.price || 0);

        document.getElementById('amzCvrAuditSku').textContent = sku || '—';
        document.getElementById('amzCvrAuditSkuInput').value = sku || '';
        document.getElementById('amzCvrAuditParent').textContent = parent || '—';
        document.getElementById('amzCvrAuditInv').textContent = Math.round(parseFloat(inv) || 0).toLocaleString('en-US');
        document.getElementById('amzCvrAuditViews').textContent = Math.round(parseFloat(views) || 0).toLocaleString('en-US');
        cvr = parseFloat(cvr) || 0;
        document.getElementById('amzCvrAuditCvr').textContent = (Math.round(cvr) + '%');
        price = parseFloat(price) || 0;
        document.getElementById('amzCvrAuditPrice').textContent = price > 0 ? ('$' + price.toFixed(2)) : '—';
        resetIssueOptions();

        var modalEl = document.getElementById('amzCvrAuditModal');
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: true, focus: true }).show();
        setTimeout(function () {
            syncBulkUi();
            syncModalHeight();
        }, 0);
    }

    function initBuiltInAssignees(map) {
        ISSUE_ASSIGNEES = Object.assign({}, map || {});
    }

    function bindEvents() {
        var taskRowsEl = document.getElementById('amzCvrAuditTaskRows');
        taskRowsEl && taskRowsEl.addEventListener('focusin', function (e) {
            var input = e.target.closest('.amz-cvr-task-assignee-search');
            if (!input || input.readOnly) return;
            var key = input.getAttribute('data-issue');
            if (key) renderAssigneeDropdownForRow(key, input.value);
        });
        taskRowsEl && taskRowsEl.addEventListener('input', function (e) {
            var input = e.target.closest('.amz-cvr-task-assignee-search');
            if (!input || input.readOnly) return;
            var key = input.getAttribute('data-issue');
            var idInput = document.querySelector('.amz-cvr-task-assignee-id[data-issue="' + key + '"]');
            if (idInput) idInput.value = '';
            if (key) renderAssigneeDropdownForRow(key, input.value);
        });
        taskRowsEl && taskRowsEl.addEventListener('click', function (e) {
            var opt = e.target.closest('.amz-cvr-assignee-opt');
            if (!opt) return;
            var key = opt.getAttribute('data-issue');
            var idInput = document.querySelector('.amz-cvr-task-assignee-id[data-issue="' + key + '"]');
            var searchInput = document.querySelector('.amz-cvr-task-assignee-search[data-issue="' + key + '"]');
            if (idInput) idInput.value = opt.getAttribute('data-id') || '';
            if (searchInput) searchInput.value = opt.getAttribute('data-name') || '';
            hideAssigneeDropdowns();
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.amz-cvr-task-assignee-wrap')) hideAssigneeDropdowns();
        });

        document.getElementById('amzCvrAuditIssueOptions') && document.getElementById('amzCvrAuditIssueOptions').addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('amz-cvr-issue-opt')) syncIssueUi();
        });

        document.getElementById('amzCvrAddIssueBtn') && document.getElementById('amzCvrAddIssueBtn').addEventListener('click', function () {
            document.getElementById('amzCvrNewIssueLabel').value = '';
            document.getElementById('amzCvrNewIssueAssigneeId').value = '';
            document.getElementById('amzCvrNewIssueAssigneeSearch').value = '';
            renderCustomIssueManageList();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('amzCvrAddIssueModal')).show();
        });

        document.getElementById('amzCvrSaveNewIssueBtn') && document.getElementById('amzCvrSaveNewIssueBtn').addEventListener('click', function () {
            var label = ((document.getElementById('amzCvrNewIssueLabel') || {}).value || '').trim();
            var assigneeId = ((document.getElementById('amzCvrNewIssueAssigneeId') || {}).value || '').trim();
            if (!label) { alert('Please enter an Issue name.'); return; }
            if (!assigneeId) { alert('Please select an Assignee.'); return; }
            var btn = this;
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
            var body = new FormData();
            body.append('_token', csrf());
            body.append('label', label);
            body.append('assignee_user_id', assigneeId);
            fetch(ISSUE_TYPES_STORE_URL, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                body: body,
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (res) {
                    if (!res.ok) {
                        alert((res.data && res.data.message) || 'Could not save issue.');
                        return;
                    }
                    if (res.data && res.data.issue) registerCustomIssue(res.data.issue);
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('amzCvrAddIssueModal')).hide();
                })
                .catch(function () { alert('Could not save issue.'); })
                .finally(function () { btn.disabled = false; btn.innerHTML = orig; });
        });

        document.getElementById('amzCvrCustomIssueList') && document.getElementById('amzCvrCustomIssueList').addEventListener('click', function (e) {
            var delBtn = e.target.closest('.amz-cvr-del-issue');
            if (!delBtn) return;
            var id = delBtn.getAttribute('data-id');
            var key = delBtn.getAttribute('data-key');
            if (!id || !confirm('Remove this custom issue type?')) return;
            fetch(ISSUE_TYPES_DESTROY_URL + '/' + encodeURIComponent(id), {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                .then(function (res) {
                    if (!res.ok) {
                        alert((res.data && res.data.message) || 'Could not remove issue.');
                        return;
                    }
                    unregisterCustomIssue(key || (res.data && res.data.issue_key) || '');
                })
                .catch(function () { alert('Could not remove issue.'); });
        });

        // New-issue assignee search
        var newSearch = document.getElementById('amzCvrNewIssueAssigneeSearch');
        var newDd = document.getElementById('amzCvrNewIssueAssigneeDropdown');
        function renderNewAssigneeDd(q) {
            if (!newDd) return;
            q = String(q || '').toLowerCase().trim();
            var users = getAuditUsers().filter(function (u) {
                return !q || String(u.name || '').toLowerCase().indexOf(q) !== -1;
            }).slice(0, 25);
            newDd.innerHTML = users.map(function (u) {
                return '<button type="button" class="list-group-item list-group-item-action amz-cvr-new-issue-assignee-opt py-1 px-2"'
                    + ' data-id="' + esc(u.id) + '" data-name="' + esc(u.name) + '">' + esc(u.name) + '</button>';
            }).join('') || '<div class="list-group-item small text-muted">No users</div>';
            newDd.classList.remove('d-none');
        }
        newSearch && newSearch.addEventListener('focus', function () { renderNewAssigneeDd(this.value); });
        newSearch && newSearch.addEventListener('input', function () {
            document.getElementById('amzCvrNewIssueAssigneeId').value = '';
            renderNewAssigneeDd(this.value);
        });
        newDd && newDd.addEventListener('click', function (e) {
            var opt = e.target.closest('.amz-cvr-new-issue-assignee-opt');
            if (!opt) return;
            document.getElementById('amzCvrNewIssueAssigneeId').value = opt.getAttribute('data-id') || '';
            document.getElementById('amzCvrNewIssueAssigneeSearch').value = opt.getAttribute('data-name') || '';
            newDd.classList.add('d-none');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#amzCvrNewIssueAssigneeWrap') && newDd) newDd.classList.add('d-none');
        });

        document.getElementById('amzCvrAuditSubmitTaskBtn') && document.getElementById('amzCvrAuditSubmitTaskBtn').addEventListener('click', function () {
            var sourceSku = ((document.getElementById('amzCvrAuditSkuInput') || {}).value || '').trim();
            var targetSkus = getTargetSkus();
            var jobs = buildTaskJobs();
            var otherChecked = !!(document.getElementById('amzCvrIssueOther') || {}).checked;
            var otherText = ((document.getElementById('amzCvrAuditIssueOtherText') || {}).value || '').trim();
            if (!targetSkus.length) { alert('No SKU selected for audit submit.'); return; }
            if (!jobs.length) { alert('Please select at least one Issue found option.'); return; }
            if (otherChecked && !otherText) {
                alert('Please describe the additional issue for Other.');
                document.getElementById('amzCvrAuditIssueOtherText').focus();
                return;
            }
            for (var i = 0; i < jobs.length; i++) {
                var job = jobs[i];
                if (!job.title) {
                    alert('Please enter a Task for ' + job.label + '.');
                    return;
                }
                if (job.manual && !job.assigneeId) {
                    alert('Please select a manual Assignee for Other Issue.');
                    return;
                }
                if (!job.manual && !job.assigneeId) {
                    alert('No Task Manager user found for ' + job.label + '.');
                    return;
                }
            }
            if (targetSkus.length > 1) {
                if (!confirm('Apply this audit to ' + targetSkus.length + ' selected SKUs?')) return;
            }

            var btn = this;
            var orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa fa-spinner fa-spin me-1"></i> Submitting...';

            var chain = Promise.resolve();
            var results = [];
            targetSkus.forEach(function (sku) {
                jobs.forEach(function (job) {
                    chain = chain.then(function () {
                        var title = job.title;
                        if (sourceSku && sku !== sourceSku && title.indexOf(sourceSku) !== -1) {
                            title = title.split(sourceSku).join(sku);
                        }
                        return createTask({
                            title: title,
                            description: ['SKU: ' + sku, 'Issue found: ' + job.issueText, 'Assignee: ' + job.assigneeLabel].join('\n'),
                            group: job.group || 'Amazon',
                            assigneeId: job.assigneeId
                        }).then(function (result) {
                            results.push({ sku: sku, result: result });
                        });
                    });
                });
            });

            chain.then(function () {
                var failed = results.filter(function (r) { return !r.result.ok; });
                var okCount = results.length - failed.length;
                var okBySku = {};
                results.forEach(function (r) {
                    if (!r.result.ok) return;
                    okBySku[r.sku] = (okBySku[r.sku] || 0) + 1;
                });
                var okSkuList = Object.keys(okBySku);
                function finish() {
                    if (!failed.length) {
                        alert(okCount === 1 ? 'Task submitted to Task Manager.' : (okCount + ' tasks submitted.'));
                        resetIssueOptions();
                        syncBulkUi();
                        return;
                    }
                    alert('Created ' + okCount + ' of ' + results.length + ' tasks. Some failed.');
                }
                if (!okSkuList.length) { finish(); return; }
                var histChain = Promise.resolve();
                okSkuList.forEach(function (sku) {
                    histChain = histChain.then(function () {
                        return storeAuditHistory(sku, okBySku[sku]);
                    });
                });
                return histChain.then(finish);
            })
                .catch(function () { alert('Could not create task(s).'); })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    syncBulkUi();
                });
        });

        document.getElementById('amzCvrAuditSaveBtn') && document.getElementById('amzCvrAuditSaveBtn').addEventListener('click', function () {
            var modalEl = document.getElementById('amzCvrAuditModal');
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        });
    }

    function init(options) {
        options = options || {};
        if (options.taskStoreUrl) TASK_STORE_URL = options.taskStoreUrl;
        if (options.historyStoreUrl) HISTORY_STORE_URL = options.historyStoreUrl;
        if (options.builtInAssignees) initBuiltInAssignees(options.builtInAssignees);
        var modalEl = document.getElementById('amzCvrAuditModal');
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        bindEvents();
        loadIssueTypes();
        resetIssueOptions();
    }

    window.AmzCvrAudit = {
        init: init,
        open: openAuditModal,
        applyHistoryToRow: applyHistoryToRow,
        esc: esc
    };
})();
