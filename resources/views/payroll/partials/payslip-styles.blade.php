<style>
    :root {
        --ps-red: #c41e24;
        --ps-red-dark: #9a1519;
        --ps-ink: #1a1a1a;
        --ps-muted: #5c5c5c;
        --ps-border: #d8dce3;
        --ps-bg: #f4f6f9;
    }

    body.payslip-print-body {
        margin: 0;
        padding: 16px;
        background: var(--ps-bg);
    }

    .payslip-toolbar {
        max-width: 820px;
        margin: 0 auto 1rem;
    }

    .payslip-page {
        position: relative;
        max-width: 820px;
        margin: 0 auto 2rem;
        background: #fff;
        box-shadow: 0 4px 32px rgba(0,0,0,.12);
        border: 1px solid var(--ps-border);
        overflow: visible;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        color: var(--ps-ink);
    }

    .payslip-watermark {
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .payslip-watermark img {
        width: 72%;
        max-width: 520px;
        opacity: 0.07;
        transform: rotate(-18deg);
        filter: grayscale(20%);
        user-select: none;
    }

    .payslip-inner {
        position: relative;
        z-index: 1;
        padding: 28px 36px 24px;
    }

    .ps-header {
        display: grid;
        grid-template-columns: 140px 1fr auto;
        gap: 16px;
        align-items: center;
        padding-bottom: 16px;
        border-bottom: 3px solid var(--ps-ink);
    }
    .ps-header::after {
        content: '';
        grid-column: 1 / -1;
        height: 1px;
        background: var(--ps-border);
        margin-top: 4px;
    }
    .ps-logo img {
        max-height: 52px;
        width: auto;
        display: block;
    }
    .ps-company-name {
        font-size: 1.35rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        color: var(--ps-ink);
        margin: 0;
        line-height: 1.2;
    }
    .ps-company-tag {
        font-size: 0.7rem;
        color: var(--ps-muted);
        letter-spacing: 0.12em;
        text-transform: uppercase;
        margin-top: 2px;
    }
    .ps-doc-badge { text-align: right; }
    .ps-doc-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--ps-red);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin: 0;
    }
    .ps-doc-meta {
        font-size: 0.72rem;
        color: var(--ps-muted);
        margin-top: 4px;
        line-height: 1.5;
    }

    .ps-confidential {
        margin: 14px 0 18px;
        padding: 6px 12px;
        background: linear-gradient(90deg, rgba(196,30,36,.08), transparent);
        border-left: 4px solid var(--ps-red);
        font-size: 0.68rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--ps-red-dark);
    }

    .ps-employee-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px 24px;
        margin-bottom: 22px;
        padding: 16px 18px;
        background: var(--ps-bg);
        border-radius: 8px;
        border: 1px solid var(--ps-border);
    }
    .ps-field label {
        display: block;
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--ps-muted);
        margin-bottom: 2px;
        font-weight: 600;
    }
    .ps-field span {
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--ps-ink);
    }

    .ps-tables {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    .ps-table-wrap h6 {
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #fff;
        background: var(--ps-red);
        margin: 0;
        padding: 8px 12px;
        border-radius: 6px 6px 0 0;
    }
    .ps-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8rem;
        border: 1px solid var(--ps-border);
        border-top: none;
        border-radius: 0 0 6px 6px;
    }
    .ps-table td {
        padding: 8px 12px;
        border-bottom: 1px solid #eef0f4;
    }
    .ps-table tr:last-child td { border-bottom: none; }
    .ps-table td:last-child {
        text-align: right;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .ps-table tr.ps-total td {
        background: #fafbfc;
        font-weight: 700;
        border-top: 2px solid var(--ps-border);
    }
    .ps-table .text-deduct { color: var(--ps-red-dark); }

    .ps-net-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 22px;
        margin-bottom: 20px;
        background: linear-gradient(135deg, var(--ps-red) 0%, var(--ps-red-dark) 100%);
        border-radius: 10px;
        color: #fff;
        box-shadow: 0 6px 20px rgba(196,30,36,.25);
    }
    .ps-net-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        opacity: .9;
    }
    .ps-net-title {
        font-size: 1rem;
        font-weight: 700;
        margin: 4px 0 0;
    }
    .ps-net-amount {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }
    .ps-net-words {
        font-size: 0.72rem;
        opacity: .85;
        margin-top: 4px;
        max-width: 280px;
    }

    .ps-payment {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 22px;
    }
    .ps-pay-card {
        padding: 10px 12px;
        border: 1px dashed var(--ps-border);
        border-radius: 8px;
        font-size: 0.75rem;
    }
    .ps-pay-card strong {
        display: block;
        font-size: 0.62rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--ps-muted);
        margin-bottom: 4px;
    }
    .ps-pay-card .filled { color: var(--ps-ink); font-weight: 600; }
    .ps-pay-card .empty { color: #aaa; }

    .ps-footer {
        padding-top: 14px;
        border-top: 2px solid var(--ps-ink);
    }
    .ps-footer-line {
        height: 1px;
        background: var(--ps-border);
        margin-bottom: 12px;
    }
    .ps-footer-grid {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 0.68rem;
        color: var(--ps-muted);
    }
    .ps-footer-contact i { color: var(--ps-red); margin-right: 4px; }
    .ps-sign {
        text-align: right;
        margin-top: 28px;
        padding-top: 8px;
        border-top: 1px solid var(--ps-border);
    }
    .ps-sign-line {
        width: 180px;
        margin-left: auto;
        border-top: 1px solid var(--ps-ink);
        padding-top: 6px;
        font-size: 0.7rem;
        color: var(--ps-muted);
    }

    /* Keep payslip visible inside app shell (mobile / PWA) */
    @media screen {
        .content-page,
        .content-page .content,
        .content-page .container-fluid {
            overflow: visible !important;
            height: auto !important;
            min-height: 0 !important;
        }
        .payslip-page,
        .payslip-inner {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 10mm 12mm;
        }

        html, body {
            width: 100%;
            height: auto;
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body.payslip-print-body {
            padding: 0 !important;
        }

        .wrapper,
        .content-page,
        .content,
        .container-fluid,
        #layout-wrapper {
            display: block !important;
            visibility: visible !important;
            overflow: visible !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            min-height: 0 !important;
            height: auto !important;
        }

        .payslip-toolbar,
        .leftside-menu,
        .navbar-custom,
        .navbar,
        .footer,
        .page-title-box,
        .right-bar,
        .app-menu,
        .mobile-header,
        .mobile-bottom-nav,
        .mobile-splash,
        .floating-task-btn,
        #floating-task-form,
        #mobile-sidebar-overlay {
            display: none !important;
        }

        .payslip-page {
            position: static !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            box-shadow: none !important;
            border: none !important;
            overflow: visible !important;
            page-break-inside: auto;
            break-inside: auto;
        }

        .payslip-inner {
            padding: 0 !important;
        }

        .ps-net-box,
        .ps-table-wrap h6 {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .ps-net-box {
            box-shadow: none !important;
        }

        .payslip-watermark img {
            opacity: 0.04 !important;
        }

        .ps-sign {
            margin-top: 16px;
        }

        a[href]:after {
            content: none !important;
        }
    }

    @media (max-width: 640px) {
        .ps-header { grid-template-columns: 1fr; text-align: center; }
        .ps-doc-badge { text-align: center; }
        .ps-employee-grid, .ps-tables, .ps-payment { grid-template-columns: 1fr; }
        .payslip-inner { padding: 20px 16px; }
    }
</style>
