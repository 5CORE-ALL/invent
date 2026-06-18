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

        /* Quick search / create combobox for the Issue field */
        .soi-issue-combo { position: relative; }
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
        .soi-issue-item.is-active {
            background: #f1f5f9;
        }
        .soi-issue-item.soi-issue-create {
            background: #f0fdf4;
            color: #166534;
            border-bottom: 1px dashed #bbf7d0;
            font-weight: 600;
        }
        .soi-issue-item.soi-issue-create:hover { background: #dcfce7; }
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
        .soi-issue-item--critical {
            background: #fef2f2;
            color: #991b1b;
        }
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
                            <label for="soi_topbar_issue" class="form-label">
                                Issue
                                <span class="text-muted fw-normal">(Suggestor Only)</span>
                            </label>
                            <div class="soi-issue-combo">
                                <input type="text" name="issue" id="soi_topbar_issue"
                                    class="form-control soi-issue-input"
                                    placeholder="Search a common issue or type your own…"
                                    autocomplete="off">
                                <div class="soi-issue-panel" id="soi_topbar_issue_panel">
                                    <ul class="soi-issue-list" id="soi_topbar_issue_list"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label for="soi_topbar_root_cause" class="form-label">
                                Root Cause
                                <span class="text-muted fw-normal">(entered by {{ $__soiTopbarCurrentUserName }})</span>
                            </label>
                            <textarea name="root_cause" id="soi_topbar_root_cause" class="form-control" rows="2" placeholder="What is the root cause?"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="soi_topbar_fixing_root_cause" class="form-label">
                                Fixing Root Cause
                                <span class="text-muted fw-normal">(entered by {{ $__soiTopbarCurrentUserName }})</span>
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
        // ---- Shared Issue combobox (quick search + create) -----------------
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

            function render(listEl, query) {
                const q = (query || '').trim();
                const lowered = q.toLowerCase();
                const matches = ISSUES.filter(function (i) {
                    return !lowered || i.label.toLowerCase().indexOf(lowered) !== -1;
                });
                const exactMatch = ISSUES.some(function (i) {
                    return i.label.toLowerCase() === lowered;
                });

                let html = '';
                if (q && !exactMatch) {
                    html += '<li class="soi-issue-item soi-issue-create" data-value="' + escapeHtml(q) + '">'
                        + '<span class="soi-issue-emoji">➕</span>'
                        + '<span>Create: <strong>' + escapeHtml(q) + '</strong></span>'
                        + '</li>';
                }
                if (matches.length === 0 && !q) {
                    html += '<li class="soi-issue-empty">No issues defined.</li>';
                } else if (matches.length === 0 && q) {
                    // The "Create" row is already shown above.
                } else {
                    matches.forEach(function (i) {
                        const itemClass = i.critical ? 'soi-issue-item soi-issue-item--critical' : 'soi-issue-item';
                        const badge = i.critical ? '<span class="soi-issue-critical-badge">Critical</span>' : '';
                        html += '<li class="' + itemClass + '" data-value="' + escapeHtml(i.label) + '">'
                            + '<span class="soi-issue-emoji">' + i.emoji + '</span>'
                            + '<span class="soi-issue-label">' + escapeHtml(i.label) + '</span>'
                            + badge
                            + '</li>';
                    });
                }
                listEl.innerHTML = html;
            }

            function setup(opts) {
                const combo = opts.combo;
                const input = opts.input;
                const panel = opts.panel;
                const list  = opts.list;
                if (!combo || !input || !panel || !list) return;

                function open()  {
                    if (input.readOnly || input.disabled) return;
                    panel.classList.add('is-open');
                    render(list, input.value);
                }
                function close() { panel.classList.remove('is-open'); }

                input.addEventListener('focus', open);
                input.addEventListener('click', open);
                input.addEventListener('input', function () {
                    panel.classList.add('is-open');
                    render(list, input.value);
                });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') close();
                });

                list.addEventListener('mousedown', function (e) {
                    // Use mousedown so we don't lose focus before handling click.
                    const li = e.target.closest('.soi-issue-item');
                    if (!li) return;
                    e.preventDefault();
                    input.value = li.getAttribute('data-value') || '';
                    close();
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                document.addEventListener('mousedown', function (e) {
                    if (!combo.contains(e.target)) close();
                });
            }

            window.SoiIssueCombo = { setup: setup, render: render, ISSUES: ISSUES };
        })();

        $(function () {
            const modalEl = document.getElementById('soiTopbarModal');
            if (!modalEl || typeof bootstrap === 'undefined') return;

            const csrf       = '{{ csrf_token() }}';
            const storeUrl   = @json(route('scope-of-improvement.store'));
            const updateBase = @json(url('scope-of-improvement/update'));

            // Wire the Issue combobox for this modal.
            window.SoiIssueCombo.setup({
                combo: modalEl.querySelector('.soi-issue-combo'),
                input: document.getElementById('soi_topbar_issue'),
                panel: document.getElementById('soi_topbar_issue_panel'),
                list:  document.getElementById('soi_topbar_issue_list'),
            });

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
                const issue   = document.getElementById('soi_topbar_issue');
                userSel.disabled = on;
                issue.readOnly = on;
                if (on) issue.classList.add('bg-light');
                else issue.classList.remove('bg-light');
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
                    document.getElementById('soi_topbar_issue').value = row.issue || '';
                    document.getElementById('soi_topbar_root_cause').value = row.root_cause || '';
                    document.getElementById('soi_topbar_fixing_root_cause').value = row.fixing_root_cause || '';
                    document.getElementById('soi_topbar_s_by').value = row.s_by || currentUserName;
                    renderHistory(row.history);
                } else {
                    document.getElementById('soiTopbarModalTitle').textContent =
                        'Earn monthly Increments by fixing Scope of Improvement';
                    document.getElementById('soi_topbar_id').value = '';
                    document.getElementById('soi_topbar_user_id').value = '';
                    document.getElementById('soi_topbar_s_by').value = currentUserName;
                    renderHistory([]);
                }

                applyProgressLock(progressMode);
                modal.show();
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                const id  = document.getElementById('soi_topbar_id').value;
                const url = id ? (updateBase + '/' + id) : storeUrl;
                const btn = document.getElementById('soiTopbarSaveBtn');

                let payload;
                if (progressMode) {
                    payload = {
                        _token: csrf,
                        root_cause: document.getElementById('soi_topbar_root_cause').value,
                        fixing_root_cause: document.getElementById('soi_topbar_fixing_root_cause').value,
                    };
                } else {
                    const userId = document.getElementById('soi_topbar_user_id').value;
                    if (!userId) {
                        alert('Please select a user.');
                        return;
                    }
                    payload = {
                        _token: csrf,
                        user_id: userId,
                        issue: document.getElementById('soi_topbar_issue').value,
                        root_cause: document.getElementById('soi_topbar_root_cause').value,
                        fixing_root_cause: document.getElementById('soi_topbar_fixing_root_cause').value,
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
