{{--
    KPI Badges modal — opened from the new "KPI" column magnifier on the
    Task Summary page. Shows badges currently tagged on a team member
    (colour chips with icon + name + optional note), lets authorised
    viewers award additional badges from the pool, remove them, and
    (Director / Admin / Shobha only) create new badge types.

    Backed by:
      GET    /tasks/user-badges
      POST   /tasks/user-badges/award
      DELETE /tasks/user-badges/award
      POST   /tasks/badges
      DELETE /tasks/badges/{id}
--}}

<style>
    #taskSummaryUserBadgesModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryUserBadgesModal .modal-header {
        background: linear-gradient(135deg, #b45309, #f59e0b);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryUserBadgesModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryUserBadgesModal .ub-meta {
        font-size: 0.78rem;
        opacity: 0.92;
    }

    /* Awarded-badge chip */
    .ub-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35em 0.75em;
        border-radius: 999px;
        font-size: 0.84rem;
        font-weight: 600;
        line-height: 1.2;
        color: #fff;
        margin: 0.3rem 0.3rem 0 0;
        max-width: 100%;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.2);
    }
    .ub-chip i {
        font-size: 1rem;
    }
    .ub-chip-remove {
        background: rgba(255, 255, 255, 0.22);
        border: none;
        color: #fff;
        padding: 0 0.3em;
        margin-left: 0.15em;
        border-radius: 50%;
        line-height: 1;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.15s ease;
    }
    .ub-chip-remove:hover {
        background: rgba(255, 255, 255, 0.4);
    }

    /* Available-pool chip (clickable to add) */
    .ub-pool-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3em 0.65em;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.2;
        background: #fff;
        margin: 0.25rem 0.25rem 0 0;
        cursor: pointer;
        transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease;
        white-space: nowrap;
    }
    .ub-pool-chip:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 10px rgba(15, 23, 42, 0.15);
    }
    .ub-pool-chip:disabled,
    .ub-pool-chip.is-disabled {
        opacity: 0.55;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .ub-pool-chip i {
        font-size: 0.95rem;
    }

    /* Section labels */
    .ub-section-label {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #64748b;
        margin: 1rem 0 0.45rem 0;
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }

    #ts-ub-empty,
    #ts-ub-pool-empty {
        font-size: 0.82rem;
        color: #94a3b8;
        padding: 0.5rem;
    }
    #ts-ub-loading {
        text-align: center;
        padding: 1.5rem;
    }

    /* Create-new-badge form */
    #ts-ub-create-wrap {
        background: #fff7ed;
        border: 1px dashed #fdba74;
        border-radius: 10px;
        padding: 0.65rem 0.75rem;
        margin-top: 0.5rem;
    }
    #ts-ub-create-wrap .form-control {
        font-size: 0.82rem;
    }
    #ts-ub-create-wrap label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #92400e;
    }
    #ts-ub-create-icon-preview {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.9rem;
        height: 1.9rem;
        border-radius: 50%;
        color: #fff;
        background: #b45309;
    }

    /* Magnifier button on the KPI column */
    .kpi-badges-search-icon-btn {
        border: none;
        background: transparent;
        color: #b45309;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .kpi-badges-search-icon-btn:hover {
        background: rgba(180, 83, 9, 0.12);
        color: #78350f;
        transform: scale(1.1);
    }
    .kpi-badges-search-icon-btn:focus-visible {
        outline: 2px solid #b45309;
        outline-offset: 2px;
    }
    .kpi-badges-count {
        margin-left: 0.2rem;
        font-size: 0.65rem;
        font-weight: 800;
        color: #b45309;
        background: #fef3c7;
        border: 1px solid #fcd34d;
        padding: 0.05em 0.4em;
        border-radius: 999px;
        vertical-align: middle;
    }
</style>

<div class="modal fade" id="taskSummaryUserBadgesModal" tabindex="-1" aria-labelledby="taskSummaryUserBadgesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryUserBadgesModalLabel">
                            <i class="ri-medal-2-line me-2" aria-hidden="true"></i>
                            <span id="ts-ub-modal-user">KPI Badges</span>
                        </h5>
                        <div class="ub-meta">
                            <span id="ts-ub-modal-designation"></span>
                            <span class="ms-2" id="ts-ub-modal-count"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-ub-loading" class="d-none">
                    <div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="text-muted small mt-2 mb-0">Loading badges…</p>
                </div>
                <div id="ts-ub-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-ub-content" class="d-none">
                    <div class="ub-section-label">
                        <i class="ri-medal-line"></i> Tagged badges
                    </div>
                    <div id="ts-ub-awarded" aria-live="polite"></div>
                    <div id="ts-ub-empty" class="d-none">No badges tagged yet — pick from the pool below.</div>

                    <div class="ub-section-label" id="ts-ub-pool-label">
                        <i class="ri-archive-line"></i> Available badges <small class="text-muted ms-2 fw-normal text-lowercase" style="letter-spacing:0;text-transform:none;font-size:0.7rem;">click to tag</small>
                    </div>
                    <div id="ts-ub-pool"></div>
                    <div id="ts-ub-pool-empty" class="d-none">All badges in the pool are already tagged on this user.</div>

                    <div id="ts-ub-create-wrap" class="d-none">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <label class="m-0"><i class="ri-add-circle-line me-1"></i> Create new badge type</label>
                            <small class="text-muted ms-auto" style="font-size:0.7rem;">Director / admin / Shobha only</small>
                        </div>
                        <div class="row g-2 align-items-center">
                            <div class="col-12 col-md-4">
                                <input type="text" id="ts-ub-create-name" class="form-control form-control-sm" placeholder="Badge name (e.g. Crisis Manager)" maxlength="80" />
                            </div>
                            <div class="col-7 col-md-3">
                                <input type="text" id="ts-ub-create-icon" class="form-control form-control-sm" placeholder="ri-medal-line" maxlength="60" />
                            </div>
                            <div class="col-3 col-md-2">
                                <input type="color" id="ts-ub-create-color" class="form-control form-control-color form-control-sm" value="#b45309" title="Chip colour" />
                            </div>
                            <div class="col-2 col-md-3 d-flex gap-2 align-items-center">
                                <span id="ts-ub-create-icon-preview"><i class="ri-medal-line"></i></span>
                                <button type="button" class="btn btn-sm text-white flex-grow-1" id="ts-ub-create-btn" style="background:#b45309;border-color:#b45309;">
                                    <i class="ri-add-line"></i> Create
                                </button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size:0.7rem;">
                            <i class="ri-information-line me-1"></i>
                            Icon class names come from Remix Icon (e.g. <code>ri-star-line</code>, <code>ri-rocket-2-line</code>).
                        </small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="ri-information-line me-1"></i> Badges are visual recognition tags — they don't affect scores.</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
