{{--
    CL R&R (Checklist of Roles & Responsibilities) modal for Task Summary.

    For each designation R&R item, AI seeds 4–8 weighted checkpoints that
    the user can tick off. Weightages roll up into a per-item score (%)
    and an overall score (%) shown in the modal header.

    Consumed from resources/views/tasks/task-summary.blade.php.
--}}

<style>
    #taskSummaryClRrModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryClRrModal .modal-header {
        background: linear-gradient(135deg, #0e7490, #06b6d4);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryClRrModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryClRrModal .clrr-meta {
        font-size: 0.78rem;
        opacity: 0.92;
    }
    #taskSummaryClRrModal .clrr-overall {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-top: 0.4rem;
    }
    #taskSummaryClRrModal .clrr-score-circle {
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
    #taskSummaryClRrModal .clrr-overall-text {
        flex-grow: 1;
        min-width: 0;
    }
    #taskSummaryClRrModal .clrr-progress-bar-wrap {
        height: 8px;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        overflow: hidden;
        margin-top: 0.35rem;
    }
    #taskSummaryClRrModal .clrr-progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #fcd34d, #34d399);
        width: 0%;
        transition: width 0.35s ease;
    }
    .clrr-search-icon-btn {
        border: none;
        background: transparent;
        color: #0e7490;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .clrr-search-icon-btn:hover {
        background: rgba(14, 116, 144, 0.1);
        color: #0c4a6e;
        transform: scale(1.1);
    }
    .clrr-search-icon-btn:focus-visible {
        outline: 2px solid #0e7490;
        outline-offset: 2px;
    }

    #ts-clrr-item-list .clrr-item-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 0.9rem;
        margin-bottom: 0.75rem;
        background: #fff;
    }
    #ts-clrr-item-list .clrr-item-card[data-fully-done="true"] {
        background: #f0fdfa;
        border-color: #99f6e4;
    }
    #ts-clrr-item-list .clrr-item-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.4rem;
    }
    #ts-clrr-item-list .clrr-item-title {
        font-weight: 700;
        font-size: 0.95rem;
        color: #0f172a;
        flex-grow: 1;
        line-height: 1.3;
    }
    #ts-clrr-item-list .clrr-item-score {
        flex-shrink: 0;
        font-size: 0.78rem;
        font-weight: 700;
        color: #0e7490;
        background: #ecfeff;
        border: 1px solid #a5f3fc;
        padding: 0.15em 0.55em;
        border-radius: 999px;
        font-variant-numeric: tabular-nums;
    }
    #ts-clrr-item-list .clrr-item-score-bar-wrap {
        height: 5px;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
        margin: 0.15rem 0 0.55rem 0;
    }
    #ts-clrr-item-list .clrr-item-score-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #06b6d4, #0e7490);
        transition: width 0.3s ease;
    }
    #ts-clrr-item-list .clrr-checkpoint {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.35rem 0;
        border-top: 1px solid #f1f5f9;
    }
    #ts-clrr-item-list .clrr-checkpoint:first-of-type {
        border-top: 0;
    }
    #ts-clrr-item-list .clrr-checkpoint .form-check-input {
        margin-top: 0.25rem;
        cursor: pointer;
        flex-shrink: 0;
    }
    #ts-clrr-item-list .clrr-checkpoint .form-check-input:checked {
        background-color: #0e7490;
        border-color: #0e7490;
    }
    #ts-clrr-item-list .clrr-checkpoint-body {
        flex-grow: 1;
        min-width: 0;
    }
    #ts-clrr-item-list .clrr-checkpoint-title {
        font-size: 0.86rem;
        color: #1f2937;
        line-height: 1.35;
    }
    #ts-clrr-item-list .clrr-checkpoint.is-checked .clrr-checkpoint-title {
        text-decoration: line-through;
        color: #15803d;
    }
    #ts-clrr-item-list .clrr-checkpoint-desc {
        font-size: 0.72rem;
        color: #64748b;
        margin-top: 0.1rem;
    }
    #ts-clrr-item-list .clrr-checkpoint-actions {
        display: flex;
        align-items: center;
        gap: 0.3rem;
        flex-shrink: 0;
    }
    #ts-clrr-item-list .clrr-weight-input {
        width: 56px;
        font-size: 0.75rem;
        padding: 0.15rem 0.3rem;
        text-align: center;
        font-variant-numeric: tabular-nums;
    }
    #ts-clrr-item-list .clrr-source-badge {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.1em 0.4em;
        border-radius: 4px;
        background: #cffafe;
        color: #0e7490;
        margin-left: 0.35rem;
        vertical-align: middle;
    }
    #ts-clrr-item-list .clrr-source-badge.is-manual {
        background: #f1f5f9;
        color: #475569;
    }
    #ts-clrr-item-list .clrr-delete-btn {
        color: #dc2626;
        background: transparent;
        border: none;
        padding: 0.1rem 0.35rem;
        border-radius: 6px;
    }
    #ts-clrr-item-list .clrr-delete-btn:hover {
        background: rgba(220, 38, 38, 0.1);
    }
    #ts-clrr-item-list .clrr-regen-btn {
        font-size: 0.72rem;
        padding: 0.1rem 0.45rem;
        border: 1px solid #a5f3fc;
        background: #fff;
        color: #0e7490;
        border-radius: 6px;
        transition: background 0.15s ease;
    }
    #ts-clrr-item-list .clrr-regen-btn:hover {
        background: #ecfeff;
    }
    #ts-clrr-item-list .clrr-add-row {
        display: flex;
        gap: 0.3rem;
        align-items: center;
        margin-top: 0.4rem;
        padding-top: 0.4rem;
        border-top: 1px dashed #cbd5e1;
    }
    #ts-clrr-item-list .clrr-add-input {
        flex-grow: 1;
        font-size: 0.78rem;
        padding: 0.2rem 0.45rem;
    }
    #ts-clrr-item-list .clrr-add-btn {
        font-size: 0.75rem;
        padding: 0.2rem 0.55rem;
        background: #0e7490;
        color: #fff;
        border: none;
        border-radius: 6px;
    }
    #ts-clrr-item-list .clrr-add-btn:hover {
        background: #155e75;
    }
    #ts-clrr-empty,
    #ts-clrr-needs-rr {
        text-align: center;
        padding: 2rem 1rem;
        color: #64748b;
    }
    #ts-clrr-empty i,
    #ts-clrr-needs-rr i {
        font-size: 2.4rem;
        color: #67e8f9;
        display: block;
        margin-bottom: 0.5rem;
    }
    #ts-clrr-loading,
    #ts-clrr-ai-loading {
        text-align: center;
        padding: 2rem 1rem;
    }
    #ts-clrr-ai-loading .spinner-border {
        color: #0e7490;
    }
