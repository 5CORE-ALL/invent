{{--
    Team Member Profile modal — opened from the magnifying-glass button
    that sits next to the "TM" badge in the Team Member column.

    Distinct from the User Dashboard (which is metric-focused): this one is
    profile-card style with a large hero image, contact info, scores, and
    the people they manage / are managed by. Juniors section is omitted
    automatically when the member is an executive (no reports).

    Backed by the same GET /tasks/user-dashboard endpoint (extended to
    return phone, date_of_joining, managers, etc).
--}}

<style>
    #taskSummaryTmProfileModal .modal-content {
        border-radius: 18px;
        border: none;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
        overflow: hidden;
    }

    #taskSummaryTmProfileModal .tm-hero {
        position: relative;
        padding: 1.4rem 1.4rem 1rem 1.4rem;
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #334155 100%);
        color: #fff;
        overflow: hidden;
    }
    #taskSummaryTmProfileModal .tm-hero::after {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 12% 8%, rgba(56, 189, 248, 0.18), transparent 40%),
            radial-gradient(circle at 88% 92%, rgba(168, 85, 247, 0.18), transparent 45%);
        pointer-events: none;
    }
    #taskSummaryTmProfileModal .tm-hero > * { position: relative; z-index: 1; }
    #taskSummaryTmProfileModal .tm-hero .btn-close {
        position: absolute;
        right: 1.1rem;
        top: 1.1rem;
        z-index: 2;
        filter: brightness(0) invert(1);
        opacity: 0.85;
    }
    #taskSummaryTmProfileModal .tm-hero .btn-close:hover { opacity: 1; }

    #taskSummaryTmProfileModal .tm-hero-row {
        display: flex;
        align-items: center;
        gap: 1.2rem;
        flex-wrap: wrap;
    }
    #taskSummaryTmProfileModal .tm-avatar-wrap {
        position: relative;
        flex-shrink: 0;
    }
    #taskSummaryTmProfileModal .tm-avatar {
        width: 132px;
        height: 132px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid rgba(255, 255, 255, 0.85);
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
        background: #fff;
    }
    @media (max-width: 575.98px) {
        #taskSummaryTmProfileModal .tm-avatar { width: 96px; height: 96px; }
    }
    #taskSummaryTmProfileModal .tm-name {
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 0.2rem;
    }
    #taskSummaryTmProfileModal .tm-des {
        font-size: 0.92rem;
        opacity: 0.92;
        margin-bottom: 0.4rem;
    }
    #taskSummaryTmProfileModal .tm-role-pill {
        display: inline-block;
        font-size: 0.64rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        padding: 0.18em 0.7em;
        border-radius: 999px;
        margin-right: 0.35rem;
    }
    #taskSummaryTmProfileModal .tm-role-pill.is-mgr      { background: #a5f3fc; color: #155e75; }
    #taskSummaryTmProfileModal .tm-role-pill.is-director { background: #c7d2fe; color: #1e3a8a; }
    #taskSummaryTmProfileModal .tm-role-pill.is-exec     { background: #fef3c7; color: #92400e; }
    #taskSummaryTmProfileModal .tm-role-pill.is-none     { background: #e2e8f0; color: #475569; }

    #taskSummaryTmProfileModal .tm-contact-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem 1rem;
        font-size: 0.78rem;
        color: rgba(255, 255, 255, 0.85);
        margin-top: 0.4rem;
    }
    #taskSummaryTmProfileModal .tm-contact-list a { color: inherit; }
    #taskSummaryTmProfileModal .tm-contact-list span i,
    #taskSummaryTmProfileModal .tm-contact-list a i {
        margin-right: 0.3rem;
        opacity: 0.7;
    }

    /* Score row directly under the hero */
    #taskSummaryTmProfileModal .tm-scores {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.55rem;
        padding: 0.9rem 1.4rem;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    @media (max-width: 575.98px) {
        #taskSummaryTmProfileModal .tm-scores { grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0.7rem 1rem; }
    }
    #taskSummaryTmProfileModal .tm-score-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.55rem 0.6rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    #taskSummaryTmProfileModal .tm-score-card .lbl {
        font-size: 0.6rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #475569;
        margin-bottom: 0.2rem;
    }
    #taskSummaryTmProfileModal .tm-score-card .pct {
        font-size: 1.4rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        line-height: 1.05;
        color: #0f172a;
    }
    #taskSummaryTmProfileModal .tm-score-card .bar {
        height: 4px;
        margin-top: 0.35rem;
        background: #e2e8f0;
        border-radius: 999px;
        overflow: hidden;
    }
    #taskSummaryTmProfileModal .tm-score-card .fill {
        height: 100%;
        transition: width 0.35s ease;
    }
    #taskSummaryTmProfileModal .tm-score-card.is-rr     .fill { background: linear-gradient(90deg, #8b5cf6, #6d28d9); }
    #taskSummaryTmProfileModal .tm-score-card.is-clrr   .fill { background: linear-gradient(90deg, #06b6d4, #0e7490); }
    #taskSummaryTmProfileModal .tm-score-card.is-clmgr  .fill { background: linear-gradient(90deg, #6366f1, #1d4ed8); }
    #taskSummaryTmProfileModal .tm-score-card.is-clgen  .fill { background: linear-gradient(90deg, #fbbf24, #b45309); }

    /* Metric tiles */
    #taskSummaryTmProfileModal .tm-section-label {
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
    #taskSummaryTmProfileModal .tm-metrics {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.5rem;
    }
    @media (max-width: 575.98px) {
        #taskSummaryTmProfileModal .tm-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    #taskSummaryTmProfileModal .tm-tile {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.55rem 0.6rem;
        text-align: center;
    }
    #taskSummaryTmProfileModal .tm-tile .val {
        font-weight: 800;
        font-size: 1.1rem;
        font-variant-numeric: tabular-nums;
        line-height: 1.1;
        color: #0f172a;
    }
    #taskSummaryTmProfileModal .tm-tile .lbl {
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-top: 0.2rem;
    }
    #taskSummaryTmProfileModal .tm-tile.is-done .val { color: #15803d; }
    #taskSummaryTmProfileModal .tm-tile.is-overdue .val { color: #dc2626; }

    /* People rows (managers + juniors) */
    #taskSummaryTmProfileModal .tm-people-row {
        display: grid;
        grid-template-columns: 44px 1fr auto;
        gap: 0.6rem;
        align-items: center;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.5rem 0.65rem;
        margin-bottom: 0.4rem;
    }
    #taskSummaryTmProfileModal .tm-people-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        object-fit: cover;
        border: 1px solid #e2e8f0;
    }
    #taskSummaryTmProfileModal .tm-people-name {
        font-weight: 700;
        font-size: 0.9rem;
        color: #0f172a;
        line-height: 1.2;
    }
    #taskSummaryTmProfileModal .tm-people-meta {
        font-size: 0.72rem;
        color: #64748b;
    }
    #taskSummaryTmProfileModal .tm-people-pills {
        display: flex;
        gap: 0.3rem;
        flex-wrap: wrap;
        justify-content: flex-end;
        font-size: 0.7rem;
    }
    #taskSummaryTmProfileModal .tm-people-pills .pill {
        padding: 0.1em 0.5em;
        border-radius: 999px;
        font-weight: 700;
        border: 1px solid transparent;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    #taskSummaryTmProfileModal .tm-people-pills .pill.t { background: #f1f5f9; color: #334155; border-color: #cbd5e1; }
    #taskSummaryTmProfileModal .tm-people-pills .pill.d { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    #taskSummaryTmProfileModal .tm-people-pills .pill.o { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    #taskSummaryTmProfileModal .tm-people-pills .pill.s { background: #e0e7ff; color: #1e3a8a; border-color: #a5b4fc; }

    #taskSummaryTmProfileModal .tm-people-empty {
        font-size: 0.82rem;
        color: #94a3b8;
        text-align: center;
        padding: 0.8rem;
    }

    /* Team Member column TM badge + magnifier */
    .task-summary-tm-badge {
        flex-shrink: 0;
        width: 1.4rem;
        height: 1.4rem;
        padding: 0;
        margin: 0;
        border: none;
        border-radius: 7px;
        background: linear-gradient(135deg, #0d9488, #14b8a6);
        color: #fff;
        font-size: 0.6rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        line-height: 1;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        vertical-align: middle;
        box-shadow: 0 1px 3px rgba(13, 148, 136, 0.4), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
        transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    }
    .task-summary-tm-badge:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(13, 148, 136, 0.5), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
        background: linear-gradient(135deg, #0f766e, #0d9488);
    }
    .task-summary-tm-badge:focus-visible {
        outline: 2px solid #0d9488;
        outline-offset: 2px;
    }
    .task-summary-tm-badge.task-summary-tm-badge-director {
        background: linear-gradient(135deg, #4338ca, #6366f1);
        box-shadow: 0 1px 3px rgba(99, 102, 241, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
    }
    .task-summary-tm-badge.task-summary-tm-badge-director:hover {
        background: linear-gradient(135deg, #3730a3, #4f46e5);
    }
    .task-summary-tm-badge.task-summary-tm-badge-mgr {
        background: linear-gradient(135deg, #0e7490, #06b6d4);
        box-shadow: 0 1px 3px rgba(6, 182, 212, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
    }
    .task-summary-tm-badge.task-summary-tm-badge-mgr:hover {
        background: linear-gradient(135deg, #155e75, #0e7490);
    }
    .task-summary-tm-badge.task-summary-tm-badge-exec {
        background: linear-gradient(135deg, #b45309, #f59e0b);
        box-shadow: 0 1px 3px rgba(245, 158, 11, 0.45), inset 0 -1px 0 rgba(0, 0, 0, 0.08);
    }
    .task-summary-tm-badge.task-summary-tm-badge-exec:hover {
        background: linear-gradient(135deg, #92400e, #b45309);
    }

    .task-summary-tm-profile-btn {
        flex-shrink: 0;
        border: none;
        background: transparent;
        color: #64748b;
        padding: 0.1rem 0.3rem;
        margin-left: 0.15rem;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s ease, color 0.15s ease, transform 0.15s ease;
        line-height: 1;
    }
    .task-summary-tm-profile-btn:hover {
        background: rgba(15, 23, 42, 0.08);
        color: #0f172a;
        transform: scale(1.1);
    }
    .task-summary-tm-profile-btn:focus-visible {
        outline: 2px solid #334155;
        outline-offset: 2px;
    }
</style>

<div class="modal fade" id="taskSummaryTmProfileModal" tabindex="-1" aria-labelledby="taskSummaryTmProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            {{-- Hero (large image + name + designation + contact) --}}
            <div class="tm-hero">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="tm-hero-row">
                    <div class="tm-avatar-wrap">
                        <img id="ts-tm-avatar" class="tm-avatar" src="" alt="" />
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <h2 class="tm-name" id="ts-tm-name" aria-labelledby="taskSummaryTmProfileModalLabel">Team member</h2>
                        <div class="tm-des" id="ts-tm-des">—</div>
                        <div>
                            <span class="tm-role-pill" id="ts-tm-role">No role</span>
                            <span id="ts-tm-extra" class="small" style="opacity:0.85;"></span>
                        </div>
                        <div class="tm-contact-list" id="ts-tm-contact"></div>
                        <span id="taskSummaryTmProfileModalLabel" class="visually-hidden">Team member profile</span>
                    </div>
                </div>
            </div>

            {{-- Score row --}}
            <div class="tm-scores" id="ts-tm-scores"></div>

            <div class="modal-body">
                <div id="ts-tm-loading" class="text-center py-3 d-none">
                    <div class="spinner-border text-secondary" role="status"><span class="visually-hidden">Loading…</span></div>
                    <p class="text-muted small mt-2 mb-0">Loading profile…</p>
                </div>
                <div id="ts-tm-error" class="alert alert-danger d-none" role="alert"></div>

                <div id="ts-tm-content" class="d-none">
                    <div class="tm-section-label"><i class="ri-pie-chart-line"></i> Task metrics</div>
                    <div class="tm-metrics" id="ts-tm-metrics"></div>

                    <div id="ts-tm-managers-wrap" class="d-none">
                        <div class="tm-section-label">
                            <i class="ri-user-star-line"></i> Reports to
                            <span class="badge bg-secondary ms-1" id="ts-tm-managers-count">0</span>
                        </div>
                        <div id="ts-tm-managers"></div>
                    </div>

                    <div id="ts-tm-juniors-wrap" class="d-none">
                        <div class="tm-section-label">
                            <i class="ri-team-line"></i> People reporting to this member
                            <span class="badge bg-secondary ms-1" id="ts-tm-juniors-count">0</span>
                        </div>
                        <div id="ts-tm-juniors"></div>
                        <div id="ts-tm-juniors-empty" class="tm-people-empty d-none">
                            <i class="ri-team-line d-block mb-1" style="font-size:1.6rem;color:#cbd5e1;"></i>
                            No juniors tagged under this person yet.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto"><i class="ri-information-line me-1"></i> Junior section is hidden automatically for executives.</small>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
