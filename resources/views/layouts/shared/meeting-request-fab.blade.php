{{--
    Floating "Request a meeting" button, shown on every page for logged-in users.
    Opens a modal to pick members + write an agenda, shows a review/confirm step before sending,
    and lets invitees accept / decline requests addressed to them.
    Hide on a page with: @extends('layouts.vertical', [..., 'hideMeetingFab' => true])
--}}
@auth
@unless($hideMeetingFab ?? false)
<style>
    #mrqFab {
        position: fixed; right: 20px; bottom: 84px; z-index: 1040;
        width: 54px; height: 54px; border-radius: 50%; border: 0; padding: 0;
        background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: #fff; font-size: 26px;
        display: flex; align-items: center; justify-content: center;
        box-shadow: 0 6px 18px rgba(37, 99, 235, .45); cursor: pointer; transition: transform .15s ease, box-shadow .15s ease;
    }
    #mrqFab:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(37, 99, 235, .55); }
    #mrqFab .mrq-badge {
        position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 10px;
        background: #dc2626; color: #fff; font-size: 11px; font-weight: 700; line-height: 20px; text-align: center; border: 2px solid #fff;
    }
    #mrqFab.has-pending { animation: mrqRing 2s infinite; }
    @keyframes mrqRing { 0%,100% { box-shadow: 0 6px 18px rgba(37,99,235,.45) } 50% { box-shadow: 0 0 0 10px rgba(37,99,235,.18) } }
    @media (max-width: 991.98px) { #mrqFab { right: 12px; bottom: 146px; width: 50px; height: 50px; } }

    .mrq-toast {
        position: fixed; right: 84px; bottom: 90px; z-index: 1041; max-width: 320px; background: #0f172a; color: #fff;
        border-radius: 10px; padding: 10px 14px; box-shadow: 0 10px 28px rgba(15,23,42,.35); cursor: pointer; font-size: .85rem;
    }
    @media (max-width: 991.98px) { .mrq-toast { right: 72px; bottom: 150px; } }

    /* =====================  Meeting modal — next-gen UI  ===================== */
    #mrqModal {
        --mq-bg: #ffffff; --mq-surface: #f8fafc; --mq-border: #e2e8f0; --mq-text: #0f172a; --mq-muted: #64748b;
        --mq-accent: #4f46e5; --mq-accent-2: #2563eb; --mq-ring: rgba(79, 70, 229, .18);
        --mq-hover: #f1f5f9; --mq-sel: #eef2ff;
    }
    html[data-bs-theme="dark"] #mrqModal {
        --mq-bg: #131a2a; --mq-surface: #1a2236; --mq-border: rgba(255, 255, 255, .12); --mq-text: #e6eaf5; --mq-muted: #9aa7c2;
        --mq-accent: #818cf8; --mq-accent-2: #60a5fa; --mq-ring: rgba(129, 140, 248, .25);
        --mq-hover: rgba(255, 255, 255, .06); --mq-sel: rgba(129, 140, 248, .16);
    }
    /* double the default modal-lg width (800px -> 1600px), never wider than the screen */
    #mrqModal .modal-dialog.modal-lg { max-width: min(1600px, 96vw); --bs-modal-margin: .75rem; }
    /* full height: the dialog fills the screen; the body scrolls only if the content is taller than that */
    #mrqModal .modal-dialog.modal-dialog-scrollable { height: calc(100vh - 1.5rem); height: calc(100dvh - 1.5rem); }
    #mrqModal .modal-dialog-scrollable .modal-content { height: 100%; max-height: 100%; }
    #mrqModal .modal-body { display: flex; flex-direction: column; }
    #mrqModal #mrqStepForm { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
    #mrqModal.fade .modal-dialog { transform: translateY(26px) scale(.98); opacity: 0; transition: transform .35s cubic-bezier(.2, .8, .2, 1), opacity .25s ease; }
    #mrqModal.show .modal-dialog { transform: none; opacity: 1; }
    body:has(#mrqModal.show) .modal-backdrop { backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); }

    #mrqModal .modal-content {
        border: 0; border-radius: 22px; overflow: hidden; background: var(--mq-bg); color: var(--mq-text);
        box-shadow: 0 40px 90px -25px rgba(30, 41, 90, .55), 0 0 0 1px rgba(255, 255, 255, .06);
    }
    #mrqModal .modal-header {
        position: relative; border: 0; padding: 20px 30px; color: #fff; overflow: hidden;
        background: linear-gradient(120deg, #4338ca 0%, #2563eb 55%, #06b6d4 100%);
    }
    #mrqModal .modal-header::after {
        content: ''; position: absolute; inset: 0; pointer-events: none;
        background: radial-gradient(420px 160px at 90% -30%, rgba(255, 255, 255, .28), transparent 70%);
    }
    #mrqModal .modal-title { position: relative; z-index: 1; display: flex; align-items: center; gap: 14px; font-weight: 700; letter-spacing: -.01em; color: #fff; }
    #mrqModal .modal-title small { display: block; font-size: .78rem; font-weight: 400; opacity: .85; letter-spacing: 0; }
    #mrqModal .mrq-hico {
        display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; font-size: 24px;
        background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .28);
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }
    #mrqModal .modal-header .btn-close { position: relative; z-index: 1; filter: invert(1) grayscale(100%) brightness(200%); opacity: .85; }
    #mrqModal .modal-header .btn-close:hover { opacity: 1; }
    #mrqModal .modal-body { padding: 24px 30px; background: var(--mq-bg); }
    #mrqModal .modal-footer { border-top: 1px solid var(--mq-border); padding: 14px 30px; background: var(--mq-surface); gap: 8px; }

    /* segmented tabs */
    #mrqModal .mrq-tabs { display: inline-flex; gap: 4px; background: var(--mq-surface); border: 1px solid var(--mq-border); border-radius: 14px; padding: 4px; margin: 0 0 22px; }
    #mrqModal .mrq-tab { border: 0; background: transparent; padding: 8px 18px; border-radius: 10px; font-weight: 600; font-size: .9rem; color: var(--mq-muted); transition: all .2s ease; }
    #mrqModal .mrq-tab:hover { color: var(--mq-text); }
    #mrqModal .mrq-tab.active { background: var(--mq-bg); color: var(--mq-accent); box-shadow: 0 2px 10px rgba(15, 23, 42, .14); }
    #mrqModal .mrq-tab .badge { margin-left: 6px; border-radius: 10px; }
    #mrqModal .mrq-hidden { display: none !important; }

    /* form layout: two columns on wide screens */
    #mrqModal .mrq-form-grid {
        display: grid; gap: 20px 32px; grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
        grid-template-areas: "title members" "agenda members" "prio members" "when when" "meta meta";
        grid-template-rows: auto minmax(150px, 1fr) auto auto auto;
        flex: 1 1 auto;
    }
    #mrqModal .mrq-f-agenda { display: flex; flex-direction: column; }
    #mrqModal .mrq-f-agenda textarea.form-control { flex: 1 1 auto; resize: none; }
    #mrqModal .mrq-f-title { grid-area: title; } #mrqModal .mrq-f-agenda { grid-area: agenda; }
    #mrqModal .mrq-f-members { grid-area: members; display: flex; flex-direction: column; min-width: 0; }
    #mrqModal .mrq-f-prio { grid-area: prio; } #mrqModal .mrq-f-meta { grid-area: meta; }
    #mrqModal .mrq-f-when { grid-area: when; background: var(--mq-surface); border: 1px solid var(--mq-border); border-radius: 16px; padding: 16px 18px; }
    @media (max-width: 991.98px) {
        #mrqModal .mrq-form-grid { grid-template-columns: 1fr; grid-template-areas: "title" "agenda" "members" "prio" "when" "meta"; grid-template-rows: none; }
        #mrqModal .modal-body, #mrqModal .modal-header, #mrqModal .modal-footer { padding-left: 18px; padding-right: 18px; }
    }

    /* fields */
    #mrqModal .form-label { font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--mq-muted); margin-bottom: 6px; }
    #mrqModal .form-label small { text-transform: none; letter-spacing: 0; font-weight: 400; margin-left: 4px; }
    #mrqModal .form-control, #mrqModal .form-select {
        background-color: var(--mq-surface); border: 1px solid var(--mq-border); border-radius: 12px; padding: .65rem .9rem; color: var(--mq-text);
        transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    #mrqModal .form-control::placeholder { color: var(--mq-muted); opacity: .65; }
    #mrqModal .form-control:focus, #mrqModal .form-select:focus { background-color: var(--mq-bg); border-color: var(--mq-accent); box-shadow: 0 0 0 4px var(--mq-ring); outline: 0; }
    #mrqModal .form-control-sm { padding: .45rem .8rem; border-radius: 10px; }
    #mrqModal .mrq-f-when .form-control, #mrqModal .mrq-f-when .form-select { background-color: var(--mq-bg); }
    #mrqModal textarea.form-control { resize: vertical; min-height: 120px; }

    /* alerts + notes */
    #mrqModal .alert { border: 0; border-radius: 14px; padding: 12px 16px; font-size: .86rem; margin-bottom: 18px; }
    #mrqModal .alert-danger { background: rgba(239, 68, 68, .1); color: #b91c1c; box-shadow: inset 4px 0 0 #ef4444; }
    #mrqModal .alert-warning { background: rgba(245, 158, 11, .12); color: #92400e; box-shadow: inset 4px 0 0 #f59e0b; }
    html[data-bs-theme="dark"] #mrqModal .alert-danger { color: #fca5a5; }
    html[data-bs-theme="dark"] #mrqModal .alert-warning { color: #fcd34d; }
    #mrqError::before, #mrqError2::before { content: '\26A0'; margin-right: 8px; }
    #mrqModal .mrq-note { background: rgba(239, 68, 68, .08); border: 1px solid rgba(239, 68, 68, .25); color: #b91c1c; border-radius: 14px; padding: 12px 16px; font-size: .85rem; }
    html[data-bs-theme="dark"] #mrqModal .mrq-note { color: #fca5a5; }
    #mrqModal .mrq-note.mrq-note-info { background: linear-gradient(135deg, rgba(37, 99, 235, .1), rgba(6, 182, 212, .1)); border-color: rgba(37, 99, 235, .25); color: #1e3a8a; }
    html[data-bs-theme="dark"] #mrqModal .mrq-note.mrq-note-info { color: #bfdbfe; }

    /* members picker */
    #mrqModal .mrq-list { flex: 1 1 0; min-height: 260px; overflow-y: auto; border: 1px solid var(--mq-border); border-radius: 14px; padding: 6px; background: var(--mq-surface); scrollbar-width: thin; }
    #mrqModal .mrq-list label { display: flex; gap: 12px; align-items: center; padding: 8px 10px; margin: 0; border-radius: 10px; cursor: pointer; font-weight: 500; transition: background .15s ease, box-shadow .15s ease; }
    #mrqModal .mrq-list label:hover { background: var(--mq-hover); }
    #mrqModal .mrq-list label:has(input:checked) { background: var(--mq-sel); box-shadow: inset 0 0 0 1px var(--mq-accent); }
    #mrqModal .mrq-list input[type="checkbox"] { width: 18px; height: 18px; margin: 0; accent-color: var(--mq-accent); flex-shrink: 0; }
    #mrqModal .mrq-list small { color: var(--mq-muted); margin-left: 4px; }
    #mrqModal .mrq-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; min-height: 8px; }
    #mrqModal .mrq-chip { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #6366f1, #2563eb); color: #fff; border-radius: 20px; padding: 4px 6px 4px 12px; font-size: .8rem; font-weight: 600; box-shadow: 0 4px 10px -4px rgba(79, 70, 229, .6); }
    #mrqModal .mrq-chip button { border: 0; background: rgba(255, 255, 255, .22); color: #fff; width: 18px; height: 18px; border-radius: 50%; font-size: 13px; line-height: 1; padding: 0; cursor: pointer; }
    #mrqModal .mrq-chip button:hover { background: rgba(255, 255, 255, .4); }
    #mrqModal .mrq-av { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; overflow: hidden; color: #fff; font-size: 12px; font-weight: 700; flex-shrink: 0; box-shadow: 0 0 0 2px var(--mq-bg), 0 2px 6px rgba(15, 23, 42, .2); }
    #mrqModal .mrq-av img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }

    /* priority */
    #mrqModal .mrq-popt { position: relative; margin: 0; }
    #mrqModal .mrq-popt input { position: absolute; opacity: 0; }
    #mrqModal .mrq-popt span { display: inline-flex; align-items: center; gap: 8px; border: 1.5px solid var(--mq-border); background: var(--mq-surface); border-radius: 12px; padding: 9px 18px; cursor: pointer; font-weight: 600; font-size: .88rem; transition: all .18s ease; }
    #mrqModal .mrq-popt span i { display: none; }
    #mrqModal .mrq-popt span:hover { transform: translateY(-1px); border-color: var(--mq-muted); }
    #mrqModal .mrq-popt.urgent input:checked + span { border-color: #ef4444; background: rgba(239, 68, 68, .1); box-shadow: 0 0 0 4px rgba(239, 68, 68, .14); }
    #mrqModal .mrq-popt.immediate input:checked + span { border-color: #f97316; background: rgba(249, 115, 22, .1); box-shadow: 0 0 0 4px rgba(249, 115, 22, .14); }
    #mrqModal .mrq-popt.normal input:checked + span { border-color: #22c55e; background: rgba(34, 197, 94, .1); box-shadow: 0 0 0 4px rgba(34, 197, 94, .14); }
    #mrqModal .mrq-popt input:focus-visible + span { outline: 2px solid var(--mq-accent); outline-offset: 2px; }
    #mrqModal .mrq-prio { display: inline-flex; align-items: center; gap: 5px; border-radius: 12px; padding: 1px 9px 1px 7px; font-size: .74rem; font-weight: 700; }
    #mrqModal .mrq-prio i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
    #mrqModal .mrq-prio.urgent { background: #fee2e2; color: #991b1b; } #mrqModal .mrq-prio.urgent i { background: #dc2626; }
    #mrqModal .mrq-prio.immediate { background: #ffedd5; color: #9a3412; } #mrqModal .mrq-prio.immediate i { background: #f97316; }
    #mrqModal .mrq-prio.normal { background: #dcfce7; color: #166534; } #mrqModal .mrq-prio.normal i { background: #16a34a; }
    #mrqModal .mrq-room { background: var(--mq-sel); color: var(--mq-accent); border-radius: 8px; padding: 1px 8px; font-size: .74rem; font-weight: 600; }

    /* time row helpers */
    #mrqModal .mrq-f-meta small.text-muted { color: var(--mq-muted) !important; }

    /* review step */
    #mrqModal .mrq-review { display: grid; grid-template-columns: 180px minmax(0, 1fr); background: var(--mq-surface); border: 1px solid var(--mq-border); border-radius: 16px; padding: 6px 20px; }
    #mrqModal .mrq-review dt { color: var(--mq-muted); font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; margin: 0; padding: 14px 0; border-bottom: 1px solid var(--mq-border); }
    #mrqModal .mrq-review dd { margin: 0; padding: 12px 0; color: var(--mq-text); white-space: pre-line; border-bottom: 1px solid var(--mq-border); }
    #mrqModal .mrq-review dt:nth-last-of-type(1), #mrqModal .mrq-review dd:last-of-type { border-bottom: 0; }
    @media (max-width: 767.98px) { #mrqModal .mrq-review { grid-template-columns: 1fr; } #mrqModal .mrq-review dt { border-bottom: 0; padding-bottom: 0; } }

    /* sent / inbox cards */
    #mrqModal .mrq-req { border: 1px solid var(--mq-border); background: var(--mq-surface); border-radius: 16px; padding: 16px 18px; margin-bottom: 12px; transition: box-shadow .2s ease, transform .2s ease; }
    #mrqModal .mrq-req:hover { box-shadow: 0 10px 26px -14px rgba(15, 23, 42, .35); transform: translateY(-1px); }
    /* inbox card: text on the left, action buttons parallel on the right (saves vertical space) */
    #mrqModal .mrq-req-row { display: flex; align-items: center; gap: 12px 20px; flex-wrap: wrap; }
    #mrqModal .mrq-req-info { flex: 1 1 360px; min-width: 0; }
    #mrqModal .mrq-req-row [data-actions] { flex: 0 0 auto; flex-wrap: nowrap !important; margin-left: auto; }
    #mrqModal .mrq-req-row [data-actions] .btn { white-space: nowrap; }
    #mrqModal .mrq-req h6 { font-weight: 700; margin: 0 0 2px; color: var(--mq-text); }

    /* success */
    #mrqModal .mrq-success-icon { font-size: 64px; color: #16a34a; line-height: 1; animation: mrqPop .5s cubic-bezier(.2, 1.4, .4, 1) both; }
    @keyframes mrqPop { 0% { transform: scale(.4); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

    /* footer buttons */
    #mrqModal .modal-footer .btn { border-radius: 12px; padding: .55rem 1.3rem; font-weight: 600; transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease; }
    #mrqModal .modal-footer .btn:hover:not(:disabled) { transform: translateY(-1px); }
    #mrqModal .modal-footer .btn-primary { border: 0; background: linear-gradient(135deg, #4f46e5, #2563eb); box-shadow: 0 10px 22px -10px rgba(79, 70, 229, .8); }
    #mrqModal .modal-footer .btn-success { border: 0; background: linear-gradient(135deg, #059669, #10b981); box-shadow: 0 10px 22px -10px rgba(5, 150, 105, .8); }
    #mrqModal .modal-footer .btn:disabled { opacity: .55; }
    #mrqModal .modal-footer .btn-light { background: var(--mq-bg); border: 1px solid var(--mq-border); color: var(--mq-text); }
    #mrqModal .modal-footer .btn-outline-secondary, #mrqModal .modal-footer .btn-outline-primary { border-width: 1.5px; }
    #mrqModal .mrq-gmeet { background: #1a73e8; border-color: #1a73e8; color: #fff; }
    #mrqModal .mrq-gmeet:hover { background: #1557b0; color: #fff; }
</style>

<button type="button" id="mrqFab" data-bs-toggle="modal" data-bs-target="#mrqModal" title="Request a meeting" aria-label="Request a meeting">
    <i class="ri-vidicon-fill"></i>
    <span class="mrq-badge mrq-hidden" id="mrqBadge" style="display:none">0</span>
</button>
<div class="mrq-toast" id="mrqToast" style="display:none" role="status"></div>

<div class="modal fade" id="mrqModal" tabindex="-1" aria-hidden="true" aria-labelledby="mrqTitle">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mrqTitle"><span class="mrq-hico"><i class="ri-vidicon-line"></i></span><span>Meeting<small>Plan it, invite your team and get a Google Meet link</small></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mrq-tabs" id="mrqTabs">
                    <button type="button" class="mrq-tab active" data-tab="new"><i class="ri-add-circle-line me-1"></i>Request a meeting</button>
                    <button type="button" class="mrq-tab" data-tab="sent"><i class="ri-send-plane-line me-1"></i>Sent by you</button>
                    <button type="button" class="mrq-tab" data-tab="inbox"><i class="ri-inbox-line me-1"></i>Requests for you <span class="badge bg-danger mrq-hidden" id="mrqTabBadge">0</span></button>
                </div>

                {{-- STEP 1: form --}}
                <div id="mrqStepForm">
                    <div class="alert alert-danger py-2 mrq-hidden" id="mrqError"></div>
                    <div class="mrq-form-grid">
                        <div class="mrq-f-title">
                            <label class="form-label" for="mrqTitleInput">Meeting title <span class="text-danger">*</span></label>
                            <input type="text" id="mrqTitleInput" class="form-control" maxlength="255" placeholder="e.g. Amazon ads budget review">
                        </div>
                        <div class="mrq-f-agenda">
                            <label class="form-label" for="mrqAgenda">Agenda
                                <small class="text-muted">what do you want to discuss / decide?</small></label>
                            <textarea id="mrqAgenda" class="form-control" rows="5" maxlength="5000"
                                      placeholder="1. Review last week's ACOS&#10;2. Decide budget for next week&#10;3. Assign owners"></textarea>
                        </div>
                        <div class="mrq-f-members">
                            <div class="d-flex justify-content-between align-items-center mb-1 gap-2">
                                <label class="form-label mb-0">Add members <span class="text-danger">*</span></label>
                                <input type="search" id="mrqSearch" class="form-control form-control-sm" style="width:220px" placeholder="Search name / designation">
                            </div>
                            <div class="mrq-list" id="mrqMembers"><div class="text-muted small py-2 px-2">Loading team…</div></div>
                            <div class="mrq-chips" id="mrqChips"></div>
                        </div>
                        <div class="mrq-f-prio">
                            <label class="form-label d-block">Priority <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2">
                                <label class="mrq-popt urgent"><input type="radio" name="mrqPrio" value="urgent"><span><i></i>🔴 Urgent</span></label>
                                <label class="mrq-popt immediate"><input type="radio" name="mrqPrio" value="immediate"><span><i></i>🟠 Immediate</span></label>
                                <label class="mrq-popt normal"><input type="radio" name="mrqPrio" value="normal" checked><span><i></i>🟢 Normal</span></label>
                            </div>
                        </div>
                        <div class="mrq-f-when">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label" for="mrqRoom">Meeting room <span class="text-danger">*</span></label>
                                    <select id="mrqRoom" class="form-select">
                                        <option value="">Select room…</option>
                                        <option value="1">Meeting Room 1</option><option value="2">Meeting Room 2</option>
                                        <option value="3">Meeting Room 3</option><option value="4">Meeting Room 4</option>
                                    </select>
                                </div>
                                {{-- Date & time are hidden: they are stamped automatically when the request is made and shown in "Requests for you". --}}
                                <input type="hidden" id="mrqDate"><input type="hidden" id="mrqStart">
                                <div class="col-md-4"><label class="form-label" for="mrqApprox">Approx time <span class="text-danger">*</span></label>
                                    <select id="mrqApprox" class="form-select" title="Approximate length of the meeting">
                                        <option value="15">~ 15 min</option><option value="30" selected>~ 30 min</option>
                                        <option value="45">~ 45 min</option><option value="60">~ 1 hr</option>
                                        <option value="90">~ 1.5 hr</option><option value="120">~ 2 hr</option>
                                        <option value="180">~ 3 hr</option>
                                    </select></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
                                <small class="text-muted"><i class="ri-time-line me-1"></i>Date &amp; time are set automatically when you send the request (<strong id="mrqTz">Asia/Kolkata</strong>).</small>
                            </div>
                            <div class="small mt-2" id="mrqAvail"></div>
                        </div>
                        <div class="mrq-f-meta">
                            <div class="mrq-note mrq-note-info" id="mrqProviderNote"><i class="ri-vidicon-fill me-1"></i>A real <strong>Google Meet</strong> link is created through Google Calendar and every member gets a Google Calendar invitation.</div>
                        </div>
                    </div>
                </div>

                {{-- STEP 2: review & confirm --}}
                <div id="mrqStepReview" class="mrq-hidden">
                    <div class="alert alert-danger py-2 mrq-hidden" id="mrqError2"></div>
                    <p class="mb-1 fw-semibold">Please review and confirm your meeting request</p>
                    <dl class="mrq-review mb-3">
                        <dt>Title</dt><dd id="mrqRvTitle"></dd>
                        <dt>Agenda</dt><dd id="mrqRvAgenda"></dd>
                        <dt>Priority</dt><dd id="mrqRvPrio"></dd>
                        <dt>Meeting room</dt><dd id="mrqRvRoom"></dd>
                        <dt>Members (<span id="mrqRvCount">0</span>)</dt><dd id="mrqRvMembers"></dd>
                        <dt>When</dt><dd id="mrqRvWhen"></dd>
                    </dl>
                    <div class="mrq-note"><span id="mrqRvNotify"></span> They can accept or decline from the meeting icon.</div>
                </div>

                {{-- STEP 3: sent --}}
                <div id="mrqStepDone" class="mrq-hidden text-center py-3">
                    <div class="mrq-success-icon"><i class="ri-checkbox-circle-fill"></i></div>
                    <h5 class="mt-2 mb-1" id="mrqDoneTitle">Meeting request sent</h5>
                    <p class="text-muted mb-2" id="mrqDoneText"></p>
                    <div class="alert alert-warning py-2 small mrq-hidden text-start" id="mrqDoneWarn"></div>
                    <div id="mrqDoneInvitees" class="d-flex flex-wrap justify-content-center gap-1"></div>
                    <div class="small text-muted mt-2">Track their answers under the <strong>Sent by you</strong> tab.</div>
                </div>

                {{-- Sent by you --}}
                <div id="mrqSent" class="mrq-hidden">
                    <div id="mrqSentList"><div class="text-muted">Loading…</div></div>
                </div>

                {{-- Inbox --}}
                <div id="mrqInbox" class="mrq-hidden">
                    <div id="mrqInboxList"><div class="text-muted">Loading…</div></div>
                </div>
            </div>
            <div class="modal-footer" id="mrqFooter">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="mrqBtnCancel">Cancel</button>
                <button type="button" class="btn btn-outline-secondary mrq-hidden" id="mrqBtnBack">Back</button>
                <button type="button" class="btn btn-primary" id="mrqBtnReview">Review request</button>
                <button type="button" class="btn btn-success mrq-hidden" id="mrqBtnConfirm"><i class="ri-send-plane-fill me-1"></i>Confirm &amp; send</button>
                <a class="btn mrq-gmeet mrq-hidden" id="mrqBtnStart" href="#" target="_blank" rel="noopener noreferrer"><i class="ri-vidicon-fill me-1"></i><span id="mrqBtnStartLbl">Join Google Meet</span></a>
                <a class="btn btn-outline-primary mrq-hidden" id="mrqBtnLobby" href="#">View meetings</a>
                <button type="button" class="btn btn-light mrq-hidden" id="mrqBtnClose" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        if (window.self !== window.top) { // never inside an embedded iframe
            var f = document.getElementById('mrqFab'); if (f) f.remove();
            return;
        }
        var URLS = {
            members: @json(route('meetings.members')),
            request: @json(route('meetings.request')),
            requests: @json(route('meetings.requests')),
            sent: @json(route('meetings.sent')),
            availability: @json(route('meetings.availability'))
        };
        var CSRF = @json(csrf_token());
        var $ = function (id) { return document.getElementById(id); };
        var hide = function (id, h) { $(id).classList.toggle('mrq-hidden', h !== false); };
        var members = null, selected = {}, step = 'form', tab = 'new', lastPayload = null, googleOk = true, tz = 'Asia/Kolkata';

        function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
        function call(method, url, body) {
            return fetch(url, {
                method: method, credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: body ? JSON.stringify(body) : undefined
            }).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, status: r.status, data: j }; }); });
        }
        function firstError(res) {
            if (res.data && res.data.errors) { var k = Object.keys(res.data.errors)[0]; return res.data.errors[k][0]; }
            return (res.data && res.data.message) || 'Something went wrong. Please try again.';
        }

        // ---------- members picker ----------
        function loadMembers() {
            if (members) return;
            call('GET', URLS.members).then(function (res) {
                members = (res.data && res.data.members) || [];
                if (res.data && res.data.google && res.data.google.ok === false) {
                    // Not connected: no blocking error. The server creates the meeting-room link automatically.
                    googleOk = false;
                    $('mrqProviderNote').innerHTML = '<i class="ri-link-m me-1"></i>A <strong>meeting-room link is created automatically</strong> when you send the request, and every member can join it from <strong>Requests for you</strong>. (Google Meet links are used once a manager connects the Google account.)';
                }
                if (res.data && res.data.timezone) { tz = res.data.timezone; $('mrqTz').textContent = tz; }
                renderMembers();
            }).catch(function () { $('mrqMembers').innerHTML = '<div class="text-danger small py-2">Could not load team members.</div>'; });
        }
        function avatar(m) {
            // photo when the employee has one; initials otherwise (also when the photo fails to load)
            return '<span class="mrq-av" style="background:' + esc(m.colour || '#64748b') + '">' + esc(m.initials || '?')
                + (m.avatar ? '<img src="' + esc(m.avatar) + '" alt="" loading="lazy" onerror="this.remove()">' : '') + '</span>';
        }
        function renderMembers() {
            var q = $('mrqSearch').value.trim().toLowerCase();
            var html = '';
            (members || []).forEach(function (m) {
                var hay = (m.name + ' ' + m.designation).toLowerCase();
                if (q && hay.indexOf(q) === -1) return;
                html += '<label><input type="checkbox" value="' + m.id + '"' + (selected[m.id] ? ' checked' : '') + '>'
                    + avatar(m) + '<span>' + esc(m.name) + ' <small>' + esc(m.designation) + '</small></span></label>';
            });
            $('mrqMembers').innerHTML = html || '<div class="text-muted small py-2">No members match.</div>';
        }
        function renderChips() {
            var box = $('mrqChips'); box.innerHTML = '';
            Object.keys(selected).forEach(function (id) {
                var c = document.createElement('span'); c.className = 'mrq-chip';
                c.appendChild(document.createTextNode(selected[id]));
                var x = document.createElement('button'); x.type = 'button'; x.setAttribute('aria-label', 'Remove'); x.innerHTML = '&times;';
                x.addEventListener('click', function () { delete selected[id]; renderChips(); renderMembers(); });
                c.appendChild(x); box.appendChild(c);
            });
        }
        $('mrqMembers').addEventListener('change', function (e) {
            var cb = e.target; if (!cb || cb.type !== 'checkbox') return;
            var m = (members || []).filter(function (x) { return String(x.id) === cb.value; })[0];
            if (!m) return;
            if (cb.checked) selected[m.id] = m.name; else delete selected[m.id];
            renderChips();
        });
        $('mrqSearch').addEventListener('input', renderMembers);
        function nowIn(plusMin) {
            var d = new Date(Date.now() + (plusMin || 0) * 60000), p = {};
            new Intl.DateTimeFormat('en-GB', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
                .formatToParts(d).forEach(function (x) { p[x.type] = x.value; });
            return { date: p.year + '-' + p.month + '-' + p.day, time: p.hour + ':' + p.minute };
        }
        // Hidden date & time = "now". Refreshed whenever the modal opens and again at review (the server stamps the final value).
        function resetPriority() { var n = document.querySelector('input[name="mrqPrio"][value="normal"]'); if (n) n.checked = true; }
        function stampNow() { var a = nowIn(0); $('mrqDate').value = a.date; $('mrqStart').value = a.time; }
        stampNow();
        var availSeq = 0;
        function checkAvail() {
            var room = $('mrqRoom').value, date = $('mrqDate').value, box = $('mrqAvail');
            if (!room || !date) { box.innerHTML = ''; return; }
            var my = ++availSeq;
            call('GET', URLS.availability + '?room_slot=' + encodeURIComponent(room) + '&meeting_date=' + encodeURIComponent(date)).then(function (res) {
                if (my !== availSeq || !res.ok) return;
                var busy = res.data.busy || [];
                box.innerHTML = busy.length
                    ? '<span class="text-danger"><i class="ri-error-warning-line"></i> ' + esc(res.data.room) + ' is already booked:</span> ' + busy.map(function (b) { return '<span class="badge bg-light text-dark border">' + esc(b.from) + ' – ' + esc(b.to) + '</span>'; }).join(' ')
                    : '<span class="text-success"><i class="ri-checkbox-circle-line"></i> ' + esc(res.data.room) + ' is free all day.</span>';
            });
        }
        $('mrqRoom').addEventListener('change', checkAvail);
        $('mrqDate').addEventListener('change', checkAvail);
        var PRIO = { urgent: ['🔴', 'Urgent'], immediate: ['🟠', 'Immediate'], normal: ['🟢', 'Normal'] };
        function prioBadge(p) {
            p = p || { label: 'Normal', hex: '#16a34a' };
            var key = String(p.label || 'Normal').toLowerCase();
            return '<span class="mrq-prio ' + esc(key) + '"><i></i>' + esc(p.label || 'Normal') + '</span>';
        }

        // ---------- steps ----------
        function setStep(s) {
            step = s;
            hide('mrqStepForm', s !== 'form'); hide('mrqStepReview', s !== 'review'); hide('mrqStepDone', s !== 'done');
            hide('mrqBtnCancel', s !== 'form'); hide('mrqBtnReview', s !== 'form');
            hide('mrqBtnBack', s !== 'review'); hide('mrqBtnConfirm', s !== 'review');
            hide('mrqBtnClose', s !== 'done'); hide('mrqBtnLobby', s !== 'done'); hide('mrqBtnStart', true);
            $('mrqError').classList.add('mrq-hidden'); $('mrqError2').classList.add('mrq-hidden');
        }
        function setTab(t) {
            tab = t;
            document.querySelectorAll('#mrqTabs .mrq-tab').forEach(function (b) { b.classList.toggle('active', b.getAttribute('data-tab') === t); });
            var isNew = t === 'new';
            hide('mrqInbox', t !== 'inbox');
            hide('mrqSent', t !== 'sent');
            if (isNew) { setStep(step); }
            else {
                ['mrqStepForm', 'mrqStepReview', 'mrqStepDone'].forEach(function (id) { hide(id, true); });
                ['mrqBtnCancel', 'mrqBtnReview', 'mrqBtnBack', 'mrqBtnConfirm', 'mrqBtnLobby', 'mrqBtnStart'].forEach(function (id) { hide(id, true); });
                hide('mrqBtnClose', false);
                if (t === 'inbox') loadInbox(); else loadSent();
            }
        }
        document.querySelectorAll('#mrqTabs .mrq-tab').forEach(function (b) {
            b.addEventListener('click', function () { setTab(b.getAttribute('data-tab')); });
        });

        function showErr(id, msg) { var e = $(id); e.textContent = msg; e.classList.remove('mrq-hidden'); }

        function approxLabel() { var o = $('mrqApprox'); return o.options[o.selectedIndex].text.replace('~ ', ''); }

        function collect() {
            var prio = document.querySelector('input[name="mrqPrio"]:checked');
            return {
                title: $('mrqTitleInput').value.trim(),
                agenda: $('mrqAgenda').value.trim(),
                invitee_ids: Object.keys(selected).map(Number),
                priority: prio ? prio.value : 'normal',
                room_slot: $('mrqRoom').value ? Number($('mrqRoom').value) : null,
                approx_minutes: Number($('mrqApprox').value || 30)
            };
        }

        $('mrqBtnReview').addEventListener('click', function () {
            var p = collect();
            if (!p.title) return showErr('mrqError', 'Please enter a meeting title.');
            if (!p.room_slot) return showErr('mrqError', 'Choose one of the four meeting rooms.');

            if (!p.invitee_ids.length) return showErr('mrqError', 'Add at least one member to the meeting.');
            lastPayload = p;
            $('mrqRvTitle').textContent = p.title;
            $('mrqRvAgenda').textContent = p.agenda || '—';
            $('mrqRvPrio').textContent = PRIO[p.priority][0] + ' ' + PRIO[p.priority][1];
            $('mrqRvRoom').textContent = 'Meeting Room ' + p.room_slot + (googleOk ? ' (Google Meet)' : ' (meeting-room link, created automatically)');
            $('mrqRvCount').textContent = p.invitee_ids.length;
            $('mrqRvMembers').textContent = Object.keys(selected).map(function (id) { return selected[id]; }).join(', ');
            stampNow();
            $('mrqRvWhen').textContent = $('mrqDate').value + ', ' + $('mrqStart').value + ' (set automatically when sent) · approx ' + approxLabel() + ' (' + tz + ')';
            $('mrqRvNotify').textContent = p.invitee_ids.length + ' member' + (p.invitee_ids.length === 1 ? '' : 's') + (googleOk ? ' will get a Google Calendar invitation with the Google Meet link.' : ' will be notified and can join with the meeting-room link.');
            setStep('review');
        });
        $('mrqBtnBack').addEventListener('click', function () { setStep('form'); });

        $('mrqBtnConfirm').addEventListener('click', function () {
            var btn = this; btn.disabled = true; btn.textContent = 'Creating meeting link…';
            var reset = function () { btn.disabled = false; btn.innerHTML = '<i class="ri-send-plane-fill me-1"></i>Confirm &amp; send'; };
            call('POST', URLS.request, lastPayload).then(function (res) {
                reset();
                if (!res.ok || !res.data.ok) return showErr('mrqError2', firstError(res));
                var d = res.data;
                var isG = d.meeting.is_google !== false;
                $('mrqDoneTitle').textContent = isG ? (d.meeting.meet_url ? 'Google Meet created' : 'Meeting saved') : 'Meeting link created';
                $('mrqDoneText').textContent = '"' + d.meeting.title + '" · ' + d.meeting.room + ' · ' + d.meeting.scheduled_at + '. '
                    + d.meeting.invited + ' member' + (d.meeting.invited === 1 ? ' has' : 's have') + (isG ? ' been invited through Google Calendar.' : ' been invited. They can join from "Requests for you".');
                var warn = $('mrqDoneWarn');
                warn.classList.toggle('mrq-hidden', !d.google_error);
                warn.textContent = d.google_error ? 'Google Meet link not ready: ' + d.google_error : '';
                $('mrqBtnStart').href = d.meeting.join_url || d.meeting.meet_url || d.details_url;
                $('mrqBtnStartLbl').textContent = isG ? 'Join Google Meet' : 'Open meeting room';
                $('mrqBtnStart').setAttribute('target', isG ? '_blank' : '_self');
                $('mrqBtnLobby').href = d.details_url;
                $('mrqBtnLobby').textContent = 'Open meeting details';
                var inv = $('mrqDoneInvitees'); inv.innerHTML = '';
                (d.meeting.invitees || []).forEach(function (n) { var b = document.createElement('span'); b.className = 'badge bg-warning text-dark'; b.textContent = n + ' · waiting for reply'; inv.appendChild(b); });
                setStep('done');
                hide('mrqBtnStart', !(d.meeting.join_url || d.meeting.meet_url));
                // reset form for next time
                $('mrqTitleInput').value = ''; $('mrqAgenda').value = ''; selected = {}; $('mrqRoom').value = ''; resetPriority(); stampNow(); $('mrqApprox').value = '30'; $('mrqAvail').innerHTML = '';
                renderChips(); renderMembers();
                poll(false);
            }).catch(function () {
                reset();
                showErr('mrqError2', 'Network problem. Your request was not sent — please try again.');
            });
        });

        // ---------- sent by me: invited members + their answers ----------
        var STATUS = {
            in_room: ['In room', 'bg-success'], left: ['Left', 'bg-secondary'], accepted: ['Accepted', 'bg-success'],
            declined: ['Declined', 'bg-danger'], completed: ['Completed', 'bg-primary'], pending: ['Waiting for reply', 'bg-warning text-dark']
        };
        function loadSent() {
            var box = $('mrqSentList'); box.innerHTML = '<div class="text-muted">Loading…</div>';
            call('GET', URLS.sent).then(function (res) {
                var list = (res.data && res.data.meetings) || [];
                if (!res.ok) { box.innerHTML = '<div class="text-danger">Could not load your meeting requests.</div>'; return; }
                if (!list.length) { box.innerHTML = '<div class="text-muted py-3 text-center">You have no upcoming or live meetings. Use the <strong>Request a meeting</strong> tab to create one.</div>'; return; }
                box.innerHTML = '';
                list.forEach(function (m) {
                    var div = document.createElement('div'); div.className = 'mrq-req';
                    var counts = { accepted: 0, declined: 0, pending: 0 };
                    m.invitees.forEach(function (i) { if (i.status === 'declined') counts.declined++; else if (i.status === 'pending') counts.pending++; else counts.accepted++; });
                    var chips = m.invitees.map(function (i) {
                        var st = STATUS[i.status] || STATUS.pending;
                        return '<span class="badge ' + st[1] + ' me-1 mb-1" style="font-size:.78rem">' + esc(i.name) + ' &middot; ' + st[0] + '</span>';
                    }).join('');
                    var join = m.meet_url
                        ? '<a class="btn btn-sm mrq-gmeet" target="_blank" rel="noopener noreferrer" href="' + esc(m.meet_url) + '"><i class="ri-vidicon-fill"></i> Join Google Meet</a> '
                        : (m.google
                            ? '<span class="badge bg-warning text-dark">Google Meet link ' + (m.google_status === 'failed' ? 'not created' : 'pending') + '</span> '
                            : '<a class="btn btn-sm btn-success" href="' + esc(m.room_url) + '"><i class="ri-vidicon-line"></i> Open meeting room</a> ');
                    div.innerHTML = '<h6>' + esc(m.title) + ' ' + prioBadge(m.priority) + (m.live ? ' <span class="badge bg-success">Live now</span>' : '') + '</h6>'
                        + '<div class="small text-muted mb-1">' + esc(m.scheduled_at || (m.live ? 'happening now' : 'No time set')) + (m.room ? ' &middot; <span class="mrq-room">' + esc(m.room) + '</span>' : '') + '</div>'
                        + (m.agenda ? '<div class="small mb-2" style="white-space:pre-line"><span class="text-muted">Agenda:</span> ' + esc(m.agenda) + '</div>' : '')
                        + '<div class="small mb-1"><strong>' + counts.accepted + '</strong> accepted &middot; <strong>' + counts.declined + '</strong> declined &middot; <strong>' + counts.pending + '</strong> waiting</div>'
                        + '<div class="mb-2">' + (chips || '<span class="text-muted small">No members invited.</span>') + '</div>'
                        + join + '<a class="btn btn-sm btn-light" href="' + esc(m.details_url || m.room_url) + '">Details</a>';
                    box.appendChild(div);
                });
            }).catch(function () { box.innerHTML = '<div class="text-danger">Could not load your meeting requests.</div>'; });
        }

        // ---------- inbox (requests for me) ----------
        var seenKey = 'mrqSeenRequests';
        function seenIds() { try { return JSON.parse(localStorage.getItem(seenKey) || '[]'); } catch (e) { return []; } }
        function rememberSeen(ids) { try { localStorage.setItem(seenKey, JSON.stringify(ids.slice(-100))); } catch (e) {} }

        function setBadge(n) {
            var b = $('mrqBadge'), tb = $('mrqTabBadge');
            b.textContent = n; b.style.display = n ? '' : 'none'; b.classList.remove('mrq-hidden');
            tb.textContent = n; tb.classList.toggle('mrq-hidden', !n);
            $('mrqFab').classList.toggle('has-pending', n > 0);
            // same count on the sidebar ("Tasks" menu + "Meeting Room" item)
            document.querySelectorAll('[data-mrq-badge]').forEach(function (el) { el.textContent = n; el.style.display = n ? '' : 'none'; });
        }

        var pollTimer = null;
        function poll(showToast) {
            return call('GET', URLS.requests).then(function (res) {
                if (!res.ok) return;
                var list = res.data.requests || [];
                setBadge(res.data.count || 0);
                if (showToast !== false) {
                    var seen = seenIds();
                    var fresh = list.filter(function (r) { return seen.indexOf(r.id) === -1; });
                    if (fresh.length) {
                        var r = fresh[0];
                        var t = $('mrqToast');
                        t.innerHTML = '<strong>' + esc(r.host || 'Someone') + '</strong> requested a meeting'
                            + (fresh.length > 1 ? ' (+' + (fresh.length - 1) + ' more)' : '') + ':<br>' + esc(r.title);
                        t.style.display = '';
                        t.onclick = function () { t.style.display = 'none'; openModal('inbox'); };
                        setTimeout(function () { t.style.display = 'none'; }, 30000);
                        rememberSeen(seen.concat(fresh.map(function (x) { return x.id; })));
                    }
                }
                return list;
            }).catch(function () {});
        }

        function loadInbox() {
            $('mrqInboxList').innerHTML = '<div class="text-muted">Loading…</div>';
            poll(false).then(function (list) {
                list = list || [];
                rememberSeen(seenIds().concat(list.map(function (x) { return x.id; })));
                if (!list.length) {
                    $('mrqInboxList').innerHTML = '<div class="text-muted py-3 text-center">No pending meeting requests.</div>';
                    return;
                }
                $('mrqInboxList').innerHTML = '';
                list.forEach(function (r) { $('mrqInboxList').appendChild(requestCard(r)); });
            });
        }

        function requestCard(r) {
            var div = document.createElement('div'); div.className = 'mrq-req';
            div.innerHTML = '<div class="mrq-req-row"><div class="mrq-req-info"><h6>' + esc(r.title) + ' ' + prioBadge(r.priority) + (r.live ? ' <span class="badge bg-success">Live now</span>' : '') + '</h6>'
                + '<div class="small text-muted mb-1">From <strong>' + esc(r.host || '—') + '</strong> &middot; '
                + '<i class="ri-calendar-event-line"></i> <strong>Date &amp; time:</strong> ' + esc(r.scheduled_at || (r.live ? 'happening now' : 'time not set')) + (r.room ? ' &middot; <span class="mrq-room">' + esc(r.room) + '</span>' : '')
                + (r.requested_at ? ' &middot; requested ' + esc(r.requested_at) : '') + '</div>'
                + (r.agenda ? '<div class="small" style="white-space:pre-line"><span class="text-muted">Agenda:</span> ' + esc(r.agenda) + '</div>' : '')
                + '</div><div class="d-flex gap-2" data-actions></div></div><div class="small mt-2" data-result></div>';
            var actions = div.querySelector('[data-actions]'), result = div.querySelector('[data-result]');
            function btn(label, cls, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm ' + cls; b.innerHTML = label; b.addEventListener('click', fn); actions.appendChild(b); return b; }
            function answer(resp) {
                if (resp === 'declined' && !confirm('Decline "' + r.title + '"? The host will see that you declined.')) return;
                if (resp === 'completed' && !confirm('Mark "' + r.title + '" as completed? It will be removed from your requests and the host will see it as completed.')) return;
                actions.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                call('POST', r.rsvp_url, { response: resp }).then(function (res) {
                    if (!res.ok || !res.data.ok) {
                        actions.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                        result.innerHTML = '<span class="text-danger">' + esc(firstError(res)) + '</span>';
                        return;
                    }
                    actions.innerHTML = '';
                    if (resp === 'accepted') {
                        result.innerHTML = '<span class="text-success fw-semibold"><i class="ri-checkbox-circle-fill"></i> You accepted.</span>'
                            + (r.meet_url
                                ? ' <a class="btn btn-sm mrq-gmeet ms-2" target="_blank" rel="noopener noreferrer" href="' + esc(r.meet_url) + '"><i class="ri-vidicon-fill"></i> Join Google Meet</a>'
                                : ' <a class="btn btn-success btn-sm ms-2" href="' + esc(res.data.room_url) + '"><i class="ri-vidicon-line"></i> Meeting details</a>')
                            + (r.google || res.data.live ? '' : '<div class="text-muted mt-1">Not started yet — you can join once the host starts it.</div>');
                    } else if (resp === 'completed') {
                        result.innerHTML = '<span class="text-primary fw-semibold"><i class="ri-check-double-line"></i> Marked as completed.</span>';
                        setTimeout(function () { div.style.transition = 'opacity .4s ease'; div.style.opacity = '0'; setTimeout(function () { div.remove(); if (!$('mrqInboxList').children.length) loadInbox(); }, 400); }, 1200);
                    } else {
                        result.innerHTML = '<span class="text-secondary"><i class="ri-close-circle-fill"></i> You declined.</span>';
                    }
                    poll(false);
                }).catch(function () {
                    actions.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
                    result.innerHTML = '<span class="text-danger">Network problem. Please try again.</span>';
                });
            }
            // Join is always offered (joining counts as accepting); Accept only while unanswered.
            if (r.meet_url) {
                var j = document.createElement('a'); j.className = 'btn btn-sm mrq-gmeet'; j.target = '_blank'; j.rel = 'noopener noreferrer'; j.href = r.meet_url;
                j.innerHTML = '<i class="ri-vidicon-fill"></i> Join Google Meet'; actions.appendChild(j);
            } else {
                btn('<i class="ri-vidicon-line"></i> ' + (r.google ? 'Meeting details' : 'Join meeting'), 'btn-primary', function () { window.location.href = r.room_url; });
            }
            if (r.rsvp !== 'accepted') btn('<i class="ri-check-line"></i> Accept', 'btn-success', function () { answer('accepted'); });
            btn('<i class="ri-close-line"></i> Decline', 'btn-outline-danger', function () { answer('declined'); });
            btn('<i class="ri-check-double-line"></i> Completed', 'btn-outline-primary', function () { answer('completed'); });
            return div;
        }

        // ---------- open / close ----------
        function openModal(t) {
            var el = $('mrqModal');
            if (window.bootstrap && window.bootstrap.Modal) window.bootstrap.Modal.getOrCreateInstance(el).show();
            setTab(t || 'new');
        }
        $('mrqModal').addEventListener('show.bs.modal', function (e) {
            stampNow(); checkAvail();
            if (step === 'form') resetPriority();
            loadMembers();
            if (e.relatedTarget && e.relatedTarget.id === 'mrqFab') {
                // Clicking the floating icon: go straight to pending requests if someone is waiting on you.
                var pending = parseInt($('mrqBadge').textContent, 10) > 0 && $('mrqBadge').style.display !== 'none';
                if (step === 'done') setStep('form');
                setTab(pending ? 'inbox' : 'new');
            } else if (tab === 'new' && step === 'done') { setStep('form'); }
        });
        $('mrqModal').addEventListener('shown.bs.modal', function () { if (tab === 'new' && step === 'form') $('mrqTitleInput').focus(); });
        setStep('form');

        // ---------- background poll for incoming requests ----------
        function startPolling() {
            poll(true);
            pollTimer = setInterval(function () { if (!document.hidden) poll(true); }, 30000);
        }
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(true); });
        if (document.readyState === 'complete') startPolling(); else window.addEventListener('load', startPolling);
    })();
</script>
@endunless
@endauth
