{{--
    Mgr "Tags" modal for Task Summary "Role" column.

    Lightweight UI for assigning the people a Mgr is responsible for.
    Shares the same manager_juniors pivot used by CL Mgr (so juniors
    added here also show up — and contribute to score — in the CL Mgr
    modal, and vice-versa).

    Consumed from resources/views/tasks/task-summary.blade.php and shown
    only when the row's org_level is "mgr".
--}}

<style>
    #taskSummaryMgrTagsModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryMgrTagsModal .modal-header {
        background: linear-gradient(135deg, #1d4ed8, #6366f1);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryMgrTagsModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryMgrTagsModal .mgrtag-meta {
        font-size: 0.78rem;
        opacity: 0.92;
    }

    /* Role select & dot in the table row — laid out side-by-side, with
       enough room around them so the dot never visually touches the next
       column's cell border. */
    .task-summary-role-cell {
        white-space: nowrap;
        text-align: center;
        /* Reserve room for: select (96–120px) + gap + dot (0.65rem) +
           dot's right margin. Prevents the auto-sized column from
           collapsing tight enough to make the dot look like it's in
           the next column. */
        min-width: 150px;
        padding-right: 0.7rem !important;
        overflow: visible;
    }
    .task-summary-role-cell > .task-summary-role-select,
    .task-summary-role-cell > .task-summary-role-mgr-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        vertical-align: middle;
    }
    .task-summary-role-cell > .task-summary-role-mgr-dot {
        margin-right: 0.25rem;
    }
    .task-summary-role-select {
        font-size: 0.78rem;
        padding: 0.15rem 1.5rem 0.15rem 0.45rem;
        min-width: 96px;
        max-width: 120px;
        /* margin: 0 auto removed — the cell now lays its children in a
           single inline-flex row instead of stacking them. */
    }
    .task-summary-role-mgr-dot {
        display: inline-block;
        width: 0.65rem;
        height: 0.65rem;
        margin-left: 0.35rem;
        border-radius: 50%;
        background: #1d4ed8;
        box-shadow: 0 0 0 2px rgba(29, 78, 216, 0.18);
        cursor: pointer;
        vertical-align: middle;
        transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        border: none;
        padding: 0;
    }
    .task-summary-role-mgr-dot:hover {
        background: #1e40af;
        transform: scale(1.35);
        box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.3);
    }
    .task-summary-role-mgr-dot:focus-visible {
        outline: 2px solid #1d4ed8;
        outline-offset: 2px;
    }
    .task-summary-role-mgr-dot[data-junior-count]::after {
        content: attr(data-junior-count);
        position: relative;
        display: none; /* count exposed via title — keeps the dot itself minimal */
    }

    /* Tag chips inside the modal */
    #ts-mgrtag-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        min-height: 2.2rem;
        padding: 0.55rem;
        border: 1px solid #e0e7ff;
        border-radius: 10px;
        background: #eef2ff;
    }
    #ts-mgrtag-chips:empty::before {
        content: 'No juniors tagged yet. Use the box below to add the people this manager is responsible for.';
        font-size: 0.78rem;
        color: #6366f1;
        align-self: center;
    }
    .mgrtag-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        background: #fff;
        border: 1px solid #c7d2fe;
        color: #1e3a8a;
        border-radius: 999px;
        padding: 0.2rem 0.55rem 0.2rem 0.7rem;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
        max-width: 100%;
    }
    .mgrtag-chip .mgrtag-chip-des {
        color: #64748b;
        font-weight: 500;
        font-size: 0.7rem;
    }
    .mgrtag-chip .mgrtag-chip-remove {
        background: transparent;
        border: none;
        color: #dc2626;
        padding: 0 0.1rem;
        margin-left: 0.2rem;
        font-size: 1rem;
        line-height: 1;
        cursor: pointer;
        border-radius: 50%;
        transition: background 0.15s ease;
    }
    .mgrtag-chip .mgrtag-chip-remove:hover {
        background: rgba(220, 38, 38, 0.12);
    }
    #ts-mgrtag-add-wrap {
        margin-top: 0.55rem;
    }
    #ts-mgrtag-add-wrap .input-group-text {
        background: #eef2ff;
        border-color: #c7d2fe;
        color: #1e3a8a;
        font-size: 0.75rem;
        font-weight: 700;
    }
    #ts-mgrtag-loading,
    #ts-mgrtag-error {
        font-size: 0.82rem;
    }
</style>

<div class="modal fade" id="taskSummaryMgrTagsModal" tabindex="-1" aria-labelledby="taskSummaryMgrTagsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryMgrTagsModalLabel">
                            <i class="ri-price-tag-3-line me-2" aria-hidden="true"></i>
                            <span id="ts-mgrtag-modal-user">Manager — Tags</span>
                        </h5>
                        <div class="mgrtag-meta">
                            <span id="ts-mgrtag-modal-designation"></span>
                            <span class="ms-2" id="ts-mgrtag-modal-count"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-mgrtag-loading" class="text-center py-3 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Loading tagged juniors…</p>
                </div>
                <div id="ts-mgrtag-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-mgrtag-content" class="d-none">
                    <label class="form-label small fw-semibold text-muted mb-1">
                        <i class="ri-team-line me-1"></i> People this manager is responsible for
                    </label>
                    <div id="ts-mgrtag-chips" aria-live="polite"></div>

                    <div id="ts-mgrtag-add-wrap">
                        <label for="ts-mgrtag-add-select" class="form-label small fw-semibold text-muted mb-1 mt-2">
                            <i class="ri-add-circle-line me-1"></i> Add a tag
                        </label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="ri-search-line"></i></span>
                            <select id="ts-mgrtag-add-select" class="form-select form-select-sm">
                                <option value="">Select a team member…</option>
                            </select>
                            <button type="button" class="btn text-white" id="ts-mgrtag-add-btn" style="background:#1d4ed8;border-color:#1d4ed8;">
                                <i class="ri-add-line"></i> Add tag
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">
                            <i class="ri-information-line me-1"></i>
                            These tags also drive the CL Mgr "Juniors" panel and the manager's combined score.
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
