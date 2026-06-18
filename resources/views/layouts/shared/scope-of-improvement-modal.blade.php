{{--
    Shared "Add Scope of Improvement" quick-entry modal.

    Included once from the main layout so the modal is available on every page.
    Opened from the topbar Ideas (lightbulb) and Activity (running person)
    buttons. Uses its own scoped IDs (#soiTopbarModal, ...) so it does NOT
    collide with the inline modal on the Scope of Improvement index page.

    Exposes a tiny global API: window.ScopeOfImprovementTopbarModal.open(mode, row).
--}}
@auth
    @php
        $__soiTopbarUsers = \App\Models\User::orderBy('name')->get(['id', 'name']);
        $__soiTopbarCurrentUserName = \Illuminate\Support\Facades\Auth::user()->name ?? '';
    @endphp

    <style>
        .soi-topbar-history-box {
            max-height: 180px;
            overflow-y: auto;
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .soi-topbar-history-item {
            font-size: 0.8rem;
            padding: 3px 0;
            border-bottom: 1px dashed #e5e7eb;
        }
        .soi-topbar-history-item:last-child { border-bottom: none; }

        /* Left-anchored, full-height sheet (20% of viewport width). */
        #soiTopbarModal .modal-dialog {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            margin: 0;
            width: 20vw;
            max-width: 20vw;
            min-width: 320px;
            height: 100vh;
            max-height: 100vh;
            display: flex;
        }
        #soiTopbarModal .modal-content {
            width: 100%;
            height: 100vh;
            max-height: 100vh;
            border-radius: 0;
            display: flex;
            flex-direction: column;
        }
        #soiTopbarModal .modal-body { flex: 1 1 auto; overflow-y: auto; }
        @media (max-width: 767.98px) {
            #soiTopbarModal .modal-dialog {
                width: 100vw;
                max-width: 100vw;
            }
        }

        /* "Earn monthly Increments" themed modal header */
        #soiTopbarModal .modal-content { border: none; border-radius: 0; overflow: hidden; }
        #soiTopbarModal .soi-earn-header {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 55%, #166534 100%);
            color: #fff;
            border-bottom: 3px solid #facc15;
            padding: 14px 18px;
        }
        #soiTopbarModal .soi-earn-header .modal-title {
            color: #fff;
            font-weight: 700;
            font-size: 1.02rem;
            line-height: 1.25;
        }
        #soiTopbarModal .soi-earn-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.85;
        }
        #soiTopbarModal .soi-earn-header .btn-close:hover { opacity: 1; }
        #soiTopbarModal .soi-rupee-badge {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            background: radial-gradient(circle at 30% 30%, #fde68a 0%, #facc15 55%, #d97706 100%);
            color: #5b3a05;
            border-radius: 50%;
            font-size: 1.15rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.25), inset 0 -2px 4px rgba(0, 0, 0, 0.12);
            margin-right: 12px;
            flex-shrink: 0;
        }
        #soiTopbarModal .soi-rupee-badge .soi-rupee-symbol {
            position: absolute;
            bottom: -4px;
            right: -4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: #fff;
            color: #16a34a;
            border-radius: 50%;
            font-size: 0.78rem;
            font-weight: 800;
            border: 2px solid #16a34a;
            line-height: 1;
        }

        /* Multi-select combobox for the Issue field */
        .soi-issue-combo { position: relative; }
        .soi-issue-control {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            padding: 5px 8px;
            min-height: 38px;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            cursor: text;
        }
        .soi-issue-control:focus-within {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }
        .soi-issue-input {
            flex: 1;
            min-width: 140px;
            border: 0;
            padding: 4px 2px;
            background: transparent;
            outline: none;
            font-size: 0.875rem;
            color: #212529;
        }
        .soi-issue-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 4px 3px 8px;
            background: #e7f1ff;
            color: #0d6efd;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 600;
            line-height: 1.2;
            max-width: 100%;
        }
        .soi-issue-chip__emoji { font-size: 0.95rem; line-height: 1; }
        .soi-issue-chip.is-critical { background: #fee2e2; color: #991b1b; }
        .soi-issue-chip__remove {
            background: transparent;
            border: 0;
            color: inherit;
            cursor: pointer;
            padding: 0 4px;
            font-size: 14px;
            line-height: 1;
            border-radius: 50%;
        }
        .soi-issue-chip__remove:hover { background: rgba(0, 0, 0, 0.08); }

        .soi-issue-panel {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 1080;
            max-height: 300px;
            display: none;
            flex-direction: column;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 6px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }
        .soi-issue-panel.is-open { display: flex; }
        .soi-issue-list {
            list-style: none;
            padding: 6px 0;
            margin: 0;
            overflow-y: auto;
            max-height: 290px;
        }
        .soi-issue-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 14px;
            font-size: 13.5px;
            line-height: 1.25;
            cursor: pointer;
            user-select: none;
        }
        .soi-issue-item:hover,
        .soi-issue-item.is-active { background: #f1f5f9; }
        .soi-issue-item.soi-issue-create {
            background: #f0fdf4;
            color: #166534;
            border-bottom: 1px dashed #bbf7d0;
            font-weight: 600;
        }
        .soi-issue-item.soi-issue-create:hover { background: #dcfce7; }
        .soi-issue-item__check {
            width: 18px;
            height: 18px;
            border: 1.5px solid #adb5bd;
            border-radius: 4px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            background: #fff;
            color: transparent;
            line-height: 1;
        }
        .soi-issue-item.is-selected .soi-issue-item__check {
            background: #16a34a;
            border-color: #16a34a;
            color: #fff;
        }
        .soi-issue-emoji {
            font-size: 1.15rem;
            flex-shrink: 0;
            width: 22px;
            text-align: center;
        }
        .soi-issue-empty {
            padding: 10px 14px;
            font-size: 13px;
            color: #6c757d;
            font-style: italic;
        }
        .soi-issue-label { flex: 1; min-width: 0; }
        .soi-issue-item--critical { background: #fef2f2; color: #991b1b; }
        .soi-issue-item--critical:hover { background: #fee2e2; }
        .soi-issue-critical-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            background: #dc2626;
            color: #fff;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            border-radius: 10px;
            margin-left: 8px;
            flex-shrink: 0;
        }

        /* "See All" circular runner button in modal footer */
        .soi-see-all-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #16a34a;
            color: #fff;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.45);
            border: none;
            text-decoration: none;
            font-size: 1.35rem;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .soi-see-all-btn:hover {
            background: #15803d;
            color: #fff;
            transform: scale(1.08);
            text-decoration: none;
        }
        .soi-see-all-btn:focus { color: #fff; outline: none; box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.3); }
    </style>

    <div class="modal fade" id="soiTopbarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <form class="modal-content" id="soiTopbarForm">
                @csrf
                <div class="modal-header soi-earn-header">
                    <div class="d-flex align-items-center">
                        <span class="soi-rupee-badge" aria-hidden="true">
                            <i class="fas fa-sack-dollar"></i>
                            <span class="soi-rupee-symbol">&#8377;</span>
                        </span>
                        <h5 class="modal-title mb-0" id="soiTopbarModalTitle">Earn monthly Increments by fixing Scope of Improvement</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="soi_topbar_id" name="id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="soi_topbar_user_id" class="form-label">For User <span class="text-danger">*</span></label>
                            <select name="user_id" id="soi_topbar_user_id" class="form-select" required>
                                <option value="">Select user</option>
                                @foreach ($__soiTopbarUsers as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="soi_topbar_s_by" class="form-label">Suggested By</label>
                            <input type="text" id="soi_topbar_s_by" class="form-control bg-light"
                                value="{{ $__soiTopbarCurrentUserName }}" readonly>
                        </div>
                        <div class="col-12">
                            <label for="soi_topbar_issue_input" class="form-label">
                                Issues
                                <span class="text-muted fw-normal">(Suggestor Only — select one or more)</span>
                            </label>
                            <div class="soi-issue-combo" id="soi_topbar_issue_combo">
                                <div class="soi-issue-control" id="soi_topbar_issue_control">
                                    <span class="soi-issue-chips" id="soi_topbar_issue_chips"></span>
                                    <input type="text" id="soi_topbar_issue_input"
                                        class="soi-issue-input"
                                        placeholder="Search a common issue or type your own…"
                                        autocomplete="off">
                                </div>
                                <div class="soi-issue-panel" id="soi_topbar_issue_panel">
                                    <ul class="soi-issue-list" id="soi_topbar_issue_list"></ul>
                                </div>
                                <div id="soi_topbar_issues_hidden" style="display:none;"></div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="soi_topbar_root_cause" class="form-label">
                                Root Cause
                                <span class="text-muted fw-normal">(entered by <span class="soi-topbar-for-user-label">User</span>)</span>
                            </label>
                            <textarea name="root_cause" id="soi_topbar_root_cause" class="form-control" rows="2" placeholder="What is the root cause?"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="soi_topbar_fixing_root_cause" class="form-label">
                                Root Cause Fixed by <span class="soi-topbar-for-user-label">User</span>
                            </label>
                            <textarea name="fixing_root_cause" id="soi_topbar_fixing_root_cause" class="form-control" rows="2" placeholder="How will the root cause be fixed?"></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="form-label mb-1"><i class="fas fa-history me-1"></i> History Update</label>
                        <div class="soi-topbar-history-box" id="soiTopbarHistoryBox">
                            <div class="text-muted soi-topbar-history-item">No history yet.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <a href="{{ route('scope-of-improvement.index') }}" class="soi-see-all-btn" title="See All Issues" aria-label="See All Issues">
                        <i class="fas fa-person-running"></i>
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="soiTopbarSaveBtn">Save</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ---- Shared Issue combobox (multi-select + create + remaining filter)
        // Exposed on window so the inline modal on the Scope of Improvement
        // index page can reuse the same predefined list + behavior.
        (function () {
            if (window.SoiIssueCombo) return;

            const ISSUES = [
                { label: 'Under hour attendance',                 emoji: '⏰' },
                { label: 'Pending Task Overdue',                  emoji: '📋' },
                { label: 'Not assigning any task',                emoji: '🚫' },
                { label: 'Communications / Follow-up issues',     emoji: '💬' },
                { label: 'Carelessness',                          emoji: '😴' },
                { label: 'Skill Issues',                          emoji: '🎯' },
                { label: 'Not accepting Automations',             emoji: '🤖' },
                { label: 'Not following Instructions',            emoji: '📝' },
                { label: 'Not Asking For Help',                   emoji: '🙋' },
                { label: 'Not Helping Colleagues or Juniors',     emoji: '🤝' },
                { label: 'Not escalating issues',                 emoji: '📢' },
                { label: 'Not Raising Flags',                     emoji: '🚩' },
                { label: 'Misleading Reports / Statements',       emoji: '📊' },
                { label: 'Misutilizing attendance',               emoji: '🕒', critical: true },
                { label: 'Marking Done Without Doing Fully',      emoji: '🚨', critical: true },
            ];

            function escapeHtml(s) {
                const d = document.createElement('div');
                d.textContent = (s == null ? '' : String(s));
                return d.innerHTML;
            }

            function setup(opts) {
                const combo  = opts.combo;
                const input  = opts.input;     // typing input
                const panel  = opts.panel;
                const list   = opts.list;
                const chips  = opts.chips;     // chips container
                const hidden = opts.hidden;    // hidden inputs container (for issues[])
                const hiddenName = opts.hiddenName || 'issues[]';
                const onChange = typeof opts.onChange === 'function' ? opts.onChange : null;
                if (!combo || !input || !panel || !list) return null;

                const selected = new Set();   // current selections
                const excludes = new Set();   // labels filtered out (already submitted etc.)

                function emit() { if (onChange) onChange(Array.from(selected)); }

                function renderChips() {
                    if (!chips) return;
                    if (selected.size === 0) {
                        chips.innerHTML = '';
                        return;
                    }
                    let html = '';
                    selected.forEach(function (label) {
                        const meta = ISSUES.find(function (i) { return i.label === label; });
                        const isCritical = meta && meta.critical;
                        const emoji = meta ? meta.emoji : '✏️';
                        html += '<span class="soi-issue-chip' + (isCritical ? ' is-critical' : '') + '" data-value="' + escapeHtml(label) + '">'
                            + '<span class="soi-issue-chip__emoji">' + emoji + '</span>'
                            + '<span>' + escapeHtml(label) + '</span>'
                            + '<button type="button" class="soi-issue-chip__remove" aria-label="Remove">×</button>'
                            + '</span>';
                    });
                    chips.innerHTML = html;
                }

                function renderHidden() {
                    if (!hidden) return;
                    let html = '';
                    selected.forEach(function (label) {
                        html += '<input type="hidden" name="' + escapeHtml(hiddenName) + '" value="' + escapeHtml(label) + '">';
                    });
                    hidden.innerHTML = html;
                }

                function syncOut() { renderChips(); renderHidden(); emit(); }

                function renderList() {
                    const q = (input.value || '').trim();
                    const lowered = q.toLowerCase();
                    const matches = ISSUES.filter(function (i) {
                        if (excludes.has(i.label)) return false;
                        return !lowered || i.label.toLowerCase().indexOf(lowered) !== -1;
                    });
                    const exactMatch = ISSUES.some(function (i) {
                        return i.label.toLowerCase() === lowered;
                    }) || selected.has(q);

                    let html = '';
                    if (q && !exactMatch) {
                        html += '<li class="soi-issue-item soi-issue-create" data-value="' + escapeHtml(q) + '">'
                            + '<span class="soi-issue-item__check">✓</span>'
                            + '<span class="soi-issue-emoji">➕</span>'
                            + '<span class="soi-issue-label">Create: <strong>' + escapeHtml(q) + '</strong></span>'
                            + '</li>';
                    }
                    if (matches.length === 0 && !q) {
                        html += '<li class="soi-issue-empty">No remaining issues.</li>';
                    }
                    matches.forEach(function (i) {
                        const isSel = selected.has(i.label);
                        let cls = 'soi-issue-item';
                        if (i.critical) cls += ' soi-issue-item--critical';
                        if (isSel) cls += ' is-selected';
                        const badge = i.critical ? '<span class="soi-issue-critical-badge">Critical</span>' : '';
                        html += '<li class="' + cls + '" data-value="' + escapeHtml(i.label) + '">'
                            + '<span class="soi-issue-item__check">✓</span>'
                            + '<span class="soi-issue-emoji">' + i.emoji + '</span>'
                            + '<span class="soi-issue-label">' + escapeHtml(i.label) + '</span>'
                            + badge
                            + '</li>';
                    });
                    list.innerHTML = html;
                }

                function open()  {
                    if (input.readOnly || input.disabled) return;
                    panel.classList.add('is-open');
                    renderList();
                }
                function close() { panel.classList.remove('is-open'); }

                function toggle(label) {
                    if (!label) return;
                    if (selected.has(label)) selected.delete(label);
                    else selected.add(label);
                    syncOut();
                    renderList();
                }

                input.addEventListener('focus', open);
                input.addEventListener('click', open);
                input.addEventListener('input', function () {
                    panel.classList.add('is-open');
                    renderList();
                });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') { close(); return; }
                    if (e.key === 'Enter') {
                        const q = (input.value || '').trim();
                        if (q) {
                            e.preventDefault();
                            toggle(q);
                            input.value = '';
                            renderList();
                        }
                    }
                    if (e.key === 'Backspace' && !input.value && selected.size) {
                        const last = Array.from(selected).pop();
                        toggle(last);
                    }
                });

                list.addEventListener('mousedown', function (e) {
                    const li = e.target.closest('.soi-issue-item');
                    if (!li) return;
                    e.preventDefault();
                    const label = li.getAttribute('data-value') || '';
                    toggle(label);
                    input.value = '';
                });

                if (chips) {
                    chips.addEventListener('click', function (e) {
                        const btn = e.target.closest('.soi-issue-chip__remove');
                        if (!btn) return;
                        const chip = btn.closest('.soi-issue-chip');
                        if (!chip) return;
                        toggle(chip.getAttribute('data-value'));
                    });
                }

                // Click on the control wrapper focuses the typing input.
                combo.addEventListener('mousedown', function (e) {
                    if (e.target.closest('.soi-issue-panel')) return;
                    if (e.target.closest('.soi-issue-chip__remove')) return;
                    if (e.target === input) return;
                    if (e.target.closest('.soi-issue-chip')) return;
                    setTimeout(function () { input.focus(); }, 0);
                });

                document.addEventListener('mousedown', function (e) {
                    if (!combo.contains(e.target)) close();
                });

                // Public API for the modal to call.
                return {
                    setSelections: function (arr) {
                        selected.clear();
                        (arr || []).filter(Boolean).forEach(function (l) { selected.add(String(l)); });
                        input.value = '';
                        syncOut();
                        renderList();
                    },
                    getSelections: function () { return Array.from(selected); },
                    setExcludes: function (arr) {
                        excludes.clear();
                        (arr || []).filter(Boolean).forEach(function (l) { excludes.add(String(l)); });
                        renderList();
                    },
                    clearExcludes: function () { excludes.clear(); renderList(); },
                    close: close,
                    open:  open,
                };
            }

            window.SoiIssueCombo = { setup: setup, ISSUES: ISSUES };
        })();

        $(function () {
            const modalEl = document.getElementById('soiTopbarModal');
            if (!modalEl || typeof bootstrap === 'undefined') return;

            const csrf       = '{{ csrf_token() }}';
            const storeUrl   = @json(route('scope-of-improvement.store'));
            const updateBase = @json(url('scope-of-improvement/update'));

            // Wire the multi-select Issue combobox for this modal.
            const issueCombo = window.SoiIssueCombo.setup({
                combo:  document.getElementById('soi_topbar_issue_combo'),
                input:  document.getElementById('soi_topbar_issue_input'),
                panel:  document.getElementById('soi_topbar_issue_panel'),
                list:   document.getElementById('soi_topbar_issue_list'),
                chips:  document.getElementById('soi_topbar_issue_chips'),
                hidden: document.getElementById('soi_topbar_issues_hidden'),
                hiddenName: 'issues[]',
            });

            const userIssuesUrlBase = @json(url('scope-of-improvement/user-issues'));
            const userSelect = document.getElementById('soi_topbar_user_id');

            function updateTopbarForUserLabel() {
                const opt = userSelect && userSelect.options[userSelect.selectedIndex];
                const name = (userSelect && userSelect.value && opt) ? (opt.text || '').trim() : '';
                const text = name || 'User';
                modalEl.querySelectorAll('.soi-topbar-for-user-label').forEach(function (el) {
                    el.textContent = text;
                });
            }

            if (userSelect) {
                userSelect.addEventListener('change', function () {
                    updateTopbarForUserLabel();

                    if (!issueCombo) return;
                    const uid = userSelect.value;
                    if (!uid) { issueCombo.clearExcludes(); return; }
                    $.getJSON(userIssuesUrlBase + '/' + encodeURIComponent(uid), function (res) {
                        const existing = (res && Array.isArray(res.issues)) ? res.issues : [];
                        const editingId = document.getElementById('soi_topbar_id').value;
                        // When editing an existing row, keep that row's issue
                        // available so the user can re-pick or change it.
                        const editingIssue = editingId ? (modalEl.__editingIssue || '') : '';
                        issueCombo.setExcludes(existing.filter(function (l) { return l !== editingIssue; }));
                    }).fail(function () {
                        issueCombo.clearExcludes();
                    });
                });
            }

            const modal = new bootstrap.Modal(modalEl);
            const form  = document.getElementById('soiTopbarForm');
            let progressMode = false;

            function esc(s) {
                const d = document.createElement('div');
                d.textContent = (s == null ? '' : String(s));
                return d.innerHTML;
            }

            function renderHistory(history) {
                const box = document.getElementById('soiTopbarHistoryBox');
                if (!history || !history.length) {
                    box.innerHTML = '<div class="text-muted soi-topbar-history-item">No history yet.</div>';
                    return;
                }
                box.innerHTML = history.slice().reverse().map(function (h) {
                    return '<div class="soi-topbar-history-item">' +
                        '<strong>' + esc(h.email || '—') + '</strong> ' +
                        '<span class="badge bg-soft-secondary text-secondary">' + esc(h.action || '') + '</span> ' +
                        '<span class="text-muted">' + esc(h.at || '') + '</span>' +
                        '</div>';
                }).join('');
            }

            function applyProgressLock(on) {
                const userSel = document.getElementById('soi_topbar_user_id');
                const issue   = document.getElementById('soi_topbar_issue_input');
                if (userSel) userSel.disabled = on;
                if (issue) {
                    issue.readOnly = on;
                    if (on) issue.classList.add('bg-light');
                    else issue.classList.remove('bg-light');
                }
            }

            const currentUserName = @json($__soiTopbarCurrentUserName);

            function openModal(mode, row) {
                form.reset();
                mode = mode || (row && row.id ? 'edit' : 'add');
                progressMode = (mode === 'progress');

                if (row && row.id) {
                    document.getElementById('soiTopbarModalTitle').textContent =
                        progressMode ? 'My Progress — Update' : 'Edit Scope of Improvement';
                    document.getElementById('soi_topbar_id').value = row.id;
                    document.getElementById('soi_topbar_user_id').value = row.user_id || '';
                    document.getElementById('soi_topbar_root_cause').value = row.root_cause || '';
                    document.getElementById('soi_topbar_fixing_root_cause').value = row.fixing_root_cause || '';
                    document.getElementById('soi_topbar_s_by').value = row.s_by || currentUserName;
                    modalEl.__editingIssue = row.issue || '';
                    if (issueCombo) issueCombo.setSelections(row.issue ? [row.issue] : []);
                    renderHistory(row.history);
                } else {
                    document.getElementById('soiTopbarModalTitle').textContent =
                        'Earn monthly Increments by fixing Scope of Improvement';
                    document.getElementById('soi_topbar_id').value = '';
                    document.getElementById('soi_topbar_user_id').value = '';
                    document.getElementById('soi_topbar_s_by').value = currentUserName;
                    modalEl.__editingIssue = '';
                    if (issueCombo) {
                        issueCombo.setSelections([]);
                        issueCombo.clearExcludes();
                    }
                    renderHistory([]);
                }

                updateTopbarForUserLabel();
                applyProgressLock(progressMode);
                modal.show();
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const id  = document.getElementById('soi_topbar_id').value;
                const url = id ? (updateBase + '/' + id) : storeUrl;
                const btn = document.getElementById('soiTopbarSaveBtn');
                const rootCause = document.getElementById('soi_topbar_root_cause').value;
                const fixingRootCause = document.getElementById('soi_topbar_fixing_root_cause').value;

                let payload;
                if (progressMode) {
                    payload = {
                        _token: csrf,
                        root_cause: rootCause,
                        fixing_root_cause: fixingRootCause,
                    };
                } else if (id) {
                    // Edit mode — single row, keep the first selected issue.
                    const userId = document.getElementById('soi_topbar_user_id').value;
                    if (!userId) { alert('Please select a user.'); return; }
                    const selections = issueCombo ? issueCombo.getSelections() : [];
                    payload = {
                        _token: csrf,
                        user_id: userId,
                        issue: selections[0] || '',
                        root_cause: rootCause,
                        fixing_root_cause: fixingRootCause,
                    };
                } else {
                    // Add mode — may create multiple rows (one per selected issue).
                    const userId = document.getElementById('soi_topbar_user_id').value;
                    if (!userId) { alert('Please select a user.'); return; }
                    const typed = (document.getElementById('soi_topbar_issue_input').value || '').trim();
                    let selections = issueCombo ? issueCombo.getSelections() : [];
                    if (typed && selections.indexOf(typed) === -1) selections.push(typed);
                    if (selections.length === 0) {
                        alert('Please select or type at least one issue.');
                        return;
                    }
                    payload = {
                        _token: csrf,
                        user_id: userId,
                        issues: selections,
                        root_cause: rootCause,
                        fixing_root_cause: fixingRootCause,
                    };
                }

                btn.disabled = true;
                $.post(url, payload, function () {
                    modal.hide();
                    if (window.__soiTable && typeof window.__soiTable.replaceData === 'function') {
                        window.__soiTable.replaceData();
                    }
                }).fail(function (xhr) {
                    let msg = 'Something went wrong.';
                    if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                    alert(msg);
                }).always(function () {
                    btn.disabled = false;
                });
            });

            window.ScopeOfImprovementTopbarModal = { open: openModal };

            function openAddForm() { openModal('add'); }

            const ideaBtn = document.getElementById('ideaTopbarBtn');
            if (ideaBtn) ideaBtn.addEventListener('click', openAddForm);
            const activityBtn = document.getElementById('activityTopbarBtn');
            if (activityBtn) activityBtn.addEventListener('click', openAddForm);
        });
    </script>
@endauth
