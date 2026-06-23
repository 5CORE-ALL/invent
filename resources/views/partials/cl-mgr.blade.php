{{--
    CL Mgr (Manager / Senior Checklist) modal for Task Summary.

    Per-designation weighted checkpoints for leadership duties (training,
    auditing, monitoring, assigning, follow-ups, delivery, mentoring, etc).
    Manager picks the juniors they oversee from inside this modal; the
    combined Mgr score blends the manager's own score with the average of
    juniors' (CL R&R + CL Gen)/2 score.

    Consumed from resources/views/tasks/task-summary.blade.php.
--}}

<style>
    #taskSummaryClMgrModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryClMgrModal .modal-header {
        background: linear-gradient(135deg, #1d4ed8, #6366f1);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryClMgrModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryClMgrModal .clmgr-meta {
        font-size: 0.78rem;
        opacity: 0.92;
    }
    #taskSummaryClMgrModal .clmgr-score-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0.55rem;
        margin-top: 0.55rem;
    }
    @media (max-width: 575.98px) {
        #taskSummaryClMgrModal .clmgr-score-grid {
            grid-template-columns: 1fr;
        }
    }
    #taskSummaryClMgrModal .clmgr-score-tile {
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 12px;
        padding: 0.5rem 0.65rem;
        display: flex;
        align-items: center;
        gap: 0.55rem;
    }
    #taskSummaryClMgrModal .clmgr-score-tile.is-combined {
        background: rgba(255, 255, 255, 0.22);
        border-color: #fff;
    }
    #taskSummaryClMgrModal .clmgr-score-tile .pct {
        font-weight: 800;
        font-size: 1.15rem;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        min-width: 44px;
        text-align: center;
    }
    #taskSummaryClMgrModal .clmgr-score-tile .lbl {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-weight: 700;
        line-height: 1.15;
    }
    #taskSummaryClMgrModal .clmgr-score-tile .sub {
        font-size: 0.65rem;
        opacity: 0.85;
    }
    #taskSummaryClMgrModal .clmgr-progress-bar-wrap {
        height: 8px;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        overflow: hidden;
        margin-top: 0.55rem;
    }
    #taskSummaryClMgrModal .clmgr-progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #fbbf24, #34d399);
        width: 0%;
        transition: width 0.35s ease;
    }

    .clmgr-search-icon-btn {
        border: none;
        background: transparent;
        color: #1d4ed8;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .clmgr-search-icon-btn:hover {
        background: rgba(29, 78, 216, 0.12);
        color: #1e3a8a;
        transform: scale(1.1);
    }
    .clmgr-search-icon-btn:focus-visible {
        outline: 2px solid #1d4ed8;
        outline-offset: 2px;
    }

    /* Section tabs (own checkpoints vs juniors) */
    #taskSummaryClMgrModal .clmgr-tabs {
        display: flex;
        gap: 0.25rem;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 0.65rem;
    }
    #taskSummaryClMgrModal .clmgr-tab {
        background: transparent;
        border: 0;
        padding: 0.45rem 0.85rem;
        font-size: 0.82rem;
        font-weight: 600;
        color: #64748b;
        border-bottom: 2px solid transparent;
        cursor: pointer;
    }
    #taskSummaryClMgrModal .clmgr-tab.is-active {
        color: #1d4ed8;
        border-bottom-color: #1d4ed8;
    }

    /* Checkpoint grouping cards */
    #ts-clmgr-list .clmgr-cat-card {
        border: 1px solid #e0e7ff;
        border-radius: 12px;
        padding: 0.6rem 0.8rem 0.45rem 0.8rem;
        margin-bottom: 0.6rem;
        background: #eef2ff;
    }
    #ts-clmgr-list .clmgr-cat-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #3730a3;
        margin-bottom: 0.4rem;
    }
    #ts-clmgr-list .clmgr-cat-header .clmgr-cat-count {
        margin-left: auto;
        font-size: 0.72rem;
        font-weight: 700;
        color: #1d4ed8;
        background: #c7d2fe;
        border: 1px solid #a5b4fc;
        padding: 0.05em 0.45em;
        border-radius: 999px;
        font-variant-numeric: tabular-nums;
        text-transform: none;
        letter-spacing: 0;
    }
    #ts-clmgr-list .clmgr-item {
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
        padding: 0.45rem 0.5rem;
        background: #fff;
        border-radius: 8px;
        margin-bottom: 0.3rem;
        border: 1px solid #c7d2fe;
    }
    #ts-clmgr-list .clmgr-item.is-checked {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    #ts-clmgr-list .clmgr-item .form-check-input {
        margin-top: 0.25rem;
        flex-shrink: 0;
        cursor: pointer;
    }
    #ts-clmgr-list .clmgr-item .form-check-input:checked {
        background-color: #1d4ed8;
        border-color: #1d4ed8;
    }
    #ts-clmgr-list .clmgr-item-body {
        flex-grow: 1;
        min-width: 0;
    }
    #ts-clmgr-list .clmgr-item-title {
        font-size: 0.86rem;
        color: #1f2937;
        line-height: 1.35;
    }
    #ts-clmgr-list .clmgr-item.is-checked .clmgr-item-title {
        text-decoration: line-through;
        color: #15803d;
    }
    #ts-clmgr-list .clmgr-item-desc {
        font-size: 0.72rem;
        color: #64748b;
        margin-top: 0.1rem;
    }
    #ts-clmgr-list .clmgr-source-badge {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.1em 0.4em;
        border-radius: 4px;
        background: #e0e7ff;
        color: #3730a3;
        margin-left: 0.35rem;
        vertical-align: middle;
    }
    #ts-clmgr-list .clmgr-source-badge.is-manual {
        background: #f1f5f9;
        color: #475569;
    }
    #ts-clmgr-list .clmgr-item-actions {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-shrink: 0;
    }
    #ts-clmgr-list .clmgr-weight-input {
        width: 56px;
        font-size: 0.75rem;
        padding: 0.15rem 0.3rem;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
    #ts-clmgr-list .clmgr-delete-btn {
        color: #dc2626;
        background: transparent;
        border: none;
        padding: 0.1rem 0.35rem;
        border-radius: 6px;
    }
    #ts-clmgr-list .clmgr-delete-btn:hover {
        background: rgba(220, 38, 38, 0.1);
    }

    /* Add new checkpoint form */
    #ts-clmgr-add-form {
        background: #eff6ff;
        border: 1px dashed #93c5fd;
        border-radius: 10px;
        padding: 0.65rem 0.75rem;
        margin-top: 0.5rem;
    }
    #ts-clmgr-add-form label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #1e3a8a;
    }
    #ts-clmgr-add-form .form-control {
        font-size: 0.82rem;
    }

    /* Juniors panel */
    #ts-clmgr-juniors-list .clmgr-junior-row {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.55rem 0.65rem;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 0.45rem;
    }
    #ts-clmgr-juniors-list .clmgr-junior-meta {
        flex-grow: 1;
        min-width: 0;
    }
    #ts-clmgr-juniors-list .clmgr-junior-name {
        font-weight: 600;
        font-size: 0.88rem;
        color: #1f2937;
        line-height: 1.2;
    }
    #ts-clmgr-juniors-list .clmgr-junior-des {
        font-size: 0.72rem;
        color: #64748b;
    }
    #ts-clmgr-juniors-list .clmgr-junior-scores {
        display: flex;
        gap: 0.35rem;
        flex-shrink: 0;
        font-size: 0.7rem;
        font-variant-numeric: tabular-nums;
    }
    #ts-clmgr-juniors-list .clmgr-junior-scores .pill {
        padding: 0.1em 0.5em;
        border-radius: 999px;
        font-weight: 700;
        border: 1px solid transparent;
    }
    #ts-clmgr-juniors-list .clmgr-junior-scores .pill.clrr {
        background: #ecfeff;
        color: #0e7490;
        border-color: #a5f3fc;
    }
    #ts-clmgr-juniors-list .clmgr-junior-scores .pill.clgen {
        background: #fef3c7;
        color: #92400e;
        border-color: #fcd34d;
    }
    #ts-clmgr-juniors-list .clmgr-junior-scores .pill.blend {
        background: #e0e7ff;
        color: #1e3a8a;
        border-color: #a5b4fc;
    }
    #ts-clmgr-juniors-list .clmgr-junior-remove {
        background: transparent;
        border: none;
        color: #dc2626;
        padding: 0.1rem 0.35rem;
        border-radius: 6px;
        flex-shrink: 0;
    }
    #ts-clmgr-juniors-list .clmgr-junior-remove:hover {
        background: rgba(220, 38, 38, 0.1);
    }
    #ts-clmgr-add-junior-wrap {
        background: #eff6ff;
        border: 1px dashed #93c5fd;
        border-radius: 10px;
        padding: 0.65rem 0.75rem;
        margin-top: 0.4rem;
    }
    #ts-clmgr-juniors-empty {
        text-align: center;
        padding: 1.5rem;
        color: #94a3b8;
        font-size: 0.85rem;
    }

    #ts-clmgr-empty,
    #ts-clmgr-loading,
    #ts-clmgr-ai-loading {
        text-align: center;
        padding: 2rem 1rem;
        color: #64748b;
    }
    #ts-clmgr-empty i {
        font-size: 2.4rem;
        color: #a5b4fc;
        display: block;
        margin-bottom: 0.5rem;
    }
    #ts-clmgr-ai-loading .spinner-border {
        color: #1d4ed8;
    }
