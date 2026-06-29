{{--
    KPI modal — Task Summary "KPI" column.
    Assigns live page badges from badges_data (All Marketplace Master, Forecast Analysis, On Sea Transit, …).

    GET    /tasks/user-kpis
    POST   /tasks/user-kpis
    DELETE /tasks/user-kpis
--}}

<style>
    #taskSummaryUserKpisModal .modal-content {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
    }
    #taskSummaryUserKpisModal .modal-header {
        background: linear-gradient(135deg, #0f766e, #14b8a6);
        color: #fff;
        border-bottom: 0;
        border-top-left-radius: 16px;
        border-top-right-radius: 16px;
    }
    #taskSummaryUserKpisModal .modal-header .btn-close {
        filter: brightness(0) invert(1);
    }
    #taskSummaryUserKpisModal .uk-meta {
        font-size: 0.78rem;
        opacity: 0.92;
    }
    .kpi-badges-search-icon-btn {
        border: none;
        background: transparent;
        color: #0f766e;
        padding: 0.15rem 0.35rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
    }
    .kpi-badges-search-icon-btn:hover {
        background: rgba(15, 118, 110, 0.12);
        color: #115e59;
        transform: scale(1.1);
    }
    .kpi-badges-search-icon-btn:focus-visible {
        outline: 2px solid #0f766e;
        outline-offset: 2px;
    }
    .kpi-badges-count {
        margin-left: 0.2rem;
        font-size: 0.65rem;
        font-weight: 800;
        color: #0f766e;
        background: #ccfbf1;
        border: 1px solid #99f6e4;
        padding: 0.05em 0.4em;
        border-radius: 999px;
        vertical-align: middle;
    }
    #ts-uk-assigned-table td {
        vertical-align: middle;
    }
    #ts-uk-assigned-table .uk-value {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    /* Custom searchable multi-select (no Select2) */
    .uk-picker {
        position: relative;
    }
    .uk-picker-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        min-height: 0;
        margin-bottom: 0.5rem;
    }
    .uk-picker-chips:empty {
        display: none;
    }
    .uk-picker-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        background: #ccfbf1;
        border: 1px solid #99f6e4;
        color: #115e59;
        font-size: 0.78rem;
        font-weight: 600;
        max-width: 100%;
    }
    .uk-picker-chip button {
        border: none;
        background: transparent;
        color: #0f766e;
        padding: 0;
        line-height: 1;
        cursor: pointer;
        font-size: 0.95rem;
    }
    .uk-picker-input-wrap {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #fff;
        padding: 0.35rem 0.55rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .uk-picker-input-wrap:focus-within,
    .uk-picker.is-open .uk-picker-input-wrap {
        border-color: #14b8a6;
        box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
    }
    .uk-picker-input-wrap > i {
        color: #64748b;
        font-size: 1rem;
        flex-shrink: 0;
    }
    #ts-uk-picker-search {
        border: none;
        outline: none;
        box-shadow: none;
        flex: 1 1 auto;
        min-width: 0;
        font-size: 0.875rem;
        padding: 0.1rem 0;
        background: transparent;
    }
    .uk-picker-toggle {
        border: none;
        background: transparent;
        color: #64748b;
        padding: 0.1rem;
        line-height: 1;
        cursor: pointer;
        flex-shrink: 0;
    }
    .uk-picker-toggle i {
        transition: transform 0.15s ease;
    }
    .uk-picker.is-open .uk-picker-toggle i {
        transform: rotate(180deg);
    }
    .uk-picker-dropdown {
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        max-height: 260px;
        overflow: auto;
    }
    .uk-picker-dropdown.is-floating {
        position: fixed;
        z-index: 1065;
    }
    #ts-uk-add-wrap {
        overflow: visible;
    }
    .uk-picker-group-label {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: 0.5rem 0.75rem;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        background: #f1f5f9;
        border-bottom: 1px solid #e2e8f0;
    }
    .uk-picker-group--all-marketplace-master {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1e3a8a;
        border-bottom-color: #93c5fd;
    }
    .uk-picker-group--forecast-analysis {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #065f46;
        border-bottom-color: #6ee7b7;
    }
    .uk-picker-group--on-sea-transit {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #92400e;
        border-bottom-color: #fcd34d;
    }
    .uk-picker-option[data-page="all-marketplace-master"]:hover:not(.is-disabled) {
        background: #eff6ff;
    }
    .uk-picker-option[data-page="all-marketplace-master"].is-selected {
        background: #dbeafe;
    }
    .uk-picker-option[data-page="forecast-analysis"]:hover:not(.is-disabled) {
        background: #ecfdf5;
    }
    .uk-picker-option[data-page="forecast-analysis"].is-selected {
        background: #d1fae5;
    }
    .uk-picker-option[data-page="on-sea-transit"]:hover:not(.is-disabled) {
        background: #fffbeb;
    }
    .uk-picker-option[data-page="on-sea-transit"].is-selected {
        background: #fef3c7;
    }
    .uk-picker-option {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.45rem 0.75rem;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.12s ease;
        margin: 0;
    }
    .uk-picker-option:last-child {
        border-bottom: 0;
    }
    .uk-picker-option:hover:not(.is-disabled) {
        background: #f0fdfa;
    }
    .uk-picker-option.is-disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }
    .uk-picker-option.is-selected {
        background: #ecfdf5;
    }
    .uk-picker-option input {
        margin: 0;
        flex-shrink: 0;
        accent-color: #0f766e;
    }
    .uk-picker-option-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        min-width: 0;
        flex: 1 1 auto;
    }
    .uk-picker-option-title {
        display: block;
        flex: 1 1 auto;
        min-width: 0;
        font-size: 0.84rem;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.3;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .uk-picker-option-meta {
        display: block;
        flex: 0 0 auto;
        font-size: 0.78rem;
        font-weight: 600;
        color: #0f766e;
        line-height: 1.3;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .uk-picker-empty {
        padding: 1rem 0.75rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.82rem;
    }
    .uk-picker-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 0.55rem;
    }
    #ts-uk-picker-count {
        font-size: 0.78rem;
        color: #64748b;
    }
