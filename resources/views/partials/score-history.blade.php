{{--
    Lifetime score history modal.

    Opened from the small history dot rendered next to the CL R&R / CL Mgr /
    CL Gen score chips in the Task Summary table. Backed by the
    user_score_history table (snapshots written from the three toggle
    endpoints) plus a "now" point computed live on every fetch so the chart
    is never empty.

    Renders an ApexCharts line chart — ApexCharts is already loaded by
    task-summary.blade.php's @section('script'), so no extra <script> here.
--}}

<style>
    #taskSummaryScoreHistoryModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryScoreHistoryModal .modal-header {
        background: linear-gradient(135deg, #0f766e, #14b8a6);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryScoreHistoryModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryScoreHistoryModal .schist-meta {
        font-size: 0.8rem;
        opacity: 0.92;
    }
    #taskSummaryScoreHistoryModal .schist-current {
        background: rgba(255, 255, 255, 0.18);
        border: 2px solid rgba(255, 255, 255, 0.55);
        border-radius: 999px;
        padding: 0.15em 0.7em;
        font-weight: 800;
        font-size: 0.95rem;
        font-variant-numeric: tabular-nums;
    }
    #taskSummaryScoreHistoryModal .schist-type-pill {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 0.1em 0.5em;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.25);
        margin-left: 0.4rem;
    }
    #ts-schist-chart {
        min-height: 320px;
    }
    #ts-schist-empty,
    #ts-schist-loading {
        text-align: center;
        padding: 1.5rem;
        color: #64748b;
    }

    /* Tiny dot button shown after the score chip in CL columns */
    .cl-score-chip {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        padding: 0.1em 0.45em;
        border-radius: 999px;
        margin-left: 0.25rem;
        vertical-align: middle;
        border: 1px solid transparent;
    }
    .cl-score-chip.is-clrr   { background: #ecfeff; color: #0e7490; border-color: #a5f3fc; }
    .cl-score-chip.is-clmgr  { background: #e0e7ff; color: #1e3a8a; border-color: #a5b4fc; }
    .cl-score-chip.is-clgen  { background: #fef3c7; color: #92400e; border-color: #fde68a; }

    .cl-history-dot {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 0.95rem;
        height: 0.95rem;
        margin-left: 0.2rem;
        border-radius: 50%;
        border: none;
        background: #94a3b8;
        color: #fff;
        cursor: pointer;
        vertical-align: middle;
        transition: transform 0.15s ease, background 0.15s ease;
        padding: 0;
        font-size: 0.55rem;
    }
    .cl-history-dot:hover {
        transform: scale(1.2);
    }
    .cl-history-dot.is-clrr  { background: #0e7490; }
    .cl-history-dot.is-clmgr { background: #1d4ed8; }
    .cl-history-dot.is-clgen { background: #b45309; }
    .cl-history-dot:focus-visible {
        outline: 2px solid currentColor;
        outline-offset: 2px;
    }
</style>

<div class="modal fade" id="taskSummaryScoreHistoryModal" tabindex="-1" aria-labelledby="taskSummaryScoreHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryScoreHistoryModalLabel">
                            <i class="ri-line-chart-line me-2" aria-hidden="true"></i>
                            <span id="ts-schist-user">Score history</span>
                            <span class="schist-type-pill" id="ts-schist-type"></span>
                        </h5>
                        <div class="schist-meta">
                            <span id="ts-schist-designation"></span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-2">
                        <span class="schist-current" id="ts-schist-current">—</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-schist-loading" class="d-none">
                    <div class="spinner-border text-info" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="text-muted small mt-2 mb-0">Loading score history…</p>
                </div>
                <div id="ts-schist-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="ts-schist-content" class="d-none">
                    <div id="ts-schist-chart"></div>
                </div>
                <div id="ts-schist-empty" class="d-none">
                    <i class="ri-line-chart-line d-block mb-2" style="font-size:2rem;color:#94a3b8;"></i>
                    <div class="fw-semibold mb-1">No history yet</div>
                    <p class="small mb-0">Each time a checkpoint is toggled, a snapshot is recorded — interact with the related modal and reopen this chart.</p>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="ri-information-line me-1"></i> Lifetime snapshots — points are written when a checkpoint is toggled.</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
