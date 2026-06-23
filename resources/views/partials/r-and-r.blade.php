{{--
    R&R (Roles & Responsibilities) modal for Task Summary.

    Designation-driven: all users sharing a designation see the same list of
    R&R items, but each user owns their own status / note (used to track
    individual progress). The first time a designation is opened from the
    magnifying-glass column the list is seeded by AI; from then on team
    members can add or delete items manually.

    Consumed from resources/views/tasks/task-summary.blade.php.
--}}

<style>
    #taskSummaryRrModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryRrModal .modal-header {
        background: linear-gradient(135deg, #6d28d9, #8b5cf6);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryRrModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryRrModal .rr-meta {
        font-size: 0.78rem;
        opacity: 0.9;
    }
    #taskSummaryRrModal .rr-progress-bar-wrap {
        height: 8px;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        overflow: hidden;
    }
    #taskSummaryRrModal .rr-progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #34d399, #10b981);
        width: 0%;
        transition: width 0.35s ease;
    }
    .rr-search-icon-btn {
        border: none;
        background: transparent;
        color: #6d28d9;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .rr-search-icon-btn:hover {
        background: rgba(109, 40, 217, 0.1);
        color: #4c1d95;
        transform: scale(1.1);
    }
    .rr-search-icon-btn:focus-visible {
        outline: 2px solid #6d28d9;
        outline-offset: 2px;
    }
    #ts-rr-item-list .rr-item {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.65rem 0.75rem;
        margin-bottom: 0.5rem;
        background: #fff;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    #ts-rr-item-list .rr-item:hover {
        border-color: #c4b5fd;
        box-shadow: 0 4px 14px rgba(109, 40, 217, 0.08);
    }
    #ts-rr-item-list .rr-item[data-status="done"] {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    #ts-rr-item-list .rr-item[data-status="in_progress"] {
        background: #fffbeb;
        border-color: #fde68a;
    }
    #ts-rr-item-list .rr-item .rr-item-title {
        font-weight: 600;
        font-size: 0.92rem;
        color: #1f2937;
        line-height: 1.3;
    }
    #ts-rr-item-list .rr-item[data-status="done"] .rr-item-title {
        text-decoration: line-through;
        color: #15803d;
    }
    #ts-rr-item-list .rr-item .rr-item-desc {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 0.2rem;
    }
    #ts-rr-item-list .rr-item .rr-source-badge {
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 0.15em 0.45em;
        border-radius: 4px;
        background: #ede9fe;
        color: #6d28d9;
        margin-left: 0.4rem;
        vertical-align: middle;
    }
    #ts-rr-item-list .rr-item .rr-source-badge.is-manual {
        background: #f1f5f9;
        color: #475569;
    }
    #ts-rr-item-list .rr-status-select {
        font-size: 0.78rem;
        padding: 0.2rem 1.7rem 0.2rem 0.5rem;
        min-width: 130px;
    }
    #ts-rr-item-list .rr-delete-btn {
        color: #dc2626;
        background: transparent;
        border: none;
        padding: 0.15rem 0.4rem;
        border-radius: 6px;
        transition: background 0.15s ease;
    }
    #ts-rr-item-list .rr-delete-btn:hover {
        background: rgba(220, 38, 38, 0.1);
    }
    #ts-rr-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: #64748b;
    }
    #ts-rr-empty i {
        font-size: 2.4rem;
        color: #c4b5fd;
        display: block;
        margin-bottom: 0.5rem;
    }
    #ts-rr-add-form {
        background: #faf5ff;
        border: 1px dashed #c4b5fd;
        border-radius: 10px;
        padding: 0.75rem;
        margin-top: 0.5rem;
    }
    #ts-rr-loading,
    #ts-rr-ai-loading {
        text-align: center;
        padding: 2rem 1rem;
    }
    #ts-rr-ai-loading .spinner-border {
        color: #6d28d9;
    }
</style>

<div class="modal fade" id="taskSummaryRrModal" tabindex="-1" aria-labelledby="taskSummaryRrModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryRrModalLabel">
                            <i class="ri-shield-user-line me-2" aria-hidden="true"></i>
                            <span id="ts-rr-modal-user">Roles &amp; Responsibilities</span>
                        </h5>
                        <div class="rr-meta">
                            <span id="ts-rr-modal-designation"></span>
                            <span class="ms-2" id="ts-rr-modal-progress-text"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="mt-2 rr-progress-bar-wrap" role="progressbar" aria-label="R&R completion">
                    <div class="rr-progress-bar-fill" id="ts-rr-modal-progress-fill"></div>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-rr-loading" class="d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Loading R&amp;R…</p>
                </div>

                <div id="ts-rr-ai-loading" class="d-none">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Generating…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Asking AI to draft the initial R&amp;R for this designation…</p>
                </div>

                <div id="ts-rr-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-rr-empty" class="d-none">
                    <i class="ri-file-list-3-line" aria-hidden="true"></i>
                    <div class="fw-semibold mb-1">No R&amp;R items yet for this designation</div>
                    <p class="small mb-3">
                        Let AI draft an initial list based on the designation, then add or remove items as needed.
                    </p>
                    <button type="button" class="btn btn-primary" id="ts-rr-generate-ai-btn" style="background:linear-gradient(135deg,#6d28d9,#8b5cf6);border:none;">
                        <i class="ri-magic-line me-1"></i> Generate with AI
                    </button>
                </div>

                <div id="ts-rr-content" class="d-none">
                    <div id="ts-rr-item-list"></div>

                    <div id="ts-rr-add-form">
                        <label for="ts-rr-add-title" class="form-label small fw-semibold text-muted mb-1">
                            <i class="ri-add-circle-line me-1"></i> Add new responsibility
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="ts-rr-add-title" class="form-control" placeholder="e.g. Review weekly KPI dashboard" maxlength="500" />
                            <button type="button" class="btn btn-primary" id="ts-rr-add-btn" style="background:#6d28d9;border-color:#6d28d9;">
                                <i class="ri-add-line"></i> Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="ts-rr-regenerate-btn" style="border-color:#6d28d9;color:#6d28d9;display:none;">
                    <i class="ri-refresh-line me-1"></i> Re-generate with AI
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
