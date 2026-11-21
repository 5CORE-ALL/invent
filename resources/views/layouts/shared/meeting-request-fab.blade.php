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
    #mrqModal .mrq-subtabs { display: flex; gap: 8px; margin: 0 0 16px; }
    #mrqModal .mrq-subtab { border: 1.5px solid var(--mq-border); background: var(--mq-surface); color: var(--mq-muted); border-radius: 20px; padding: 5px 16px; font-size: .85rem; font-weight: 600; transition: all .15s ease; }
    #mrqModal .mrq-subtab:hover { color: var(--mq-text); }
    #mrqModal .mrq-subtab.active { border-color: var(--mq-accent); color: var(--mq-accent); background: var(--mq-sel); }
    #mrqModal .mrq-subtab .badge { margin-left: 4px; border-radius: 10px; }

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

    /* meeting type: phone / Google Meet, the green circle marks the selected one */
    #mrqModal .mrq-type-opt { position: relative; width: 54px; height: 54px; padding: 0; border-radius: 50%; background: var(--mq-surface); border: 2px solid var(--mq-border); display: inline-flex; align-items: center; justify-content: center; transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; cursor: pointer; }
    #mrqModal .mrq-type-opt:hover { transform: translateY(-1px); }
    #mrqModal .mrq-type-opt:focus-visible { outline: 0; box-shadow: 0 0 0 4px var(--mq-ring); }
    #mrqModal .mrq-type-ico { display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--mq-muted); }
    #mrqModal .mrq-type-opt.active { border-color: #16a34a; box-shadow: 0 0 0 4px rgba(22, 163, 74, .18); }
    #mrqModal .mrq-type-opt.active .mrq-type-ico { color: #16a34a; }
    #mrqModal .mrq-type-opt:not(.active) svg { filter: grayscale(.85); opacity: .7; }
    #mrqModal .mrq-type-dot { position: absolute; right: -4px; top: -4px; width: 20px; height: 20px; border-radius: 50%; background: #16a34a; color: #fff; font-size: .8rem; display: none; align-items: center; justify-content: center; border: 2px solid var(--mq-bg, #fff); }
    #mrqModal .mrq-type-opt.active .mrq-type-dot { display: inline-flex; }

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
    /* members: three columns, alphabetical left-to-right then down, tall card */
    #mrqModal .mrq-list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 2px 8px; align-content: start; flex: 1 1 0; min-height: 560px; overflow-y: auto; border: 1px solid var(--mq-border); border-radius: 14px; padding: 6px; background: var(--mq-surface); scrollbar-width: thin; }
    #mrqModal .mrq-list label { display: flex; gap: 12px; align-items: center; padding: 8px 10px; margin: 0; border-radius: 10px; cursor: pointer; font-weight: 500; transition: background .15s ease, box-shadow .15s ease; }
    #mrqModal .mrq-list label > span:last-child { min-width: 0; overflow-wrap: anywhere; line-height: 1.25; }
    #mrqModal .mrq-list.mrq-vertical { grid-auto-flow: column; }
    #mrqModal .mrq-list label small { display: block; margin-left: 0; }
    #mrqModal .mrq-list > .text-muted, #mrqModal .mrq-list > .text-danger { grid-column: 1 / -1; }
    @media (max-width: 767.98px) { #mrqModal .mrq-list, #mrqModal .mrq-list.mrq-vertical { grid-template-columns: repeat(1, minmax(0, 1fr)); grid-auto-flow: row; grid-template-rows: none !important; } }
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
    #mrqModal .mrq-req-row [data-actions] .btn.mrq-ico { width: 38px; height: 38px; padding: 0; display: inline-flex; align-items: center; justify-content: center; font-size: 1.15rem; border-radius: 10px; }
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
                            <div class="mrq-type mb-3">
                                <label class="form-label d-block" id="mrqTypeLbl">Meeting type</label>
                                <div class="d-flex align-items-center gap-3" role="radiogroup" aria-labelledby="mrqTypeLbl" id="mrqType">
                                    <button type="button" class="mrq-type-opt active" data-type="phone" role="radio" aria-checked="true" title="Phone call">
                                        <span class="mrq-type-ico"><i class="ri-phone-fill"></i></span>
                                        <span class="mrq-type-dot"><i class="ri-check-line"></i></span>
                                    </button>
                                    <button type="button" class="mrq-type-opt" data-type="google_meet" role="radio" aria-checked="false" title="Google Meet">
                                        <span class="mrq-type-ico">
                                            <svg viewBox="0 0 87.5 72" width="26" height="22" aria-hidden="true">
                                                <path fill="#00832d" d="M49.5 36l8.53 9.75 11.47 7.33 2-17.02-2-16.64-11.69 6.44z"/>
                                                <path fill="#0066da" d="M0 51.5V66c0 3.315 2.685 6 6 6h14.5l3-10.96-3-9.54-9.95-3z"/>
                                                <path fill="#e94235" d="M20.5 0L0 20.5l10.55 3 9.95-3 2.95-9.41z"/>
                                                <path fill="#2684fc" d="M20.5 20.5H0v31h20.5z"/>
                                                <path fill="#00ac47" d="M82.6 8.68L69.5 19.42v33.66l13.16 10.79c1.97 1.54 4.85.135 4.85-2.37V11c0-2.535-2.945-3.925-4.91-2.32zM49.5 36v15.5h-29V72h43c3.315 0 6-2.685 6-6V53.08z"/>
                                                <path fill="#ffba00" d="M63.5 0h-43v20.5h29V36l20-16.57V6c0-3.315-2.685-6-6-6z"/>
                                            </svg>
                                        </span>
                                        <span class="mrq-type-dot"><i class="ri-check-line"></i></span>
                                    </button>
                                    <span class="small fw-semibold" id="mrqTypeName">Phone call</span>
                                </div>
                            </div>
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
                                <label class="mrq-popt normal"><input type="radio" name="mrqPrio" value="normal" checked><span><i></i>🟢 Normal</span></label>
                            </div>
                        </div>
                        <div class="mrq-f-when">
                            <div class="row g-3">
                                <div class="col-md-8 mrq-hidden" id="mrqLinkWrap">
                                    <label class="form-label" for="mrqLinkBtn">Google Meet link</label>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <button type="button" class="btn mrq-gmeet" id="mrqLinkBtn"><i class="ri-vidicon-fill me-1"></i><span id="mrqLinkBtnLbl">Create New Google Meeting</span></button>
                                        <div class="input-group mrq-hidden" id="mrqLinkBox" style="max-width:360px">
                                            <input type="text" id="mrqLinkUrl" class="form-control form-control-sm" readonly aria-label="Google Meet link">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="mrqLinkCopy" title="Copy link"><i class="ri-file-copy-line"></i></button>
                                        </div>
                                    </div>
                                    <div class="small mt-1 text-danger mrq-hidden" id="mrqLinkErr"></div>
                                    <div class="small mt-1 text-muted" id="mrqLinkHint">Creates a new Google Meet link and adds it to the meeting message (agenda) so everyone invited gets it.</div>
                                </div>
                                {{-- Date & time are hidden: they are stamped automatically when the request is made and shown in "Requests for you". --}}
                                <input type="hidden" id="mrqDate"><input type="hidden" id="mrqStart">
                                <div class="col-md-4"><label class="form-label" for="mrqApprox">Approx time <span class="text-danger">*</span></label>
                                    <select id="mrqApprox" class="form-select" title="Approximate length of the meeting">
                                        <option value="10" selected>~ 10 min</option><option value="15">~ 15 min</option><option value="30">~ 30 min</option>
                                        <option value="45">~ 45 min</option><option value="60">~ 1 hr</option>
                                        <option value="90">~ 1.5 hr</option><option value="120">~ 2 hr</option>
                                        <option value="180">~ 3 hr</option>
                                    </select></div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
                                <small class="text-muted"><i class="ri-time-line me-1"></i>Date &amp; time are set automatically when you send the request (<strong id="mrqTz">Asia/Kolkata</strong>).</small>
                            </div>
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
                        <dt>Meeting type</dt><dd id="mrqRvType"></dd>
                        <dt id="mrqRvRoomDt">Google Meet link</dt><dd id="mrqRvRoom"></dd>
                        <dt>Members (<span id="mrqRvCount">0</span>)</dt><dd id="mrqRvMembers"></dd>
                        <dt>When</dt><dd id="mrqRvWhen"></dd>
                    </dl>
                    <div class="mrq-note"><span id="mrqRvNotify"></span> They will see it under "Requests for you" from the meeting icon.</div>
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
                    <div class="mrq-subtabs" id="mrqSentFilter">
                        <button type="button" class="mrq-subtab active" data-filter="active"><i class="ri-time-line me-1"></i>Active</button>
                        <button type="button" class="mrq-subtab" data-filter="completed"><i class="ri-check-double-line me-1"></i>Completed <span class="badge bg-primary mrq-hidden" id="mrqCompletedCount">0</span></button>
                    </div>
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
                <button type="button" class="btn btn-primary" id="mrqBtnReview">Send</button>
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
            link: @json(route('meetings.link'))
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
                members = ((res.data && res.data.members) || []).slice().sort(function (a, b) {
                    return String(a.name || '').localeCompare(String(b.name || ''), undefined, { sensitivity: 'base' }); // A to Z
                });
                if (res.data && res.data.google && res.data.google.ok === false) {
                    // Not connected: meetings are Google Meet only, so a manager has to connect the Google account first.
                    googleOk = false;
                    $('mrqProviderNote').innerHTML = '<i class="ri-error-warning-line me-1"></i><strong>Google Meet is not connected yet.</strong> A manager must click "Connect Google account" on the Meeting Room page before meetings can be created.';
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
            var box = $('mrqMembers'), count = box.dataset.count = String(html ? (html.match(/<label>/g) || []).length : 0);
            box.innerHTML = html || '<div class="text-muted small py-2">No members match.</div>';
            // A to Z down each column: fill column by column, 3 columns
            if (html) { box.classList.add('mrq-vertical'); box.style.gridTemplateRows = 'repeat(' + Math.ceil(Number(count) / 3) + ', auto)'; }
            else { box.classList.remove('mrq-vertical'); box.style.gridTemplateRows = ''; }
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

        // ---------- "Create New Google Meeting": makes a Meet link and writes it into the meeting message ----------
        var meetLink = null, linkToken = null, LINK_MARK = 'Join Google Meet: ';
        function writeLinkIntoAgenda(url) {
            var ta = $('mrqAgenda');
            var lines = ta.value.split('\n').filter(function (l) { return l.indexOf(LINK_MARK) !== 0; }); // replace an older link
            while (lines.length && lines[lines.length - 1].trim() === '') lines.pop();
            ta.value = (lines.length ? lines.join('\n') + '\n\n' : '') + LINK_MARK + url;
        }
        function clearLink() {
            meetLink = null; linkToken = null;
            $('mrqLinkBox').classList.add('mrq-hidden'); $('mrqLinkUrl').value = '';
            $('mrqLinkErr').classList.add('mrq-hidden');
            $('mrqLinkBtnLbl').textContent = 'Create New Google Meeting';
        }
        $('mrqLinkBtn').addEventListener('click', function () {
            var btn = this, err = $('mrqLinkErr');
            if (meetLink && !confirm('Create another Google Meet link? The current link in the message will be replaced.')) return;
            btn.disabled = true; $('mrqLinkBtnLbl').textContent = 'Creating Google Meet…'; err.classList.add('mrq-hidden');
            var restore = function () { btn.disabled = false; $('mrqLinkBtnLbl').textContent = meetLink ? 'Create another Google Meeting' : 'Create New Google Meeting'; };
            call('POST', URLS.link, { title: $('mrqTitleInput').value.trim() }).then(function (res) {
                if (!res.ok || !res.data.ok) { restore(); err.textContent = firstError(res); err.classList.remove('mrq-hidden'); return; }
                meetLink = res.data.meet_url; linkToken = res.data.link_token;
                $('mrqLinkUrl').value = meetLink; $('mrqLinkBox').classList.remove('mrq-hidden');
                writeLinkIntoAgenda(meetLink);
                restore();
            }).catch(function () {
                restore(); err.textContent = 'Network problem. The Google Meet link was not created — please try again.'; err.classList.remove('mrq-hidden');
            });
        });

        // ---------- meeting type toggle: phone (default) or Google Meet ----------
        var meetingType = 'phone', TYPE_NAME = { phone: 'Phone call', google_meet: 'Google Meet' };
        function setType(t) {
            var changed = t !== meetingType;
            meetingType = t;
            document.querySelectorAll('#mrqType .mrq-type-opt').forEach(function (b) {
                var on = b.getAttribute('data-type') === t;
                b.classList.toggle('active', on); b.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            $('mrqTypeName').textContent = TYPE_NAME[t];
            // default length: phone 10 min, Google Meet 30 min (still changeable; only reset when the type or the form changes)
            if (changed || arguments[1] === true) $('mrqApprox').value = t === 'google_meet' ? '30' : '10';
            hide('mrqLinkWrap', t !== 'google_meet');
            if (t !== 'google_meet' && meetingType === 'phone') { // a phone call carries no Google Meet link
                var ta = $('mrqAgenda');
                ta.value = ta.value.split('\n').filter(function (l) { return l.indexOf(LINK_MARK) !== 0; }).join('\n').replace(/\s+$/, '');
                clearLink();
            }
        }
        document.querySelectorAll('#mrqType .mrq-type-opt').forEach(function (b) {
            b.addEventListener('click', function () { setType(b.getAttribute('data-type')); });
        });
        $('mrqLinkCopy').addEventListener('click', function () {
            var inp = $('mrqLinkUrl'); inp.select();
            if (navigator.clipboard) navigator.clipboard.writeText(inp.value).catch(function () { document.execCommand('copy'); });
            else document.execCommand('copy');
        });
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
                link_token: meetingType === 'google_meet' ? linkToken : null,
                approx_minutes: Number($('mrqApprox').value || 30)
            };
        }

        // "Send": validates, sends the request straight away and closes the window (no review step).
        $('mrqBtnReview').addEventListener('click', function () {
            var p = collect();
            if (!p.title) return showErr('mrqError', 'Please enter a meeting title.');
            if (!p.invitee_ids.length) return showErr('mrqError', 'Add at least one member to the meeting.');
            var btn = this; btn.disabled = true; btn.textContent = 'Sending…';
            $('mrqError').classList.add('mrq-hidden');
            var reset = function () { btn.disabled = false; btn.textContent = 'Send'; };
            call('POST', URLS.request, p).then(function (res) {
                reset();
                if (!res.ok || !res.data.ok) return showErr('mrqError', firstError(res));
                var d = res.data;
                // reset the form for next time, then close the window
                $('mrqTitleInput').value = ''; $('mrqAgenda').value = ''; selected = {}; clearLink(); setType('phone', true); resetPriority(); stampNow();
                renderChips(); renderMembers();
                var modalEl = $('mrqModal');
                if (window.bootstrap && window.bootstrap.Modal) window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                var t = $('mrqToast');
                t.innerHTML = '<strong>Meeting request sent</strong><br>' + esc(d.meeting.title) + ' &middot; ' + d.meeting.invited + ' member' + (d.meeting.invited === 1 ? '' : 's') + ' invited'
                    + (d.meeting.meet_url ? '<br><a href="' + esc(d.meeting.meet_url) + '" target="_blank" rel="noopener noreferrer" style="color:#8ab4f8;font-weight:600">Join Google Meet</a>' : '')
                    + (d.google_error ? '<br><small>Google Meet link not ready: ' + esc(d.google_error) + '</small>' : '');
                t.style.display = ''; t.onclick = function (e) { if (!e.target.closest('a')) t.style.display = 'none'; };
                setTimeout(function () { t.style.display = 'none'; }, 15000);
                poll(false);
            }).catch(function () {
                reset();
                showErr('mrqError', 'Network problem. Your request was not sent — please try again.');
            });
        });

        // ---------- sent by me: invited members + their answers ----------
        var STATUS = {
            in_room: ['In room', 'bg-success'], left: ['Left', 'bg-secondary'], accepted: ['Accepted', 'bg-success'],
            declined: ['Declined', 'bg-danger'], completed: ['Completed', 'bg-primary'], pending: ['Waiting for reply', 'bg-warning text-dark']
        };
        var sentSig = '', sentFilter = 'active';
        document.querySelectorAll('#mrqSentFilter .mrq-subtab').forEach(function (b) {
            b.addEventListener('click', function () {
                sentFilter = b.getAttribute('data-filter'); sentSig = '';
                document.querySelectorAll('#mrqSentFilter .mrq-subtab').forEach(function (x) { x.classList.toggle('active', x === b); });
                loadSent();
            });
        });
        // Inline "Reschedule" form on a sent-meeting card (works before, during and after the meeting, also once completed).
        var DURATIONS = [[10, '10 min'], [15, '15 min'], [30, '30 min'], [45, '45 min'], [60, '1 hr'], [90, '1.5 hr'], [120, '2 hr'], [180, '3 hr']];
        function toggleReschedule(card, m) {
            var box = card.querySelector('[data-resched]');
            if (!box.classList.contains('mrq-hidden')) { box.classList.add('mrq-hidden'); box.innerHTML = ''; return; }
            var cur = (m.scheduled_date || '') + ' ' + (m.scheduled_time || ''), now = nowIn(0);
            // keep the old slot if it is still ahead, otherwise suggest "in 30 minutes"
            var pre = (m.scheduled_date && cur > now.date + ' ' + now.time) ? { date: m.scheduled_date, time: m.scheduled_time } : nowIn(30);
            var dur = Number(m.duration_minutes) || 30;
            var opts = DURATIONS.slice();
            if (!opts.some(function (d) { return d[0] === dur; })) { opts.push([dur, dur + ' min']); opts.sort(function (a, b) { return a[0] - b[0]; }); }
            box.innerHTML = '<div class="border rounded-3 p-2" style="background:rgba(245,158,11,.07)">'
                + '<div class="small fw-semibold mb-2"><i class="ri-calendar-schedule-line"></i> Reschedule meeting</div>'
                + '<div class="d-flex flex-wrap gap-1 mb-2" data-rs-quick>'
                + [[1, 'After 1 day'], [2, 'After 2 days'], [3, 'After 3 days'], ['monday', 'Next Monday'], [7, 'After 1 week'], [14, 'After 2 weeks'], [0, 'Custom']].map(function (q) {
                    return '<button type="button" class="btn btn-sm btn-outline-secondary" data-rs-days="' + q[0] + '">' + q[1] + '</button>';
                }).join('') + '</div>'
                + '<div class="row g-2 align-items-end">'
                + '<div class="col-sm-4"><label class="form-label small mb-1">New date</label><input type="date" class="form-control form-control-sm" data-rs-date value="' + esc(pre.date) + '" min="' + esc(now.date) + '"></div>'
                + '<div class="col-sm-3"><label class="form-label small mb-1">Start time</label><input type="time" class="form-control form-control-sm" data-rs-time value="' + esc(pre.time) + '"></div>'
                + '<div class="col-sm-3"><label class="form-label small mb-1">Length</label><select class="form-select form-select-sm" data-rs-dur>'
                + opts.map(function (d) { return '<option value="' + d[0] + '"' + (d[0] === dur ? ' selected' : '') + '>' + esc(d[1]) + '</option>'; }).join('') + '</select></div>'
                + '<div class="col-sm-2 d-flex gap-1"><button type="button" class="btn btn-sm btn-warning flex-fill" data-rs-save>Save</button><button type="button" class="btn btn-sm btn-light" data-rs-cancel title="Close">&times;</button></div>'
                + '</div><div class="small mt-2" data-rs-msg></div>'
                + '<div class="small text-muted mt-1">Invitees see the new time under Requests for you' + (m.google ? ', and the Google Calendar event is updated' : '') + '. Members who marked it completed will see it again.</div></div>';
            box.classList.remove('mrq-hidden');
            var msg = box.querySelector('[data-rs-msg]'), save = box.querySelector('[data-rs-save]');
            box.querySelector('[data-rs-cancel]').addEventListener('click', function () { box.classList.add('mrq-hidden'); box.innerHTML = ''; });
            // Quick picks: N days from today (company time zone), same start time; "Custom" = pick any date yourself.
            var dateIn = box.querySelector('[data-rs-date]'), quick = box.querySelectorAll('[data-rs-days]');
            function mark(btn) { quick.forEach(function (b) { b.classList.toggle('btn-secondary', b === btn); b.classList.toggle('btn-outline-secondary', b !== btn); }); }
            function addDays(ymd, n) { var p = ymd.split('-').map(Number), d = new Date(Date.UTC(p[0], p[1] - 1, p[2] + n)); return d.toISOString().slice(0, 10); }
            quick.forEach(function (b) {
                b.addEventListener('click', function () {
                    var raw = b.getAttribute('data-rs-days'), n = Number(raw);
                    if (raw === 'monday') { // next Monday that is after today
                        var t0 = nowIn(0).date.split('-').map(Number), dow = new Date(Date.UTC(t0[0], t0[1] - 1, t0[2])).getUTCDay();
                        n = ((8 - dow) % 7) || 7;
                    }
                    mark(b);
                    if (n > 0) dateIn.value = addDays(nowIn(0).date, n); else { try { dateIn.showPicker(); } catch (e) { dateIn.focus(); } }
                });
            });
            dateIn.addEventListener('input', function () { mark(box.querySelector('[data-rs-days="0"]')); });
            save.addEventListener('click', function () {
                var d = box.querySelector('[data-rs-date]').value, t = box.querySelector('[data-rs-time]').value;
                if (!d || !t) { msg.innerHTML = '<span class="text-danger">Pick the new date and start time.</span>'; return; }
                if (!confirm('Reschedule "' + m.title + '" to ' + d + ' ' + t + '?')) return;
                save.disabled = true; msg.innerHTML = '<span class="text-muted">Saving…</span>';
                call('POST', m.reschedule_url, { meeting_date: d, start_time: t, approx_minutes: Number(box.querySelector('[data-rs-dur]').value) }).then(function (r) {
                    if (!r.ok || !(r.data && r.data.ok)) { save.disabled = false; msg.innerHTML = '<span class="text-danger">' + esc(firstError(r)) + '</span>'; return; }
                    msg.innerHTML = '<span class="text-success fw-semibold"><i class="ri-checkbox-circle-fill"></i> Rescheduled to ' + esc(r.data.scheduled_at || (d + ' ' + t)) + '.</span>'
                        + (r.data.google_error ? '<div class="text-warning-emphasis">Saved here, but Google Calendar was not updated: ' + esc(r.data.google_error) + '</div>' : '');
                    setTimeout(function () { sentSig = ''; loadSent(); poll(false); }, 1200);
                }).catch(function () { save.disabled = false; msg.innerHTML = '<span class="text-danger">Network problem. Please try again.</span>'; });
            });
        }
        function loadSent(silent) {
            var box = $('mrqSentList'), done = sentFilter === 'completed', reqFilter = sentFilter;
            if (!silent) box.innerHTML = '<div class="text-muted">Loading…</div>';
            call('GET', URLS.sent + (done ? '?filter=completed' : '')).then(function (res) {
                if (reqFilter !== sentFilter) return; // user switched Active/Completed meanwhile
                var list = (res.data && res.data.meetings) || [];
                if (!res.ok) { if (!silent) box.innerHTML = '<div class="text-danger">Could not load your meeting requests.</div>'; return; }
                var cc = (res.data && res.data.completed_count) || 0, cb = $('mrqCompletedCount');
                cb.textContent = cc; cb.classList.toggle('mrq-hidden', !cc);
                var sig = JSON.stringify(list);
                if (silent && (sig === sentSig || tab !== 'sent' || box.querySelector('[data-resched]:not(.mrq-hidden)'))) return; // nothing changed (or a reschedule form is open): leave the list alone
                sentSig = sig;
                if (!list.length) { box.innerHTML = done
                    ? '<div class="text-muted py-3 text-center">No completed meetings yet.</div>'
                    : '<div class="text-muted py-3 text-center">You have no upcoming or live meetings. Use the <strong>Request a meeting</strong> tab to create one.</div>'; return; }
                box.innerHTML = '';
                list.forEach(function (m) {
                    var div = document.createElement('div'); div.className = 'mrq-req';
                    var counts = { accepted: 0, declined: 0, pending: 0, completed: 0 };
                    m.invitees.forEach(function (i) { if (i.status === 'declined') counts.declined++; else if (i.status === 'pending') counts.pending++; else if (i.status === 'completed') counts.completed++; else counts.accepted++; });
                    var chips = m.invitees.map(function (i) {
                        var st = STATUS[i.status] || STATUS.pending;
                        return '<span class="badge ' + st[1] + ' me-1 mb-1" style="font-size:.78rem">' + esc(i.name) + ' &middot; ' + st[0] + '</span>';
                    }).join('');
                    var join = done ? '' : m.meet_url
                        ? '<a class="btn btn-sm mrq-gmeet" target="_blank" rel="noopener noreferrer" href="' + esc(m.meet_url) + '"><i class="ri-vidicon-fill"></i> Join Google Meet</a> '
                        : (m.google
                            ? '<span class="badge bg-warning text-dark">Google Meet link ' + (m.google_status === 'failed' ? 'not created' : 'pending') + '</span> '
                            : '<a class="btn btn-sm btn-success" href="' + esc(m.room_url) + '"><i class="ri-vidicon-line"></i> Open meeting room</a> ');
                    div.innerHTML = '<h6>' + esc(m.title) + ' ' + prioBadge(m.priority) + (m.live ? ' <span class="badge bg-success">Live now</span>' : '') + (done ? ' <span class="badge bg-primary"><i class="ri-check-double-line"></i> Completed</span>' : '') + '</h6>'
                        + '<div class="small text-muted mb-1">' + esc(m.scheduled_at || (m.live ? 'happening now' : 'No time set')) + (m.room ? ' &middot; <span class="mrq-room">' + esc(m.room) + '</span>' : '') + '</div>'
                        + (m.agenda ? '<div class="small mb-2" style="white-space:pre-line"><span class="text-muted">Agenda:</span> ' + esc(m.agenda) + '</div>' : '')
                        + '<div class="small mb-1"><strong>' + counts.accepted + '</strong> accepted &middot; <strong>' + counts.completed + '</strong> completed &middot; <strong>' + counts.declined + '</strong> declined &middot; <strong>' + counts.pending + '</strong> waiting</div>'
                        + '<div class="mb-2">' + (chips || '<span class="text-muted small">No members invited.</span>') + '</div>'
                        + join + '<a class="btn btn-sm btn-light" href="' + esc(m.details_url || m.room_url) + '">Details</a>'
                        + (done ? '' : ' <button type="button" class="btn btn-sm btn-primary" data-complete title="Mark this meeting as completed"><i class="ri-check-double-line"></i> Completed</button>')
                        + (m.reschedule_url ? ' <button type="button" class="btn btn-sm btn-outline-warning" data-reschedule title="Move this meeting to another date and time"><i class="ri-calendar-schedule-line"></i> Reschedule</button>' : '')
                        + '<div class="mt-2 mrq-hidden" data-resched></div>'
                        + '<div class="small mt-2" data-result></div>';
                    var rbtn = div.querySelector('[data-reschedule]');
                    if (rbtn) rbtn.addEventListener('click', function () { toggleReschedule(div, m); });
                    var cbtn = div.querySelector('[data-complete]');
                    if (cbtn) cbtn.addEventListener('click', function () {
                        if (!confirm('Mark "' + m.title + '" as completed? It will move to the Completed tab.')) return;
                        var res = div.querySelector('[data-result]');
                        cbtn.disabled = true;
                        call('POST', m.end_url).then(function (r) {
                            if (!r.ok || !(r.data && r.data.ok)) {
                                cbtn.disabled = false;
                                res.innerHTML = '<span class="text-danger">' + esc((r.data && r.data.message) || 'Could not mark as completed.') + '</span>';
                                return;
                            }
                            res.innerHTML = '<span class="text-primary fw-semibold"><i class="ri-check-double-line"></i> Marked as completed.</span>';
                            setTimeout(function () { sentSig = ''; loadSent(); }, 800);
                        }).catch(function () {
                            cbtn.disabled = false;
                            res.innerHTML = '<span class="text-danger">Network problem. Please try again.</span>';
                        });
                    });
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
                var modalOpen = $('mrqModal').classList.contains('show');
                if (showToast !== false && modalOpen && tab === 'inbox') {
                    // Inbox is on screen: update it in place (no toast, no reload).
                    rememberSeen(seenIds().concat(list.map(function (x) { return x.id; })));
                    refreshInboxLive(list);
                } else if (showToast !== false && modalOpen && tab === 'sent') {
                    loadSent(true);
                }
                if (showToast !== false && !(modalOpen && tab === 'inbox')) {
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

        var inboxSig = '';
        function renderInbox(list) {
            inboxSig = list.map(function (x) { return x.id + ':' + x.rsvp + ':' + x.scheduled_at; }).join('|');
            if (!list.length) {
                $('mrqInboxList').innerHTML = '<div class="text-muted py-3 text-center">No pending meeting requests.</div>';
                return;
            }
            $('mrqInboxList').innerHTML = '';
            list.forEach(function (r) { $('mrqInboxList').appendChild(requestCard(r)); });
        }
        // Background refresh: redraw only when something changed and no card is mid-action (so buttons never vanish under the cursor).
        function refreshInboxLive(list) {
            var sig = list.map(function (x) { return x.id + ':' + x.rsvp + ':' + x.scheduled_at; }).join('|');
            if (sig === inboxSig) return;
            var busy = Array.prototype.some.call($('mrqInboxList').querySelectorAll('[data-result]'), function (el) { return el.textContent.trim() !== ''; })
                || Array.prototype.some.call($('mrqInboxList').querySelectorAll('[data-actions] button'), function (b) { return b.disabled; });
            if (!busy) renderInbox(list);
        }
        function loadInbox() {
            $('mrqInboxList').innerHTML = '<div class="text-muted">Loading…</div>';
            poll(false).then(function (list) {
                list = list || [];
                rememberSeen(seenIds().concat(list.map(function (x) { return x.id; })));
                renderInbox(list);
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
            function btn(label, cls, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm mrq-ico ' + cls;
                // icon only; the text becomes the tooltip / screen-reader label
                var tip = label.replace(/<[^>]*>/g, '').trim(), ic = label.match(/<i[^>]*><\/i>/);
                b.innerHTML = ic ? ic[0] : label; b.title = tip; b.setAttribute('aria-label', tip);
                b.addEventListener('click', fn); actions.appendChild(b); return b; }
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
                j.className += ' mrq-ico'; j.innerHTML = '<i class="ri-vidicon-fill"></i>'; j.title = 'Join Google Meet'; j.setAttribute('aria-label', 'Join Google Meet'); actions.appendChild(j);
            } else {
                btn('<i class="ri-vidicon-line"></i> ' + (r.google ? 'Meeting details' : 'Join meeting'), 'btn-primary', function () { window.location.href = r.room_url; });
            }
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
            stampNow();
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
            pollTimer = setInterval(function () { if (!document.hidden) poll(true); }, 10000);
        }
        document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(true); });
        if (document.readyState === 'complete') startPolling(); else window.addEventListener('load', startPolling);
    })();
</script>
@endunless
@endauth