</style>

<div class="modal fade" id="taskSummaryUserKpisModal" tabindex="-1" aria-labelledby="taskSummaryUserKpisModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header flex-column align-items-stretch">
                <div class="d-flex align-items-start w-100">
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-1" id="taskSummaryUserKpisModalLabel">
                            <i class="ri-bar-chart-box-line me-2" aria-hidden="true"></i>
                            <span id="ts-uk-modal-user">KPI</span>
                        </h5>
                        <div class="uk-meta">
                            <span id="ts-uk-modal-designation"></span>
                            <span class="ms-2" id="ts-uk-modal-count"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="ts-uk-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-success" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="text-muted small mt-2 mb-0">Loading KPI badges…</p>
                </div>
                <div id="ts-uk-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-uk-content" class="d-none">
                    <h6 class="text-muted text-uppercase small fw-bold mb-2">Assigned KPIs</h6>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-hover align-middle mb-0" id="ts-uk-assigned-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Badge</th>
                                    <th class="text-end">Live value</th>
                                    <th class="text-end" style="width:4rem;"></th>
                                </tr>
                            </thead>
                            <tbody id="ts-uk-assigned-body"></tbody>
                        </table>
                    </div>
                    <p id="ts-uk-empty" class="text-muted small d-none mb-3">No KPI badges assigned yet.</p>

                    <div id="ts-uk-add-wrap">
                        <h6 class="text-muted text-uppercase small fw-bold mb-2">Add from badges_data</h6>
                        <div class="uk-picker" id="ts-uk-picker">
                            <div class="uk-picker-chips" id="ts-uk-picker-chips" aria-live="polite"></div>
                            <div class="uk-picker-control">
                                <div class="uk-picker-input-wrap" id="ts-uk-picker-anchor">
                                    <i class="ri-search-line" aria-hidden="true"></i>
                                    <input type="text"
                                           id="ts-uk-picker-search"
                                           class="form-control form-control-sm border-0 shadow-none p-0"
                                           placeholder="Search page badges…"
                                           autocomplete="off"
                                           aria-controls="ts-uk-picker-list"
                                           aria-expanded="false"
                                           aria-autocomplete="list" />
                                    <button type="button" class="uk-picker-toggle" id="ts-uk-picker-toggle" aria-label="Show badge list">
                                        <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <div class="uk-picker-dropdown d-none" id="ts-uk-picker-dropdown">
                                    <div id="ts-uk-picker-list" role="listbox" aria-multiselectable="true"></div>
                                </div>
                            </div>
                            <div class="uk-picker-footer">
                                <span id="ts-uk-picker-count">0 selected</span>
                                <button type="button" class="btn btn-sm text-white" id="ts-uk-add-btn" style="background:#0f766e;border-color:#0f766e;" disabled>
                                    <i class="ri-add-line"></i> Add selected
                                </button>
                            </div>
                        </div>
                        <p id="ts-uk-add-hint" class="text-muted small mt-2 mb-0 d-none">All available badges are already assigned (max 5).</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="ri-information-line me-1"></i> Values refresh from badges_data when you open this modal.</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