</style>

<div class="modal fade" id="taskSummaryClMgrModal" tabindex="-1" aria-labelledby="taskSummaryClMgrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryClMgrModalLabel">
                            <i class="ri-user-star-line me-2" aria-hidden="true"></i>
                            <span id="ts-clmgr-modal-user">CL Mgr — Manager Checklist</span>
                        </h5>
                        <div class="clmgr-meta">
                            <span id="ts-clmgr-modal-designation"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="clmgr-score-grid">
                    <div class="clmgr-score-tile">
                        <div class="pct" id="ts-clmgr-own-pct">0%</div>
                        <div>
                            <div class="lbl">Own score</div>
                            <div class="sub" id="ts-clmgr-own-sub">0/0 pts</div>
                        </div>
                    </div>
                    <div class="clmgr-score-tile">
                        <div class="pct" id="ts-clmgr-juniors-pct">0%</div>
                        <div>
                            <div class="lbl">Juniors avg</div>
                            <div class="sub" id="ts-clmgr-juniors-sub">0 juniors</div>
                        </div>
                    </div>
                    <div class="clmgr-score-tile is-combined">
                        <div class="pct" id="ts-clmgr-combined-pct">0%</div>
                        <div>
                            <div class="lbl">Combined Mgr</div>
                            <div class="sub" id="ts-clmgr-combined-sub">60% own · 40% juniors</div>
                        </div>
                    </div>
                </div>
                <div class="clmgr-progress-bar-wrap" role="progressbar" aria-label="Combined Mgr score">
                    <div class="clmgr-progress-bar-fill" id="ts-clmgr-progress-fill"></div>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-clmgr-loading" class="d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Loading CL Mgr…</p>
                </div>

                <div id="ts-clmgr-ai-loading" class="d-none">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Generating…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Asking AI to draft the manager checklist for this designation…</p>
                </div>

                <div id="ts-clmgr-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-clmgr-empty" class="d-none">
                    <i class="ri-magic-line" aria-hidden="true"></i>
                    <div class="fw-semibold mb-1">No CL Mgr checklist for this designation yet</div>
                    <p class="small mb-3">
                        Let AI draft a weighted manager-level checklist (training, auditing, monitoring,
                        assigning, follow-ups, on-time delivery…). You can add or remove items afterwards.
                    </p>
                    <button type="button" class="btn btn-primary" id="ts-clmgr-generate-ai-btn" style="background:linear-gradient(135deg,#1d4ed8,#6366f1);border:none;">
                        <i class="ri-magic-line me-1"></i> Generate Manager Checklist with AI
                    </button>
                </div>

                <div id="ts-clmgr-content" class="d-none">
                    <div class="clmgr-tabs" role="tablist">
                        <button type="button" class="clmgr-tab is-active" data-tab="checklist" role="tab" aria-selected="true">
                            <i class="ri-list-check-3 me-1"></i> Checklist
                        </button>
                        <button type="button" class="clmgr-tab" data-tab="juniors" role="tab" aria-selected="false">
                            <i class="ri-team-line me-1"></i> Juniors <span class="badge bg-secondary ms-1" id="ts-clmgr-juniors-badge">0</span>
                        </button>
                    </div>

                    <div data-pane="checklist">
                        <div id="ts-clmgr-list"></div>

                        <div id="ts-clmgr-add-form">
                            <div class="mb-1"><label><i class="ri-add-circle-line me-1"></i> Add manager checkpoint</label></div>
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-5">
                                    <input type="text" id="ts-clmgr-add-title" class="form-control form-control-sm" placeholder="Checkpoint title (e.g. Audits juniors&#8217; tasks weekly)" maxlength="500" />
                                </div>
                                <div class="col-7 col-md-3">
                                    <input type="text" id="ts-clmgr-add-category" class="form-control form-control-sm" placeholder="Category (e.g. Auditing)" maxlength="100" />
                                </div>
                                <div class="col-3 col-md-2">
                                    <input type="number" id="ts-clmgr-add-weight" class="form-control form-control-sm" min="1" max="10" step="1" value="1" title="Weightage (1–10)" />
                                </div>
                                <div class="col-2 col-md-2">
                                    <button type="button" class="btn btn-sm w-100 text-white" id="ts-clmgr-add-btn" style="background:#1d4ed8;border-color:#1d4ed8;">
                                        <i class="ri-add-line"></i> Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div data-pane="juniors" class="d-none">
                        <div id="ts-clmgr-juniors-list"></div>
                        <div id="ts-clmgr-juniors-empty" class="d-none">
                            <i class="ri-team-line d-block mb-2" style="font-size:1.8rem;color:#a5b4fc;"></i>
                            No juniors assigned yet. Add juniors below — their average score will contribute to this manager's combined score.
                        </div>

                        <div id="ts-clmgr-add-junior-wrap">
                            <label for="ts-clmgr-add-junior" class="form-label small fw-semibold text-muted mb-1">
                                <i class="ri-user-add-line me-1"></i> Assign a junior to this manager
                            </label>
                            <div class="input-group input-group-sm">
                                <select id="ts-clmgr-add-junior" class="form-select form-select-sm">
                                    <option value="">Select a team member…</option>
                                </select>
                                <button type="button" class="btn text-white" id="ts-clmgr-add-junior-btn" style="background:#1d4ed8;border-color:#1d4ed8;">
                                    <i class="ri-add-line"></i> Add Junior
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-wrap gap-2 justify-content-between">
                <small class="text-muted" id="ts-clmgr-formula-hint">
                    <i class="ri-information-line me-1"></i>
                    Combined Mgr = Own × 60% + Juniors-avg × 40% · Junior score = (CL R&amp;R + CL Gen) / 2.
                </small>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="ts-clmgr-refresh-ai-btn" style="border-color:#1d4ed8;color:#1d4ed8;display:none;">
                        <i class="ri-refresh-line me-1"></i> Refresh checklist with AI
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
