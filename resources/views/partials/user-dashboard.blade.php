{{--
    User Dashboard modal — opened from the KPI column magnifying glass.

    Shows the clicked user's profile + task metrics + scores (R&R, CL R&R,
    CL Gen, CL Mgr) plus everyone tagged under them (via manager_juniors)
    with the same metrics, so it acts as a "what does this person own"
    dashboard scoped by their tags.
--}}

<style>
    #taskSummaryUserDashboardModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryUserDashboardModal .modal-header {
        background: linear-gradient(135deg, #0f172a, #334155);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryUserDashboardModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }

    /* User header */
    #ts-udash-user-header {
        display: flex;
        align-items: center;
        gap: 0.85rem;
    }
    #ts-udash-user-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        border: 3px solid rgba(255, 255, 255, 0.6);
        object-fit: cover;
        flex-shrink: 0;
    }
    #ts-udash-user-name {
        font-size: 1.1rem;
        font-weight: 700;
        line-height: 1.15;
        margin-bottom: 0.15rem;
    }
    #ts-udash-user-meta {
        font-size: 0.78rem;
        opacity: 0.92;
    }
    .ts-udash-role-pill {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 0.12em 0.55em;
        border-radius: 999px;
        margin-left: 0.4rem;
        vertical-align: middle;
    }
    .ts-udash-role-pill.is-mgr { background: #a5f3fc; color: #155e75; }
    .ts-udash-role-pill.is-director { background: #c7d2fe; color: #1e3a8a; }
    .ts-udash-role-pill.is-exec { background: #fef3c7; color: #92400e; }
    .ts-udash-role-pill.is-none { background: #e2e8f0; color: #475569; }

    /* Section labels */
    .ts-udash-section-label {
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

    /* Metric tiles */
    .ts-udash-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.5rem;
    }
    @media (max-width: 575.98px) {
        .ts-udash-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .ts-udash-tile {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.55rem 0.6rem;
        text-align: center;
    }
    .ts-udash-tile .val {
        font-weight: 800;
        font-size: 1.15rem;
        font-variant-numeric: tabular-nums;
        line-height: 1.15;
        color: #0f172a;
    }
    .ts-udash-tile .lbl {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-top: 0.2rem;
    }
    .ts-udash-tile.is-done .val { color: #15803d; }
    .ts-udash-tile.is-overdue .val { color: #dc2626; }

    /* Score grid */
    .ts-udash-scores {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.5rem;
    }
    @media (max-width: 575.98px) {
        .ts-udash-scores { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .ts-udash-score-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.55rem 0.6rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .ts-udash-score-card .lbl {
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #475569;
        margin-bottom: 0.25rem;
    }
    .ts-udash-score-card .pct {
        font-size: 1.4rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
        color: #0f172a;
    }
    .ts-udash-score-card .bar {
        height: 5px;
        margin-top: 0.4rem;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    .ts-udash-score-card .fill {
        height: 100%;
        transition: width 0.3s ease;
    }
    .ts-udash-score-card.is-rr     .fill { background: linear-gradient(90deg, #8b5cf6, #6d28d9); }
    .ts-udash-score-card.is-clrr   .fill { background: linear-gradient(90deg, #06b6d4, #0e7490); }
    .ts-udash-score-card.is-clmgr  .fill { background: linear-gradient(90deg, #6366f1, #1d4ed8); }
    .ts-udash-score-card.is-clgen  .fill { background: linear-gradient(90deg, #fbbf24, #b45309); }

    /* Juniors list */
    .ts-udash-junior-card {
        display: grid;
        grid-template-columns: 36px 1fr auto;
        gap: 0.55rem;
        align-items: center;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.55rem 0.7rem;
        margin-bottom: 0.45rem;
    }
    .ts-udash-junior-card .ts-udash-junior-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }
    .ts-udash-junior-name {
        font-weight: 700;
        font-size: 0.88rem;
        color: #0f172a;
        line-height: 1.2;
    }
    .ts-udash-junior-des {
        font-size: 0.72rem;
        color: #64748b;
    }
    .ts-udash-junior-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem;
        font-size: 0.7rem;
        justify-content: flex-end;
    }
    .ts-udash-junior-pills .pill {
        padding: 0.1em 0.5em;
        border-radius: 999px;
        font-weight: 700;
        border: 1px solid transparent;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .ts-udash-junior-pills .pill.task { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
    .ts-udash-junior-pills .pill.done { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    .ts-udash-junior-pills .pill.overdue { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .ts-udash-junior-pills .pill.score { background: #e0e7ff; color: #1e3a8a; border-color: #a5b4fc; }

    #ts-udash-juniors-empty {
        text-align: center;
        font-size: 0.82rem;
        color: #94a3b8;
        padding: 1rem;
    }

    /* KPI button (new magnifier style — matches the other column dots) */
    .kpi-search-icon-btn {
        border: none;
        background: transparent;
        color: #334155;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .kpi-search-icon-btn:hover {
        background: rgba(15, 23, 42, 0.08);
        color: #0f172a;
        transform: scale(1.1);
    }
    .kpi-search-icon-btn:focus-visible {
        outline: 2px solid #334155;
        outline-offset: 2px;
    }
</style>

<div class="modal fade" id="taskSummaryUserDashboardModal" tabindex="-1" aria-labelledby="taskSummaryUserDashboardModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div id="ts-udash-user-header" class="flex-grow-1 min-w-0">
                    <img id="ts-udash-user-avatar" src="" alt="" />
                    <div class="flex-grow-1 min-w-0">
                        <div id="ts-udash-user-name">User Dashboard</div>
                        <div id="ts-udash-user-meta">
                            <span id="ts-udash-user-des"></span>
                            <span class="ts-udash-role-pill" id="ts-udash-user-role"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ts-udash-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden">Loading…</span>
                    </div>
                    <p class="text-muted small mt-2 mb-0">Loading dashboard…</p>
                </div>
                <div id="ts-udash-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-udash-content" class="d-none">
                    <div class="ts-udash-section-label">
                        <i class="ri-pie-chart-line"></i> Task metrics
                    </div>
                    <div class="ts-udash-metrics" id="ts-udash-self-metrics"></div>

                    <div class="ts-udash-section-label">
                        <i class="ri-bar-chart-grouped-line"></i> Scores
                    </div>
                    <div class="ts-udash-scores" id="ts-udash-self-scores"></div>

                    <div class="ts-udash-section-label" id="ts-udash-juniors-label">
                        <i class="ri-team-line"></i> Team tagged under this person <span id="ts-udash-juniors-count" class="badge bg-secondary ms-1">0</span>
                    </div>
                    <div id="ts-udash-juniors-list"></div>
                    <div id="ts-udash-juniors-empty" class="d-none">
                        No team members tagged under this user.
                        @if($canEditTags ?? false)
                            <br><small class="text-muted">If they're a Mgr or Director, use the Tags dot in the Role column to assign juniors.</small>
                        @endif
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto" id="ts-udash-formula-hint">
                    <i class="ri-information-line me-1"></i>
                    Junior score pill = (CL R&amp;R + CL Gen) / 2 — also drives the manager's combined Mgr score.
                </small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