</style>

<div class="modal fade" id="taskSummaryClRrModal" tabindex="-1" aria-labelledby="taskSummaryClRrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryClRrModalLabel">
                            <i class="ri-list-check-3 me-2" aria-hidden="true"></i>
                            <span id="ts-clrr-modal-user">CL R&amp;R — Checklist</span>
                        </h5>
                        <div class="clrr-meta">
                            <span id="ts-clrr-modal-designation"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="clrr-overall">
                    <div class="clrr-score-circle" id="ts-clrr-modal-score">0%</div>
                    <div class="clrr-overall-text">
                        <div class="small fw-semibold">Overall score</div>
                        <div class="small" id="ts-clrr-modal-score-detail">0/0 points · 0 of 0 checkpoints done</div>
                        <div class="clrr-progress-bar-wrap" role="progressbar" aria-label="Overall CL R&R score">
                            <div class="clrr-progress-bar-fill" id="ts-clrr-modal-progress-fill"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-clrr-loading" class="d-none">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Loading CL R&amp;R…</p>
                </div>

                <div id="ts-clrr-ai-loading" class="d-none">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Generating…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Asking AI to draft the checklist for each R&amp;R item…</p>
                </div>

                <div id="ts-clrr-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-clrr-needs-rr" class="d-none">
                    <i class="ri-error-warning-line" aria-hidden="true"></i>
                    <div class="fw-semibold mb-1">No R&amp;R items for this designation yet</div>
                    <p class="small mb-0">
                        Open the <strong>R&amp;R</strong> column first (the magnifying glass to the left) and generate the
                        R&amp;R list. After that, come back here to build the checklist.
                    </p>
                </div>

                <div id="ts-clrr-empty" class="d-none">
                    <i class="ri-magic-line" aria-hidden="true"></i>
                    <div class="fw-semibold mb-1">No checklist for this designation yet</div>
                    <p class="small mb-3">
                        Let AI draft a weighted checklist for each R&amp;R item, then add or remove checkpoints as needed.
                    </p>
                    <button type="button" class="btn btn-info text-white" id="ts-clrr-generate-ai-btn" style="background:linear-gradient(135deg,#0e7490,#06b6d4);border:none;">
                        <i class="ri-magic-line me-1"></i> Generate Checklist with AI
                    </button>
                </div>

                <div id="ts-clrr-content" class="d-none">
                    <div id="ts-clrr-item-list"></div>
                </div>
            </div>
            <div class="modal-footer flex-wrap gap-2 justify-content-between">
                <small class="text-muted" id="ts-clrr-formula-hint">
                    <i class="ri-information-line me-1"></i>
                    Score = checked-weightage / total-weightage · weightage 1–10 reflects importance.
                </small>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-info" id="ts-clrr-refresh-ai-btn" style="border-color:#0e7490;color:#0e7490;display:none;">
                        <i class="ri-refresh-line me-1"></i> Refresh with AI
                    </button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
