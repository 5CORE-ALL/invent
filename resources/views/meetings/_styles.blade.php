{{-- Shared look for the Meeting Room pages (matches the existing meeting cards). --}}
<style>
    .mt-card { background: #fff; border: 1px solid #e5e9f0; border-radius: 10px; padding: 14px 16px; box-shadow: 0 1px 3px rgba(15,23,42,.05); height: 100%; }
    .mt-card h6 { margin: 0 0 4px; font-weight: 700; color: #0f172a; }
    .mt-meta { color: #64748b; font-size: .82rem; }
    .mt-section-title { font-weight: 700; color: #0f172a; margin: 22px 0 10px; }
    .mt-rec-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: 10px 14px; font-size: .9rem; }
    .mt-rec-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #dc2626; margin-right: 6px; animation: mtPulse 1.4s infinite; }
    @keyframes mtPulse { 0%,100% { opacity: 1 } 50% { opacity: .25 } }

    /* priority + status */
    .mt-prio { display: inline-flex; align-items: center; gap: 5px; border-radius: 12px; padding: 2px 10px 2px 8px; font-size: .76rem; font-weight: 700; border: 1px solid transparent; white-space: nowrap; }
    .mt-prio i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
    .mt-prio.urgent { background: #fee2e2; color: #991b1b; border-color: #fecaca; } .mt-prio.urgent i { background: #dc2626; }
    .mt-prio.immediate { background: #ffedd5; color: #9a3412; border-color: #fed7aa; } .mt-prio.immediate i { background: #f97316; }
    .mt-prio.normal { background: #dcfce7; color: #166534; border-color: #bbf7d0; } .mt-prio.normal i { background: #16a34a; }
    .mt-card.prio-urgent { border-left: 4px solid #dc2626; } .mt-card.prio-immediate { border-left: 4px solid #f97316; } .mt-card.prio-normal { border-left: 4px solid #16a34a; }
    .mt-status { font-size: .74rem; font-weight: 700; border-radius: 10px; padding: 2px 9px; white-space: nowrap; }
    .mt-status.scheduled { background: #e0f2fe; color: #075985; } .mt-status.live { background: #dcfce7; color: #166534; }
    .mt-status.ended { background: #e2e8f0; color: #475569; } .mt-status.cancelled { background: #fee2e2; color: #991b1b; text-decoration: line-through; }
    .mt-room-pill { display: inline-flex; align-items: center; gap: 4px; background: #eef2ff; color: #3730a3; border-radius: 8px; padding: 1px 8px; font-size: .76rem; font-weight: 600; white-space: nowrap; }
    .mt-join { background: #1a73e8; border-color: #1a73e8; color: #fff; font-weight: 600; }
    .mt-join:hover { background: #1557b0; border-color: #1557b0; color: #fff; }

    /* avatars (photo, or initials when there is no photo / it fails to load) */
    .mt-av { position: relative; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden; color: #fff; font-weight: 700; flex-shrink: 0; vertical-align: middle; border: 2px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; line-height: 1; }
    .mt-av img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .mt-av-stack { display: inline-flex; } .mt-av-stack .mt-av + .mt-av { margin-left: -9px; }
    .mt-person { display: inline-flex; align-items: center; gap: 7px; }
    .mt-person small { color: #94a3b8; display: block; line-height: 1.1; }

    /* booking form */
    .mt-room-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
    @media (max-width: 575.98px) { .mt-room-grid { grid-template-columns: repeat(2, 1fr); } }
    .mt-room-opt input, .mt-prio-opt input { position: absolute; opacity: 0; pointer-events: none; }
    .mt-room-opt { position: relative; margin: 0; }
    .mt-room-opt span { display: block; text-align: center; border: 2px solid #e2e8f0; border-radius: 10px; padding: 10px 6px; font-weight: 600; cursor: pointer; color: #334155; background: #fff; }
    .mt-room-opt span small { display: block; font-weight: 400; color: #94a3b8; font-size: .7rem; }
    .mt-room-opt input:checked + span { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; }
    .mt-room-opt input:focus-visible + span, .mt-prio-opt input:focus-visible + span { outline: 2px solid #2563eb; outline-offset: 2px; }
    .mt-prio-opt { position: relative; margin: 0; }
    .mt-prio-opt span { display: inline-flex; align-items: center; gap: 6px; border: 2px solid #e2e8f0; border-radius: 20px; padding: 6px 14px; cursor: pointer; font-weight: 600; background: #fff; }
    .mt-prio-opt span i { width: 11px; height: 11px; border-radius: 50%; }
    .mt-prio-opt.urgent span i { background: #dc2626; } .mt-prio-opt.immediate span i { background: #f97316; } .mt-prio-opt.normal span i { background: #16a34a; }
    .mt-prio-opt.urgent input:checked + span { border-color: #dc2626; background: #fef2f2; } .mt-prio-opt.immediate input:checked + span { border-color: #f97316; background: #fff7ed; } .mt-prio-opt.normal input:checked + span { border-color: #16a34a; background: #f0fdf4; }
    .mt-invitee-list { max-height: 240px; overflow-y: auto; border: 1px solid #e5e9f0; border-radius: 8px; padding: 4px 10px; }
    .mt-invitee-list label { display: flex; gap: 10px; align-items: center; padding: 4px 0; margin: 0; cursor: pointer; font-weight: 400; }
    .mt-invitee-list small { color: #94a3b8; }
    .mt-avail { font-size: .82rem; }

    /* President Summary */
    .ps-card { background: #fff; border: 1px solid #e5e9f0; border-radius: 12px; padding: 22px 26px; margin-bottom: 22px; box-shadow: 0 2px 8px rgba(15,23,42,.06); width: 100%; }
    .ps-card.prio-urgent { border-left: 6px solid #dc2626; } .ps-card.prio-immediate { border-left: 6px solid #f97316; } .ps-card.prio-normal { border-left: 6px solid #16a34a; }
    .ps-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
    .ps-title { margin: 0; font-weight: 800; color: #0f172a; word-break: break-word; }
    .ps-sub { color: #64748b; margin-top: 2px; }
    .ps-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 12px; margin-bottom: 8px; }
    .ps-box { background: #f8fafc; border: 1px solid #e5e9f0; border-radius: 10px; padding: 12px 14px; min-width: 0; }
    .ps-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; font-weight: 700; margin-bottom: 6px; }
    .ps-big { font-size: 1.15rem; font-weight: 700; color: #0f172a; }
    .ps-section { margin-top: 18px; }
    .ps-text { white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere; line-height: 1.6; color: #1e293b; }
    @media print { .ps-card { box-shadow: none; page-break-inside: avoid; } }
</style>
