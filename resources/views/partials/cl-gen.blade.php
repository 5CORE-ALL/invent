{{--
    CL Gen (Global / General Checklist) modal for Task Summary.

    A single shared list of weighted checkpoints that applies to every team
    member regardless of designation (attendance, communication, helpfulness,
    ETC vs ATC, overdues, TAT, etc.). Each user owns their own check state;
    weightages roll up into a single General score (%) per person.

    Consumed from resources/views/tasks/task-summary.blade.php.
--}}

<style>
    #taskSummaryClGenModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryClGenModal .modal-header {
        background: linear-gradient(135deg, #b45309, #f59e0b);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryClGenModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryClGenModal .clgen-meta {
        font-size: 0.78rem;
        opacity: 0.92;
    }
    #taskSummaryClGenModal .clgen-overall {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.4rem;
    }
    #taskSummaryClGenModal .clgen-score-circle {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.18);
        border: 3px solid #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1rem;
        font-variant-numeric: tabular-nums;
        line-height: 1;
        flex-shrink: 0;
    }
    #taskSummaryClGenModal .clgen-progress-bar-wrap {
        height: 8px;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        overflow: hidden;
        margin-top: 0.35rem;
    }
    #taskSummaryClGenModal .clgen-progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #fbbf24, #16a34a);
        width: 0%;
        transition: width 0.35s ease;
    }
    .clgen-search-icon-btn {
        border: none;
        background: transparent;
        color: #b45309;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .clgen-search-icon-btn:hover {
        background: rgba(180, 83, 9, 0.12);
        color: #78350f;
        transform: scale(1.1);
    }
    .clgen-search-icon-btn:focus-visible {
        outline: 2px solid #b45309;
        outline-offset: 2px;
    }

    /* Category grouping cards */
    #ts-clgen-list .clgen-cat-card {
        border: 1px solid #fef3c7;
        border-radius: 12px;
        padding: 0.65rem 0.85rem 0.45rem 0.85rem;
        margin-bottom: 0.75rem;
        background: #fffbeb;
    }
    #ts-clgen-list .clgen-cat-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #92400e;
        margin-bottom: 0.4rem;
    }
    #ts-clgen-list .clgen-cat-header .clgen-cat-count {
        margin-left: auto;
        font-size: 0.72rem;
        font-weight: 700;
        color: #b45309;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        padding: 0.05em 0.45em;
        border-radius: 999px;
        font-variant-numeric: tabular-nums;
        text-transform: none;
        letter-spacing: 0;
    }
    #ts-clgen-list .clgen-item {
        display: flex;
        align-items: flex-start;
        gap: 0.55rem;
        padding: 0.45rem 0;
        border-top: 1px solid #fde68a;
        background: #fff;
        border-radius: 8px;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
        margin-bottom: 0.3rem;
        border: 1px solid #fde68a;
    }
    #ts-clgen-list .clgen-item.is-checked {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    #ts-clgen-list .clgen-item .form-check-input {
        margin-top: 0.25rem;
        flex-shrink: 0;
        cursor: pointer;
    }
    #ts-clgen-list .clgen-item .form-check-input:checked {
        background-color: #b45309;
        border-color: #b45309;
    }
    #ts-clgen-list .clgen-item-body {
        flex-grow: 1;
        min-width: 0;
    }
    #ts-clgen-list .clgen-item-title {
        font-size: 0.86rem;
        color: #1f2937;
        line-height: 1.35;
    }
    #ts-clgen-list .clgen-item.is-checked .clgen-item-title {
        text-decoration: line-through;
        color: #15803d;
    }
    #ts-clgen-list .clgen-item-desc {
        font-size: 0.72rem;
        color: #64748b;
        margin-top: 0.1rem;
    }
    #ts-clgen-list .clgen-source-badge {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.1em 0.4em;
        border-radius: 4px;
        background: #fef3c7;
        color: #92400e;
        margin-left: 0.35rem;
        vertical-align: middle;
    }
    #ts-clgen-list .clgen-source-badge.is-manual {
        background: #f1f5f9;
        color: #475569;
    }
    #ts-clgen-list .clgen-item-actions {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-shrink: 0;
    }
    #ts-clgen-list .clgen-weight-input {
        width: 56px;
        font-size: 0.75rem;
        padding: 0.15rem 0.3rem;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
    #ts-clgen-list .clgen-delete-btn {
        color: #dc2626;
        background: transparent;
        border: none;
        padding: 0.1rem 0.35rem;
        border-radius: 6px;
    }
    #ts-clgen-list .clgen-delete-btn:hover {
        background: rgba(220, 38, 38, 0.1);
    }

    #ts-clgen-add-form {
        background: #fff7ed;
        border: 1px dashed #fdba74;
        border-radius: 10px;
        padding: 0.65rem 0.75rem;
        margin-top: 0.5rem;
    }
    #ts-clgen-add-form label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #92400e;
    }
    #ts-clgen-add-form .form-control {
        font-size: 0.82rem;
    }

    #ts-clgen-empty,
    #ts-clgen-loading,
    #ts-clgen-ai-loading {
        text-align: center;
        padding: 2rem 1rem;
        color: #64748b;
    }
    #ts-clgen-empty i {
        font-size: 2.4rem;
        color: #fcd34d;
        display: block;
        margin-bottom: 0.5rem;
    }
    #ts-clgen-ai-loading .spinner-border {
        color: #b45309;
    }
