{{--
  Next-gen look for the shared "Dil vs PRMT" modal.
  Used by BOTH the channel pages (partials/channel-pef-promo → #chPromoDilVsPrmtModal)
  and the Promotions page (#promo-prmt-modal). Styling only, scoped to `.dpm`:
  no ids / classes the page scripts rely on are touched.
  Include once per page (guarded so it never prints twice).
--}}
@once
<style>
    .dpm .modal-content {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        background: linear-gradient(180deg, #f3fdf8 0%, #ffffff 55%);
        box-shadow: 0 24px 70px rgba(6, 78, 59, .32);
    }

    /* ---- Hero header ---- */
    .dpm .modal-header {
        position: relative;
        align-items: center;
        gap: 12px;
        padding: 16px 22px !important;
        border: 0;
        color: #fff;
        background: linear-gradient(120deg, #047857, #10b981, #06b6d4, #047857);
        background-size: 300% 300%;
        animation: dpmFlow 10s ease infinite;
    }
    .dpm .modal-header::after {
        content: "";
        position: absolute;
        inset: 0;
        pointer-events: none;
        background:
            radial-gradient(circle at 10% 20%, rgba(255, 255, 255, .24), transparent 38%),
            radial-gradient(circle at 90% 90%, rgba(255, 255, 255, .16), transparent 40%);
    }
    .dpm .modal-header > * { position: relative; z-index: 1; }
    @keyframes dpmFlow {
        0%   { background-position: 0% 50%; }
        50%  { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    .dpm .modal-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
        font-size: 1.15rem !important;
        font-weight: 800;
        letter-spacing: .01em;
        color: #fff;
        text-shadow: 0 2px 8px rgba(0, 0, 0, .22);
    }
    .dpm .modal-title > i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        margin: 0 !important;
        border-radius: 12px;
        font-size: 1.05rem;
        color: #047857;
        background: #fff;
        text-shadow: none;
        box-shadow: 0 6px 16px rgba(0, 0, 0, .22);
    }
    .dpm .modal-title .badge {
        margin: 0 !important;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 800;
        color: #065f46 !important;
        background: #fff !important;
        text-shadow: none;
        box-shadow: 0 3px 10px rgba(0, 0, 0, .18);
    }
    .dpm .modal-header .btn-close {
        filter: invert(1) grayscale(1) brightness(2);
        opacity: .9;
    }

    /* ---- Body ---- */
    .dpm .modal-body { padding: 18px 22px 12px !important; }
    .dpm .modal-body > p.small {
        margin-bottom: 14px !important;
        padding: 10px 14px;
        border-radius: 14px;
        font-size: .78rem;
        line-height: 1.5;
        color: #065f46 !important;
        background: #d1fae5;
        border: 1px solid #a7f3d0;
    }
    .dpm .table-responsive { overflow-x: auto; padding: 0 2px; }

    /* ---- Slab table → one card per slab ---- */
    .dpm table {
        width: 100%;
        border-collapse: separate !important;
        border-spacing: 0 8px;
        --bs-table-bg: transparent;
    }
    .dpm table > :not(caption) > * > * {
        border: 0 !important;
        box-shadow: none;
        background-color: transparent;
    }
    .dpm thead th {
        padding: 4px 8px !important;
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: #047857;
    }
    .dpm tbody tr {
        background: #fff;
        box-shadow: 0 2px 10px rgba(6, 78, 59, .09);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .dpm tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(6, 78, 59, .17);
    }
    .dpm tbody td {
        padding: 10px 8px !important;
        vertical-align: middle;
        background: #fff !important;
    }
    .dpm tbody td:first-child {
        border-radius: 14px 0 0 14px;
        border-left: 6px solid #10b981 !important;
    }
    .dpm tbody td:last-child { border-radius: 0 14px 14px 0; }
    .dpm tbody tr.ch-promo-dil-prmt-custom-row td:first-child,
    .dpm tbody tr.promo-prmt-custom td:first-child { border-left-color: #8b5cf6 !important; }

    .dpm tbody td .text-muted.small {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px !important;
        height: 24px;
        border-radius: 50%;
        font-weight: 800;
        color: #047857 !important;
        background: #d1fae5;
    }

    /* Dil% range inputs */
    .dpm input[data-bound] {
        width: auto !important;
        min-width: 0;
        flex: 1 1 64px;
        border: 2px solid #d1fae5;
        border-radius: 10px;
        background: #fff !important;
        color: #064e3b !important;
        font-weight: 700;
        text-align: center;
        transition: border-color .15s, box-shadow .15s;
    }
    .dpm input[data-bound]:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, .22);
    }

    /* PRMT % input = green pill */
    .dpm .ch-promo-dil-prmt-input,
    .dpm .promo-prmt-input {
        width: 92px !important;
        margin-left: auto;
        border: 0 !important;
        border-radius: 999px !important;
        background: linear-gradient(135deg, #059669, #10b981 60%, #14b8a6) !important;
        color: #fff !important;
        font-weight: 800;
        font-size: 1rem;
        text-align: center;
        box-shadow: 0 4px 12px rgba(5, 150, 105, .38);
        transition: transform .15s, box-shadow .15s;
    }
    .dpm .ch-promo-dil-prmt-input:focus,
    .dpm .promo-prmt-input:focus {
        transform: scale(1.06);
        box-shadow: 0 0 0 4px rgba(16, 185, 129, .3), 0 4px 12px rgba(5, 150, 105, .38);
    }

    /* SKU count badge */
    .dpm .ch-promo-dil-prmt-count {
        display: inline-block;
        min-width: 44px;
        padding: 5px 12px;
        border-radius: 999px;
        font-weight: 800;
        font-size: .85rem;
        color: #fff !important;
        background: linear-gradient(135deg, #0ea5e9, #6366f1) !important;
        box-shadow: 0 3px 10px rgba(99, 102, 241, .35);
    }

    /* Delete row */
    .dpm .ch-promo-dil-prmt-remove-row,
    .dpm .promo-prmt-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        font-size: .75rem;
        color: #dc2626;
        background: #fee2e2;
        transition: transform .15s, background .15s, color .15s;
    }
    .dpm .ch-promo-dil-prmt-remove-row:hover,
    .dpm .promo-prmt-remove:hover {
        color: #fff;
        background: #dc2626;
        transform: rotate(90deg);
    }

    /* ---- Toolbar under the table ---- */
    #ch-promo-dil-prmt-add-row-btn,
    #promo-prmt-add-row {
        padding: 7px 18px;
        border: 0;
        border-radius: 999px;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, #06b6d4, #10b981, #84cc16);
        box-shadow: 0 4px 14px rgba(16, 185, 129, .4);
        transition: transform .15s;
    }
    #ch-promo-dil-prmt-add-row-btn:hover,
    #promo-prmt-add-row:hover { color: #fff; transform: translateY(-2px) scale(1.03); }

    #ch-promo-dil-prmt-restore-btn,
    #promo-prmt-restore {
        border-radius: 999px;
        font-weight: 700;
        border-width: 2px;
    }
    #ch-promo-dil-prmt-row-count,
    #promo-prmt-row-count {
        padding: 6px 12px;
        border-radius: 999px;
        font-weight: 800;
        background: #064e3b !important;
    }
    #ch-promo-dil-prmt-count-total {
        font-weight: 700;
        color: #047857 !important;
    }

    /* Status line with pulse dot */
    #ch-promo-dil-prmt-status,
    #promo-prmt-status {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px !important;
        font-size: .8rem;
        font-weight: 600;
        color: #047857 !important;
    }
    #ch-promo-dil-prmt-status::before,
    #promo-prmt-status::before {
        content: "";
        flex: 0 0 auto;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #10b981;
        animation: dpmPulse 1.8s infinite;
    }
    @keyframes dpmPulse {
        0%   { box-shadow: 0 0 0 0 rgba(16, 185, 129, .55); }
        70%  { box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    /* ---- Footer ---- */
    .dpm .modal-footer {
        padding: 12px 22px 18px !important;
        border: 0;
        background: transparent;
    }
    #ch-promo-dil-prmt-apply-btn,
    #promo-prmt-save {
        padding: 9px 26px;
        border: 0;
        border-radius: 999px;
        font-weight: 800;
        color: #fff;
        background: linear-gradient(135deg, #059669, #10b981, #06b6d4);
        box-shadow: 0 6px 18px rgba(5, 150, 105, .42);
        transition: transform .15s, box-shadow .15s;
    }
    #ch-promo-dil-prmt-apply-btn:hover,
    #promo-prmt-save:hover {
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(5, 150, 105, .5);
    }
    #ch-promo-dil-prmt-apply-btn:disabled,
    #promo-prmt-save:disabled { opacity: .65; transform: none; }

    @media (max-width: 575.98px) {
        .dpm .modal-body { padding: 14px 12px 8px !important; }
        .dpm .ch-promo-dil-prmt-input,
        .dpm .promo-prmt-input { width: 76px !important; }
    }
</style>
@endonce
