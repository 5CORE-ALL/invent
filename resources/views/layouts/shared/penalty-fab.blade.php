{{--
    Floating "Penalty" icon, shown on every page for logged-in users (sits above the Salary icon).

    PURPOSE: penalty points are NOT meant to deduct money. They exist to evaluate the mistakes and
    shortcomings in a person's performance so they can be spotted early, corrected and learned from.

    - Every user sees ONLY their own penalty points + reasons, the last-30-days count and amount (each with a
      history dot opening the standard line graph), the slabs, the monthly totals frozen on the 1st of every
      month, and can add corrective points for themselves.
    - Only president@5core.com sees the president tools (team, add penalty, auto rules, slab editing).
      This is enforced again on the server in PenaltyFabController.

      GET  /penalty-fab            (own detail; plus tools data for president@5core.com; ?user_id= president only)
      GET  /penalty-fab/history    (rolling L30 count / amount graph data)
      POST /penalty-fab/...        (president: entries, rules, slabs | everyone: own corrections)

    Hide on a page with: @extends('layouts.vertical', [..., 'hidePenaltyFab' => true])
--}}
@auth
@unless($hidePenaltyFab ?? false)
<style>
    /* ---------- floating button ---------- */
    #penaltyFab {
        position: fixed; right: 20px; bottom: 276px; z-index: 1040;
        width: 56px; height: 56px; border-radius: 20px; border: 0; padding: 0;
        background: linear-gradient(135deg, #f43f5e 0%, #f97316 100%); color: #fff;
        display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1;
        box-shadow: 0 8px 22px rgba(244, 63, 94, .45), inset 0 1px 0 rgba(255,255,255,.35);
        cursor: pointer; transition: transform .18s ease, box-shadow .18s ease, border-radius .18s ease;
        animation: penBob 3.6s ease-in-out infinite .6s;
    }
    #penaltyFab:hover { transform: translateY(-4px) rotate(4deg) scale(1.05); border-radius: 50%; box-shadow: 0 14px 28px rgba(249, 115, 22, .5); animation: none; }
    #penaltyFab i { font-size: 21px; }
    #penaltyFab .pen-fab-text { font-size: 8px; font-weight: 900; letter-spacing: .08em; margin-top: 2px; }
    #penaltyFab .pen-fab-badge {
        position: absolute; top: -6px; right: -6px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 999px;
        background: #fff; color: #e11d48; font-size: 11px; font-weight: 900; display: none; align-items: center; justify-content: center;
        box-shadow: 0 3px 8px rgba(0,0,0,.25);
    }
    @keyframes penBob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    @media (max-width: 991.98px) { #penaltyFab { right: 12px; bottom: 330px; width: 50px; height: 50px; border-radius: 18px; } }
    @media (prefers-reduced-motion: reduce) { #penaltyFab { animation: none; } }

    /* ---------- modal shell ---------- */
    #penaltyFabModal .modal-content { border-radius: 28px; border: 0; overflow: hidden; background: #f7f5fb; box-shadow: 0 30px 80px rgba(136, 19, 55, .3); }
    #penaltyFabModal .modal-header { position: relative; overflow: hidden; border-bottom: 0; color: #fff; padding: 20px 22px 28px; background: #1f1235; }
    #penaltyFabModal .modal-header::before, #penaltyFabModal .modal-header::after { content: ''; position: absolute; border-radius: 50%; filter: blur(34px); opacity: .85; pointer-events: none; }
    #penaltyFabModal .modal-header::before { width: 220px; height: 220px; left: -50px; top: -90px; background: #f43f5e; }
    #penaltyFabModal .modal-header::after { width: 200px; height: 200px; right: -40px; top: -70px; background: #f97316; }
    #penaltyFabModal .pen-head { position: relative; z-index: 1; flex: 1; min-width: 0; }
    #penaltyFabModal .modal-title { font-weight: 900; font-size: 1.35rem; letter-spacing: -.02em; }
    #penaltyFabModal .pen-sub { font-size: .85rem; opacity: .88; margin-top: 2px; }
    #penaltyFabModal .pen-back { border: 0; border-radius: 999px; background: rgba(255,255,255,.22); color: #fff; font-weight: 800; font-size: .72rem; padding: 3px 10px; margin-left: 6px; }
    #penaltyFabModal .btn-close { position: relative; z-index: 1; filter: brightness(0) invert(1); opacity: .9; }
    #penaltyFabModal .modal-body { padding: 0 18px 8px; margin-top: -16px; position: relative; z-index: 2; }
    #penaltyFabModal .modal-footer { border-top: 0; background: transparent; }
    #penaltyFabModal .modal-footer .btn { border-radius: 14px; font-weight: 700; padding: 8px 22px; }
    #penaltyFabModal .pen-hidden { display: none !important; }
    #penaltyFabModal .pen-error { border-radius: 14px; font-size: .85rem; margin-bottom: 10px; }

    /* ---------- declaration ---------- */
    #penaltyFabModal .pen-decl {
        border-radius: 22px; padding: 14px 16px; color: #3b0764; display: flex; gap: 12px; align-items: flex-start;
        background: linear-gradient(135deg, #f3e8ff 0%, #ffe4e6 55%, #ffedd5 100%); box-shadow: 0 8px 22px rgba(168, 85, 247, .16);
    }
    #penaltyFabModal .pen-decl-emoji { font-size: 1.7rem; line-height: 1; }
    #penaltyFabModal .pen-decl-title { font-weight: 900; font-size: .95rem; letter-spacing: -.01em; }
    #penaltyFabModal .pen-decl-text { font-size: .8rem; line-height: 1.45; margin-top: 2px; opacity: .92; }

    /* ---------- L30 badges ---------- */
    #penaltyFabModal .pen-badges { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 12px; }
    #penaltyFabModal .pen-badge { position: relative; border-radius: 24px; padding: 16px 18px; color: #fff; overflow: hidden; }
    #penaltyFabModal .pen-badge.count { background: linear-gradient(135deg, #f43f5e 0%, #fb7185 60%, #fb923c 130%); box-shadow: 0 12px 28px rgba(244, 63, 94, .35); }
    #penaltyFabModal .pen-badge.amount { background: linear-gradient(135deg, #7c3aed 0%, #a855f7 60%, #ec4899 130%); box-shadow: 0 12px 28px rgba(124, 58, 237, .33); }
    #penaltyFabModal .pen-badge.clean.count { background: linear-gradient(135deg, #10b981 0%, #34d399 70%, #22d3ee 140%); box-shadow: 0 12px 28px rgba(16, 185, 129, .33); }
    #penaltyFabModal .pen-badge-label { font-size: .68rem; font-weight: 900; letter-spacing: .12em; text-transform: uppercase; opacity: .88; }
    #penaltyFabModal .pen-badge-value { font-size: 2.6rem; font-weight: 900; letter-spacing: -.04em; line-height: 1.1; font-variant-numeric: tabular-nums; margin-top: 2px; }
    #penaltyFabModal .pen-badge-sub { font-size: .75rem; opacity: .9; }
    #penaltyFabModal .pen-badge-emoji { position: absolute; right: 14px; bottom: 6px; font-size: 3rem; opacity: .22; pointer-events: none; }
    /* history dot: same red dot as the Task Summary O-Due history */
    #penaltyFabModal .pen-hist-dot {
        position: absolute; top: 14px; right: 14px; width: 14px; height: 14px; border-radius: 50%; border: 0; padding: 0; cursor: pointer;
        background: #dc2626; box-shadow: 0 0 0 3px rgba(255,255,255,.55); transition: transform .15s ease;
    }
    #penaltyFabModal .pen-hist-dot:hover { transform: scale(1.3); }
    #penaltyFabModal .pen-hist-dot:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }

    /* ---------- history chart panel ---------- */
    #penaltyFabModal .pen-chart { margin-top: 12px; background: #fff; border-radius: 22px; padding: 14px 14px 6px; box-shadow: 0 8px 22px rgba(30, 27, 75, .08); }
    #penaltyFabModal .pen-chart-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    #penaltyFabModal .pen-chart-title { font-weight: 900; font-size: .88rem; color: #1f1235; flex: 1; min-width: 150px; }
    #penaltyFabModal .pen-chart-title small { display: block; font-weight: 600; color: #8b8aa8; font-size: .7rem; }
    #penaltyFabModal .pen-chart select { width: 92px; border-radius: 10px; }
    #penaltyFabModal .pen-chart-x { border: 0; background: #f1effa; border-radius: 10px; width: 30px; height: 30px; font-size: 1.1rem; line-height: 1; color: #6b6a86; }
    #penaltyFabModal .pen-chart-empty { text-align: center; padding: 28px 10px; color: #8b8aa8; font-size: .85rem; }

    /* ---------- slab ladder ---------- */
    #penaltyFabModal .pen-card { background: #fff; border-radius: 22px; padding: 14px 16px; margin-top: 12px; box-shadow: 0 6px 20px rgba(30, 27, 75, .07); }
    #penaltyFabModal .pen-section { font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .12em; color: #be123c; margin: 0 0 10px; }
    #penaltyFabModal .pen-ladder { display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; }
    #penaltyFabModal .pen-step { border-radius: 14px; padding: 8px 4px; text-align: center; background: #f4f2fb; color: #8b8aa8; transition: transform .2s ease; }
    #penaltyFabModal .pen-step b { display: block; font-size: 1.05rem; font-weight: 900; color: #6b6a86; }
    #penaltyFabModal .pen-step span { font-size: .66rem; font-weight: 800; }
    #penaltyFabModal .pen-step.passed { background: #ffe4e6; }
    #penaltyFabModal .pen-step.passed b { color: #be123c; }
    #penaltyFabModal .pen-step.now { background: linear-gradient(135deg, #f43f5e, #f97316); color: #fff; transform: translateY(-4px) scale(1.04); box-shadow: 0 8px 18px rgba(244, 63, 94, .4); }
    #penaltyFabModal .pen-step.now b { color: #fff; }
    #penaltyFabModal .pen-ladder-note { font-size: .78rem; color: #6b6a86; margin-top: 10px; }

    /* ---------- chips ---------- */
    #penaltyFabModal .pen-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
    #penaltyFabModal .pen-chip { font-size: .76rem; font-weight: 800; padding: 6px 12px; border-radius: 999px; background: #fff; color: #4c1d95; box-shadow: 0 3px 10px rgba(30, 27, 75, .06); }
    #penaltyFabModal .pen-chip.good { background: #ecfdf5; color: #047857; }
    #penaltyFabModal .pen-chip.warn { background: #fff1f2; color: #be123c; }
    #penaltyFabModal .pen-msg { font-size: .9rem; font-weight: 800; color: #1f1235; margin-top: 12px; }

    /* ---------- penalty log ---------- */
    #penaltyFabModal .pen-list { display: flex; flex-direction: column; gap: 8px; max-height: 360px; overflow-y: auto; padding: 2px 4px 4px 2px; }
    #penaltyFabModal .pen-row { display: flex; gap: 12px; align-items: flex-start; background: #fff7f8; border-radius: 18px; padding: 10px 12px; }
    #penaltyFabModal .pen-pts { flex: none; width: 46px; height: 46px; border-radius: 15px; display: grid; place-items: center; font-weight: 900; font-size: 1.05rem; color: #fff; background: linear-gradient(135deg, #f43f5e, #f97316); box-shadow: 0 5px 12px rgba(244, 63, 94, .35); }
    #penaltyFabModal .pen-row-main { flex: 1; min-width: 0; }
    #penaltyFabModal .pen-reason { font-weight: 700; color: #1f1235; font-size: .88rem; word-break: break-word; }
    #penaltyFabModal .pen-meta { font-size: .72rem; color: #8b8aa8; margin-top: 2px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    #penaltyFabModal .pen-tag { font-size: .64rem; font-weight: 900; letter-spacing: .06em; text-transform: uppercase; padding: 1px 8px; border-radius: 999px; background: #ede9fe; color: #6d28d9; }
    #penaltyFabModal .pen-tag.auto { background: #e0f2fe; color: #0369a1; }
    #penaltyFabModal .pen-tag.fixed { background: #d1fae5; color: #047857; }
    #penaltyFabModal .pen-link { border: 0; background: none; color: #7c3aed; font-weight: 800; font-size: .74rem; padding: 0; cursor: pointer; }
    #penaltyFabModal .pen-link.danger { color: #e11d48; }
    #penaltyFabModal .pen-inline { display: flex; gap: 6px; margin-top: 8px; }
    #penaltyFabModal .pen-empty { text-align: center; color: #8b8aa8; font-size: .85rem; padding: 18px 8px; }
    #penaltyFabModal .pen-empty b { display: block; font-size: 1.6rem; }

    /* ---------- corrective ---------- */
    #penaltyFabModal .pen-fix { background: linear-gradient(135deg, #ecfdf5, #d1fae5); }
    #penaltyFabModal .pen-fix .pen-section { color: #047857; }
    #penaltyFabModal .pen-fix-item { display: flex; gap: 8px; align-items: flex-start; font-size: .82rem; color: #065f46; padding: 6px 0; border-top: 1px dashed rgba(4, 120, 87, .25); }
    #penaltyFabModal .pen-fix-item:first-child { border-top: 0; }
    #penaltyFabModal .pen-btn { border: 0; border-radius: 12px; padding: 8px 14px; font-weight: 800; font-size: .8rem; color: #fff; background: linear-gradient(135deg, #8b5cf6, #ec4899); white-space: nowrap; transition: transform .12s ease; }
    #penaltyFabModal .pen-btn:hover { transform: scale(1.04); }
    #penaltyFabModal .pen-btn:disabled { opacity: .55; transform: none; }
    #penaltyFabModal .pen-btn.green { background: linear-gradient(135deg, #10b981, #059669); }
    #penaltyFabModal .pen-btn.ghost { background: #ede9fe; color: #6d28d9; }
    #penaltyFabModal .pen-input { border: 2px solid transparent; background: #f3f0ff; border-radius: 12px; padding: 8px 12px; font-size: .85rem; width: 100%; outline: none; color: #1f1235; }
    #penaltyFabModal .pen-input:focus { border-color: #a78bfa; box-shadow: 0 0 0 3px rgba(167, 139, 250, .25); }
    #penaltyFabModal textarea.pen-input { resize: vertical; min-height: 60px; }

    /* ---------- months ---------- */
    #penaltyFabModal .pen-month { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px solid #f1effa; font-size: .84rem; flex-wrap: wrap; }
    #penaltyFabModal .pen-month:first-of-type { border-top: 0; }
    #penaltyFabModal .pen-month b { color: #1f1235; }
    #penaltyFabModal .pen-month .amt { font-weight: 900; color: #7c3aed; font-variant-numeric: tabular-nums; }
    #penaltyFabModal .pen-note { font-size: .74rem; color: #8b8aa8; margin-top: 8px; }

    /* ---------- president tools ---------- */
    #penaltyFabModal .pen-admin { background: linear-gradient(135deg, #1f1235, #3b0764); color: #fff; }
    #penaltyFabModal .pen-admin .pen-section { color: #fda4af; }
    #penaltyFabModal .pen-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
    #penaltyFabModal .pen-tab { border: 0; border-radius: 999px; padding: 6px 14px; font-weight: 800; font-size: .78rem; background: rgba(255,255,255,.12); color: #e9d5ff; }
    #penaltyFabModal .pen-tab.active { background: linear-gradient(135deg, #f43f5e, #f97316); color: #fff; }
    #penaltyFabModal .pen-admin .pen-input { background: rgba(255,255,255,.1); color: #fff; }
    #penaltyFabModal .pen-admin .pen-input option { color: #1f1235; }
    #penaltyFabModal .pen-admin .pen-input::placeholder { color: rgba(255,255,255,.5); }
    #penaltyFabModal .pen-admin label { font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; color: #d8b4fe; margin: 8px 0 3px; display: block; }
    #penaltyFabModal .pen-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    #penaltyFabModal .pen-grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
    #penaltyFabModal .pen-emp { display: flex; align-items: center; gap: 10px; padding: 7px 8px; border-radius: 14px; background: rgba(255,255,255,.07); }
    #penaltyFabModal .pen-emp + .pen-emp { margin-top: 6px; }
    #penaltyFabModal .pen-emp-name { flex: 1; font-weight: 800; font-size: .85rem; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #penaltyFabModal .pen-emp-pts { font-size: .78rem; font-weight: 900; padding: 2px 10px; border-radius: 999px; background: rgba(255,255,255,.14); }
    #penaltyFabModal .pen-emp-pts.has { background: #f43f5e; }
    #penaltyFabModal .pen-emp-list { max-height: 300px; overflow-y: auto; margin-top: 8px; }
    #penaltyFabModal .pen-rule { background: rgba(255,255,255,.08); border-radius: 16px; padding: 10px 12px; margin-bottom: 8px; }
    #penaltyFabModal .pen-rule.off { opacity: .55; }
    #penaltyFabModal .pen-rule-name { font-weight: 900; font-size: .88rem; }
    #penaltyFabModal .pen-rule-desc { font-size: .75rem; color: #e9d5ff; margin-top: 2px; }
    #penaltyFabModal .pen-rule-actions { display: flex; gap: 12px; margin-top: 6px; flex-wrap: wrap; }
    #penaltyFabModal .pen-admin .pen-link { color: #fbcfe8; }
    #penaltyFabModal .pen-admin .pen-link.danger { color: #fda4af; }
    #penaltyFabModal .pen-ok { display: inline-block; margin-left: 8px; font-size: .78rem; font-weight: 800; color: #86efac; }
    #penaltyFabModal .pen-slab-row { display: grid; grid-template-columns: 70px 1fr 1fr; gap: 10px; align-items: center; margin-bottom: 8px; }
    #penaltyFabModal .pen-slab-from { font-weight: 900; font-size: .85rem; color: #fbcfe8; }
    #penaltyFabModal .pen-hint-small { font-size: .72rem; color: #d8b4fe; margin-top: 8px; }

    /* ---------- dark theme ---------- */
    html[data-bs-theme="dark"] #penaltyFabModal .modal-content { background: #150d26; }
    html[data-bs-theme="dark"] #penaltyFabModal .pen-card,
    html[data-bs-theme="dark"] #penaltyFabModal .pen-chart,
    html[data-bs-theme="dark"] #penaltyFabModal .pen-chip { background: #221638; color: #efe9ff; }
    html[data-bs-theme="dark"] #penaltyFabModal .pen-row { background: #2c1630; }
    html[data-bs-theme="dark"] #penaltyFabModal .pen-reason,
    html[data-bs-theme="dark"] #penaltyFabModal .pen-msg,
    html[data-bs-theme="dark"] #penaltyFabModal .pen-chart-title,
    html[data-bs-theme="dark"] #penaltyFabModal .pen-month b { color: #f5f0ff; }
    html[data-bs-theme="dark"] #penaltyFabModal .pen-step { background: #2b1d45; }
    html[data-bs-theme="dark"] #penaltyFabModal .pen-input { background: #2b1d45; color: #f5f0ff; }

    @media (max-width: 575.98px) {
        #penaltyFabModal .modal-content { border-radius: 0; }
        #penaltyFabModal .pen-grid2, #penaltyFabModal .pen-grid3 { grid-template-columns: 1fr; }
        #penaltyFabModal .pen-slab-row { grid-template-columns: 56px 1fr 1fr; }
    }
</style>

<button type="button" id="penaltyFab" data-bs-toggle="modal" data-bs-target="#penaltyFabModal" title="My penalty points" aria-label="Open my penalty points">
    <i class="ri-alarm-warning-line" aria-hidden="true"></i>
    <span class="pen-fab-text">PENALTY</span>
    <span class="pen-fab-badge" id="penFabBadge"></span>
</button>

<div class="modal fade" id="penaltyFabModal" tabindex="-1" aria-hidden="true" aria-labelledby="penaltyFabTitle">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <div class="pen-head">
                    <h5 class="modal-title mb-0" id="penaltyFabTitle">Penalty Points 🚨</h5>
                    <div class="pen-sub"><span id="penGreeting">loading your report…</span><button type="button" class="pen-back pen-hidden" id="penBack">← back to me</button></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-danger py-2 pen-error pen-hidden" id="penError" role="alert"></div>

                {{-- Declaration --}}
                <div class="pen-decl">
                    <div class="pen-decl-emoji">💜</div>
                    <div>
                        <div class="pen-decl-title">This is about growth, not about cutting your pay.</div>
                        <div class="pen-decl-text">Penalty points are <b>not</b> meant to deduct money. We use them to evaluate mistakes and shortcomings in performance, so they can be spotted early, corrected and learned from. The hours are only a way to keep score. Add a corrective point whenever you fix something. It shows how you are levelling up.</div>
                    </div>
                </div>

                {{-- L30 badges --}}
                <div class="pen-badges" id="penBadges"></div>

                {{-- History graph (opened from the red dots) --}}
                <div class="pen-chart pen-hidden" id="penChart">
                    <div class="pen-chart-head">
                        <div class="pen-chart-title"><span id="penChartTitle">History</span><small>Rolling last 30 days, one point per day</small></div>
                        <select class="form-select form-select-sm" id="penChartDays" aria-label="Days">
                            <option value="30">30 days</option><option value="60" selected>60 days</option><option value="90">90 days</option>
                        </select>
                        <button type="button" class="pen-chart-x" id="penChartClose" aria-label="Close graph">&times;</button>
                    </div>
                    <div id="penChartMount" style="min-height:260px"></div>
                    <div class="pen-chart-empty pen-hidden" id="penChartEmpty"></div>
                </div>

                <div id="penStatus"></div>

                {{-- Slab ladder (everyone can see; president edits in his tools) --}}
                <div class="pen-card">
                    <div class="pen-section">🎚️ Penalty slabs · hours per point</div>
                    <div class="pen-ladder" id="penLadder"></div>
                    <div class="pen-ladder-note" id="penLadderNote"></div>
                </div>

                {{-- Penalty log --}}
                <div class="pen-card">
                    <div class="pen-section">🧾 Penalty log · last 30 days</div>
                    <div class="pen-list" id="penLog"></div>
                </div>

                {{-- Corrective points --}}
                <div class="pen-card pen-fix pen-hidden" id="penFixCard">
                    <div class="pen-section">💪 Corrective points · I'm fixing it</div>
                    <div class="small mb-2" style="color:#065f46">Mistakes happen. What matters is what you change. Write what you fixed (or will fix) and level up your score.</div>
                    <div class="pen-inline">
                        <input type="text" class="pen-input" id="penFixNote" maxlength="500" placeholder="e.g. Filing my DAR before 6 pm every day">
                        <button type="button" class="pen-btn green" id="penFixAdd">Add</button>
                    </div>
                    <div class="mt-2" id="penFixList"></div>
                    <div class="pen-note"><a href="{{ route('tasks.index') }}" style="color:#047857;font-weight:800">➕ Also add a corrective action task in the Task Manager</a></div>
                </div>

                {{-- Monthly closings --}}
                <div class="pen-card">
                    <div class="pen-section">🗓️ Monthly totals</div>
                    <div id="penMonths"></div>
                    <div class="pen-note">On the 1st of every month the previous month's points are frozen and priced with the slabs above: every point counts at the rate of the slab the month total falls in.</div>
                </div>

                {{-- President tools --}}
                <div class="pen-card pen-admin pen-hidden" id="penAdmin">
                    <div class="pen-section">👑 President tools</div>
                    <div class="pen-tabs" id="penTabs">
                        <button type="button" class="pen-tab active" data-tab="team">👥 Team</button>
                        <button type="button" class="pen-tab" data-tab="add">➕ Add penalty</button>
                        <button type="button" class="pen-tab" data-tab="rules">🤖 Auto rules</button>
                        <button type="button" class="pen-tab" data-tab="slabs">🎚️ Slabs</button>
                    </div>
                    <div id="penAdminBody"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.self !== window.top) { // never inside an embedded iframe
            var f = document.getElementById('penaltyFab'); if (f) f.remove();
            return;
        }
        var URL_SHOW = @json(route('penaltyFab.show'));
        var URL_HIST = @json(route('penaltyFab.history'));
        var URL_ENTRIES = @json(route('penaltyFab.entries.store'));
        var URL_ENTRY_DEL = @json(url('penalty-fab/entries'));
        var URL_RULES = @json(route('penaltyFab.rules.save'));
        var URL_RULES_RUN = @json(route('penaltyFab.rules.run'));
        var URL_RULE_BASE = @json(url('penalty-fab/rules'));
        var URL_SLABS = @json(route('penaltyFab.slabs.save'));
        var URL_FIX = @json(route('penaltyFab.corrections.add'));
        var URL_FIX_DEL = @json(url('penalty-fab/corrections'));
        var CSRF = @json(csrf_token());
        var CDN_APEX = 'https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js';

        var modalEl = document.getElementById('penaltyFabModal');
        var $ = function (id) { return document.getElementById(id); };
        var state = { data: null, viewUserId: 0, tab: 'team', chartMetric: null, editRule: null, flash: '' };
        var chart = null;

        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
        function num(n) { return Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 }); }
        // Penalty values are shown in hours (the stored numbers are unchanged).
        function rs(n) { var v = Number(n || 0); return num(v) + (v === 1 ? ' hr' : ' hrs'); }
        function showError(msg) { var e = $('penError'); e.textContent = msg || ''; e.classList.toggle('pen-hidden', !msg); }
        function firstError(d) {
            if (d && d.errors) { var k = Object.keys(d.errors)[0]; return d.errors[k][0]; }
            return (d && d.message) || 'Something went wrong. Please try again.';
        }
        function call(method, url, body) {
            return fetch(url, {
                method: method, credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, data: j }; }); });
        }
        // run a mutation, then refresh; keep the UI calm on errors
        function act(method, url, body, done) {
            showError('');
            return call(method, url, body).then(function (res) {
                if (!res.ok || !res.data || res.data.success === false) { showError(firstError(res.data)); return res; }
                if (done) done(res.data);
                return res;
            }).catch(function () { showError('Network error. Please try again.'); });
        }

        // ---------- top: badges ----------
        function msgFor(p) {
            if (p === 0) return 'Clean sheet! Zero penalty points 🧼✨ Keep that streak alive.';
            if (p <= 5) return 'You are in the lowest zone. Fix the small stuff and keep it clean 💪';
            if (p <= 10) return 'It is heating up 🔥 Add corrective points and clear one mistake a day.';
            return 'Time for a reset 🔄 Pick one mistake today, fix it and log a corrective point.';
        }
        function renderTop() {
            var d = state.data.detail, p = d.l30_points;
            var first = String(d.name || 'you').split(' ')[0].toLowerCase();
            $('penGreeting').textContent = state.data.viewing_me
                ? 'hey ' + first + ', here is your last 30 days'
                : 'viewing ' + d.name + ' · last 30 days';
            $('penBack').classList.toggle('pen-hidden', state.data.viewing_me);

            $('penBadges').innerHTML =
                '<div class="pen-badge count' + (p === 0 ? ' clean' : '') + '">'
                + '<button type="button" class="pen-hist-dot" data-metric="count" title="Penalty count history" aria-label="Open penalty count history"></button>'
                + '<div class="pen-badge-label">Penalty count · L30</div>'
                + '<div class="pen-badge-value">' + p + '</div>'
                + '<div class="pen-badge-sub">' + (p === 0 ? 'no penalty points 🎉' : (p === 1 ? 'point' : 'points') + ' in the last 30 days') + '</div>'
                + '<div class="pen-badge-emoji">' + (p === 0 ? '🌿' : '🚨') + '</div></div>'
                + '<div class="pen-badge amount">'
                + '<button type="button" class="pen-hist-dot" data-metric="amount" title="Penalty hours history" aria-label="Open penalty hours history"></button>'
                + '<div class="pen-badge-label">Penalty hours · L30</div>'
                + '<div class="pen-badge-value">' + esc(rs(d.l30_amount)) + '</div>'
                + '<div class="pen-badge-sub">' + p + ' pts × ' + esc(rs(d.rate)) + ' per point</div>'
                + '<div class="pen-badge-emoji">🪙</div></div>';

            var fix = (d.corrective_notes_l30 || 0) + (d.corrective_tasks_l30 || 0);
            var chips = '<span class="pen-chip ' + (d.clean_days === null || d.clean_days >= 7 ? 'good' : (d.clean_days === 0 ? 'warn' : '')) + '">🔥 '
                + (d.clean_days === null ? 'no penalties on record' : d.clean_days + ' penalty-free day' + (d.clean_days === 1 ? '' : 's')) + '</span>'
                + '<span class="pen-chip good">💪 ' + fix + ' corrective point' + (fix === 1 ? '' : 's') + ' · L30</span>';
            $('penStatus').innerHTML = '<div class="pen-chips">' + chips + '</div><div class="pen-msg">' + esc(msgFor(p)) + '</div>';

            // floating button counter
            var fb = $('penFabBadge');
            if (state.data.viewing_me) { fb.textContent = p; fb.style.display = p > 0 ? 'flex' : 'none'; }
        }

        // ---------- slab ladder ----------
        function renderLadder() {
            var d = state.data.detail, slabs = state.data.slabs, html = '';
            slabs.forEach(function (s) {
                var cls = s.position === d.slab_position ? 'now' : (s.position < d.slab_position ? 'passed' : '');
                html += '<div class="pen-step ' + cls + '"><b>' + esc(rs(s.rate)) + '</b><span>' + esc(s.label) + ' pts</span></div>';
            });
            $('penLadder').innerHTML = html;
            var note;
            if (d.points_to_next_slab !== null && d.points_to_next_slab !== undefined) {
                note = 'You are in the ' + esc(rs(d.rate)) + ' per point slab (' + esc(d.slab_label) + ' pts). '
                    + d.points_to_next_slab + ' more point' + (d.points_to_next_slab === 1 ? '' : 's') + ' moves the rate to ' + esc(rs(d.next_slab_rate)) + '. Stay low to keep it light 😉';
            } else {
                note = 'You are in the top slab (' + esc(d.slab_label) + ' pts) at ' + esc(rs(d.rate)) + ' per point. Corrective actions will bring it down 💪';
            }
            $('penLadderNote').innerHTML = note;
        }

        // ---------- log ----------
        function renderLog() {
            var d = state.data.detail, me = state.data.viewing_me, isSetter = state.data.is_setter, html = '';
            d.entries.forEach(function (e) {
                var src = e.source === 'auto'
                    ? '<span class="pen-tag auto">auto' + (e.rule ? ' · ' + esc(e.rule) : '') + '</span>'
                    : '<span class="pen-tag">by president</span>';
                var fixed = e.corrections > 0 ? '<span class="pen-tag fixed">✓ ' + e.corrections + ' fix' + (e.corrections === 1 ? '' : 'es') + '</span>' : '';
                var actions = '';
                if (me) actions += '<button type="button" class="pen-link" data-act="fix" data-id="' + e.id + '">💪 I\'ll fix this</button>';
                if (isSetter) actions += ' <button type="button" class="pen-link danger" data-act="del" data-id="' + e.id + '">🗑 remove</button>';
                html += '<div class="pen-row" data-entry="' + e.id + '"><div class="pen-pts">+' + e.points + '</div>'
                    + '<div class="pen-row-main"><div class="pen-reason">' + esc(e.reason) + '</div>'
                    + '<div class="pen-meta"><span>' + esc(e.date) + '</span>' + src + fixed + (actions ? '<span>' + actions + '</span>' : '') + '</div>'
                    + '<div class="pen-inline pen-hidden" data-fixbox="' + e.id + '"><input type="text" class="pen-input" maxlength="500" placeholder="What will you do differently?">'
                    + '<button type="button" class="pen-btn green" data-act="fixsave" data-id="' + e.id + '">Add</button></div>'
                    + '</div></div>';
            });
            $('penLog').innerHTML = html || '<div class="pen-empty"><b>🌿</b>No penalty points in the last 30 days. Keep going!</div>';
        }

        // ---------- corrective ----------
        function renderFix() {
            var d = state.data.detail, me = state.data.viewing_me;
            $('penFixCard').classList.toggle('pen-hidden', !me);
            var html = '';
            d.corrections.forEach(function (c) {
                html += '<div class="pen-fix-item"><span>✅</span><span style="flex:1">' + esc(c.note)
                    + ' <small style="opacity:.7">· ' + esc(c.date || '') + '</small></span>'
                    + '<button type="button" class="pen-link danger" data-act="fixdel" data-id="' + c.id + '" title="Remove">✕</button></div>';
            });
            if (d.corrective_tasks_l30 > 0) {
                html += '<div class="pen-fix-item"><span>🛠️</span><span style="flex:1">' + d.corrective_tasks_l30 + ' corrective action task' + (d.corrective_tasks_l30 === 1 ? '' : 's') + ' in the Task Manager</span></div>';
            }
            $('penFixList').innerHTML = html || '<div class="small" style="color:#047857;opacity:.8">No corrective points yet. Your first one counts the most ✨</div>';
        }

        function renderMonths() {
            var ms = state.data.detail.months, html = '';
            ms.forEach(function (m) {
                html += '<div class="pen-month"><b>' + esc(m.label) + '</b><span>' + m.points + ' pts × ' + esc(rs(m.rate)) + '</span><span class="amt">' + esc(rs(m.amount)) + '</span></div>';
            });
            $('penMonths').innerHTML = html || '<div class="pen-empty">No closed month yet. The first one is frozen on the 1st.</div>';
        }

        // ---------- history graph (standard line graph, opened from the red dots) ----------
        function loadApex(cb) {
            if (typeof ApexCharts !== 'undefined') { cb(); return; }
            var s = document.createElement('script');
            s.src = CDN_APEX; s.onload = cb;
            s.onerror = function () { chartMessage('Chart library could not be loaded.'); };
            document.head.appendChild(s);
        }
        function chartMessage(m) {
            if (chart) { chart.destroy(); chart = null; }
            $('penChartMount').innerHTML = '';
            var e = $('penChartEmpty'); e.textContent = m; e.classList.remove('pen-hidden');
        }
        function openChart(metric) {
            state.chartMetric = metric;
            $('penChart').classList.remove('pen-hidden');
            $('penChartTitle').textContent = (metric === 'amount' ? 'Penalty hours' : 'Penalty count') + ' · L30 history';
            drawChart();
            $('penChart').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function drawChart() {
            var metric = state.chartMetric; if (!metric) return;
            $('penChartEmpty').classList.add('pen-hidden');
            var url = URL_HIST + '?metric=' + metric + '&days=' + encodeURIComponent($('penChartDays').value)
                + (state.viewUserId ? '&user_id=' + state.viewUserId : '');
            call('GET', url).then(function (res) {
                if (!res.ok || !res.data || res.data.success === false) { chartMessage(firstError(res.data)); return; }
                var pts = res.data.points || [];
                var any = pts.some(function (p) { return p.value > 0; });
                if (!any) { chartMessage('No penalty history in this period 🎉'); return; }
                loadApex(function () {
                    if (chart) { chart.destroy(); chart = null; }
                    $('penChartMount').innerHTML = '';
                    var color = metric === 'amount' ? '#7c3aed' : '#dc2626';
                    chart = new ApexCharts($('penChartMount'), {
                        chart: { type: 'line', height: 280, toolbar: { show: true }, animations: { enabled: true, easing: 'easeinout', speed: 500 }, fontFamily: 'inherit' },
                        series: [{ name: metric === 'amount' ? 'Penalty hours' : 'Penalty count', data: pts.map(function (p) { return { x: new Date(p.date + 'T12:00:00').getTime(), y: p.value }; }) }],
                        stroke: { curve: 'smooth', width: 3, colors: [color] },
                        markers: { size: 3, colors: [color], strokeColors: '#fff', strokeWidth: 2, hover: { size: 6 } },
                        colors: [color],
                        xaxis: { type: 'datetime', labels: { style: { fontSize: '11px' }, datetimeUTC: false } },
                        yaxis: { min: 0, forceNiceScale: true, decimalsInFloat: 0, labels: { style: { fontSize: '12px' } } },
                        grid: { borderColor: '#e2e8f0', strokeDashArray: 4 },
                        dataLabels: { enabled: false },
                        tooltip: { theme: 'light', x: { format: 'dd MMM yyyy' } }
                    });
                    chart.render();
                });
            }).catch(function () { chartMessage('Network error. Please try again.'); });
        }
        function closeChart() {
            state.chartMetric = null;
            $('penChart').classList.add('pen-hidden');
            if (chart) { chart.destroy(); chart = null; }
        }

        // ---------- president tools ----------
        function renderAdmin() {
            var D = state.data;
            $('penAdmin').classList.toggle('pen-hidden', !D.is_setter);
            if (!D.is_setter) return;
            document.querySelectorAll('#penTabs .pen-tab').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tab') === state.tab); });
            var body = $('penAdminBody');
            if (state.tab === 'team') body.innerHTML = adminTeam();
            else if (state.tab === 'add') body.innerHTML = adminAdd();
            else if (state.tab === 'rules') body.innerHTML = adminRules();
            else body.innerHTML = adminSlabs();
            if (state.flash) {
                var f = document.createElement('span'); f.className = 'pen-ok'; f.textContent = state.flash;
                var first = body.querySelector('.pen-pending-flash'); if (first) first.appendChild(f);
                state.flash = '';
            }
        }
        function adminTeam() {
            var rows = '';
            state.data.employees.forEach(function (e) {
                rows += '<div class="pen-emp" data-name="' + esc(String(e.name).toLowerCase()) + '"><span class="pen-emp-name">' + esc(e.name) + '</span>'
                    + '<span class="pen-emp-pts' + (e.l30_points > 0 ? ' has' : '') + '">' + e.l30_points + ' pts · ' + esc(rs(e.l30_amount)) + '</span>'
                    + '<button type="button" class="pen-btn ghost" data-act="view" data-id="' + e.user_id + '">View</button></div>';
            });
            return '<input type="search" class="pen-input" id="penTeamSearch" placeholder="Search team member…" autocomplete="off">'
                + '<div class="pen-emp-list" id="penTeamList">' + rows + '</div>';
        }
        function adminAdd() {
            var D = state.data, opts = '';
            D.employees.forEach(function (e) {
                var sel = (state.viewUserId ? e.user_id === state.viewUserId : false) ? ' selected' : '';
                opts += '<option value="' + e.user_id + '"' + sel + '>' + esc(e.name) + '</option>';
            });
            return '<label>Team member</label><select class="pen-input" id="penAddUser"><option value="">Choose…</option>' + opts + '</select>'
                + '<div class="pen-grid2"><div><label>Points</label><input type="number" class="pen-input" id="penAddPts" min="1" max="100" step="1" value="1"></div>'
                + '<div><label>Date</label><input type="date" class="pen-input" id="penAddDate" min="' + esc(D.month_start) + '" max="' + esc(D.today) + '" value="' + esc(D.today) + '"></div></div>'
                + '<label>Reason (shown to the member)</label><textarea class="pen-input" id="penAddReason" maxlength="500" placeholder="What went wrong? Be specific and kind."></textarea>'
                + '<div class="mt-2 pen-pending-flash"><button type="button" class="pen-btn" data-act="addentry">Add penalty</button></div>'
                + '<div class="pen-hint-small">The member sees the points and the reason. Dates are limited to the current month, earlier months are already closed.</div>';
        }
        function ruleDesc(r) {
            return esc(r.metric_label) + ' ' + esc(r.operator_label) + ' <b>' + esc(r.threshold) + '</b> → <b>+' + r.points + ' pt' + (r.points === 1 ? '' : 's') + '</b> · '
                + esc(r.repeat) + ' at ' + esc(r.run_time) + (r.weekdays_only ? ' · weekdays' : '') + (r.last_run_at ? ' · last run ' + esc(r.last_run_at) + ' (+' + r.last_added_count + ')' : '');
        }
        function adminRules() {
            var D = state.data, html = '', e = state.editRule || { id: '', name: '', metric: '', operator: '>=', threshold: '', points: 1, reason: '', repeat: 'daily', run_time: '19:00', weekdays_only: true };
            D.rules.forEach(function (r) {
                html += '<div class="pen-rule' + (r.is_active ? '' : ' off') + '"><div class="pen-rule-name">' + esc(r.name) + (r.is_active ? '' : ' · paused') + '</div>'
                    + '<div class="pen-rule-desc">' + ruleDesc(r) + '</div>'
                    + '<div class="pen-rule-actions"><button type="button" class="pen-link" data-act="rtoggle" data-id="' + r.id + '">' + (r.is_active ? '⏸ pause' : '▶ resume') + '</button>'
                    + '<button type="button" class="pen-link" data-act="redit" data-id="' + r.id + '">✏️ edit</button>'
                    + '<button type="button" class="pen-link danger" data-act="rdel" data-id="' + r.id + '">🗑 delete</button></div></div>';
            });
            if (!html) html = '<div class="pen-hint-small" style="margin:0 0 8px">No auto rules yet. Add one below, e.g. <b>Overdue tasks is at least 3 → +1 point</b>.</div>';

            var mopts = '', group = null;
            D.metrics.forEach(function (m) {
                if (m.group !== group) { if (group !== null) mopts += '</optgroup>'; mopts += '<optgroup label="' + esc(m.group) + '">'; group = m.group; }
                mopts += '<option value="' + esc(m.key) + '"' + (m.key === e.metric ? ' selected' : '') + '>' + esc(m.label) + '</option>';
            });
            if (group !== null) mopts += '</optgroup>';
            var oopts = '';
            D.operators.forEach(function (o) { oopts += '<option value="' + esc(o.op) + '"' + (o.op === e.operator ? ' selected' : '') + '>' + esc(o.label) + '</option>'; });
            var ropts = ['daily', 'weekly', 'monthly'].map(function (x) { return '<option value="' + x + '"' + (x === e.repeat ? ' selected' : '') + '>once a ' + (x === 'daily' ? 'day' : x === 'weekly' ? 'week' : 'month') + ' at most</option>'; }).join('');

            return html
                + '<div class="pen-section" style="margin-top:12px">' + (e.id ? '✏️ Edit rule' : '➕ New rule') + '</div>'
                + '<input type="hidden" id="penRuleId" value="' + esc(e.id) + '">'
                + '<label>Rule name</label><input type="text" class="pen-input" id="penRuleName" maxlength="120" value="' + esc(e.name) + '" placeholder="e.g. Too many overdue tasks">'
                + '<label>When this number…</label><select class="pen-input" id="penRuleMetric"><option value="">Choose a Task Summary column…</option>' + mopts + '</select>'
                + '<div class="pen-grid2"><div><label>…is</label><select class="pen-input" id="penRuleOp">' + oopts + '</select></div>'
                + '<div><label>This number</label><input type="number" step="any" class="pen-input" id="penRuleThr" value="' + esc(e.threshold) + '"></div></div>'
                + '<div class="pen-grid3"><div><label>Add points</label><input type="number" min="1" max="50" class="pen-input" id="penRulePts" value="' + esc(e.points) + '"></div>'
                + '<div><label>How often</label><select class="pen-input" id="penRuleRepeat">' + ropts + '</select></div>'
                + '<div><label>Check at</label><input type="time" class="pen-input" id="penRuleTime" value="' + esc(e.run_time) + '"></div></div>'
                + '<label>Reason shown to the member</label><textarea class="pen-input" id="penRuleReason" maxlength="500" placeholder="{metric} is {value} (limit {threshold})">' + esc(e.reason) + '</textarea>'
                + '<div class="pen-hint-small" style="margin-top:4px">You can use {name}, {value}, {threshold}, {metric}, {rule} in the reason.</div>'
                + '<label style="display:flex;gap:8px;align-items:center;text-transform:none;letter-spacing:0;font-size:.8rem"><input type="checkbox" id="penRuleWeek"' + (e.weekdays_only ? ' checked' : '') + '> Weekdays only</label>'
                + '<div class="mt-2 pen-pending-flash" style="display:flex;gap:8px;flex-wrap:wrap">'
                + '<button type="button" class="pen-btn" data-act="rsave">' + (e.id ? 'Update rule' : 'Save rule') + '</button>'
                + (e.id ? '<button type="button" class="pen-btn ghost" data-act="rcancel">Cancel</button>' : '')
                + '<button type="button" class="pen-btn ghost" data-act="rrun">⚡ Run active rules now</button></div>'
                + '<div class="pen-hint-small">Rules run automatically every day at their time. One penalty per rule, person and period, so nobody is penalised twice for the same thing.</div>';
        }
        function adminSlabs() {
            var slabs = state.data.slabs, rows = '';
            slabs.forEach(function (s, i) {
                var last = i === slabs.length - 1;
                rows += '<div class="pen-slab-row"><div class="pen-slab-from">' + s.from + (last ? '+' : ' to') + '</div>'
                    + (last ? '<div class="pen-hint-small" style="margin:0">and above</div>'
                        : '<input type="number" min="0" class="pen-input pen-slab-max" value="' + s.to + '" aria-label="Slab ' + s.position + ' upper limit">')
                    + '<input type="number" min="0" step="0.5" class="pen-input pen-slab-rate" value="' + s.rate + '" aria-label="Slab ' + s.position + ' rate"></div>';
            });
            return '<div class="pen-slab-row" style="margin-bottom:2px"><label style="margin:0">From</label><label style="margin:0">Up to (points)</label><label style="margin:0">Hours per point</label></div>'
                + rows
                + '<div class="mt-2 pen-pending-flash"><button type="button" class="pen-btn" data-act="slabsave">Save slabs</button></div>'
                + '<div class="pen-hint-small">Five slabs. Every point of a month total is priced at the rate of the slab the total falls in. Members can see these slabs.</div>';
        }

        function renderAll() { renderTop(); renderLadder(); renderLog(); renderFix(); renderMonths(); renderAdmin(); }

        function load(keepAdmin) {
            showError('');
            var url = URL_SHOW + (state.viewUserId ? '?user_id=' + state.viewUserId : '');
            return call('GET', url).then(function (res) {
                if (!res.ok || !res.data || res.data.success === false) { showError(firstError(res.data)); return; }
                state.data = res.data;
                if (state.viewUserId && res.data.viewing_me) state.viewUserId = 0;
                if (keepAdmin) {
                    renderTop(); renderLadder(); renderLog(); renderFix(); renderMonths();
                    if (state.tab === 'team') renderAdmin(); // keep the team's points fresh
                } else renderAll();
                if (state.chartMetric) drawChart();
            }).catch(function () { showError('Network error. Please try again.'); });
        }

        // ---------- events ----------
        $('penBadges').addEventListener('click', function (e) {
            var b = e.target.closest('.pen-hist-dot'); if (b) openChart(b.getAttribute('data-metric'));
        });
        $('penChartClose').addEventListener('click', closeChart);
        $('penChartDays').addEventListener('change', drawChart);
        $('penBack').addEventListener('click', function () { state.viewUserId = 0; closeChart(); load(); });

        $('penLog').addEventListener('click', function (e) {
            var b = e.target.closest('[data-act]'); if (!b) return;
            var id = b.getAttribute('data-id'), act_ = b.getAttribute('data-act');
            if (act_ === 'fix') {
                var box = document.querySelector('[data-fixbox="' + id + '"]');
                if (box) { box.classList.toggle('pen-hidden'); var i = box.querySelector('input'); if (i) i.focus(); }
            } else if (act_ === 'fixsave') {
                var inp = document.querySelector('[data-fixbox="' + id + '"] input');
                var note = inp ? inp.value.trim() : '';
                if (note.length < 3) { showError('Write a few words about how you will fix it.'); return; }
                act('POST', URL_FIX, { note: note, penalty_entry_id: parseInt(id, 10) }, function () { load(true); });
            } else if (act_ === 'del') {
                if (!confirm('Remove this penalty entry?')) return;
                act('DELETE', URL_ENTRY_DEL + '/' + id, null, function () { load(true); });
            }
        });
        $('penFixAdd').addEventListener('click', function () {
            var inp = $('penFixNote'), note = inp.value.trim();
            if (note.length < 3) { showError('Write a few words about what you are fixing.'); return; }
            act('POST', URL_FIX, { note: note }, function () { inp.value = ''; load(true); });
        });
        $('penFixNote').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('penFixAdd').click(); } });
        $('penFixList').addEventListener('click', function (e) {
            var b = e.target.closest('[data-act="fixdel"]'); if (!b) return;
            act('DELETE', URL_FIX_DEL + '/' + b.getAttribute('data-id'), null, function () { load(true); });
        });

        $('penTabs').addEventListener('click', function (e) {
            var b = e.target.closest('.pen-tab'); if (!b) return;
            state.tab = b.getAttribute('data-tab'); state.editRule = null; renderAdmin();
        });
        // team list search (delegated because the tab re-renders)
        $('penAdminBody').addEventListener('input', function (e) {
            if (e.target.id !== 'penTeamSearch') return;
            var q = e.target.value.trim().toLowerCase();
            document.querySelectorAll('#penTeamList .pen-emp').forEach(function (r) { r.style.display = !q || r.getAttribute('data-name').indexOf(q) >= 0 ? '' : 'none'; });
        });
        $('penAdminBody').addEventListener('click', function (e) {
            var b = e.target.closest('[data-act]'); if (!b) return;
            var a = b.getAttribute('data-act'), id = b.getAttribute('data-id'), D = state.data;
            if (a === 'view') {
                state.viewUserId = parseInt(id, 10); closeChart(); load(true).then(function () { window.scrollTo && modalEl.querySelector('.modal-body').scrollTo({ top: 0, behavior: 'smooth' }); });
            } else if (a === 'addentry') {
                var uid = $('penAddUser').value, pts = parseInt($('penAddPts').value, 10), reason = $('penAddReason').value.trim(), date = $('penAddDate').value;
                if (!uid) { showError('Choose a team member.'); return; }
                if (!pts || pts < 1) { showError('Enter the points (1 or more).'); return; }
                if (reason.length < 3) { showError('Write the reason the member will see.'); return; }
                b.disabled = true;
                act('POST', URL_ENTRIES, { user_id: parseInt(uid, 10), points: pts, reason: reason, penalty_date: date }, function () {
                    state.flash = '✓ Added'; state.viewUserId = parseInt(uid, 10);
                    load(false);
                }).then(function () { b.disabled = false; });
            } else if (a === 'rsave') {
                var body = {
                    id: $('penRuleId').value ? parseInt($('penRuleId').value, 10) : null,
                    name: $('penRuleName').value.trim(), metric: $('penRuleMetric').value, operator: $('penRuleOp').value,
                    threshold: $('penRuleThr').value, points: parseInt($('penRulePts').value, 10) || 0,
                    reason: $('penRuleReason').value.trim(), repeat: $('penRuleRepeat').value, run_time: $('penRuleTime').value,
                    weekdays_only: $('penRuleWeek').checked, is_active: true
                };
                if (!body.name || !body.metric || body.threshold === '' || !body.reason) { showError('Fill the name, column, number and reason.'); return; }
                act('POST', URL_RULES, body, function () { state.editRule = null; state.flash = '✓ Rule saved'; load(false); });
            } else if (a === 'rcancel') { state.editRule = null; renderAdmin(); }
            else if (a === 'redit') { state.editRule = D.rules.filter(function (r) { return String(r.id) === id; })[0] || null; renderAdmin(); }
            else if (a === 'rtoggle') { act('POST', URL_RULE_BASE + '/' + id + '/toggle', null, function () { load(false); }); }
            else if (a === 'rdel') { if (confirm('Delete this rule? Penalties it already added stay.')) act('DELETE', URL_RULE_BASE + '/' + id, null, function () { load(false); }); }
            else if (a === 'rrun') {
                b.disabled = true;
                act('POST', URL_RULES_RUN, null, function (d) { state.flash = '✓ ' + (d.message || 'Done'); load(false); }).then(function () { b.disabled = false; });
            } else if (a === 'slabsave') {
                var maxes = Array.prototype.map.call(document.querySelectorAll('.pen-slab-max'), function (i) { return i.value === '' ? null : parseInt(i.value, 10); });
                var rates = Array.prototype.map.call(document.querySelectorAll('.pen-slab-rate'), function (i) { return i.value; });
                var slabs = rates.map(function (r, i) { return { max_points: i < maxes.length ? maxes[i] : null, rate: r }; });
                act('POST', URL_SLABS, { slabs: slabs }, function () { state.flash = '✓ Slabs saved'; load(false); });
            }
        });

        modalEl.addEventListener('shown.bs.modal', function () { load(false); });
        modalEl.addEventListener('hidden.bs.modal', function () { closeChart(); });

        // light up the floating button with the user's own L30 count without opening the window
        (function prime() {
            call('GET', URL_SHOW + '?lite=1').then(function (res) {
                if (res.ok && res.data && res.data.detail) {
                    var p = res.data.detail.l30_points, fb = $('penFabBadge');
                    fb.textContent = p; fb.style.display = p > 0 ? 'flex' : 'none';
                }
            }).catch(function () {});
        })();
    })();
</script>
@endunless
@endauth