</style>

<div class="modal fade" id="taskSummaryClGenModal" tabindex="-1" aria-labelledby="taskSummaryClGenModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryClGenModalLabel">
                            <i class="ri-checkbox-multiple-line me-2" aria-hidden="true"></i>
                            <span id="ts-clgen-modal-user">CL Gen — General Checklist</span>
                        </h5>
                        <div class="clgen-meta">
                            <i class="ri-team-line me-1"></i>
                            <span>Team-wide checklist (applies to every member, regardless of designation)</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="clgen-overall">
                    <div class="clgen-score-circle" id="ts-clgen-modal-score">0%</div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="small fw-semibold">General score</div>
                        <div class="small" id="ts-clgen-modal-score-detail">0/0 points · 0 of 0 checks done</div>
                        <div class="clgen-progress-bar-wrap" role="progressbar" aria-label="General score">
                            <div class="clgen-progress-bar-fill" id="ts-clgen-modal-progress-fill"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-clgen-loading" class="d-none">
                    <div class="spinner-border text-warning" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Loading General Checklist…</p>
                </div>

                <div id="ts-clgen-ai-loading" class="d-none">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Generating…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Asking AI to draft the team-wide checklist…</p>
                </div>

                <div id="ts-clgen-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-clgen-empty" class="d-none">
                    <i class="ri-magic-line" aria-hidden="true"></i>
                    <div class="fw-semibold mb-1">No general checklist yet</div>
                    <p class="small mb-3">
                        Let AI draft a team-wide weighted checklist (attendance, communication, ETC/ATC, overdues, TAT…)
                        — applies to every team member. You can add or remove items afterwards.
                    </p>
                    <button type="button" class="btn btn-warning text-white" id="ts-clgen-generate-ai-btn" style="background:linear-gradient(135deg,#b45309,#f59e0b);border:none;">
                        <i class="ri-magic-line me-1"></i> Generate Checklist with AI
                    </button>
                </div>

                <div id="ts-clgen-content" class="d-none">
                    <div id="ts-clgen-list"></div>

                    <div id="ts-clgen-add-form">
                        <div class="mb-1"><label><i class="ri-add-circle-line me-1"></i> Add new checkpoint</label></div>
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-5">
                                <input type="text" id="ts-clgen-add-title" class="form-control form-control-sm" placeholder="Checkpoint title (e.g. Responds to escalations within 1h)" maxlength="500" />
                            </div>
                            <div class="col-7 col-md-3">
                                <input type="text" id="ts-clgen-add-category" class="form-control form-control-sm" placeholder="Category (e.g. Communication)" maxlength="100" />
                            </div>
                            <div class="col-3 col-md-2">
                                <input type="number" id="ts-clgen-add-weight" class="form-control form-control-sm" min="1" max="10" step="1" value="1" title="Weightage (1–10)" />
                            </div>
                            <div class="col-2 col-md-2">
                                <button type="button" class="btn btn-sm w-100 text-white" id="ts-clgen-add-btn" style="background:#b45309;border-color:#b45309;">
                                    <i class="ri-add-line"></i> Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-wrap gap-2 justify-content-between">
                <small class="text-muted" id="ts-clgen-formula-hint">
                    <i class="ri-information-line me-1"></i>
                    General score = checked-weightage / total-weightage · weightage 1–10 reflects importance.
                </small>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-warning" id="ts-clgen-refresh-ai-btn" style="border-color:#b45309;color:#b45309;display:none;">
                        <i class="ri-refresh-line me-1"></i> Refresh with AI
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
