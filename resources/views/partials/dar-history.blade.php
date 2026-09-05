{{--
    Last-30-days DAR submission chart.

    Opened from the history dot beside the Task Summary DAR % cell.
    Series is passed from the row (unique report_date flags), so this
    modal does not need its own endpoint.
--}}

<style>
    #taskSummaryDarHistoryModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryDarHistoryModal .modal-header {
        background: linear-gradient(135deg, #7c3aed, #db2777);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryDarHistoryModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryDarHistoryModal .darhist-meta {
        font-size: 0.8rem;
        opacity: 0.92;
    }
    #taskSummaryDarHistoryModal .darhist-current {
        background: rgba(255, 255, 255, 0.18);
        border: 2px solid rgba(255, 255, 255, 0.55);
        border-radius: 999px;
        padding: 0.15em 0.7em;
        font-weight: 800;
        font-size: 0.95rem;
        font-variant-numeric: tabular-nums;
    }
    #ts-darhist-chart {
        min-height: 320px;
    }
    .cl-history-dot.is-dar {
        background: #7c3aed;
    }
</style>

<div class="modal fade" id="taskSummaryDarHistoryModal" tabindex="-1" aria-labelledby="taskSummaryDarHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryDarHistoryModalLabel">
                            <i class="ri-line-chart-line me-2" aria-hidden="true"></i>
                            <span id="ts-darhist-user">DAR history</span>
                        </h5>
                        <div class="darhist-meta">Last 30 days · unique days submitted vs target of 25</div>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-2">
                        <span class="darhist-current" id="ts-darhist-current">—</span>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-darhist-empty" class="d-none text-center py-4 text-muted">
                    <i class="ri-line-chart-line d-block mb-2" style="font-size:2rem;color:#94a3b8;"></i>
                    <div class="fw-semibold mb-1">No DAR days in the last 30 days</div>
                    <p class="small mb-0">A bar is drawn for each calendar day. Teal = submitted.</p>
                </div>
                <div id="ts-darhist-content">
                    <div id="ts-darhist-chart"></div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="ri-information-line me-1"></i> Teal = submitted · red = weekday miss · gray = weekend miss</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
