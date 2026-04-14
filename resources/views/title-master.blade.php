@extends('layouts.vertical', ['title' => 'Title Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

    <style>
        .card.title-master-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(44, 110, 213, 0.06);
        }
        .card.title-master-card .card-body {
            padding: 1.25rem 1.5rem;
        }
        .title-master-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        .title-master-toolbar .btn {
            padding: 0.3rem 0.6rem;
            font-size: 0.8rem;
            border-radius: 6px;
        }
        .title-master-toolbar .btn i {
            font-size: 0.75rem;
        }
        .table-responsive {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            background-color: white;
        }

        #title-master-table thead th {
            vertical-align: middle !important;
        }
        #title-master-table thead th.title-master-action-th {
            text-align: center;
            width: 44px;
            max-width: 52px;
            padding-left: 6px;
            padding-right: 6px;
        }
        #title-master-table thead th.title-master-action-th .fa-eye {
            font-size: 15px;
            color: #fff;
            line-height: 1;
            vertical-align: middle;
        }
        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 6px 8px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 10px;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 4px 6px;
            margin-top: 4px;
            font-size: 10px;
            width: 100%;
            transition: all 0.2s;
        }

        .table-responsive thead select {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 2px 4px;
            margin-top: 4px;
            font-size: 9px;
            width: 100%;
            transition: all 0.2s;
        }

        .table-responsive thead input:focus {
            background-color: white;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        #title-master-table tbody tr {
            align-items: center;
        }
        #title-master-table tbody td {
            padding: 8px 12px;
            vertical-align: middle !important;
            border-bottom: 1px solid #edf2f9;
            font-size: 12px;
            line-height: 1.35;
            color: #475569;
        }
        /* Widen SKU column (4th column) */
        #title-master-table thead th:nth-child(4),
        #title-master-table tbody td:nth-child(4) {
            min-width: 180px;
            width: 180px;
            white-space: nowrap;
        }
        /* Pricing Master CVR metrics (same snapshot as /pricing-master-cvr) */
        #title-master-table thead th.title-master-pmcvr-th {
            background: #5dbeb6 !important;
            color: #111 !important;
            width: 38px;
            min-width: 38px;
            max-width: 44px;
            text-align: center;
            vertical-align: middle !important;
            padding: 6px 2px !important;
            white-space: normal;
        }
        #title-master-table thead th.title-master-pmcvr-th .title-master-pmcvr-th-inner {
            display: inline-block;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            font-weight: 700;
            font-size: 9px;
            letter-spacing: 0.04em;
            line-height: 1.1;
        }
        #title-master-table tbody td.title-master-pmcvr-td {
            text-align: center;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 600;
        }
        #title-master-table thead th.title-master-pmcvr-th.title-master-sortable {
            cursor: pointer;
        }
        #title-master-table thead th.title-master-pmcvr-th.title-master-sort-disabled {
            cursor: not-allowed;
            opacity: 0.72;
            pointer-events: none;
        }
        #title-master-table thead th.title-master-pmcvr-th .title-master-pmcvr-th-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }
        #title-master-table thead th.title-master-pmcvr-th .title-master-sort-icon {
            font-size: 9px;
            line-height: 1;
            opacity: 0.45;
        }
        #title-master-table thead th.title-master-pmcvr-th.title-master-sort-active .title-master-sort-icon {
            opacity: 1;
            color: #0d47a1;
        }
        #title-master-table thead th.title-master-ai-th {
            background: #5dbeb6 !important;
            color: #111 !important;
            width: 44px;
            min-width: 44px;
            max-width: 52px;
            text-align: center;
            vertical-align: middle !important;
            padding: 6px 2px !important;
            font-size: 9px;
            font-weight: 700;
        }
        #title-master-table tbody td.title-master-ai-td {
            text-align: center;
            vertical-align: middle !important;
            padding: 4px 2px !important;
        }
        .title-master-ai-stack-btn {
            border: none;
            background: transparent;
            padding: 4px 6px;
            cursor: pointer;
            border-radius: 8px;
            line-height: 1;
            color: #4361ee;
        }
        .title-master-ai-stack-btn:hover {
            background: #eef2ff;
            color: #312e81;
        }
        .title-master-ai-stack-btn i {
            font-size: 15px;
        }
        #tmAiStackModal .tm-ai-ref-area {
            font-size: 0.9rem;
            background: #fff;
            min-height: 2.25rem;
        }
        #tmAiStackModal .tm-ai-prompt-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }
        #tmAiStackModal .tm-ai-prompt-toolbar .tm-ai-prompt-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn {
            width: 2.5rem;
            height: 2.5rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 0.375rem;
            box-sizing: border-box;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn i {
            font-size: 1.05rem;
            line-height: 1;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn .tm-ai-stack-generate-spinner {
            width: 1.05rem;
            height: 1.05rem;
            border-width: 0.14em;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn-eye {
            color: #495057;
            background: #fff;
            border: 2px solid #6c757d;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn-eye:hover {
            background: #f8f9fa;
            color: #212529;
            border-color: #495057;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn-wand {
            color: #fff;
            background: #0d6efd;
            border: 2px solid #0d6efd;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn-wand:hover:not(:disabled) {
            background: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        #tmAiStackModal .tm-ai-prompt-icon-btn-wand:disabled {
            opacity: 0.65;
        }
        #tmAiStackPromptEditorModal {
            z-index: 1060;
        }
        #tmAiStackModal .tm-ai-stack-draft-row textarea {
            min-height: 56px;
        }
        #tmAiStackModal .tm-ai-stack-apply-btn {
            min-width: 44px;
        }
        /* Tall modals: stay within viewport; scroll inside body (no whole-page scroll) */
        #titleModal .modal-dialog,
        #viewTitleModal .modal-dialog,
        #tmAiStackModal .modal-dialog,
        #tmAiStackPromptEditorModal .modal-dialog {
            height: auto;
            max-height: calc(100vh - 1.25rem);
            margin: 0.625rem auto;
        }
        #titleModal .modal-content,
        #viewTitleModal .modal-content,
        #tmAiStackModal .modal-content,
        #tmAiStackPromptEditorModal .modal-content {
            max-height: calc(100vh - 1.25rem);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        #titleModal .modal-header,
        #titleModal .modal-footer,
        #viewTitleModal .modal-header,
        #viewTitleModal .modal-footer,
        #tmAiStackModal .modal-header,
        #tmAiStackModal .modal-footer,
        #tmAiStackPromptEditorModal .modal-header,
        #tmAiStackPromptEditorModal .modal-footer {
            flex-shrink: 0;
        }
        #titleModal .modal-body,
        #viewTitleModal .modal-body,
        #tmAiStackModal .modal-body,
        #tmAiStackPromptEditorModal .modal-body {
            overflow-y: auto;
            flex: 1 1 auto;
            min-height: 0;
            -webkit-overflow-scrolling: touch;
        }
        #title-master-table thead th.title-master-bs-th {
            background: #5dbeb6 !important;
            color: #111 !important;
            width: 42px;
            min-width: 42px;
            max-width: 48px;
            text-align: center;
            vertical-align: middle !important;
            padding: 6px 2px !important;
            font-size: 9px;
            font-weight: 700;
        }
        #title-master-table tbody td.title-master-bs-td {
            text-align: center;
            vertical-align: middle;
            font-size: 11px;
            line-height: 1.25;
            padding: 4px 2px !important;
        }
        #title-master-table tbody td.title-master-bs-td a {
            display: inline-block;
            font-weight: 600;
        }
        #title-master-table .table-img-cell {
            width: 48px;
            text-align: center;
            overflow: visible;
            position: relative;
        }
        #title-master-table tbody td.table-img-cell img {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            display: inline-block;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        #title-master-table tbody td.table-img-cell:hover img {
            transform: scale(1.2);
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.18);
            position: relative;
            z-index: 20;
        }
        .title-master-img-hover-preview {
            position: fixed;
            z-index: 10600;
            padding: 6px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 16px 48px rgba(15, 23, 42, 0.22);
            border: 1px solid #e2e8f0;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.12s ease, visibility 0.12s;
            box-sizing: border-box;
        }
        .title-master-img-hover-preview.is-visible {
            opacity: 1;
            visibility: visible;
        }
        .title-master-img-hover-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
            display: block;
        }

        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
        }

        .title-text {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #title-master-table thead th.title-master-title-dot-th {
            width: 1%;
            min-width: 48px;
            max-width: 64px;
            text-align: center;
            vertical-align: middle !important;
            padding: 6px 4px !important;
        }
        #title-master-table thead th.title-master-title-dot-th .form-control,
        #title-master-table thead th.title-master-title-dot-th select {
            font-size: 9px;
            padding: 2px 4px;
            min-width: 0;
            width: 100%;
            max-width: 100%;
        }
        #title-master-table tbody td.title-master-title-dot-td {
            width: 1%;
            text-align: center;
            vertical-align: middle !important;
            padding: 6px 4px !important;
            cursor: help;
        }
        .title-master-title-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            vertical-align: middle;
            box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.12);
        }
        .title-master-title-dot--has {
            background-color: #28a745;
        }
        .title-master-title-dot--empty {
            background-color: #dc3545;
        }

        .info-icon {
            cursor: help;
            opacity: 0.85;
            font-size: 12px;
        }

        /* Action column: View */
        .action-buttons-cell {
            white-space: nowrap;
            vertical-align: middle !important;
        }
        .action-buttons-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: nowrap;
        }
        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .marketplace-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            min-width: 120px;
        }

        /* Title Master: align status dots with buttons (same grid per row) */
        #title-master-table .marketplaces-150-cell,
        #title-master-table .marketplaces-100-cell,
        #title-master-table .marketplaces-80-cell {
            vertical-align: middle !important;
            padding: 6px 8px !important;
            min-width: 148px;
        }
        #title-master-table .marketplaces-dots-wrapper {
            width: 100%;
            margin-bottom: 3px;
            padding-bottom: 0;
            box-sizing: border-box;
        }
        #title-master-table .marketplaces-150-cell .marketplaces-dots,
        #title-master-table .marketplaces-100-cell .marketplaces-dots,
        #title-master-table .marketplaces-80-cell .marketplaces-dots {
            display: grid;
            grid-template-columns: repeat(3, minmax(32px, 1fr));
            column-gap: 12px;
            row-gap: 0;
            align-items: center;
            justify-items: center;
            width: 100%;
            min-height: 16px;
        }
        #title-master-table .marketplaces-150-cell .marketplace-buttons,
        #title-master-table .marketplaces-100-cell .marketplace-buttons,
        #title-master-table .marketplaces-80-cell .marketplace-buttons {
            display: grid;
            grid-template-columns: repeat(3, minmax(32px, 1fr));
            column-gap: 12px;
            row-gap: 8px;
            align-items: center;
            justify-items: center;
            justify-content: center;
            min-width: 0;
            flex-wrap: nowrap;
            width: 100%;
        }
        #title-master-table .marketplaces-150-cell .mp-dot,
        #title-master-table .marketplaces-100-cell .mp-dot,
        #title-master-table .marketplaces-80-cell .mp-dot {
            flex-shrink: 0;
        }
        /* PLS label is wider than icon-only buttons — keep column alignment */
        #title-master-table .marketplaces-100-cell .marketplace-btn.btn-shopify-pls {
            width: auto;
            min-width: 36px;
            padding: 0 6px;
        }
        @media (max-width: 768px) {
            #title-master-table .marketplaces-150-cell .marketplaces-dots,
            #title-master-table .marketplaces-100-cell .marketplaces-dots,
            #title-master-table .marketplaces-80-cell .marketplaces-dots,
            #title-master-table .marketplaces-150-cell .marketplace-buttons,
            #title-master-table .marketplaces-100-cell .marketplace-buttons,
            #title-master-table .marketplaces-80-cell .marketplace-buttons {
                column-gap: 8px;
            }
            #title-master-table .marketplaces-150-cell,
            #title-master-table .marketplaces-100-cell,
            #title-master-table .marketplaces-80-cell {
                min-width: 0;
            }
        }

        .marketplace-btn {
            width: 28px;
            height: 28px;
            border: none;
            border-radius: 4px;
            color: #fff;
            font-weight: 600;
            font-size: 11px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            padding: 0;
        }

        .marketplace-btn:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.18);
        }

        .marketplace-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-amazon { background-color: #146eb4; }
        .btn-temu { background-color: #28a745; }
        .btn-reverb { background-color: #ffc107; color: #333; }
        .btn-wayfair { background-color: #dc3545; }
        .btn-walmart { background-color: #dc3545; }
        .btn-shopify-main { background-color: #198754; }
        .btn-shopify-pls { background-color: #6f42c1; }
        .btn-doba { background-color: #fd7e14; }
        .btn-ebay1 { background-color: #0d6efd; }
        .btn-ebay2 { background-color: #198754; }
        .btn-ebay3 { background-color: #fd7e14; }
        .btn-macy { background-color: #0d6efd; }
        .btn-faire { background-color: #6f42c1; }
        .mp-dot.ebay1 { color: #0d6efd; }
        .mp-dot.ebay2 { color: #198754; }
        .mp-dot.ebay3 { color: #fd7e14; }
        .mp-dot.walmart { color: #0071ce; }
        .mp-dot.macy { color: #0d6efd; }
        .mp-dot.faire { color: #6f42c1; }

        /* Tooltips use Bootstrap (container: body, top) — see initMarketplaceTooltips() */
        #title-master-table .marketplaces-cell,
        #title-master-table .marketplaces-100-cell,
        #title-master-table .marketplaces-80-cell {
            overflow: visible;
        }
        .action-btn i {
            font-size: 11px;
        }
        .view-btn {
            background: #17a2b8;
            color: white;
            padding: 6px 8px;
            justify-content: center;
            min-width: 34px;
        }
        .view-btn i {
            font-size: 14px;
        }
        .view-btn:hover {
            background: #138496;
            color: white;
            box-shadow: 0 2px 6px rgba(23, 162, 184, 0.3);
        }
        .push-button-cell {
            vertical-align: middle !important;
            min-width: 52px;
        }
        .push-amazon-btn {
            width: 100%;
            min-width: 44px;
            min-height: 36px;
            background: #ff9900;
            color: #232f3e;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 600;
            border: none;
            border-radius: 999px;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .push-amazon-btn .tm-push-all-icon {
            width: 20px;
            height: 20px;
        }
        .push-amazon-btn:hover {
            background: #e88b00;
            color: white;
            box-shadow: 0 2px 6px rgba(255, 153, 0, 0.35);
        }
        .push-amazon-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        @media (max-width: 768px) {
            .action-buttons-group { flex-direction: column; gap: 4px; }
            .push-button-cell { min-width: 48px; }
        }
        /* Market column: dot indicators (default; Title Master overrides with grid) */
        .marketplaces-cell { white-space: nowrap; vertical-align: middle !important; }
        .marketplaces-dots { display: flex; align-items: center; justify-content: center; gap: 6px; }
        .mp-dot {
            width: 12px; height: 12px; border-radius: 50%;
            border: 2px solid currentColor;
            background: transparent;
            transition: all 0.2s;
        }
        .mp-dot.success { background: currentColor; border-color: currentColor; }
        .mp-dot.failed { background: #dc3545; border-color: #dc3545; }
        .mp-dot.pending { background: transparent; }
        .mp-dot.loading { background: transparent; border-color: transparent; }
        .mp-dot.amazon { color: #2c6ed5; }
        .mp-dot.temu { color: #28a745; }
        .mp-dot.reverb { color: #ffc107; }
        .mp-dot.wayfair { color: #dc3545; }
        .mp-dot.shopify_main { color: #95bf47; }
        .mp-dot.shopify_pls { color: #2b6cb0; }
        .mp-dot.doba { color: #6f42c1; }
        .mp-dot[title] { cursor: help; }
        .btn-push-all {
            background: #ff9900 !important;
            color: #232f3e !important;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-push-all:hover {
            background: #e88b00 !important;
            color: white !important;
        }
        .tm-push-all-icon {
            width: 16px;
            height: 16px;
            object-fit: contain;
            flex-shrink: 0;
            vertical-align: middle;
        }
        .push-all-th-inner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            line-height: 1.15;
            font-size: 10px;
            font-weight: 700;
        }
        .push-to-all-th .tm-push-all-icon {
            width: 20px;
            height: 20px;
        }
        #pushSelectedBtn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        #rainbow-loader {
            display: none;
            text-align: center;
            padding: 40px;
        }

        .rainbow-loader .loading-text {
            margin-top: 20px;
            font-weight: bold;
            color: #2c6ed5;
        }

        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            color: white;
        }

        .char-counter.warning {
            color: #b8860b;
            font-weight: 600;
        }
        .char-counter {
            font-size: 11px;
            color: #6c757d;
            float: right;
        }

        .char-counter.error {
            color: #dc3545;
        }
        .char-counter.success {
            color: #198754;
            font-weight: 600;
        }

        .platform-selector-modal .platform-item {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .platform-selector-modal .platform-item:hover {
            border-color: #2c6ed5;
            background-color: #f8f9fa;
        }

        .platform-selector-modal .platform-item.selected {
            border-color: #198754;
            background-color: #d1e7dd;
        }

        .platform-selector-modal .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }

        .platform-icon {
            font-size: 20px;
            margin-right: 10px;
        }

        .platform-badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-left: 10px;
        }

        .btn-ai-improve {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%) !important;
            color: #ffffff !important;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .btn-ai-improve:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(90, 103, 216, 0.5);
            background: linear-gradient(135deg, #4c51bf 0%, #553c9a 100%) !important;
            color: #ffffff !important;
        }
        .btn-ai-improve:disabled {
            opacity: 0.8;
            cursor: not-allowed;
            transform: none;
            color: #ffffff !important;
        }
        .btn-keep-title {
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            font-weight: 500;
        }
        .btn-keep-title:hover {
            background-color: #218838;
            color: white;
        }
        .btn-regen-titles {
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
        }
        .btn-regen-titles:hover {
            background-color: #5a6268;
            color: white;
        }
        .btn-cancel-ai {
            background: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
            border-radius: 6px;
            padding: 8px 16px;
        }
        .btn-cancel-ai:hover {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-color: #dc3545;
        }
        .ai-title-score {
            font-size: 13px;
        }
        .ai-title-score:not(:empty) {
            display: inline-block;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            border: 1px solid #f59e0b;
            box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
        }
        .ai-title100-score {
            font-size: 13px;
        }
        .ai-title100-score:not(:empty) {
            display: inline-block;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            border: 1px solid #f59e0b;
            box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Title Master',
        'sub_title' => 'Manage Product Titles',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card title-master-card">
                <div class="card-body">
                    <div class="mb-3 title-master-toolbar">
                            <button id="addTitleBtn" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Title
                            </button>
                            <button id="exportBtn" class="btn btn-primary">
                                <i class="fas fa-download"></i> Export
                            </button>
                            <button id="importBtn" class="btn btn-info">
                                <i class="fas fa-upload"></i> Import
                            </button>
                            <button id="pushAllBtn" class="btn btn-push-all">
                                <img src="{{ asset('images/title-master/distribute-all-icon.png') }}" alt="" class="tm-push-all-icon" width="16" height="16"> Distribute ALL to All Markets
                            </button>
                            <button id="pushSelectedBtn" class="btn btn-secondary" style="display:none;">
                                <img src="{{ asset('images/title-master/distribute-all-icon.png') }}" alt="" class="tm-push-all-icon" width="16" height="16"> Distribute Selected (<span id="pushSelectedCount">0</span>) to All Markets
                            </button>
                            <button id="updateAmazonBtn" class="btn btn-warning" style="display:none;">
                                <i class="fas fa-sync"></i> Update Titles (<span id="selectedCount">0</span> selected)
                            </button>
                            <input type="file" id="importFile" accept=".csv,.xlsx,.xls" style="display: none;">
                            <label class="small text-muted mb-0 ms-2 me-1">Per page</label>
                            <select id="perPageSelect" class="form-select form-select-sm" style="width:88px;display:inline-block;vertical-align:middle;">
                                <option value="50">50</option>
                                <option value="75" selected>75</option>
                                <option value="100">100</option>
                            </select>
                            <label class="small text-muted mb-0 ms-2 me-1" for="filterTitleInv">Inv</label>
                            <select id="filterTitleInv" class="form-select form-select-sm" style="width:130px;display:inline-block;vertical-align:middle;" title="Filter by inventory (snapshot → Shopify → stock mapping)">
                                <option value="gt_zero" selected>Inv &gt; 0</option>
                                <option value="zero">Inv = 0</option>
                                <option value="all">Inv = all</option>
                            </select>
                    </div>

                    <div class="table-responsive">
                        <table id="title-master-table" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll" title="Select All">
                                    </th>
                                    <th>Images</th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>Parent</span>
                                            <span id="parentCount">(0)</span>
                                        </div>
                                        <input type="text" id="parentSearch" class="form-control-sm"
                                            placeholder="Search Parent">
                                    </th>
                                    <th>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span>SKU</span>
                                            <span id="skuCount">(0)</span>
                                        </div>
                                        <input type="text" id="skuSearch" class="form-control-sm"
                                            placeholder="Search SKU">
                                    </th>
                                    <th class="title-master-bs-th"
                                        title="Buyer / Seller links from amazon_data_view (same as Amazon FBM tabulator Links: B Link = buyer, S Link = seller).">
                                        B/S
                                    </th>
                                    <th class="title-master-pmcvr-th title-master-sortable" data-tm-sort="inv"
                                        title="INV from latest Pricing Master CVR snapshot (and Shopify fallbacks). Click to sort.">
                                        <div class="title-master-pmcvr-th-wrap">
                                            <span class="title-master-pmcvr-th-inner">INV</span>
                                            <i class="fas fa-sort title-master-sort-icon" aria-hidden="true"></i>
                                        </div>
                                    </th>
                                    <th class="title-master-pmcvr-th title-master-sortable" data-tm-sort="dil"
                                        title="Dil % from snapshot / Shopify. Click to sort.">
                                        <div class="title-master-pmcvr-th-wrap">
                                            <span class="title-master-pmcvr-th-inner">Dil %</span>
                                            <i class="fas fa-sort title-master-sort-icon" aria-hidden="true"></i>
                                        </div>
                                    </th>
                                    <th class="title-master-pmcvr-th title-master-sortable @if (! \Illuminate\Support\Facades\Schema::hasTable('pricing_master_daily_snapshots_sku')) title-master-sort-disabled @endif" data-tm-sort="cvr"
                                        title="{{ \Illuminate\Support\Facades\Schema::hasTable('pricing_master_daily_snapshots_sku') ? 'CVR % from latest Pricing Master CVR snapshot. Click to sort.' : 'CVR % sorting needs the pricing_master_daily_snapshots_sku table (open /pricing-master-cvr).' }}">
                                        <div class="title-master-pmcvr-th-wrap">
                                            <span class="title-master-pmcvr-th-inner">CVR %</span>
                                            @if (\Illuminate\Support\Facades\Schema::hasTable('pricing_master_daily_snapshots_sku'))
                                                <i class="fas fa-sort title-master-sort-icon" aria-hidden="true"></i>
                                            @endif
                                        </div>
                                    </th>
                                    <th class="title-master-pmcvr-th"
                                        title="Listing Quality Score from Jungle Scout (junglescout_product_data JSON listing_quality_score). Latest row by SKU, else by Parent.">
                                        <span class="title-master-pmcvr-th-inner">LQS</span>
                                    </th>
                                    <th class="title-master-ai-th" title="Open AI workspace: Title 170 + Title 100/80/60 references and 3 AI drafts (150–175 chars when generated).">
                                        AI
                                    </th>
                                    <th class="title-master-title-dot-th">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 3px; flex-wrap: wrap;">
                                            <span style="font-size: 9px;">170</span>
                                            <span id="title150MissingCount" class="text-warning" style="font-weight: bold; font-size: 9px;">(0)</span>
                                            <span class="info-icon" style="font-size: 10px;" title="Green dot = title present, red = missing. Hover dot for full text. Filters include Exceeds 170 chars.">ⓘ</span>
                                        </div>
                                        <select id="filterTitle150" class="form-control form-control-sm mt-1">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                            <option value="exceeds">Exceeds</option>
                                        </select>
                                    </th>
                                    <th class="title-master-title-dot-th">
                                        <div style="font-size: 9px;">100 <span id="title100MissingCount" class="text-warning" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle100" class="form-control form-control-sm mt-1">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="title-master-title-dot-th">
                                        <div style="font-size: 9px;">80 <span id="title80MissingCount" class="text-warning" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle80" class="form-control form-control-sm mt-1">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="title-master-title-dot-th">
                                        <div style="font-size: 9px;">60 <span id="title60MissingCount" class="text-warning" style="font-weight: bold;">(0)</span></div>
                                        <select id="filterTitle60" class="form-control form-control-sm mt-1">
                                            <option value="all">All</option>
                                            <option value="missing">Missing</option>
                                        </select>
                                    </th>
                                    <th class="title-master-action-th" scope="col" title="View title details">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                        <span class="visually-hidden">View</span>
                                    </th>
                                    <th title="Amazon, Temu, Reverb">MARKET (170)</th>
                                    <th title="Shopify Main, Shopify PLS, Macy's (Title 60 push)">MARKET (100)</th>
                                    <th title="eBay 1 (AmarjitK), eBay 2 (ProLight), eBay 3 (KaneerKa)">MARKET (80)</th>
                                    <th class="push-to-all-th" title="Push Title 170 to Amazon, Temu, Reverb">
                                        <div class="push-all-th-inner">
                                            <img src="{{ asset('images/title-master/distribute-all-icon.png') }}" alt="" class="tm-push-all-icon" width="20" height="20">
                                            <span>PUSH</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="table-body"></tbody>
                        </table>
                    </div>

                    <div id="titleMasterImgPreview" class="title-master-img-hover-preview" aria-hidden="true">
                        <img src="" alt="">
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 px-1" id="tmPaginationWrap">
                        <div class="small text-muted" id="tmPageInfo"></div>
                        <nav><ul class="pagination pagination-sm mb-0" id="tmPagination"></ul></nav>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="loading-text">Loading Title Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Title Modal -->
    <div class="modal fade" id="titleModal" tabindex="-1" aria-labelledby="titleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="titleModalLabel">
                        <i class="fas fa-edit me-2"></i><span id="modalTitle">Add Title</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="titleForm">
                        <input type="hidden" id="editSku" name="sku">
                        
                        <div class="mb-3">
                            <label for="selectSku" class="form-label">Select SKU <span class="text-danger">*</span></label>
                            <select class="form-select" id="selectSku" name="sku" required>
                                <option value="">Choose SKU...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="title150" class="form-label">
                                Title 170 <span class="char-counter" id="counter150">0/170</span>
                            </label>
                            <textarea class="form-control" id="title150" name="title150" rows="3" maxlength="500" data-max-display="170"></textarea>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn" title="Generate Title 170 (about 120–170 characters) with AI and review in popup">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>

                        <div class="mb-3">
                            <label for="title100" class="form-label">
                                Title 100 <span class="char-counter" id="counter100">0/105</span>
                            </label>
                            <textarea class="form-control" id="title100" name="title100" rows="2" maxlength="105"></textarea>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn100" title="Generate Title 100 (90-105 chars, target 95-100) with AI and review in popup">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>

                        <div class="mb-3">
                            <label for="title80" class="form-label">
                                Title 80 <span class="char-counter" id="counter80">0/80</span>
                            </label>
                            <textarea class="form-control" id="title80" name="title80" rows="2" maxlength="80"></textarea>
                        </div>

                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn80" title="Generate Title 80 (75-85 chars) with AI for eBay">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>

                        <div class="mb-3">
                            <label for="title60" class="form-label">
                                Title 60 <span class="char-counter" id="counter60">0/60</span>
                            </label>
                            <textarea class="form-control" id="title60" name="title60" rows="2" maxlength="60"></textarea>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-ai-improve" id="aiImproveBtn60" title="Generate Title 60 (55-60 chars) with AI for Macy's/Faire">
                                <i class="fas fa-magic"></i> Improve with AI
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveTitleBtn">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Title Modal -->
    <div class="modal fade" id="viewTitleModal" tabindex="-1" aria-labelledby="viewTitleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="viewTitleModalLabel">
                        <i class="fas fa-eye me-2"></i>View Title Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Image</label>
                            <div id="viewImage" class="mt-2"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">SKU</label>
                            <div class="form-control-plaintext" id="viewSku"></div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Parent</label>
                            <div class="form-control-plaintext" id="viewParent"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 170</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle150" style="min-height: 60px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 100</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle100" style="min-height: 50px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 80</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle80" style="min-height: 50px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Title 60</label>
                        <div class="form-control-plaintext border rounded p-2" id="viewTitle60" style="min-height: 50px; white-space: pre-wrap; word-wrap: break-word;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Title Master: AI stack — Title 170 + Title 100/80/60 refs + 3 draft fields (150–175 chars for AI drafts) -->
    <div class="modal fade" id="tmAiStackModal" tabindex="-1" aria-labelledby="tmAiStackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="tmAiStackModalLabel">
                        <i class="fas fa-wand-magic-sparkles me-2"></i>AI workspace
                        <span class="fs-6 fw-normal ms-2 text-white-50" id="tmAiStackSkuWrap">SKU: <span id="tmAiStackSkuLabel"></span></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="tmAiStackAiAlert" class="alert alert-danger py-2 small d-none mb-2" role="alert"></div>
                    <div class="mb-2 tm-ai-prompt-toolbar">
                        <label class="form-label fw-bold mb-0 text-nowrap flex-shrink-0">AI Prompt</label>
                        <span class="text-muted small flex-shrink-0" id="tmAiStackPromptSummary" title="">No prompt</span>
                        <div class="tm-ai-prompt-actions">
                            <button type="button" class="btn tm-ai-prompt-icon-btn tm-ai-prompt-icon-btn-eye" id="tmAiStackPromptOpenBtn" title="View / edit full AI prompt">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                                <span class="visually-hidden">View or edit AI prompt</span>
                            </button>
                            <button type="button" class="btn tm-ai-prompt-icon-btn tm-ai-prompt-icon-btn-wand" id="tmAiStackGenerateBtn" title="Generate 3 drafts with AI">
                                <span class="tm-ai-stack-generate-spinner spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                                <i class="fas fa-wand-magic-sparkles tm-ai-stack-generate-icon" aria-hidden="true"></i>
                                <span class="visually-hidden">Generate drafts</span>
                            </button>
                        </div>
                        <textarea id="tmAiStackAiPrompt" class="d-none" maxlength="15000" tabindex="-1" aria-hidden="true"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold mb-1" for="tmAiStackTitle150Ref">Title 170 (ref) <span class="text-muted fw-normal" id="tmAiStackTitle150RefCount">0 chars</span></label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="tmAiStackTitle150Ref" class="form-control tm-ai-ref-area flex-grow-1" rows="2" maxlength="500" placeholder="Edit Title 170 (max 170 applied to grid)"></textarea>
                            <button type="button" class="btn btn-outline-primary tm-ai-stack-apply-btn align-self-stretch" data-tm-apply-ref-field="title150" title="Apply this Title 170 text to the grid (max 170 chars). Persist with Add Title → Save."><span class="visually-hidden">Apply Title 170 to grid</span><i class="fas fa-check" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold mb-1" for="tmAiStackTitle100Ref">Title 100 (ref) <span class="text-muted fw-normal" id="tmAiStackTitle100RefCount">0 chars</span></label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="tmAiStackTitle100Ref" class="form-control tm-ai-ref-area flex-grow-1" rows="1" maxlength="105" placeholder="Max 105 chars — or leave empty and Apply prefills from Title 170"></textarea>
                            <button type="button" class="btn btn-outline-primary tm-ai-stack-apply-btn align-self-stretch" data-tm-apply-ref-field="title100" title="Apply this Title 100 to the grid (max 105). If empty, prefill from Title 170 (truncated) then apply."><span class="visually-hidden">Apply Title 100 to grid</span><i class="fas fa-check" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold mb-1" for="tmAiStackTitle80Ref">Title 80 (ref) <span class="text-muted fw-normal" id="tmAiStackTitle80RefCount">0 chars</span></label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="tmAiStackTitle80Ref" class="form-control tm-ai-ref-area flex-grow-1" rows="1" maxlength="80" placeholder="Max 80 chars — or leave empty and Apply prefills from Title 170"></textarea>
                            <button type="button" class="btn btn-outline-primary tm-ai-stack-apply-btn align-self-stretch" data-tm-apply-ref-field="title80" title="Apply this Title 80 to the grid (max 80). If empty, prefill from Title 170 (truncated) then apply."><span class="visually-hidden">Apply Title 80 to grid</span><i class="fas fa-check" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold mb-1" for="tmAiStackTitle60Ref">Title 60 (ref) <span class="text-muted fw-normal" id="tmAiStackTitle60RefCount">0 chars</span></label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="tmAiStackTitle60Ref" class="form-control tm-ai-ref-area flex-grow-1" rows="1" maxlength="60" placeholder="Max 60 chars — or leave empty and Apply prefills from Title 170"></textarea>
                            <button type="button" class="btn btn-outline-primary tm-ai-stack-apply-btn align-self-stretch" data-tm-apply-ref-field="title60" title="Apply this Title 60 to the grid (max 60). If empty, prefill from Title 170 (truncated) then apply."><span class="visually-hidden">Apply Title 60 to grid</span><i class="fas fa-check" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="mb-2 tm-ai-stack-draft-row">
                        <label class="form-label fw-bold mb-1" for="tmAiStackVariant1">Draft 1 <span class="text-muted fw-normal small" id="tmAiStackVariant1Count">0/175</span></label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="tmAiStackVariant1" class="form-control flex-grow-1" rows="2" maxlength="175" placeholder="150–175 characters (max 175)"></textarea>
                            <button type="button" class="btn btn-outline-primary tm-ai-stack-apply-btn align-self-stretch" data-tm-apply-draft="1" title="Apply draft 1 to Title 170 (grid; use Add Title → Save to store in database)"><span class="visually-hidden">Apply draft 1 to Title 170</span><i class="fas fa-check" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="mb-2 tm-ai-stack-draft-row">
                        <label class="form-label fw-bold mb-1" for="tmAiStackVariant2">Draft 2 <span class="text-muted fw-normal small" id="tmAiStackVariant2Count">0/175</span></label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="tmAiStackVariant2" class="form-control flex-grow-1" rows="2" maxlength="175" placeholder="150–175 characters (max 175)"></textarea>
                            <button type="button" class="btn btn-outline-primary tm-ai-stack-apply-btn align-self-stretch" data-tm-apply-draft="2" title="Apply draft 2 to Title 170 (grid; use Add Title → Save to store in database)"><span class="visually-hidden">Apply draft 2 to Title 170</span><i class="fas fa-check" aria-hidden="true"></i></button>
                        </div>
                    </div>
                    <div class="mb-0 tm-ai-stack-draft-row">
                        <label class="form-label fw-bold mb-1" for="tmAiStackVariant3">Draft 3 <span class="text-muted fw-normal small" id="tmAiStackVariant3Count">0/175</span></label>
                        <div class="d-flex gap-2 align-items-start">
                            <textarea id="tmAiStackVariant3" class="form-control flex-grow-1" rows="2" maxlength="175" placeholder="150–175 characters (max 175)"></textarea>
                            <button type="button" class="btn btn-outline-primary tm-ai-stack-apply-btn align-self-stretch" data-tm-apply-draft="3" title="Apply draft 3 to Title 170 (grid; use Add Title → Save to store in database)"><span class="visually-hidden">Apply draft 3 to Title 170</span><i class="fas fa-check" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Title Master AI workspace: full AI prompt view / edit (opened from eye icon) -->
    <div class="modal fade" id="tmAiStackPromptEditorModal" tabindex="-1" aria-labelledby="tmAiStackPromptEditorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tmAiStackPromptEditorModalLabel"><i class="fas fa-eye me-2"></i>AI prompt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Edit the instructions sent to the model. Changes apply when you click <strong>Done</strong> or type (kept in sync automatically).</p>
                    <textarea id="tmAiStackAiPromptEditor" class="form-control font-monospace small" rows="18" maxlength="15000" placeholder="AI prompt…"></textarea>
                    <p class="small text-muted mb-0 mt-2" id="tmAiStackAiPromptEditorCount">0 characters</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title 100 Preview Modal (4 options) -->
    <div class="modal fade" id="aiTitle100Modal" tabindex="-1" aria-labelledby="aiTitle100ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitle100ModalLabel" title="90-105 chars. Perfect (95-100), Slightly long (101-105), Good (90-94). Target: 100 chars.">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (90-105 chars)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitle100Warning" class="alert alert-warning mb-3 d-none" role="alert"></div>
                    <div id="aiTitle100Option1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle100Option2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle100Option3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle100Option4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title100-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title100-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char100-badge badge">0/105 chars</span>
                            <span class="ai-char100-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-100" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn100">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title 80 Preview Modal (4 options, 75-85 chars) -->
    <div class="modal fade" id="aiTitle80Modal" tabindex="-1" aria-labelledby="aiTitle80ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitle80ModalLabel" title="75-85 chars for eBay">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (80 chars)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitle80Warning" class="alert alert-warning mb-3 d-none" role="alert"></div>
                    <div id="aiTitle80Option1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle80Option2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle80Option3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle80Option4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title80-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title80-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char80-badge badge">0/80 chars</span>
                            <span class="ai-char80-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-80" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn80">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title 60 Preview Modal (4 options, 55-60 chars) -->
    <div class="modal fade" id="aiTitle60Modal" tabindex="-1" aria-labelledby="aiTitle60ModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitle60ModalLabel" title="55-60 chars for Macy's/Faire">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (60 chars)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitle60Warning" class="alert alert-warning mb-3 d-none" role="alert"></div>
                    <div id="aiTitle60Option1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle60Option2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle60Option3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitle60Option4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title60-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title60-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char60-badge badge">0/60 chars</span>
                            <span class="ai-char60-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn-60" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn60">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Generated Title Preview Modal (3 options) -->
    <div class="modal fade" id="aiTitleModal" tabindex="-1" aria-labelledby="aiTitleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title" id="aiTitleModalLabel" title="Target up to 170 characters for Amazon-style titles.">
                        <i class="fas fa-magic me-2"></i>AI Generated Titles (4 options, up to 170 chars)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="aiTitleOption1" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 1:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/170 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="0"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitleOption2" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 2:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/170 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="1"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitleOption3" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 3:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/170 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="2"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                    <div id="aiTitleOption4" class="p-3 bg-light rounded mb-3 border">
                        <div class="fw-bold mb-2">Option 4:</div>
                        <p class="mb-2 ai-title-text" style="font-size: 15px; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"></p>
                        <div class="ai-title-score mb-2 text-muted small fw-bold"></div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <span class="ai-char-badge badge">0/170 chars</span>
                            <span class="ai-char-status text-success"></span>
                        </div>
                        <button type="button" class="btn btn-keep-title ai-keep-btn" data-option="3"><i class="fas fa-check me-1"></i> KEEP THIS TITLE</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-regen-titles" id="aiRegenerateBtn">
                        <i class="fas fa-redo-alt me-1"></i> REGENERATE 4 NEW TITLES
                    </button>
                    <button type="button" class="btn btn-cancel-ai" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Platform Selection Modal -->
    <div class="modal fade" id="platformModal" tabindex="-1" aria-labelledby="platformModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content platform-selector-modal">
                <div class="modal-header modal-header-gradient">
                    <h5 class="modal-title" id="platformModalLabel">
                        <i class="fas fa-globe me-2"></i>Select Platforms to Update (<span id="platformSkuCount">0</span> SKUs)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Select which platforms you want to update. Each platform will update its corresponding title field.
                    </div>

                    <div class="row">
                        <!-- Amazon -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('amazon')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="amazon" id="platform_amazon">
                                    <label class="form-check-label w-100" for="platform_amazon">
                                        <i class="fab fa-amazon platform-icon text-warning"></i>
                                        <strong>Amazon</strong>
                                        <span class="badge bg-primary platform-badge">Title 170</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Shopify Main -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('shopify_main')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="shopify_main" id="platform_shopify_main">
                                    <label class="form-check-label w-100" for="platform_shopify_main">
                                        <i class="fab fa-shopify platform-icon text-success"></i>
                                        <strong>Shopify</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Shopify PLS (ProLightSounds) -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('shopify_pls')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="shopify_pls" id="platform_shopify_pls">
                                    <label class="form-check-label w-100" for="platform_shopify_pls">
                                        <i class="fab fa-shopify platform-icon text-success"></i>
                                        <strong>Shopify PLS</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 1 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay1')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay1" id="platform_ebay1">
                                    <label class="form-check-label w-100" for="platform_ebay1">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 1</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 2 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay2')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay2" id="platform_ebay2">
                                    <label class="form-check-label w-100" for="platform_ebay2">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 2</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- eBay 3 -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('ebay3')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="ebay3" id="platform_ebay3">
                                    <label class="form-check-label w-100" for="platform_ebay3">
                                        <i class="fas fa-gavel platform-icon text-info"></i>
                                        <strong>eBay 3</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Walmart -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('walmart')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="walmart" id="platform_walmart">
                                    <label class="form-check-label w-100" for="platform_walmart">
                                        <i class="fas fa-store platform-icon text-primary"></i>
                                        <strong>Walmart</strong>
                                        <span class="badge bg-info platform-badge">Title 80</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Temu -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('temu')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="temu" id="platform_temu">
                                    <label class="form-check-label w-100" for="platform_temu">
                                        <i class="fas fa-shopping-bag platform-icon text-danger"></i>
                                        <strong>Temu</strong>
                                        <span class="badge bg-primary platform-badge">Title 170</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Doba -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('doba')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="doba" id="platform_doba">
                                    <label class="form-check-label w-100" for="platform_doba">
                                        <i class="fas fa-box platform-icon text-secondary"></i>
                                        <strong>Doba</strong>
                                        <span class="badge bg-success platform-badge">Title 100</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Shein -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('shein')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="shein" id="platform_shein">
                                    <label class="form-check-label w-100" for="platform_shein">
                                        <i class="fas fa-shopping-bag platform-icon text-danger"></i>
                                        <strong>Shein</strong>
                                        <span class="badge bg-primary platform-badge">Title 170</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Wayfair -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('wayfair')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="wayfair" id="platform_wayfair">
                                    <label class="form-check-label w-100" for="platform_wayfair">
                                        <i class="fas fa-home platform-icon text-info"></i>
                                        <strong>Wayfair</strong>
                                        <span class="badge bg-primary platform-badge">Title 170</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Reverb -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('reverb')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="reverb" id="platform_reverb">
                                    <label class="form-check-label w-100" for="platform_reverb">
                                        <i class="fas fa-guitar platform-icon text-warning"></i>
                                        <strong>Reverb</strong>
                                        <span class="badge bg-primary platform-badge">Title 170</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Faire -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('macy')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="macy" id="platform_macy">
                                    <label class="form-check-label w-100" for="platform_macy">
                                        <i class="fas fa-building platform-icon text-primary"></i>
                                        <strong>Macy's</strong>
                                        <span class="badge bg-info platform-badge">Title 60</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('faire')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="faire" id="platform_faire">
                                    <label class="form-check-label w-100" for="platform_faire">
                                        <i class="fas fa-store platform-icon text-success"></i>
                                        <strong>Faire</strong>
                                        <span class="badge bg-info platform-badge">Title 60</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Aliexpress -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('aliexpress')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="aliexpress" id="platform_aliexpress">
                                    <label class="form-check-label w-100" for="platform_aliexpress">
                                        <i class="fas fa-shopping-cart platform-icon text-danger"></i>
                                        <strong>Aliexpress</strong>
                                        <span class="badge bg-primary platform-badge">Title 170</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- TikTok -->
                        <div class="col-md-6 mb-3">
                            <div class="platform-item" onclick="togglePlatform('tiktok')">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="tiktok" id="platform_tiktok">
                                    <label class="form-check-label w-100" for="platform_tiktok">
                                        <i class="fab fa-tiktok platform-icon text-dark"></i>
                                        <strong>TikTok</strong>
                                        <span class="badge bg-primary platform-badge">Title 170</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Updates will respect platform rate limits. This may take several seconds per product.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmUpdateBtn">
                        <i class="fas fa-cloud-upload-alt"></i> Update Selected Platforms
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Distribute to All Markets confirmation modal -->
    <div class="modal fade" id="pushConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #ff9900; color: white;">
                    <h5 class="modal-title d-flex align-items-center gap-2 mb-0"><img src="{{ asset('images/title-master/distribute-all-icon.png') }}" alt="" class="tm-push-all-icon" width="18" height="18"> Distribute to All Markets</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="pushConfirmMessage">Distribute 0 titles to Amazon, Temu, Reverb &amp; Wayfair? This may take several minutes.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-1" id="pushConfirmBtn" style="background-color: #ff9900;">
                        <img src="{{ asset('images/title-master/distribute-all-icon.png') }}" alt="" class="tm-push-all-icon" width="16" height="16"> Distribute All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Distribute to All Markets progress modal -->
    <div class="modal fade" id="pushProgressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color: #ff9900; color: white;">
                    <h5 class="modal-title d-flex align-items-center gap-2 mb-0"><img src="{{ asset('images/title-master/distribute-all-icon.png') }}" alt="" class="tm-push-all-icon" width="18" height="18"> Distributing to All Markets</h5>
                </div>
                <div class="modal-body">
                    <div class="progress mb-2" style="height: 25px;">
                        <div id="pushProgressBar" class="progress-bar" role="progressbar" style="width: 0%;">0%</div>
                    </div>
                    <p id="pushProgressText" class="mb-0">Distributing 0/0...</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        window.titleMasterHasPricingCvrSnapshot = @json(\Illuminate\Support\Facades\Schema::hasTable('pricing_master_daily_snapshots_sku'));
        window.titleMasterDataUrl = @json(route('title.master.data', [], false));
        window.titleMasterAiStackConfigured = @json((bool) (config('services.claude.key') || config('services.anthropic.key') || config('services.openai.key')));
        window.titleMasterAiStackDraftsUrl = @json(route('title.master.ai.stack.drafts', [], false));
        window.titleMasterPushAllIconUrl = @json(asset('images/title-master/distribute-all-icon.png'));
    </script>
    <script>
        @verbatim
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const TM_PUSH_ALL_ICON_URL = typeof window.titleMasterPushAllIconUrl === 'string' ? window.titleMasterPushAllIconUrl : '';
        const TM_AI_STACK_DRAFT_MAX = 175;
        /** Amazon / Title column display limit (✅/❌ and “Exceeds N chars” filter). */
        const TITLE_MASTER_AMAZON_TITLE_MAX = 170;
        /** Title 100 field max in Edit modal (matches maxlength on #title100). */
        const TITLE_MASTER_TITLE100_UI_MAX = 105;

        function titleMasterTitleCounterSuffix(fieldId) {
            if (fieldId === 'title100') return 100;
            if (fieldId === 'title150') return 150;
            return parseInt(fieldId.replace('title', ''), 10);
        }

        function titleMasterTitleMaxLen(fieldId) {
            if (fieldId === 'title150') return TITLE_MASTER_AMAZON_TITLE_MAX;
            if (fieldId === 'title100') return 105;
            return parseInt(fieldId.replace('title', ''), 10);
        }

        function buildTmAiStackDefaultPrompt(sku, buyerLink) {
            const b = (buyerLink != null && String(buyerLink).trim() !== '') ? String(buyerLink).trim() : '(none — use reference title and SKU only; you cannot fetch URLs)';
            const s = (sku != null && String(sku).trim() !== '') ? String(sku).trim() : '(none)';
            const skuJsonNote = (s !== '(none)')
                ? 'Each string in "drafts" must end with the SKU "' + s + '" at the very end.'
                : 'No SKU in input; do not invent a trailing SKU.';
            return [
                'You are an Amazon SEO expert and high-conversion copywriter.',
                '',
                'Your task is to analyze the product listing context from the Buyer Link (B/S column), the reference title the app provides separately, and the SKU—then generate 3 optimized Amazon title variations.',
                '',
                'INPUT DATA:',
                '- Buyer Link (B/S): ' + b,
                '- SKU: ' + s,
                '',
                'INSTRUCTIONS:',
                '',
                '1. Analyze the Buyer Link and context:',
                '   - You cannot open URLs or fetch the live web; infer cues from the URL text (e.g. ASIN patterns), path, and the reference title supplied by the app.',
                '   - Understand product type, features, specs, use-case, and target audience as far as the text allows.',
                '   - Note keywords implied by the reference title; identify gaps where high-volume Amazon search terms could help.',
                '',
                '2. Competitor-style analysis (without live browsing):',
                '   - Reason about how strong competing listings for similar products are typically structured on Amazon.',
                '   - Favor high-performing patterns: primary keyword early, clear benefits, specs, compatibility, and trust cues.',
                '',
                '3. Keyword optimization:',
                '   - Add high-volume, relevant Amazon search keywords where they fit naturally.',
                '   - Use long-tail keywords where beneficial.',
                '   - Avoid keyword stuffing and repetition.',
                '',
                '4. Title creation rules:',
                '   - Generate EXACTLY 3 different title options.',
                '   - Each title must be between 150 and 175 characters (inclusive).',
                '   - Start with the primary keyword (important for SEO).',
                '   - Maintain readability and conversion focus.',
                '   - Use proper capitalization (Amazon style).',
                '   - Include key features (size, material, use-case, compatibility, etc.).',
                '',
                '5. Branding rules:',
                '   - ALWAYS include brand name: "5 Core".',
                '   - Brand should appear naturally (preferably toward the beginning or middle).',
                '   - ALWAYS append the SKU at the very end of each title when SKU is provided above (not "(none)").',
                '',
                '6. Conversion optimization:',
                '   - Focus on benefits, not just features.',
                '   - Improve click-through rate (CTR).',
                '   - Make titles appealing and clear.',
                '',
                '7. Output format (required — the app only accepts JSON):',
                'Return ONLY valid JSON, no markdown, no labels like "Option 1:", no extra keys, exactly:',
                '{"drafts":["<Optimized Title 1>","<Optimized Title 2>","<Optimized Title 3>"]}',
                skuJsonNote,
                '',
                'IMPORTANT:',
                '- Do NOT exceed 175 characters per title.',
                '- Do NOT go below 150 characters per title.',
                '- Do NOT include special characters like |, /, or excessive commas.',
                '- Avoid duplicate words.',
                '- Ensure titles are Amazon-compliant.'
            ].join('\n');
        }
        let tableData = [];
        let listMeta = { current_page: 1, last_page: 1, per_page: 75, total: 0, from: null, to: null };
        let titleMasterSort = { column: 'sku', dir: 'asc' };
        let titleMasterLoadAbort = null;
        let titleModal;
        let platformModal;
        let aiTitleModalInstance;
        let currentAIGeneratedTitles = [];
        let aiTitle100ModalInstance;
        let currentAIGeneratedTitles100 = [];
        let aiTitle80ModalInstance;
        let currentAIGeneratedTitles80 = [];
        let aiTitle60ModalInstance;
        let currentAIGeneratedTitles60 = [];
        let tmAiStackModalInstance = null;
        let tmAiStackPromptEditorModalInstance = null;

        /** NBSP + collapsed whitespace (same idea as product-master SKU search). */
        function normalizeForTextSearch(s) {
            if (s == null || s === '') return '';
            return String(s)
                .replace(/\u00a0/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        }

        function setupTitleMasterImageHoverPreview() {
            const table = document.getElementById('title-master-table');
            const layer = document.getElementById('titleMasterImgPreview');
            const layerImg = layer ? layer.querySelector('img') : null;
            if (!table || !layer || !layerImg) return;

            let hideTimer = null;
            function hidePreview() {
                layer.classList.remove('is-visible');
                layer.setAttribute('aria-hidden', 'true');
            }

            function positionPreview(anchorImg) {
                const r = anchorImg.getBoundingClientRect();
                const maxW = Math.min(280, Math.floor(window.innerWidth * 0.85));
                const maxH = Math.min(280, Math.floor(window.innerHeight * 0.55));
                const size = Math.min(maxW, maxH);
                let left = r.left + (r.width / 2) - (size / 2);
                let top = r.bottom + 10;
                left = Math.max(10, Math.min(left, window.innerWidth - size - 10));
                if (top + size > window.innerHeight - 10) {
                    top = Math.max(10, r.top - size - 10);
                }
                layer.style.width = size + 'px';
                layer.style.height = size + 'px';
                layer.style.left = left + 'px';
                layer.style.top = top + 'px';
            }

            table.addEventListener('mouseover', function(e) {
                const img = e.target.closest('td.table-img-cell img');
                if (!img) return;
                const src = img.getAttribute('src');
                if (!src || src === '') return;
                clearTimeout(hideTimer);
                layerImg.src = src;
                positionPreview(img);
                layer.classList.add('is-visible');
                layer.setAttribute('aria-hidden', 'false');
            });

            table.addEventListener('mouseout', function(e) {
                const img = e.target.closest('td.table-img-cell img');
                if (!img) return;
                const rel = e.relatedTarget;
                if (rel && img.contains(rel)) return;
                hideTimer = setTimeout(hidePreview, 60);
            });

            window.addEventListener(
                'scroll',
                function() {
                    clearTimeout(hideTimer);
                    hidePreview();
                },
                true
            );
        }

        document.addEventListener('DOMContentLoaded', function() {
            titleModal = new bootstrap.Modal(document.getElementById('titleModal'));
            platformModal = new bootstrap.Modal(document.getElementById('platformModal'));
            const aiTitleModalEl = document.getElementById('aiTitleModal');
            if (aiTitleModalEl) aiTitleModalInstance = new bootstrap.Modal(aiTitleModalEl);
            const aiTitle100ModalEl = document.getElementById('aiTitle100Modal');
            if (aiTitle100ModalEl) aiTitle100ModalInstance = new bootstrap.Modal(aiTitle100ModalEl);
            const aiTitle80ModalEl = document.getElementById('aiTitle80Modal');
            if (aiTitle80ModalEl) aiTitle80ModalInstance = new bootstrap.Modal(aiTitle80ModalEl);
            const aiTitle60ModalEl = document.getElementById('aiTitle60Modal');
            if (aiTitle60ModalEl) aiTitle60ModalInstance = new bootstrap.Modal(aiTitle60ModalEl);
            const tmAiStackModalEl = document.getElementById('tmAiStackModal');
            if (tmAiStackModalEl) tmAiStackModalInstance = new bootstrap.Modal(tmAiStackModalEl);
            const tmAiStackPromptEditorModalEl = document.getElementById('tmAiStackPromptEditorModal');
            if (tmAiStackPromptEditorModalEl) {
                tmAiStackPromptEditorModalInstance = new bootstrap.Modal(tmAiStackPromptEditorModalEl);
            }
            [1, 2, 3].forEach(function(i) {
                const ta = document.getElementById('tmAiStackVariant' + i);
                if (!ta) return;
                ta.addEventListener('input', function() {
                    const c = document.getElementById('tmAiStackVariant' + i + 'Count');
                    if (c) c.textContent = ta.value.length + '/' + TM_AI_STACK_DRAFT_MAX;
                });
            });
            setupTmAiStackPromptEditor();
            setupTmAiStackGenerateButton();
            setupTmAiStackRefFieldCharCounts();
            setupTmAiStackApplyButtons();
            updateTmAiStackPromptSummary();
            setupTitleMasterColumnSort();
            setupTitleMasterImageHoverPreview();
            loadTitleData(1);
            document.getElementById('perPageSelect')?.addEventListener('change', function() { loadTitleData(1); });
            setupSearchHandlers();
            setupModalHandlers();
            setupButtonHandlers();
            setupCheckboxHandlers();
            setupPlatformModalHandlers();
            setupPlatformCheckboxes();
        });

        function setupButtonHandlers() {
            // Add Title Button
            document.getElementById('addTitleBtn').addEventListener('click', function() {
                openModal('add');
            });

            // Push ALL Button
            const pushAllBtn = document.getElementById('pushAllBtn');
            if (pushAllBtn) {
                pushAllBtn.addEventListener('click', function() {
                    const items = (tableData || []).filter(function(item) {
                        if (item.SKU && item.SKU.toUpperCase().includes('PARENT')) return false;
                        const t = (item.amazon_title || item.title150 || '').toString().trim();
                        return t.length > 0;
                    }).map(function(item) {
                        return { sku: item.SKU, title: (item.amazon_title || item.title150 || '').toString().trim() };
                    });
                    if (items.length === 0) {
                        alert('No titles to distribute. Ensure rows have Title 170 data.');
                        return;
                    }
                    document.getElementById('pushConfirmMessage').textContent = 'Distribute ' + items.length + ' title(s) on this page to Amazon, Temu & Reverb? This may take several minutes.';
                    const confirmModalEl = document.getElementById('pushConfirmModal');
                    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                    document.getElementById('pushConfirmBtn').onclick = function() {
                        confirmModal.hide();
                        runPushBulk(items);
                    };
                    confirmModal.show();
                });
            }

            // Push Selected Button
            const pushSelectedBtn = document.getElementById('pushSelectedBtn');
            if (pushSelectedBtn) {
                pushSelectedBtn.addEventListener('click', function() {
                    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
                    const skus = Array.from(checkedBoxes).map(function(cb) { return cb.getAttribute('data-sku'); });
                    const items = skus.map(function(sku) {
                        const item = tableData.find(function(d) { return d.SKU === sku; });
                        const t = item ? (item.amazon_title || item.title150 || '').toString().trim() : '';
                        return { sku: sku, title: t };
                    }).filter(function(x) { return x.title.length > 0; });
                    if (items.length === 0) {
                        alert('No titles to distribute. Selected rows need Title 170 data.');
                        return;
                    }
                    document.getElementById('pushConfirmMessage').textContent = 'Distribute ' + items.length + ' selected title(s) on this page to Amazon, Temu & Reverb?';
                    const confirmModalEl = document.getElementById('pushConfirmModal');
                    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
                    document.getElementById('pushConfirmBtn').onclick = function() {
                        confirmModal.hide();
                        runPushBulk(items);
                    };
                    confirmModal.show();
                });
            }

            // Export Button
            document.getElementById('exportBtn').addEventListener('click', function() {
                exportToExcel();
            });

            // Import Button
            document.getElementById('importBtn').addEventListener('click', function() {
                document.getElementById('importFile').click();
            });

            // Import File Handler
            document.getElementById('importFile').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    importFromExcel(file);
                }
            });

            // Update Titles Button - opens platform selection modal
            document.getElementById('updateAmazonBtn').addEventListener('click', function() {
                openPlatformSelectionModal();
            });
        }

        function setupPlatformModalHandlers() {
            // Confirm Update Button
            document.getElementById('confirmUpdateBtn').addEventListener('click', function() {
                updateSelectedPlatforms();
            });
        }

        function togglePlatform(platformId) {
            const checkbox = document.getElementById('platform_' + platformId);
            if (!checkbox) return;
            
            const platformItem = checkbox.closest('.platform-item');
            
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                platformItem.classList.add('selected');
            } else {
                platformItem.classList.remove('selected');
            }
        }

        // Setup platform checkbox click handlers to prevent double-toggle
        function setupPlatformCheckboxes() {
            document.querySelectorAll('[id^="platform_"]').forEach(checkbox => {
                checkbox.addEventListener('click', function(e) {
                    // Stop event from bubbling to parent platform-item
                    e.stopPropagation();
                    // The checkbox will handle its own checked state
                    const platformItem = this.closest('.platform-item');
                    if (this.checked) {
                        platformItem.classList.add('selected');
                    } else {
                        platformItem.classList.remove('selected');
                    }
                });
            });
        }

        function openPlatformSelectionModal() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            selectedSkusForUpdate = Array.from(checkedBoxes).map(cb => cb.getAttribute('data-sku'));

            if (selectedSkusForUpdate.length === 0) {
                alert('Please select at least one product');
                return;
            }

            // Update SKU count in modal
            document.getElementById('platformSkuCount').textContent = selectedSkusForUpdate.length;

            // Reset all platform selections
            document.querySelectorAll('.platform-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelectorAll('[id^="platform_"]').forEach(cb => {
                cb.checked = false;
            });

            // Show the platform selection modal
            platformModal.show();
        }

        function updateSelectedPlatforms() {
            // Collect selected platforms
            const platforms = [];
            if (document.getElementById('platform_amazon').checked) platforms.push('amazon');
            if (document.getElementById('platform_shopify_main').checked) platforms.push('shopify_main');
            if (document.getElementById('platform_shopify_pls').checked) platforms.push('shopify_pls');
            if (document.getElementById('platform_ebay1').checked) platforms.push('ebay1');
            if (document.getElementById('platform_ebay2').checked) platforms.push('ebay2');
            if (document.getElementById('platform_ebay3').checked) platforms.push('ebay3');
            if (document.getElementById('platform_walmart').checked) platforms.push('walmart');
            if (document.getElementById('platform_temu').checked) platforms.push('temu');
            if (document.getElementById('platform_doba').checked) platforms.push('doba');
            if (document.getElementById('platform_shein').checked) platforms.push('shein');
            if (document.getElementById('platform_wayfair').checked) platforms.push('wayfair');
            if (document.getElementById('platform_reverb').checked) platforms.push('reverb');
            if (document.getElementById('platform_macy').checked) platforms.push('macy');
            if (document.getElementById('platform_faire').checked) platforms.push('faire');
            if (document.getElementById('platform_aliexpress').checked) platforms.push('aliexpress');
            if (document.getElementById('platform_tiktok').checked) platforms.push('tiktok');

            if (platforms.length === 0) {
                alert('Please select at least one platform to update');
                return;
            }

            // Platform display names
            const platformNames = {
                'amazon': 'Amazon (Title 170)',
                'shopify_main': 'Shopify Main (Title 100)',
                'shopify_pls': 'Shopify PLS (Title 100)',
                'ebay1': 'eBay 1 (Title 80)',
                'ebay2': 'eBay 2 (Title 80)',
                'ebay3': 'eBay 3 (Title 80)',
                'walmart': 'Walmart (Title 170)',
                'temu': 'Temu (Title 170)',
                'doba': 'Doba (Title 100)',
                'shein': 'Shein (Title 170)',
                'wayfair': 'Wayfair (Title 170)',
                'reverb': 'Reverb (Title 170)',
                'macy': "Macy's (Title 60)",
                'faire': 'Faire (Title 60)',
                'aliexpress': 'Aliexpress (Title 170)',
                'tiktok': 'TikTok (Title 170)'
            };

            const platformList = platforms.map(p => platformNames[p]).join('\n');
            const confirmMsg = 'Update ' + selectedSkusForUpdate.length + ' product(s) to:\n\n' + platformList + '\n\nThis may take several seconds. Continue?';

            if (!confirm(confirmMsg)) {
                return;
            }

            // Hide platform modal and show processing
            platformModal.hide();

            const updateBtn = document.getElementById('updateAmazonBtn');
            updateBtn.disabled = true;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            // Send to backend
            fetch('/title-master/update-platforms', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ 
                    skus: selectedSkusForUpdate,
                    platforms: platforms
                })
            })
            .then(response => {
                // Check if response is JSON or HTML
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // It's HTML (error page), read as text
                    return response.text().then(html => {
                        console.error('Server returned HTML instead of JSON:', html);
                        throw new Error('Server error - check browser console for details');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    let message = 'Update Completed!\n\n';
                    
                    // Show results by platform
                    if (data.results) {
                        for (const [platform, result] of Object.entries(data.results)) {
                            const displayName = platformNames[platform] || platform.toUpperCase();
                            message += displayName + ': ';
                            message += 'Success: ' + result.success + ', Failed: ' + result.failed + '\n';
                        }
                    }
                    
                    message += '\nTotal Success: ' + data.total_success;
                    message += '\nTotal Failed: ' + data.total_failed;
                    
                    if (data.message && data.message.trim() !== '') {
                        message += '\n\nDetails:\n' + data.message;
                    }
                    
                    alert(message);
                    
                    // Uncheck all checkboxes
                    document.querySelectorAll('.row-checkbox:checked').forEach(cb => cb.checked = false);
                    document.getElementById('selectAll').checked = false;
                    updateSelectedCount();
                    
                    // Reload data
                    loadTitleData(listMeta.current_page || 1);
                } else {
                    alert('Error: ' + (data.message || 'Failed to update platforms'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating platforms: ' + error.message);
            })
            .finally(() => {
                updateBtn.disabled = false;
                const count = document.querySelectorAll('.row-checkbox:checked').length;
                updateBtn.innerHTML = '<i class="fas fa-sync"></i> Update Titles (<span id="selectedCount">' + count + '</span> selected)';
                if (count === 0) {
                    updateBtn.style.display = 'none';
                }
            });
        }

        function setupCheckboxHandlers() {
            const table = document.getElementById('title-master-table');
            if (!table) return;
            table.addEventListener('change', function(e) {
                if (e.target.id === 'selectAll') {
                    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = e.target.checked);
                }
                if (e.target.classList.contains('row-checkbox')) {
                    // Individual checkbox changed
                }
                updateSelectedCount();
            });
        }

        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
            const count = checkedBoxes.length;
            const countElement = document.getElementById('selectedCount');
            if (countElement) countElement.textContent = count;
            const pushSelectedCountEl = document.getElementById('pushSelectedCount');
            if (pushSelectedCountEl) pushSelectedCountEl.textContent = count;
            const updateBtn = document.getElementById('updateAmazonBtn');
            if (updateBtn) updateBtn.style.display = count > 0 ? 'inline-block' : 'none';
            const pushSelectedBtn = document.getElementById('pushSelectedBtn');
            if (pushSelectedBtn) pushSelectedBtn.style.display = count > 0 ? 'inline-flex' : 'none';
        }

        function updateModalCounter(fieldId) {
            const input = document.getElementById(fieldId);
            const maxLen = titleMasterTitleMaxLen(fieldId);
            const counter = document.getElementById('counter' + titleMasterTitleCounterSuffix(fieldId));
            if (!input || !counter) return;
            const len = input.value.length;
            counter.classList.remove('error', 'warning', 'success', 'opacity-75');
            if (fieldId === 'title100') {
                const max105 = 105;
                counter.textContent = len + '/' + max105;
                if (len >= 95 && len <= 100) counter.classList.add('success');
                else if (len >= 101 && len <= max105) counter.classList.add('warning');
                else if (len >= 90 && len <= 94) counter.classList.add('success', 'opacity-75');
                else counter.classList.add('error');
            } else {
                counter.textContent = len + '/' + maxLen;
                if (len > maxLen) counter.classList.add('error');
            }
        }

        function showFieldLoading(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            field.placeholder = 'Generating...';
            field.disabled = true;
            field.classList.add('bg-light');
        }

        function removeFieldLoading(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return;
            field.placeholder = '';
            field.disabled = false;
            field.classList.remove('bg-light');
        }

        function setupModalHandlers() {
            // Character counters
            const fields = ['title150', 'title100', 'title80', 'title60'];
            fields.forEach(field => {
                const maxLength = titleMasterTitleMaxLen(field);
                const input = document.getElementById(field);
                const counter = document.getElementById('counter' + titleMasterTitleCounterSuffix(field));
                
                input.addEventListener('input', function() {
                    const length = this.value.length;
                    counter.classList.remove('error', 'warning', 'success', 'opacity-75');
                    if (field === 'title100') {
                        const max105 = 105;
                        counter.textContent = length + '/' + max105;
                        if (length >= 95 && length <= 100) counter.classList.add('success');
                        else if (length >= 101 && length <= max105) counter.classList.add('warning');
                        else if (length >= 90 && length <= 94) counter.classList.add('success', 'opacity-75');
                        else counter.classList.add('error');
                    } else {
                        counter.textContent = length + '/' + maxLength;
                        if (length > maxLength) counter.classList.add('error');
                    }
                });
            });

            // Save button
            document.getElementById('saveTitleBtn').addEventListener('click', function() {
                saveTitleFromModal();
            });

            // Improve with AI button (generates Amazon title, shows in popup)
            const aiImproveBtn = document.getElementById('aiImproveBtn');
            if (aiImproveBtn) {
                aiImproveBtn.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const currentTitle150 = document.getElementById('title150').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const parentCategory = (item && item.Parent) ? item.Parent : '';

                    if (!currentTitle150) {
                        alert('Please enter or load Title 170 (e.g. use Add Title or AI workspace) before using Improve with AI.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating...';

                    fetch('/title-master/ai/generate-title-150', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            current_title: currentTitle150,
                            parent_category: parentCategory,
                            min_length: 120,
                            max_length: TITLE_MASTER_AMAZON_TITLE_MAX
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.items && data.items.length >= 4) {
                            showAITitlePopup(data.items);
                        } else if (data.success && data.titles && data.titles.length >= 3) {
                            var items = data.titles.slice(0, 3).map(function(t) { return { title: t, score: null }; });
                            while (items.length < 4) items.push({ title: '', score: null });
                            showAITitlePopup(items);
                        } else {
                            alert('Failed to generate titles: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            // Improve with AI button for Title 100 (4 options, ≤100 chars)
            const aiImproveBtn100 = document.getElementById('aiImproveBtn100');
            if (aiImproveBtn100) {
                aiImproveBtn100.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const title150 = document.getElementById('title150').value.trim();
                    const currentTitle100 = document.getElementById('title100').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const category = (item && item.Parent) ? item.Parent : '';

                    if (!title150) {
                        alert('Please enter or load Title 170 before using Improve with AI for Title 100.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating 100-char titles...';

                    fetch('/title-master/ai/generate-title-100', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            title_150: title150,
                            current_title_100: currentTitle100,
                            category: category
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.titles && data.titles.length >= 1) {
                            showTitle100Popup(data.titles, data.invalid_count || 0);
                        } else {
                            alert(data.message || 'Failed to generate titles. Please click Regenerate to try again.');
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title 100 error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            // AI Title 100 popup: Keep buttons (apply to Title 100 field) and Regenerate
            document.querySelectorAll('.ai-keep-btn-100').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles100[idx];
                    const el100 = document.getElementById('title100');
                    if (el100 && title) {
                        el100.value = title.length > 105 ? title.substring(0, 105) : title;
                        updateModalCounter('title100');
                    }
                    if (aiTitle100ModalInstance) aiTitle100ModalInstance.hide();
                    alert('Title applied to Title 100 field. Click Save to store.');
                });
            });
            const aiRegenBtn100 = document.getElementById('aiRegenerateBtn100');
            if (aiRegenBtn100) {
                aiRegenBtn100.addEventListener('click', function() {
                    if (aiTitle100ModalInstance) aiTitle100ModalInstance.hide();
                    setTimeout(function() {
                        if (aiImproveBtn100) aiImproveBtn100.click();
                    }, 300);
                });
            }

            // Improve with AI button for Title 80 (4 options, 75-85 chars)
            const aiImproveBtn80 = document.getElementById('aiImproveBtn80');
            if (aiImproveBtn80) {
                aiImproveBtn80.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const title150 = document.getElementById('title150').value.trim();
                    const currentTitle80 = document.getElementById('title80').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const category = (item && item.Parent) ? item.Parent : '';

                    if (!title150) {
                        alert('Please enter or load Title 170 before using Improve with AI for Title 80.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating 80-char titles...';

                    fetch('/title-master/ai/generate-title-80', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            title_150: title150,
                            current_title_80: currentTitle80,
                            category: category
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.titles && data.titles.length >= 1) {
                            showTitle80Popup(data.titles, data.invalid_count || 0);
                        } else {
                            alert(data.message || 'Failed to generate titles. Please click Regenerate to try again.');
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title 80 error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            // AI Title 80 popup: Keep buttons (apply to Title 80 field) and Regenerate
            document.querySelectorAll('.ai-keep-btn-80').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles80[idx];
                    const el80 = document.getElementById('title80');
                    if (el80 && title) {
                        el80.value = title.length > 80 ? title.substring(0, 80) : title;
                        updateModalCounter('title80');
                    }
                    if (aiTitle80ModalInstance) aiTitle80ModalInstance.hide();
                    if (typeof showToast === 'function') {
                        showToast('success', 'Title applied to Title 80 field. Click Save to store.');
                    } else {
                        alert('Title applied to Title 80 field. Click Save to store.');
                    }
                });
            });
            const aiRegenBtn80 = document.getElementById('aiRegenerateBtn80');
            if (aiRegenBtn80) {
                aiRegenBtn80.addEventListener('click', function() {
                    if (aiTitle80ModalInstance) aiTitle80ModalInstance.hide();
                    setTimeout(function() {
                        if (document.getElementById('aiImproveBtn80')) document.getElementById('aiImproveBtn80').click();
                    }, 300);
                });
            }

            // Improve with AI button for Title 60 (4 options, 55-60 chars)
            const aiImproveBtn60 = document.getElementById('aiImproveBtn60');
            if (aiImproveBtn60) {
                aiImproveBtn60.addEventListener('click', function() {
                    const btn = this;
                    const originalHtml = btn.innerHTML;
                    const title150 = document.getElementById('title150').value.trim();
                    const currentTitle60 = document.getElementById('title60').value.trim();
                    const sku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const item = tableData && sku ? tableData.find(d => d.SKU === sku) : null;
                    const category = (item && item.Parent) ? item.Parent : '';

                    if (!title150) {
                        alert('Please enter or load Title 170 before using Improve with AI for Title 60.');
                        return;
                    }

                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Generating 60-char titles...';

                    fetch('/title-master/ai/generate-title-60', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            title_150: title150,
                            current_title_60: currentTitle60,
                            category: category,
                            marketplace: 'macy'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.titles && data.titles.length >= 1) {
                            showTitle60Popup(data.titles, data.invalid_count || 0);
                        } else {
                            alert(data.message || 'Failed to generate titles. Please click Regenerate to try again.');
                        }
                    })
                    .catch(err => {
                        console.error('AI generate title 60 error:', err);
                        alert('Error: ' + (err.message || 'Network or server error'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
                });
            }

            document.querySelectorAll('.ai-keep-btn-60').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles60[idx];
                    const el60 = document.getElementById('title60');
                    if (el60 && title) {
                        el60.value = title.length > 60 ? title.substring(0, 60) : title;
                        updateModalCounter('title60');
                    }
                    if (aiTitle60ModalInstance) aiTitle60ModalInstance.hide();
                    if (typeof showToast === 'function') {
                        showToast('success', 'Title applied to Title 60 field. Click Save to store.');
                    } else {
                        alert('Title applied to Title 60 field. Click Save to store.');
                    }
                });
            });
            const aiRegenBtn60 = document.getElementById('aiRegenerateBtn60');
            if (aiRegenBtn60) {
                aiRegenBtn60.addEventListener('click', function() {
                    if (aiTitle60ModalInstance) aiTitle60ModalInstance.hide();
                    setTimeout(function() {
                        if (document.getElementById('aiImproveBtn60')) document.getElementById('aiImproveBtn60').click();
                    }, 300);
                });
            }

            // AI Title popup: Keep buttons (per option) and Regenerate
            document.querySelectorAll('.ai-keep-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = parseInt(this.getAttribute('data-option'), 10);
                    const title = currentAIGeneratedTitles[idx];
                    const el150 = document.getElementById('title150');
                    if (el150 && title) {
                        el150.value = title;
                        updateModalCounter('title150');
                    }
                    if (aiTitleModalInstance) aiTitleModalInstance.hide();
                    alert('Title applied to Title 170 in Add Title. Click Save to store.');
                });
            });
            const aiRegenBtn = document.getElementById('aiRegenerateBtn');
            if (aiRegenBtn) {
                aiRegenBtn.addEventListener('click', function() {
                    if (aiTitleModalInstance) aiTitleModalInstance.hide();
                    setTimeout(function() {
                        if (aiImproveBtn) aiImproveBtn.click();
                    }, 300);
                });
            }
        }

        function showAITitlePopup(items) {
            if (!Array.isArray(items) || items.length < 3) return;
            currentAIGeneratedTitles = items.slice(0, 4).map(function(x) {
                return (x && (typeof x === 'string' ? x : x.title)) || '';
            });
            const options = document.querySelectorAll('#aiTitleOption1, #aiTitleOption2, #aiTitleOption3, #aiTitleOption4');
            const minLen = 140;
            const maxLen = TITLE_MASTER_AMAZON_TITLE_MAX;
            options.forEach(function(opt, i) {
                const item = items[i];
                const title = (item && (typeof item === 'string' ? item : item.title)) || '';
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const textEl = opt.querySelector('.ai-title-text');
                const scoreEl = opt.querySelector('.ai-title-score');
                const badgeEl = opt.querySelector('.ai-char-badge');
                const statusEl = opt.querySelector('.ai-char-status');
                if (textEl) textEl.textContent = title;
                if (scoreEl) {
                    if (score != null) scoreEl.textContent = 'Success score: ' + score + '/10';
                    else scoreEl.textContent = '';
                }
                if (badgeEl) {
                    badgeEl.textContent = len + '/' + maxLen + ' chars';
                    badgeEl.className = 'badge ai-char-badge ';
                    if (len > maxLen) badgeEl.classList.add('bg-danger');
                    else if (len < minLen) badgeEl.classList.add('bg-warning', 'text-dark');
                    else badgeEl.classList.add('bg-success');
                }
                if (statusEl) {
                    if (len > maxLen) statusEl.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Too long</span>';
                    else if (len < minLen) statusEl.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> Too short (aim 140–' + maxLen + ')</span>';
                    else statusEl.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> ✓</span>';
                }
            });
            if (aiTitleModalInstance) aiTitleModalInstance.show();
        }

        function showTitle100Popup(titles, invalidCount) {
            invalidCount = invalidCount || 0;
            if (!Array.isArray(titles) || titles.length < 1) return;
            var padded = titles.slice(0, 4);
            while (padded.length < 4) padded.push({ title: '', score: null });
            currentAIGeneratedTitles100 = padded.map(function(t) { return (t && typeof t === 'string' ? t : (t.title || '')) || ''; });
            const maxLen = 105;
            const optionIds = ['aiTitle100Option1', 'aiTitle100Option2', 'aiTitle100Option3', 'aiTitle100Option4'];
            const warningEl = document.getElementById('aiTitle100Warning');
            if (warningEl) {
                if (invalidCount > 0) {
                    const validN = titles.length;
                    warningEl.textContent = validN + ' title(s) generated, ' + invalidCount + ' was out of range (90-105).';
                    warningEl.classList.remove('d-none');
                } else {
                    warningEl.classList.add('d-none');
                }
            }
            optionIds.forEach(function(id, i) {
                const opt = document.getElementById(id);
                if (!opt) return;
                const item = padded[i];
                const title = currentAIGeneratedTitles100[i] || '';
                const hasTitle = title.length > 0;
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const perfect = len >= 95 && len <= 100;
                const slightlyLong = len >= 101 && len <= 105;
                const good = len >= 90 && len <= 94;
                const textEl = opt.querySelector('.ai-title100-text');
                const scoreEl = opt.querySelector('.ai-title100-score');
                const badgeEl = opt.querySelector('.ai-char100-badge');
                const statusEl = opt.querySelector('.ai-char100-status');
                const keepBtn = opt.querySelector('.ai-keep-btn-100');
                if (hasTitle) {
                    opt.style.display = '';
                    if (textEl) textEl.textContent = title;
                    if (scoreEl) {
                        if (score != null) scoreEl.textContent = 'Success score: ' + score + '/10';
                        else scoreEl.textContent = '';
                    }
                    if (badgeEl) {
                        badgeEl.textContent = len + '/105 chars';
                        let badgeClass = 'badge ai-char100-badge ';
                        if (perfect) badgeClass += 'bg-success';
                        else if (slightlyLong) badgeClass += 'bg-warning text-dark';
                        else if (good) badgeClass += 'bg-success bg-opacity-75';
                        else badgeClass += 'bg-danger';
                        badgeEl.className = badgeClass;
                    }
                    if (statusEl) {
                        let statusText = len + ' chars ';
                        if (perfect) statusText += '✅ Perfect (Target)';
                        else if (slightlyLong) statusText += '⚠️ Slightly long (Acceptable)';
                        else if (good) statusText += '🟢 Good (Within range)';
                        else statusText += '❌ Out of range';
                        statusEl.textContent = statusText;
                        statusEl.className = 'ai-char100-status ' + (perfect ? 'text-success' : slightlyLong ? 'text-warning' : good ? 'text-success' : 'text-danger');
                    }
                    if (keepBtn) { keepBtn.disabled = false; keepBtn.style.display = ''; }
                } else {
                    opt.style.display = 'none';
                    if (keepBtn) keepBtn.disabled = true;
                }
            });
            if (aiTitle100ModalInstance) aiTitle100ModalInstance.show();
        }

        function showTitle80Popup(titles, invalidCount) {
            invalidCount = invalidCount || 0;
            if (!Array.isArray(titles) || titles.length < 1) return;
            var padded = titles.slice(0, 4);
            while (padded.length < 4) padded.push({ title: '', score: null });
            currentAIGeneratedTitles80 = padded.map(function(t) { return (t && typeof t === 'string' ? t : (t.title || '')) || ''; });
            const minLen = 75;
            const maxLen = 85;
            const optionIds = ['aiTitle80Option1', 'aiTitle80Option2', 'aiTitle80Option3', 'aiTitle80Option4'];
            const warningEl = document.getElementById('aiTitle80Warning');
            if (warningEl) {
                if (invalidCount > 0) {
                    const validN = titles.length;
                    warningEl.textContent = validN + ' title(s) generated, ' + invalidCount + ' was out of range (75-85).';
                    warningEl.classList.remove('d-none');
                } else {
                    warningEl.classList.add('d-none');
                }
            }
            optionIds.forEach(function(id, i) {
                const opt = document.getElementById(id);
                if (!opt) return;
                const item = padded[i];
                const title = currentAIGeneratedTitles80[i] || '';
                const hasTitle = title.length > 0;
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const inRange = len >= minLen && len <= maxLen;
                const textEl = opt.querySelector('.ai-title80-text');
                const scoreEl = opt.querySelector('.ai-title80-score');
                const badgeEl = opt.querySelector('.ai-char80-badge');
                const statusEl = opt.querySelector('.ai-char80-status');
                const keepBtn = opt.querySelector('.ai-keep-btn-80');
                if (hasTitle) {
                    opt.style.display = '';
                    if (textEl) textEl.textContent = title;
                    if (scoreEl) {
                        if (score != null) scoreEl.textContent = 'Score: ' + score + '%';
                        else scoreEl.textContent = '';
                    }
                    if (badgeEl) {
                        badgeEl.textContent = len + '/80 chars';
                        let badgeClass = 'badge ai-char80-badge ';
                        if (inRange) badgeClass += 'bg-success';
                        else badgeClass += 'bg-danger';
                        badgeEl.className = badgeClass;
                    }
                    if (statusEl) {
                        statusEl.textContent = inRange ? '✅ Within 75-85' : '❌ Out of range';
                        statusEl.className = 'ai-char80-status ' + (inRange ? 'text-success' : 'text-danger');
                    }
                    if (keepBtn) { keepBtn.disabled = false; keepBtn.style.display = ''; }
                } else {
                    opt.style.display = 'none';
                    if (keepBtn) keepBtn.disabled = true;
                }
            });
            if (aiTitle80ModalInstance) aiTitle80ModalInstance.show();
        }

        function showTitle60Popup(titles, invalidCount) {
            invalidCount = invalidCount || 0;
            if (!Array.isArray(titles) || titles.length < 1) return;
            var padded = titles.slice(0, 4);
            while (padded.length < 4) padded.push({ title: '', score: null });
            currentAIGeneratedTitles60 = padded.map(function(t) { return (t && typeof t === 'string' ? t : (t.title || '')) || ''; });
            const minLen = 55;
            const maxLen = 60;
            const optionIds = ['aiTitle60Option1', 'aiTitle60Option2', 'aiTitle60Option3', 'aiTitle60Option4'];
            const warningEl = document.getElementById('aiTitle60Warning');
            if (warningEl) {
                if (invalidCount > 0) {
                    warningEl.textContent = titles.length + ' title(s) generated, ' + invalidCount + ' out of range (55-60).';
                    warningEl.classList.remove('d-none');
                } else {
                    warningEl.classList.add('d-none');
                }
            }
            optionIds.forEach(function(id, i) {
                const opt = document.getElementById(id);
                if (!opt) return;
                const item = padded[i];
                const title = currentAIGeneratedTitles60[i] || '';
                const hasTitle = title.length > 0;
                const score = item && typeof item === 'object' && item.score != null ? item.score : null;
                const len = title.length;
                const textEl = opt.querySelector('.ai-title60-text');
                const scoreEl = opt.querySelector('.ai-title60-score');
                const badgeEl = opt.querySelector('.ai-char60-badge');
                const statusEl = opt.querySelector('.ai-char60-status');
                const keepBtn = opt.querySelector('.ai-keep-btn-60');
                if (hasTitle) {
                    opt.style.display = '';
                    if (textEl) textEl.textContent = title;
                    if (scoreEl) scoreEl.textContent = score != null ? ('Score: ' + score + '%') : '';
                    if (badgeEl) {
                        badgeEl.textContent = len + '/60 chars';
                        badgeEl.className = 'ai-char60-badge badge ' + ((len >= minLen && len <= maxLen) ? 'bg-success' : 'bg-danger');
                    }
                    if (statusEl) {
                        statusEl.textContent = (len >= minLen && len <= maxLen) ? '✅ In range' : '❌ Out of range';
                        statusEl.className = 'ai-char60-status ' + ((len >= minLen && len <= maxLen) ? 'text-success' : 'text-danger');
                    }
                    if (keepBtn) { keepBtn.disabled = false; keepBtn.style.display = ''; }
                } else {
                    opt.style.display = 'none';
                    if (keepBtn) keepBtn.disabled = true;
                }
            });
            if (aiTitle60ModalInstance) aiTitle60ModalInstance.show();
        }

        function updateTitleMasterSortUi() {
            document.querySelectorAll('#title-master-table thead th[data-tm-sort]').forEach(function(th) {
                const col = th.getAttribute('data-tm-sort');
                const icon = th.querySelector('.title-master-sort-icon');
                th.classList.remove('title-master-sort-active');
                if (icon) {
                    icon.classList.remove('fa-sort-up', 'fa-sort-down');
                    icon.classList.add('fa-sort');
                }
                if (col && titleMasterSort.column === col && icon) {
                    th.classList.add('title-master-sort-active');
                    icon.classList.remove('fa-sort');
                    icon.classList.add(titleMasterSort.dir === 'desc' ? 'fa-sort-down' : 'fa-sort-up');
                }
            });
        }

        function setupTitleMasterColumnSort() {
            document.querySelectorAll('#title-master-table thead th[data-tm-sort]').forEach(function(th) {
                th.addEventListener('click', function() {
                    const col = th.getAttribute('data-tm-sort');
                    if (!col) return;
                    if (col === 'cvr' && !window.titleMasterHasPricingCvrSnapshot) return;
                    if (titleMasterSort.column === col) {
                        titleMasterSort.dir = titleMasterSort.dir === 'asc' ? 'desc' : 'asc';
                    } else {
                        titleMasterSort.column = col;
                        titleMasterSort.dir = 'asc';
                    }
                    updateTitleMasterSortUi();
                    loadTitleData(1);
                });
            });
        }

        function buildTitleMasterQueryParams(forPage) {
            const params = new URLSearchParams();
            const perPage = document.getElementById('perPageSelect')?.value || 75;
            params.set('per_page', String(perPage));
            params.set('page', String(forPage != null ? forPage : 1));
            params.set('tm_sort', titleMasterSort.column);
            params.set('tm_dir', titleMasterSort.dir);
            const qParent = normalizeForTextSearch(document.getElementById('parentSearch')?.value || '');
            const qSku = normalizeForTextSearch(document.getElementById('skuSearch')?.value || '');
            if (qParent) params.set('q_parent', qParent);
            if (qSku) params.set('q_sku', qSku);
            const f150 = document.getElementById('filterTitle150')?.value;
            const f100 = document.getElementById('filterTitle100')?.value;
            const f80 = document.getElementById('filterTitle80')?.value;
            const f60 = document.getElementById('filterTitle60')?.value;
            if (f150 && f150 !== 'all') params.set('filter_title150', f150);
            if (f100 && f100 !== 'all') params.set('filter_title100', f100);
            if (f80 && f80 !== 'all') params.set('filter_title80', f80);
            if (f60 && f60 !== 'all') params.set('filter_title60', f60);
            const fInv = document.getElementById('filterTitleInv')?.value || 'gt_zero';
            params.set('filter_inv', fInv);
            return params;
        }

        function updateCountsFromStats(stats) {
            if (!stats) return;
            document.getElementById('parentCount').textContent = '(' + (stats.distinct_parents != null ? stats.distinct_parents : 0) + ')';
            document.getElementById('skuCount').textContent = '(' + (stats.total_rows != null ? stats.total_rows : 0) + ')';
            document.getElementById('title150MissingCount').textContent = '(' + (stats.title150_missing != null ? stats.title150_missing : 0) + ')';
            document.getElementById('title100MissingCount').textContent = '(' + (stats.title100_missing != null ? stats.title100_missing : 0) + ')';
            document.getElementById('title80MissingCount').textContent = '(' + (stats.title80_missing != null ? stats.title80_missing : 0) + ')';
            document.getElementById('title60MissingCount').textContent = '(' + (stats.title60_missing != null ? stats.title60_missing : 0) + ')';
        }

        function renderPagination() {
            const info = document.getElementById('tmPageInfo');
            const ul = document.getElementById('tmPagination');
            if (!info || !ul) return;
            const cur = listMeta.current_page || 1;
            const last = listMeta.last_page || 1;
            const total = listMeta.total || 0;
            const from = listMeta.from;
            const to = listMeta.to;
            info.textContent = (from != null && to != null && total != null)
                ? ('Showing ' + from + '–' + to + ' of ' + total)
                : ('Page ' + cur + ' of ' + last);
            let html = '';
            const addLi = function(label, page, disabled, active) {
                html += '<li class="page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '') + '">';
                html += '<a class="page-link" href="#" data-tm-page="' + page + '">' + label + '</a></li>';
            };
            addLi('«', cur - 1, cur <= 1, false);
            const windowSize = 5;
            let start = Math.max(1, cur - Math.floor(windowSize / 2));
            let end = Math.min(last, start + windowSize - 1);
            start = Math.max(1, end - windowSize + 1);
            for (let i = start; i <= end; i++) addLi(String(i), i, false, i === cur);
            addLi('»', cur + 1, cur >= last, false);
            ul.innerHTML = html;
            ul.querySelectorAll('a.page-link').forEach(function(a) {
                a.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    const pg = parseInt(a.getAttribute('data-tm-page'), 10);
                    if (!pg || pg < 1 || pg > last || pg === cur) return;
                    loadTitleData(pg);
                });
            });
        }

        function loadTitleData(page) {
            if (titleMasterLoadAbort) {
                try { titleMasterLoadAbort.abort(); } catch (e) {}
            }
            titleMasterLoadAbort = new AbortController();
            const p = page != null ? page : 1;
            const params = buildTitleMasterQueryParams(p);
            document.getElementById('rainbow-loader').style.display = 'block';

            fetch((window.titleMasterDataUrl || '/title-master-data') + '?' + params.toString(), {
                signal: titleMasterLoadAbort.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(function(response) {
                    const data = response.data;
                    if (data && Array.isArray(data)) {
                        tableData = data;
                        listMeta = response.meta ? Object.assign({}, listMeta, response.meta) : listMeta;
                        if (response.meta) {
                            if (typeof response.meta.tm_sort === 'string' && response.meta.tm_sort) {
                                titleMasterSort.column = response.meta.tm_sort;
                            }
                            if (response.meta.tm_dir === 'asc' || response.meta.tm_dir === 'desc') {
                                titleMasterSort.dir = response.meta.tm_dir;
                            }
                        }
                        updateTitleMasterSortUi();
                        renderTable(tableData);
                        updateCountsFromStats(response.stats);
                        renderPagination();
                    } else {
                        console.error('Invalid data:', response);
                        showError('Invalid data format received from server');
                    }
                    document.getElementById('rainbow-loader').style.display = 'none';
                })
                .catch(function(error) {
                    if (error.name === 'AbortError') return;
                    console.error('Error:', error);
                    showError('Failed to load product data: ' + error.message);
                    document.getElementById('rainbow-loader').style.display = 'none';
                });
        }

        function escapeTooltipAttr(text) {
            return String(text == null ? '' : text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        }

        /**
         * Previously instantiated Bootstrap Tooltip on every marketplace cell (1000+ rows × many buttons),
         * which blocked the main thread for minutes. Native `title` attributes already provide hover text.
         */
        function initMarketplaceTooltips(root) {
            return;
        }

        function renderMarketplaceDots(sku, statusMap) {
            const mps = ['amazon', 'temu', 'reverb'];
            const labels = { amazon: 'Amazon', temu: 'Temu', reverb: 'Reverb' };
            let html = '<div class="marketplaces-dots" data-sku="' + (sku || '') + '">';
            mps.forEach(function(mp) {
                const st = (statusMap && statusMap[mp]) ? statusMap[mp] : 'pending';
                const title = labels[mp] + ': ' + (st === 'success' ? 'Pushed' : st === 'failed' ? 'Failed' : st === 'loading' ? 'In progress...' : 'Not pushed');
                html += '<span class="mp-dot marketplace-tooltip ' + mp + ' ' + st + '" data-marketplace="' + mp + '" title="' + escapeTooltipAttr(title) + '"></span>';
            });
            html += '</div>';
            return html;
        }

        function renderMarketplaceDots80(sku, statusMap) {
            const mps = ['ebay1', 'ebay2', 'ebay3'];
            const labels = { ebay1: 'eBay 1 (AmarjitK)', ebay2: 'eBay 2 (ProLight)', ebay3: 'eBay 3 (KaneerKa)' };
            let html = '<div class="marketplaces-dots" data-sku="' + (sku || '') + '">';
            mps.forEach(function(mp) {
                const st = (statusMap && statusMap[mp]) ? statusMap[mp] : 'pending';
                const title = labels[mp] + ': ' + (st === 'success' ? 'Pushed' : st === 'failed' ? 'Failed' : st === 'loading' ? 'In progress...' : 'Not pushed');
                html += '<span class="mp-dot marketplace-tooltip ' + mp + ' ' + st + '" data-marketplace="' + mp + '" title="' + escapeTooltipAttr(title) + '"></span>';
            });
            html += '</div>';
            return html;
        }

        function renderMarketplaceDots100(sku, statusMap) {
            const mps = ['shopify_main', 'shopify_pls', 'macy'];
            const labels = { shopify_main: 'Shopify Main', shopify_pls: 'Shopify PLS', macy: "Macy's" };
            let html = '<div class="marketplaces-dots" data-sku="' + (sku || '') + '">';
            mps.forEach(function(mp) {
                const st = (statusMap && statusMap[mp]) ? statusMap[mp] : 'pending';
                const title = labels[mp] + ': ' + (st === 'success' ? 'Pushed' : st === 'failed' ? 'Failed' : st === 'loading' ? 'In progress...' : 'Not pushed');
                html += '<span class="mp-dot marketplace-tooltip ' + mp + ' ' + st + '" data-marketplace="' + mp + '" title="' + escapeTooltipAttr(title) + '"></span>';
            });
            html += '</div>';
            return html;
        }

        function updateMarketplaceDotsInRow(row, results) {
            const wrapper = row.querySelector('.marketplaces-150-cell .marketplaces-dots-wrapper');
            if (!wrapper || !results) return;
            const btn = row.querySelector('.push-all-marketplaces-btn');
            const skuVal = (btn && btn.getAttribute('data-sku')) ? btn.getAttribute('data-sku') : '';
            const statusMap = {};
            ['amazon', 'temu', 'reverb'].forEach(function(mp) {
                statusMap[mp] = (results[mp] && results[mp].status) ? results[mp].status : 'pending';
            });
            wrapper.innerHTML = renderMarketplaceDots(skuVal, statusMap);
            initMarketplaceTooltips(wrapper);
        }

        function updateMarketplaceDots100InRow(row, results) {
            const wrapper100 = row.querySelector('.marketplaces-dots-100');
            if (!wrapper100 || !results) return;
            const btn = row.querySelector('.push-all-marketplaces-btn');
            const skuVal = (btn && btn.getAttribute('data-sku')) ? btn.getAttribute('data-sku') : '';
            const statusMap100 = {};
            ['shopify_main', 'shopify_pls', 'macy'].forEach(function(mp) {
                statusMap100[mp] = (results[mp] && results[mp].status) ? results[mp].status : 'pending';
            });
            wrapper100.innerHTML = renderMarketplaceDots100(skuVal, statusMap100);
            initMarketplaceTooltips(wrapper100);
        }

        function titleMasterGetTitle170Text(item) {
            if (!item) return '';
            const a = item.amazon_title;
            if (a != null && String(a).trim() !== '') return String(a);
            const t = item.title150;
            if (t != null && String(t).trim() !== '') return String(t);
            return '';
        }

        function titleMasterFillTitleDotCell(td, hasData, emptyTooltip, fullText) {
            td.className = 'title-master-title-dot-td';
            td.innerHTML = '<span class="title-master-title-dot ' + (hasData ? 'title-master-title-dot--has' : 'title-master-title-dot--empty') + '" role="img" aria-label="' + (hasData ? 'Has title' : 'No title') + '"></span>';
            td.title = hasData ? fullText : emptyTooltip;
        }

        const titleMasterDarkMustard = '#ff9c00';
        function styleForTitleMasterCvrColor(c) {
            if (!c) return 'font-weight:600;';
            if (c === '#ffc107') return 'color:' + titleMasterDarkMustard + ';font-weight:600;';
            return 'color:' + c + ';font-weight:600;';
        }
        /** INV / Dil% / CVR% — same daily snapshot as /pricing-master-cvr (pricing_master_daily_snapshots_sku). */
        const titleMasterPmcvrMissingTip = 'No data: refresh /pricing-master-cvr once to save a snapshot, or link this SKU in Shopify / stock mappings. CVR% only comes from that snapshot.';
        function formatTitleMasterInvCell(item) {
            const v = item.pricing_cvr_inventory;
            if (v === null || v === undefined) {
                return '<span class="text-muted" title="' + titleMasterPmcvrMissingTip.replace(/"/g, '&quot;') + '">—</span>';
            }
            const n = parseInt(v, 10) || 0;
            const numHtml = n === 0
                ? '<span style="color:#dc3545;font-weight:600;">0</span>'
                : '<span style="font-weight:600;">' + n + '</span>';
            return numHtml + ' <i class="fas fa-circle ms-1" style="color:#4361ee;font-size:7px;vertical-align:middle;" aria-hidden="true" title="Pricing Master CVR snapshot"></i>';
        }
        function formatTitleMasterDilCell(item) {
            const v = item.pricing_cvr_dil_percent;
            if (v === null || v === undefined) {
                return '<span class="text-muted" title="' + titleMasterPmcvrMissingTip.replace(/"/g, '&quot;') + '">—</span>';
            }
            const value = parseFloat(v) || 0;
            let color = '#6c757d';
            if (value === 0) color = '#6c757d';
            else if (value < 16.7) color = '#a00211';
            else if (value >= 16.7 && value < 25) color = '#ffc107';
            else if (value >= 25 && value < 50) color = '#28a745';
            else color = '#e83e8c';
            const html = '<span style="' + styleForTitleMasterCvrColor(color) + '">' + Math.round(value) + '%</span>';
            return html + ' <i class="fas fa-circle ms-1" style="color:#0d6efd;font-size:7px;vertical-align:middle;" aria-hidden="true" title="Pricing Master CVR snapshot"></i>';
        }
        function formatTitleMasterCvrCell(item) {
            const v = item.pricing_cvr_avg_cvr;
            if (v === null || v === undefined) {
                return '<span class="text-muted" title="' + titleMasterPmcvrMissingTip.replace(/"/g, '&quot;') + '">—</span>';
            }
            const value = parseFloat(v) || 0;
            let color = '#6c757d';
            if (value === 0) color = '#6c757d';
            else if (value < 1) color = '#a00211';
            else if (value >= 1 && value < 3) color = '#ffc107';
            else if (value >= 3 && value < 5) color = '#28a745';
            else color = '#e83e8c';
            const html = '<span style="' + styleForTitleMasterCvrColor(color) + '">' + String(Math.round(value)) + '%</span>';
            return html + ' <i class="fas fa-circle ms-1" style="color:#ff9c00;font-size:7px;vertical-align:middle;" aria-hidden="true" title="Pricing Master CVR snapshot"></i>';
        }
        const titleMasterLqsMissingTip = 'No Jungle Scout row for this SKU/parent, or listing_quality_score missing in junglescout_product_data.data.';
        function formatTitleMasterLqsCell(item) {
            const v = item.lqs;
            if (v === null || v === undefined) {
                return '<span class="text-muted" title="' + titleMasterLqsMissingTip.replace(/"/g, '&quot;') + '">—</span>';
            }
            const value = parseFloat(v);
            if (Number.isNaN(value)) {
                return '<span class="text-muted" title="' + titleMasterLqsMissingTip.replace(/"/g, '&quot;') + '">—</span>';
            }
            let color = '#6c757d';
            if (value < 4) color = '#a00211';
            else if (value < 6) color = '#ffc107';
            else if (value < 8) color = '#28a745';
            else color = '#157347';
            return '<span style="' + styleForTitleMasterCvrColor(color) + '">' + String(Math.round(value)) + '</span>'
                + ' <i class="fas fa-leaf ms-1" style="color:#2d6a4f;font-size:7px;vertical-align:middle;" aria-hidden="true" title="Jungle Scout LQS"></i>';
        }

        const titleMasterBsMissingTip = 'No buyer/seller links in amazon_data_view for this SKU (same source as Amazon FBM tabulator).';
        function formatTitleMasterBsCell(item) {
            const b = item.amazon_buyer_link;
            const s = item.amazon_seller_link;
            const bOk = b != null && String(b).trim() !== '';
            const sOk = s != null && String(s).trim() !== '';
            if (!bOk && !sOk) {
                return '<span class="text-muted" title="' + titleMasterBsMissingTip.replace(/"/g, '&quot;') + '">—</span>';
            }
            let html = '<div style="display:flex;flex-direction:column;gap:3px;align-items:center;">';
            if (sOk) {
                html += '<a href="' + escapeHtml(String(s).trim()) + '" target="_blank" rel="noopener noreferrer" class="text-info" title="Seller (Seller Central)">S</a>';
            }
            if (bOk) {
                html += '<a href="' + escapeHtml(String(b).trim()) + '" target="_blank" rel="noopener noreferrer" class="text-success" title="Buyer (Amazon listing)">B</a>';
            }
            html += '</div>';
            return html;
        }

        function renderTable(data) {
            const tbody = document.getElementById('table-body');
            const frag = document.createDocumentFragment();

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="19" class="text-center">No products found</td></tr>';
                return;
            }

            // Filter out parent rows before rendering
            const filteredData = data.filter(item => {
                return !(item.SKU && item.SKU.toUpperCase().includes('PARENT'));
            });

            if (filteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="19" class="text-center">No products found</td></tr>';
                return;
            }

            filteredData.forEach(item => {
                const row = document.createElement('tr');
                if (item.SKU) {
                    row.setAttribute('data-sku', item.SKU);
                }

                // Checkbox
                const checkboxCell = document.createElement('td');
                checkboxCell.innerHTML = '<input type="checkbox" class="row-checkbox" data-sku="' + escapeHtml(item.SKU) + '">';
                row.appendChild(checkboxCell);

                // Images
                const imageCell = document.createElement('td');
                imageCell.className = 'table-img-cell';
                imageCell.innerHTML = item.image_path
                    ? '<img src="' + escapeHtml(item.image_path) + '" alt="" loading="lazy" decoding="async">'
                    : '-';
                row.appendChild(imageCell);

                // Parent
                const parentCell = document.createElement('td');
                parentCell.textContent = (item.Parent != null && item.Parent !== '') ? String(item.Parent) : '-';
                row.appendChild(parentCell);

                // SKU
                const skuCell = document.createElement('td');
                skuCell.textContent = (item.SKU != null && item.SKU !== '') ? String(item.SKU) : '-';
                row.appendChild(skuCell);

                const bsCell = document.createElement('td');
                bsCell.className = 'title-master-bs-td';
                bsCell.innerHTML = formatTitleMasterBsCell(item);
                row.appendChild(bsCell);

                const invCell = document.createElement('td');
                invCell.className = 'title-master-pmcvr-td';
                invCell.innerHTML = formatTitleMasterInvCell(item);
                row.appendChild(invCell);

                const dilCell = document.createElement('td');
                dilCell.className = 'title-master-pmcvr-td';
                dilCell.innerHTML = formatTitleMasterDilCell(item);
                row.appendChild(dilCell);

                const cvrCell = document.createElement('td');
                cvrCell.className = 'title-master-pmcvr-td';
                cvrCell.innerHTML = formatTitleMasterCvrCell(item);
                row.appendChild(cvrCell);

                const lqsCell = document.createElement('td');
                lqsCell.className = 'title-master-pmcvr-td';
                lqsCell.innerHTML = formatTitleMasterLqsCell(item);
                row.appendChild(lqsCell);

                const aiCell = document.createElement('td');
                aiCell.className = 'title-master-ai-td';
                aiCell.innerHTML = '<button type="button" class="title-master-ai-stack-btn tm-ai-stack-open-btn" data-sku="' + escapeHtml(item.SKU) + '" title="AI workspace: Title 170 + Title 100/80/60 refs + 3 drafts (150–175 chars when AI-generated)" aria-label="Open AI workspace for this SKU">' +
                    '<i class="fas fa-wand-magic-sparkles text-info" aria-hidden="true"></i>' +
                    '</button>';
                row.appendChild(aiCell);

                const t170 = titleMasterGetTitle170Text(item);
                const title150Cell = document.createElement('td');
                titleMasterFillTitleDotCell(title150Cell, t170.trim() !== '', 'No Title 170', t170);
                row.appendChild(title150Cell);

                const t100 = item.title100 != null ? String(item.title100) : '';
                const title100Cell = document.createElement('td');
                titleMasterFillTitleDotCell(title100Cell, t100.trim() !== '', 'No Title 100', t100);
                row.appendChild(title100Cell);

                const t80 = item.title80 != null ? String(item.title80) : '';
                const title80Cell = document.createElement('td');
                titleMasterFillTitleDotCell(title80Cell, t80.trim() !== '', 'No Title 80', t80);
                row.appendChild(title80Cell);

                const t60 = item.title60 != null ? String(item.title60) : '';
                const title60Cell = document.createElement('td');
                titleMasterFillTitleDotCell(title60Cell, t60.trim() !== '', 'No Title 60', t60);
                row.appendChild(title60Cell);

                // View column (header: eye icon)
                const actionCell = document.createElement('td');
                actionCell.className = 'action-buttons-cell';
                actionCell.innerHTML = '<div class="action-buttons-group">' +
                    '<button type="button" class="action-btn view-btn" data-sku="' + escapeHtml(item.SKU) + '" title="View title details" aria-label="View title details"><i class="fas fa-eye" aria-hidden="true"></i></button>' +
                    '</div>';
                row.appendChild(actionCell);

                // MARKET (150): Amazon, Temu, Reverb only
                const marketplaces150Cell = document.createElement('td');
                marketplaces150Cell.className = 'marketplaces-cell marketplaces-150-cell';
                const skuEscaped = escapeHtml(item.SKU);
                const hasTitle150 = t170.trim() !== '';
                let mp150Html = '<div class="marketplaces-dots-wrapper">' +
                    renderMarketplaceDots(skuEscaped, null) +
                    '</div>';
                mp150Html += '<div class="marketplace-buttons">';
                mp150Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-150 btn-amazon" data-sku="' + skuEscaped + '" data-marketplace="amazon" data-title-type="150" title="Amazon (Title 170)" ' + (hasTitle150 ? '' : 'disabled') + '><i class="fab fa-amazon"></i></button>';
                mp150Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-150 btn-temu" data-sku="' + skuEscaped + '" data-marketplace="temu" data-title-type="150" title="Temu (Title 170)" ' + (hasTitle150 ? '' : 'disabled') + '>T</button>';
                mp150Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-150 btn-reverb" data-sku="' + skuEscaped + '" data-marketplace="reverb" data-title-type="150" title="Reverb (Title 170)" ' + (hasTitle150 ? '' : 'disabled') + '><i class="fas fa-guitar"></i></button>';
                mp150Html += '</div>';
                marketplaces150Cell.innerHTML = mp150Html;
                row.appendChild(marketplaces150Cell);

                // MARKET (100): Shopify Main, PLS + Macy's (Title 60 push)
                const marketplaces100Cell = document.createElement('td');
                marketplaces100Cell.className = 'marketplaces-100-cell';
                const hasTitle100 = !!(item.title100 && String(item.title100).trim() !== '');
                const hasTitle60 = !!(item.title60 && String(item.title60).trim() !== '');
                let mp100Html = '<div class="marketplaces-dots-wrapper marketplaces-dots-100" data-sku="' + skuEscaped + '">' + renderMarketplaceDots100(skuEscaped, null) + '</div>';
                mp100Html += '<div class="marketplace-buttons">';
                mp100Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-100 btn-shopify-pls" data-sku="' + skuEscaped + '" data-marketplace="shopify_pls" data-title-type="100" title="Push Title 100 to ProLight Sounds" ' + (hasTitle100 ? '' : 'disabled') + '>PLS</button>';
                mp100Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-100 btn-shopify-main" data-sku="' + skuEscaped + '" data-marketplace="shopify_main" data-title-type="100" title="Push Title 100 to Main Shopify" ' + (hasTitle100 ? '' : 'disabled') + '><i class="fab fa-shopify"></i></button>';
                mp100Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-100 btn-macy" data-sku="' + skuEscaped + '" data-marketplace="macy" data-title-type="60" title="Push Title 60 to Macy&#39;s" ' + (hasTitle60 ? '' : 'disabled') + '>M</button>';
                mp100Html += '</div>';
                marketplaces100Cell.innerHTML = mp100Html;
                row.appendChild(marketplaces100Cell);

                // MARKET (80): status dots + E1, E2, E3 buttons (eBay 1, 2, 3)
                const marketplaces80Cell = document.createElement('td');
                marketplaces80Cell.className = 'marketplaces-80-cell';
                const hasTitle80 = !!(item.title80 && String(item.title80).trim() !== '');
                let mp80Html = '<div class="marketplaces-dots-wrapper marketplaces-dots-80" data-sku="' + skuEscaped + '">' + renderMarketplaceDots80(skuEscaped, null) + '</div>';
                mp80Html += '<div class="marketplace-buttons">';
                mp80Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-80 btn-ebay1" data-sku="' + skuEscaped + '" data-marketplace="ebay1" data-title-type="80" title="Push Title 80 to eBay Account 1 (AmarjitK)" ' + (hasTitle80 ? '' : 'disabled') + '>E1</button>';
                mp80Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-80 btn-ebay2" data-sku="' + skuEscaped + '" data-marketplace="ebay2" data-title-type="80" title="Push Title 80 to eBay Account 2 (ProLight)" ' + (hasTitle80 ? '' : 'disabled') + '>E2</button>';
                mp80Html += '<button type="button" class="marketplace-btn marketplace-tooltip marketplace-btn-80 btn-ebay3" data-sku="' + skuEscaped + '" data-marketplace="ebay3" data-title-type="80" title="Push Title 80 to eBay Account 3 (KaneerKa)" ' + (hasTitle80 ? '' : 'disabled') + '>E3</button>';
                mp80Html += '</div>';
                marketplaces80Cell.innerHTML = mp80Html;
                row.appendChild(marketplaces80Cell);

                // Distribute to all markets column
                const pushCell = document.createElement('td');
                pushCell.className = 'push-button-cell';
                pushCell.innerHTML = '<button type="button" class="action-btn push-amazon-btn push-all-marketplaces-btn" data-sku="' + escapeHtml(item.SKU) + '" title="Push Title 170 to Amazon, Temu, Reverb" aria-label="Push Title 170 to Amazon, Temu, Reverb"><img src="' + escapeHtml(TM_PUSH_ALL_ICON_URL) + '" alt="" class="tm-push-all-icon" width="20" height="20"></button>';
                row.appendChild(pushCell);

                frag.appendChild(row);
            });

            tbody.innerHTML = '';
            tbody.appendChild(frag);

            setupViewButtons();
            setupTmAiStackButtons();
            setupPushAmazonButtons();
            setupIndividualMarketplaceButtons();
            updateSelectedCount();
            initMarketplaceTooltips(document.getElementById('title-master-table'));
        }

        const marketplaceLabels = {
            amazon: 'Amazon',
            temu: 'Temu',
            reverb: 'Reverb',
            wayfair: 'Wayfair',
            walmart: 'Walmart',
            shopify: 'Shopify',
            shopify_main: 'Shopify',
            shopify_pls: 'PLS',
            doba: 'Doba',
            ebay1: 'eBay 1 (AmarjitK)',
            ebay2: 'eBay 2 (ProLight)',
            ebay3: 'eBay 3 (KaneerKa)',
            macy: "Macy's",
            faire: 'Faire',
        };

        function setupIndividualMarketplaceButtons() {
            document.querySelectorAll('.marketplace-btn-150, .marketplace-btn-100, .marketplace-btn-80, .marketplace-btn-60').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const button = this;
                    const sku = button.getAttribute('data-sku');
                    const marketplace = button.getAttribute('data-marketplace');
                    const titleType = button.getAttribute('data-title-type'); // '150', '100', or '80'
                    const marketplaceName = marketplaceLabels[marketplace] || marketplace.toUpperCase();

                    const item = tableData.find(x => x.SKU === sku);
                    let title = '';
                    if (item) {
                        if (titleType === '150') title = titleMasterGetTitle170Text(item);
                        else if (titleType === '100') title = item.title100 || '';
                        else if (titleType === '80') title = item.title80 || '';
                        else if (titleType === '60') title = item.title60 || '';
                    }

                    if (!title || String(title).trim() === '') {
                        if (typeof showToast === 'function') {
                            showToast('error', `No Title ${titleType} available for SKU ${sku}.`);
                        } else {
                            alert(`No Title ${titleType} available for SKU ${sku}.`);
                        }
                        return;
                    }

                    if (marketplace === 'ebay3') {
                        if (!confirm('Warning! This is a Variation Platform, ARE YOU SURE?')) {
                            return;
                        }
                    }

                    const originalHtml = button.innerHTML;
                    button.disabled = true;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';

                    console.log(`🖱️ Pushing ${titleType} to ${marketplaceName}`, { sku, title: String(title).substring(0, 50) });

                    if (typeof showToast === 'function') {
                        // 0 duration hint for persistent loading toast; ignored if not supported
                        showToast('info', `⏳ Pushing Title ${titleType} to ${marketplaceName}...`, 0);
                    }

                    const row = button.closest('tr');

                    fetch('/api/marketplaces/push-single', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: sku,
                            marketplace: marketplace,
                            title_type: titleType,
                            title: title
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (typeof showToast === 'function') {
                                    showToast('success', `✅ ${marketplaceName} (Title ${titleType}) updated for ${sku}`);
                                }
                                console.log('✅ Push successful', data);
                                if (data.statuses && row) {
                                    if (marketplace === 'ebay1' || marketplace === 'ebay2' || marketplace === 'ebay3') {
                                        const wrapper80 = row.querySelector('.marketplaces-dots-80');
                                        if (wrapper80) {
                                            const statusMap80 = {};
                                            ['ebay1', 'ebay2', 'ebay3'].forEach(function(mp) {
                                                statusMap80[mp] = (data.statuses[mp] && data.statuses[mp].status) ? data.statuses[mp].status : 'pending';
                                            });
                                            wrapper80.innerHTML = renderMarketplaceDots80(sku, statusMap80);
                                            initMarketplaceTooltips(wrapper80);
                                        }
                                    } else if (marketplace === 'macy' || marketplace === 'shopify' || marketplace === 'shopify_main' || marketplace === 'shopify_pls') {
                                        updateMarketplaceDots100InRow(row, data.statuses);
                                    } else {
                                        updateMarketplaceDotsInRow(row, data.statuses);
                                    }
                                }
                            } else {
                                const msg = data.message || 'Unknown error';
                                if (typeof showToast === 'function') {
                                    showToast('error', `❌ ${marketplaceName} (Title ${titleType}) failed: ${msg}`);
                                }
                                console.error('❌ Push failed', data);
                            }
                        })
                        .catch(error => {
                            if (typeof showToast === 'function') {
                                showToast('error', `❌ ${marketplaceName} push error: ${error.message}`);
                            }
                            console.error('❌ Push error', error);
                        })
                        .finally(() => {
                            button.disabled = false;
                            button.innerHTML = originalHtml;
                        });
                });
            });
        }

        function setupViewButtons() {
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.getAttribute('data-sku');
                    openViewModal(sku);
                });
            });
        }

        function getTitleMasterTitle150ForAiStack(item) {
            if (!item) return '';
            const a = item.amazon_title;
            if (a != null && String(a).trim() !== '') return String(a);
            const t = item.title150;
            if (t != null && String(t).trim() !== '') return String(t);
            return '';
        }

        function openTmAiStackModal(sku) {
            if (!tmAiStackModalInstance) return;
            const item = tableData.find(function(x) { return x.SKU === sku; });
            const title150 = getTitleMasterTitle150ForAiStack(item);
            const buyerLink = item && item.amazon_buyer_link ? String(item.amazon_buyer_link).trim() : '';
            const skuEl = document.getElementById('tmAiStackSkuLabel');
            if (skuEl) skuEl.textContent = sku || '—';
            const refTa = document.getElementById('tmAiStackTitle150Ref');
            if (refTa) {
                refTa.value = title150;
            }
            const refCount = document.getElementById('tmAiStackTitle150RefCount');
            if (refCount) refCount.textContent = title150.length + ' chars';
            const t100 = item && item.title100 != null ? String(item.title100) : '';
            const t80 = item && item.title80 != null ? String(item.title80) : '';
            const t60 = item && item.title60 != null ? String(item.title60) : '';
            const ref100 = document.getElementById('tmAiStackTitle100Ref');
            const ref100Count = document.getElementById('tmAiStackTitle100RefCount');
            if (ref100) ref100.value = t100;
            if (ref100Count) ref100Count.textContent = t100.length + ' chars';
            const ref80 = document.getElementById('tmAiStackTitle80Ref');
            const ref80Count = document.getElementById('tmAiStackTitle80RefCount');
            if (ref80) ref80.value = t80;
            if (ref80Count) ref80Count.textContent = t80.length + ' chars';
            const ref60 = document.getElementById('tmAiStackTitle60Ref');
            const ref60Count = document.getElementById('tmAiStackTitle60RefCount');
            if (ref60) ref60.value = t60;
            if (ref60Count) ref60Count.textContent = t60.length + ' chars';
            const aiPromptTa = document.getElementById('tmAiStackAiPrompt');
            if (aiPromptTa) aiPromptTa.value = buildTmAiStackDefaultPrompt(sku || '', buyerLink);
            updateTmAiStackPromptSummary();
            const modalEl = document.getElementById('tmAiStackModal');
            if (modalEl) modalEl.dataset.tmBuyerLink = buyerLink;
            const aiAlert = document.getElementById('tmAiStackAiAlert');
            if (aiAlert) {
                aiAlert.textContent = '';
                aiAlert.classList.add('d-none');
            }
            [1, 2, 3].forEach(function(i) {
                const ta = document.getElementById('tmAiStackVariant' + i);
                const c = document.getElementById('tmAiStackVariant' + i + 'Count');
                if (ta) ta.value = '';
                if (c) c.textContent = '0/' + TM_AI_STACK_DRAFT_MAX;
            });
            tmAiStackModalInstance.show();
        }

        function setupTmAiStackButtons() {
            document.querySelectorAll('.tm-ai-stack-open-btn').forEach(function(btn) {
                btn.addEventListener('click', function(ev) {
                    ev.preventDefault();
                    const sku = btn.getAttribute('data-sku');
                    if (sku) openTmAiStackModal(sku);
                });
            });
        }

        function updateTmAiStackPromptSummary() {
            const ta = document.getElementById('tmAiStackAiPrompt');
            const sum = document.getElementById('tmAiStackPromptSummary');
            if (!ta || !sum) return;
            const n = ta.value.length;
            sum.title = ta.value ? (ta.value.slice(0, 800) + (ta.value.length > 800 ? '…' : '')) : 'No prompt — open the eye to edit';
            if (n === 0) {
                sum.textContent = 'No prompt';
            } else {
                sum.textContent = n.toLocaleString() + ' chars';
            }
        }

        function setupTmAiStackPromptEditor() {
            const openBtn = document.getElementById('tmAiStackPromptOpenBtn');
            const hiddenTa = document.getElementById('tmAiStackAiPrompt');
            const editorTa = document.getElementById('tmAiStackAiPromptEditor');
            const countEl = document.getElementById('tmAiStackAiPromptEditorCount');
            if (!openBtn || !hiddenTa || !editorTa) return;

            function updateEditorCount() {
                if (countEl) countEl.textContent = editorTa.value.length.toLocaleString() + ' / 15,000 characters';
            }

            openBtn.addEventListener('click', function() {
                editorTa.value = hiddenTa.value;
                updateEditorCount();
                if (tmAiStackPromptEditorModalInstance) {
                    tmAiStackPromptEditorModalInstance.show();
                }
            });

            editorTa.addEventListener('input', function() {
                hiddenTa.value = editorTa.value;
                updateEditorCount();
                updateTmAiStackPromptSummary();
            });

            const editorModalEl = document.getElementById('tmAiStackPromptEditorModal');
            if (editorModalEl) {
                editorModalEl.addEventListener('hidden.bs.modal', function() {
                    hiddenTa.value = editorTa.value;
                    updateTmAiStackPromptSummary();
                });
            }
        }

        function titleMasterEscapeSkuForSelector(sku) {
            if (sku == null || sku === '') return '';
            var s = String(sku);
            if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
                return CSS.escape(s);
            }
            return s.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
        }

        function getTmAiStackActiveSku() {
            var skuEl = document.getElementById('tmAiStackSkuLabel');
            var sku = skuEl ? String(skuEl.textContent || '').trim() : '';
            if (sku === '—' || sku === '\u2014') sku = '';
            return sku;
        }

        function tmAiStackSyncEditModalField(sku, fieldId, value) {
            var modalEl = document.getElementById('titleModal');
            if (!modalEl || !modalEl.classList.contains('show')) return;
            var editSku = document.getElementById('editSku');
            if (!editSku || String(editSku.value) !== String(sku)) return;
            var input = document.getElementById(fieldId);
            if (input) {
                input.value = value;
                if (typeof updateModalCounter === 'function') updateModalCounter(fieldId);
            }
        }

        function patchTitleMasterGridRowTitlesFromTableData(sku) {
            var item = (typeof tableData !== 'undefined' && tableData) ? tableData.find(function(x) { return x.SKU === sku; }) : null;
            var row = document.querySelector('#title-master-table tbody tr[data-sku="' + titleMasterEscapeSkuForSelector(sku) + '"]');
            if (!item || !row || !row.cells || row.cells.length < 14) return;
            var tds = row.cells;
            var t170p = titleMasterGetTitle170Text(item);
            var c170 = tds[10];
            if (c170) titleMasterFillTitleDotCell(c170, t170p.trim() !== '', 'No Title 170', t170p);
            var t100p = item.title100 != null ? String(item.title100) : '';
            var c100 = tds[11];
            if (c100) titleMasterFillTitleDotCell(c100, t100p.trim() !== '', 'No Title 100', t100p);
            var t80p = item.title80 != null ? String(item.title80) : '';
            var c80 = tds[12];
            if (c80) titleMasterFillTitleDotCell(c80, t80p.trim() !== '', 'No Title 80', t80p);
            var t60p = item.title60 != null ? String(item.title60) : '';
            var c60 = tds[13];
            if (c60) titleMasterFillTitleDotCell(c60, t60p.trim() !== '', 'No Title 60', t60p);
            var hasTitle150 = titleMasterGetTitle170Text(item).trim() !== '';
            var hasTitle100 = !!(item.title100 && String(item.title100).trim() !== '');
            var hasTitle80 = !!(item.title80 && String(item.title80).trim() !== '');
            var hasTitle60 = !!(item.title60 && String(item.title60).trim() !== '');
            row.querySelectorAll('.marketplace-btn-150').forEach(function(btn) { btn.disabled = !hasTitle150; });
            row.querySelectorAll('.marketplace-btn-100').forEach(function(btn) {
                var isMacy = btn.classList.contains('btn-macy');
                btn.disabled = isMacy ? !hasTitle60 : !hasTitle100;
            });
            row.querySelectorAll('.marketplace-btn-80').forEach(function(btn) { btn.disabled = !hasTitle80; });
        }

        function tmAiStackFlashApplyButton(btn) {
            var original = btn.getAttribute('data-tm-apply-original-html');
            if (!original) {
                original = btn.innerHTML;
                btn.setAttribute('data-tm-apply-original-html', original);
            }
            btn.innerHTML = '<span class="visually-hidden">Applied</span><i class="fas fa-check text-success" aria-hidden="true"></i>';
            setTimeout(function() {
                btn.innerHTML = btn.getAttribute('data-tm-apply-original-html') || original;
            }, 1400);
        }

        function tmAiStackNotifyApplied(msg) {
            if (typeof showToast === 'function') {
                showToast('success', msg);
            }
        }

        function tmAiStackApplyDraftToTitle170(draftIndex) {
            var sku = getTmAiStackActiveSku();
            if (!sku) {
                alert('No SKU for this workspace.');
                return false;
            }
            var ta = document.getElementById('tmAiStackVariant' + draftIndex);
            var text = ta && ta.value ? ta.value.trim() : '';
            if (!text) {
                alert('Draft ' + draftIndex + ' is empty.');
                return false;
            }
            if (text.length > TITLE_MASTER_AMAZON_TITLE_MAX) {
                text = text.substring(0, TITLE_MASTER_AMAZON_TITLE_MAX);
            }
            var refTa = document.getElementById('tmAiStackTitle150Ref');
            var refCount = document.getElementById('tmAiStackTitle150RefCount');
            if (refTa) refTa.value = text;
            if (refCount) refCount.textContent = text.length + ' chars';
            var idx = tableData.findIndex(function(x) { return x.SKU === sku; });
            if (idx !== -1) {
                tableData[idx].title150 = text;
                tableData[idx].amazon_title = text;
            }
            tmAiStackSyncEditModalField(sku, 'title150', text);
            patchTitleMasterGridRowTitlesFromTableData(sku);
            tmAiStackNotifyApplied('Title 170 updated in the grid. Use Add Title → Save to persist in the database.');
            return true;
        }

        function tmAiStackApplyRefFieldToGrid(fieldKey) {
            var sku = getTmAiStackActiveSku();
            if (!sku) {
                alert('No SKU for this workspace.');
                return false;
            }
            if (fieldKey === 'title150') {
                var ta150 = document.getElementById('tmAiStackTitle150Ref');
                var text150 = ta150 && ta150.value ? ta150.value.trim() : '';
                if (!text150) {
                    alert('Title 170 is empty.');
                    return false;
                }
                if (text150.length > TITLE_MASTER_AMAZON_TITLE_MAX) {
                    text150 = text150.substring(0, TITLE_MASTER_AMAZON_TITLE_MAX);
                    if (ta150) ta150.value = text150;
                }
                var c150 = document.getElementById('tmAiStackTitle150RefCount');
                if (c150) c150.textContent = text150.length + ' chars';
                var idx150 = tableData.findIndex(function(x) { return x.SKU === sku; });
                if (idx150 !== -1) {
                    tableData[idx150].title150 = text150;
                    tableData[idx150].amazon_title = text150;
                }
                tmAiStackSyncEditModalField(sku, 'title150', text150);
                patchTitleMasterGridRowTitlesFromTableData(sku);
                tmAiStackNotifyApplied('Title 170 updated in the grid. Use Add Title → Save to persist in the database.');
                return true;
            }
            var shortCfg = {
                title100: { refId: 'tmAiStackTitle100Ref', countId: 'tmAiStackTitle100RefCount', max: TITLE_MASTER_TITLE100_UI_MAX, editId: 'title100', label: 'Title 100' },
                title80: { refId: 'tmAiStackTitle80Ref', countId: 'tmAiStackTitle80RefCount', max: 80, editId: 'title80', label: 'Title 80' },
                title60: { refId: 'tmAiStackTitle60Ref', countId: 'tmAiStackTitle60RefCount', max: 60, editId: 'title60', label: 'Title 60' }
            };
            var sc = shortCfg[fieldKey];
            if (!sc) return false;
            var refEl = document.getElementById(sc.refId);
            var text = refEl && refEl.value ? refEl.value.trim() : '';
            if (text === '') {
                var ref170 = document.getElementById('tmAiStackTitle150Ref');
                var from170 = ref170 && ref170.value ? ref170.value.trim() : '';
                if (!from170) {
                    alert('This field is empty and Title 170 is empty. Type text or fill Title 170 first.');
                    return false;
                }
                text = from170.length > sc.max ? from170.substring(0, sc.max) : from170;
                if (refEl) refEl.value = text;
            } else if (text.length > sc.max) {
                text = text.substring(0, sc.max);
                if (refEl) refEl.value = text;
            }
            var countEl = document.getElementById(sc.countId);
            if (countEl) countEl.textContent = text.length + ' chars';
            var idx = tableData.findIndex(function(x) { return x.SKU === sku; });
            if (idx !== -1) {
                tableData[idx][fieldKey] = text;
            }
            tmAiStackSyncEditModalField(sku, sc.editId, text);
            patchTitleMasterGridRowTitlesFromTableData(sku);
            tmAiStackNotifyApplied(sc.label + ' updated in the grid. Use Add Title → Save to persist.');
            return true;
        }

        function setupTmAiStackRefFieldCharCounts() {
            [['tmAiStackTitle150Ref', 'tmAiStackTitle150RefCount'], ['tmAiStackTitle100Ref', 'tmAiStackTitle100RefCount'], ['tmAiStackTitle80Ref', 'tmAiStackTitle80RefCount'], ['tmAiStackTitle60Ref', 'tmAiStackTitle60RefCount']].forEach(function(pair) {
                var ta = document.getElementById(pair[0]);
                if (!ta) return;
                ta.addEventListener('input', function() {
                    var c = document.getElementById(pair[1]);
                    if (c) c.textContent = (ta.value ? ta.value.length : 0) + ' chars';
                });
            });
        }

        function setupTmAiStackApplyButtons() {
            var modal = document.getElementById('tmAiStackModal');
            if (!modal || modal.getAttribute('data-tm-apply-delegation') === '1') return;
            modal.setAttribute('data-tm-apply-delegation', '1');
            modal.addEventListener('click', function(ev) {
                var btn = ev.target.closest('.tm-ai-stack-apply-btn');
                if (!btn || !modal.contains(btn)) return;
                ev.preventDefault();
                var draftAttr = btn.getAttribute('data-tm-apply-draft');
                var refField = btn.getAttribute('data-tm-apply-ref-field');
                if (draftAttr !== null && draftAttr !== '') {
                    var di = parseInt(draftAttr, 10);
                    if (di >= 1 && di <= 3 && tmAiStackApplyDraftToTitle170(di)) {
                        tmAiStackFlashApplyButton(btn);
                    }
                } else if (refField === 'title150' || refField === 'title100' || refField === 'title80' || refField === 'title60') {
                    if (tmAiStackApplyRefFieldToGrid(refField)) {
                        tmAiStackFlashApplyButton(btn);
                    }
                }
            });
        }

        function setupTmAiStackGenerateButton() {
            const btn = document.getElementById('tmAiStackGenerateBtn');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (typeof window.titleMasterAiStackConfigured !== 'undefined' && !window.titleMasterAiStackConfigured) {
                    const alertEl = document.getElementById('tmAiStackAiAlert');
                    if (alertEl) {
                        alertEl.textContent = 'AI is not configured. Set CLAUDE_API_KEY or ANTHROPIC_API_KEY in .env (recommended), or OPENAI_API_KEY as fallback.';
                        alertEl.classList.remove('d-none');
                    } else {
                        alert('AI is not configured. Set CLAUDE_API_KEY or ANTHROPIC_API_KEY in .env (recommended), or OPENAI_API_KEY as fallback.');
                    }
                    return;
                }
                const refTa = document.getElementById('tmAiStackTitle150Ref');
                const titleRef = refTa && refTa.value ? refTa.value.trim() : '';
                if (!titleRef) {
                    const alertEl = document.getElementById('tmAiStackAiAlert');
                    if (alertEl) {
                        alertEl.textContent = 'Title 170 reference is empty. Open the AI workspace from a row that already has Title 170.';
                        alertEl.classList.remove('d-none');
                    } else {
                        alert('Title 170 reference is empty.');
                    }
                    return;
                }
                const aiPromptTa = document.getElementById('tmAiStackAiPrompt');
                const userPrompt = aiPromptTa ? aiPromptTa.value : '';
                const skuEl = document.getElementById('tmAiStackSkuLabel');
                let sku = skuEl ? String(skuEl.textContent || '').trim() : '';
                if (sku === '—' || sku === '\u2014') sku = '';
                const modalEl = document.getElementById('tmAiStackModal');
                const buyerLink = modalEl && modalEl.dataset.tmBuyerLink ? String(modalEl.dataset.tmBuyerLink) : '';
                const url = window.titleMasterAiStackDraftsUrl || '/title-master/ai/generate-ai-stack-drafts';
                const alertEl = document.getElementById('tmAiStackAiAlert');
                if (alertEl) {
                    alertEl.textContent = '';
                    alertEl.classList.add('d-none');
                }
                const spin = btn.querySelector('.tm-ai-stack-generate-spinner');
                const icon = btn.querySelector('.tm-ai-stack-generate-icon');
                btn.disabled = true;
                if (spin) spin.classList.remove('d-none');
                if (icon) icon.classList.add('d-none');

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        sku: sku,
                        buyer_link: buyerLink,
                        title_reference: titleRef,
                        user_prompt: userPrompt
                    })
                })
                    .then(function(response) {
                        return response.json().then(function(data) {
                            return { ok: response.ok, status: response.status, data: data };
                        }).catch(function() {
                            return { ok: false, status: response.status, data: { message: 'Invalid server response' } };
                        });
                    })
                    .then(function(res) {
                        btn.disabled = false;
                        if (spin) spin.classList.add('d-none');
                        if (icon) icon.classList.remove('d-none');
                        if (res.data && res.data.success && Array.isArray(res.data.drafts) && res.data.drafts.length >= 3) {
                            res.data.drafts.forEach(function(text, idx) {
                                const ta = document.getElementById('tmAiStackVariant' + (idx + 1));
                                if (ta) {
                                    ta.value = text != null ? String(text) : '';
                                    const c = document.getElementById('tmAiStackVariant' + (idx + 1) + 'Count');
                                    if (c) c.textContent = ta.value.length + '/' + TM_AI_STACK_DRAFT_MAX;
                                }
                            });
                            return;
                        }
                        const msg = (res.data && res.data.message) ? res.data.message : ('Request failed' + (res.status ? ' (' + res.status + ')' : ''));
                        if (alertEl) {
                            alertEl.textContent = msg;
                            alertEl.classList.remove('d-none');
                        } else {
                            alert(msg);
                        }
                    })
                    .catch(function(err) {
                        btn.disabled = false;
                        if (spin) spin.classList.add('d-none');
                        if (icon) icon.classList.remove('d-none');
                        const msg = (err && err.message) ? err.message : 'Network error';
                        if (alertEl) {
                            alertEl.textContent = msg;
                            alertEl.classList.remove('d-none');
                        } else {
                            alert(msg);
                        }
                    });
            });
        }

        function setupPushAmazonButtons() {
            document.querySelectorAll('.push-amazon-btn, .push-all-marketplaces-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const sku = this.getAttribute('data-sku');
                    const modalSku = (document.getElementById('editSku') && document.getElementById('editSku').value) || (document.getElementById('selectSku') && document.getElementById('selectSku').value) || '';
                    const title150Input = document.getElementById('title150');
                    let title = '';
                    if (modalSku === sku && title150Input && title150Input.value.trim()) {
                        title = title150Input.value.trim();
                    }
                    if (!title) {
                        const item = tableData.find(d => d.SKU === sku);
                        title = item ? (item.amazon_title || item.title150 || '').toString().trim() : '';
                    }
                    if (!title) {
                        alert('No title to distribute. Set Title 170 (Add Title or AI workspace) first.');
                        return;
                    }
                    pushToAllMarketplaces(this, sku, title);
                });
            });
        }

        function pushToAllMarketplaces(btn, sku, title) {
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            const row = btn.closest('tr');
            if (row) {
                const cell = row.querySelector('.marketplaces-150-cell');
                const wrap = cell ? cell.querySelector('.marketplaces-dots-wrapper') : null;
                if (wrap) {
                    wrap.innerHTML = renderMarketplaceDots(sku, { amazon: 'loading', temu: 'loading', reverb: 'loading' });
                    initMarketplaceTooltips(wrap);
                }
            }

            fetch('/api/marketplaces/push-title', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ sku: sku, title: title })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results) {
                    if (row) updateMarketplaceDotsInRow(row, data.results);
                    const r = data.results;
                    const ok = [r.amazon, r.temu, r.reverb].filter(x => x && x.status === 'success').length;
                    const fail = [r.amazon, r.temu, r.reverb].filter(x => x && x.status === 'failed').length;
                    alert('Distribute completed for ' + sku + ': ' + ok + ' succeeded, ' + fail + ' failed.');
                } else {
                    if (row) {
                        const cell = row.querySelector('.marketplaces-150-cell');
                        const wrap = cell ? cell.querySelector('.marketplaces-dots-wrapper') : null;
                        if (wrap) {
                            wrap.innerHTML = renderMarketplaceDots(sku, null);
                            initMarketplaceTooltips(wrap);
                        }
                    }
                    alert('Failed: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                if (row) {
                    const cell = row.querySelector('.marketplaces-150-cell');
                    const wrap = cell ? cell.querySelector('.marketplaces-dots-wrapper') : null;
                    if (wrap) {
                        wrap.innerHTML = renderMarketplaceDots(sku, null);
                        initMarketplaceTooltips(wrap);
                    }
                }
                alert('Error: ' + err.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        }

        function runPushBulk(items) {
            const batchSize = 5;
            const total = items.length;
            let successCount = 0;
            let failedCount = 0;
            const progressModal = new bootstrap.Modal(document.getElementById('pushProgressModal'));
            const progressBar = document.getElementById('pushProgressBar');
            const progressText = document.getElementById('pushProgressText');
            progressModal.show();
            progressText.textContent = 'Distributing to Amazon, Temu, Reverb...';

            function updateProgress(done) {
                const pct = total ? Math.round((done / total) * 100) : 0;
                progressBar.style.width = pct + '%';
                progressBar.textContent = pct + '%';
                progressText.textContent = 'Distributing ' + done + '/' + total + ' to all markets...';
            }

            function updateRowDots(sku, results) {
                const btn = document.querySelector('.push-all-marketplaces-btn[data-sku="' + sku + '"]');
                if (btn && btn.closest('tr')) updateMarketplaceDotsInRow(btn.closest('tr'), results);
            }

            function doNext(start) {
                if (start >= total) {
                    progressModal.hide();
                    alert(successCount + ' successful, ' + failedCount + ' failed (Amazon, Temu, Reverb)');
                    document.querySelectorAll('.row-checkbox:checked').forEach(function(cb) { cb.checked = false; });
                    document.getElementById('selectAll').checked = false;
                    updateSelectedCount();
                    return;
                }
                const batch = items.slice(start, start + batchSize);
                const skus = batch.map(function(x) { return x.sku; });
                const titles = {};
                batch.forEach(function(x) { titles[x.sku] = x.title; });
                fetch('/api/marketplaces/push-bulk', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ skus: skus, titles: titles })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    successCount += data.success_count || 0;
                    failedCount += data.failed_count || 0;
                    const perSku = data.per_sku_results || {};
                    Object.keys(perSku).forEach(function(sku) {
                        if (perSku[sku] && !perSku[sku].error) updateRowDots(sku, perSku[sku]);
                    });
                    updateProgress(Math.min(start + batchSize, total));
                    doNext(start + batchSize);
                })
                .catch(function(err) {
                    failedCount += batch.length;
                    updateProgress(Math.min(start + batchSize, total));
                    doNext(start + batchSize);
                });
            }

            updateProgress(0);
            doNext(0);
        }

        function openViewModal(sku) {
            const item = tableData.find(d => d.SKU === sku);
            if (!item) {
                showError('Product not found');
                return;
            }

            // Populate view modal
            const viewImage = document.getElementById('viewImage');
            if (item.image_path) {
                viewImage.innerHTML = '<img src="' + escapeHtml(item.image_path) + '" style="width:80px;height:80px;object-fit:cover;border-radius:4px;">';
            } else {
                viewImage.innerHTML = '<span class="text-muted">No image</span>';
            }

            document.getElementById('viewSku').textContent = escapeHtml(item.SKU) || '-';
            document.getElementById('viewParent').textContent = escapeHtml(item.Parent) || '-';
            document.getElementById('viewTitle150').textContent = (item.amazon_title != null && item.amazon_title !== '') ? item.amazon_title : (item.title150 || '-');
            document.getElementById('viewTitle100').textContent = item.title100 || '-';
            document.getElementById('viewTitle80').textContent = item.title80 || '-';
            document.getElementById('viewTitle60').textContent = item.title60 || '-';

            const viewModal = new bootstrap.Modal(document.getElementById('viewTitleModal'));
            viewModal.show();
        }

        function openModal(mode) {
            if (mode !== 'add') return;
            const modalTitle = document.getElementById('modalTitle');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');

            function attachSelect2DestroyOnHide() {
                const modalElement = document.getElementById('titleModal');
                modalElement.addEventListener('hidden.bs.modal', function() {
                    if ($(selectSku).hasClass('select2-hidden-accessible')) {
                        $(selectSku).select2('destroy');
                    }
                }, { once: true });
            }

            // Reset form
            document.getElementById('titleForm').reset();
            ['title150', 'title100', 'title80', 'title60'].forEach(field => {
                const maxLength = titleMasterTitleMaxLen(field);
                const c = document.getElementById('counter' + titleMasterTitleCounterSuffix(field));
                if (c) {
                    c.textContent = '0/' + maxLength;
                    c.classList.remove('error');
                }
            });

            modalTitle.textContent = 'Add Title';
            selectSku.style.display = 'block';
            selectSku.required = true;
            editSku.value = '';

            if ($(selectSku).hasClass('select2-hidden-accessible')) {
                $(selectSku).select2('destroy');
            }

            selectSku.innerHTML = '<option value="">Loading SKUs...</option>';

            fetch('/title-master/sku-options', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    const skus = resp.data || [];
                    selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                    skus.forEach(function(skuVal) {
                        const s = String(skuVal);
                        if (s && !s.toUpperCase().includes('PARENT')) {
                            selectSku.innerHTML += '<option value="' + escapeHtml(s) + '">' + escapeHtml(s) + '</option>';
                        }
                    });
                })
                .catch(function(err) {
                    console.error('sku-options:', err);
                    selectSku.innerHTML = '<option value="">Choose SKU...</option>';
                    tableData.forEach(function(item) {
                        if (item.SKU && !item.SKU.toUpperCase().includes('PARENT')) {
                            selectSku.innerHTML += '<option value="' + escapeHtml(item.SKU) + '">' + escapeHtml(item.SKU) + '</option>';
                        }
                    });
                })
                .finally(function() {
                    $(selectSku).select2({
                        theme: 'bootstrap-5',
                        placeholder: 'Choose SKU...',
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#titleModal')
                    });
                    attachSelect2DestroyOnHide();
                    titleModal.show();
                });
        }

        function resetTitleModalForm() {
            const form = document.getElementById('titleForm');
            if (form) {
                form.reset();
            }
            const editSku = document.getElementById('editSku');
            const selectSku = document.getElementById('selectSku');
            if (editSku) {
                editSku.value = '';
            }
            if (selectSku) {
                if (typeof $ !== 'undefined' && $(selectSku).hasClass('select2-hidden-accessible')) {
                    $(selectSku).val(null).trigger('change');
                }
                selectSku.selectedIndex = 0;
            }
            ['title150', 'title100', 'title80', 'title60'].forEach(function(field) {
                const maxLength = titleMasterTitleMaxLen(field);
                const counter = document.getElementById('counter' + titleMasterTitleCounterSuffix(field));
                if (counter) {
                    counter.textContent = '0/' + maxLength;
                    counter.classList.remove('error');
                }
            });
        }

        function saveTitleFromModal() {
            const form = document.getElementById('titleForm');
            const selectSku = document.getElementById('selectSku');
            const editSku = document.getElementById('editSku');
            const sku = editSku.value || selectSku.value;

            if (!sku) {
                alert('Please select a SKU');
                return;
            }

            const title150 = document.getElementById('title150').value;
            const title100 = document.getElementById('title100').value;
            const title80 = document.getElementById('title80').value;
            const title60 = document.getElementById('title60').value;

            const saveBtn = document.getElementById('saveTitleBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            fetch('/title-master/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    sku: sku,
                    title150: title150,
                    title100: title100,
                    title80: title80,
                    title60: title60
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const index = tableData.findIndex(item => item.SKU === sku);
                    if (index !== -1) {
                        tableData[index].title150 = title150;
                        tableData[index].amazon_title = title150;
                        tableData[index].title100 = title100;
                        tableData[index].title80 = title80;
                        tableData[index].title60 = title60;
                    }
                    titleModal.hide();
                    resetTitleModalForm();
                    loadTitleData(listMeta.current_page || 1);
                    if (typeof showToast === 'function') {
                        showToast('success', 'Titles saved for ' + sku + '.');
                    } else {
                        alert('Title saved successfully!');
                    }
                } else {
                    alert(data.message || 'Failed to save title');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving title: ' + error.message);
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save';
            });
        }

        function exportToExcel() {
            const params = buildTitleMasterQueryParams(1);
            params.set('export', '1');
            const loader = document.getElementById('rainbow-loader');
            if (loader) loader.style.display = 'block';

            fetch((window.titleMasterDataUrl || '/title-master-data') + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(response) {
                    if (!response.ok) throw new Error('Export request failed');
                    return response.json();
                })
                .then(function(response) {
                    const rows = response.data;
                    if (!Array.isArray(rows)) {
                        throw new Error('Invalid export data');
                    }
                    const exportData = rows
                        .filter(function(item) {
                            return item.SKU && !String(item.SKU).toUpperCase().includes('PARENT');
                        })
                        .map(function(item) {
                            return {
                                'Parent': item.Parent || '',
                                'SKU': item.SKU || '',
                                'B/S': (function() {
                                    const p = [];
                                    if (item.amazon_seller_link) p.push('S: ' + String(item.amazon_seller_link).trim());
                                    if (item.amazon_buyer_link) p.push('B: ' + String(item.amazon_buyer_link).trim());
                                    return p.join(' | ');
                                })(),
                                'INV': item.pricing_cvr_inventory != null ? item.pricing_cvr_inventory : '',
                                'Dil %': item.pricing_cvr_dil_percent != null ? item.pricing_cvr_dil_percent : '',
                                'CVR %': item.pricing_cvr_avg_cvr != null ? Math.round(Number(item.pricing_cvr_avg_cvr)) : '',
                                'LQS': item.lqs != null ? item.lqs : '',
                                'Title 170': (item.amazon_title != null && item.amazon_title !== '') ? item.amazon_title : (item.title150 || ''),
                                'Title 100': item.title100 || '',
                                'Title 80': item.title80 || '',
                                'Title 60': item.title60 || ''
                            };
                        });

                    const ws = XLSX.utils.json_to_sheet(exportData);
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Titles');
                    XLSX.writeFile(wb, 'title_master_' + new Date().toISOString().split('T')[0] + '.xlsx');
                })
                .catch(function(err) {
                    console.error(err);
                    alert('Export failed: ' + (err.message || 'Unknown error'));
                })
                .finally(function() {
                    if (loader) loader.style.display = 'none';
                });
        }

        function importFromExcel(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    
                    // First, try to read with default (row 0 as header)
                    let jsonData = XLSX.utils.sheet_to_json(firstSheet);
                    
                    console.log('First attempt - columns:', Object.keys(jsonData[0] || {}));
                    
                    // If we get __EMPTY columns, try reading from row 1 as header
                    const firstCol = Object.keys(jsonData[0] || {})[0];
                    if (firstCol && firstCol.includes('__EMPTY')) {
                        console.log('Detected merged cells or empty headers, trying range option...');
                        // Try reading with header at row 1 (index 1)
                        jsonData = XLSX.utils.sheet_to_json(firstSheet, { range: 1 });
                        console.log('Second attempt - columns:', Object.keys(jsonData[0] || {}));
                    }
                    
                    // Still empty? Try raw data approach
                    if (!jsonData || jsonData.length === 0 || Object.keys(jsonData[0])[0].includes('__EMPTY')) {
                        console.log('Still getting empty columns, reading as raw array...');
                        const rawData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });
                        console.log('Raw data first 3 rows:', rawData.slice(0, 3));
                        
                        // Find the header row (first row with non-empty values)
                        let headerRowIndex = -1;
                        for (let i = 0; i < Math.min(5, rawData.length); i++) {
                            const row = rawData[i];
                            if (row && row.some(cell => cell && cell.toString().trim() !== '')) {
                                headerRowIndex = i;
                                console.log('Found header row at index:', i, 'Values:', row);
                                break;
                            }
                        }
                        
                        if (headerRowIndex >= 0) {
                            // Convert to proper JSON with detected headers
                            const headers = rawData[headerRowIndex];
                            jsonData = [];
                            for (let i = headerRowIndex + 1; i < rawData.length; i++) {
                                const row = rawData[i];
                                if (!row || row.length === 0) continue;
                                
                                const obj = {};
                                for (let j = 0; j < headers.length; j++) {
                                    const header = headers[j] || 'Column_' + j;
                                    obj[header] = row[j];
                                }
                                jsonData.push(obj);
                            }
                            console.log('Converted data - columns:', Object.keys(jsonData[0] || {}));
                            console.log('First data row:', jsonData[0]);
                        }
                    }

                    if (jsonData.length === 0) {
                        alert('No data found in the file');
                        return;
                    }

                    console.log('Final Excel data loaded!');
                    console.log('Total rows:', jsonData.length);
                    console.log('Columns:', Object.keys(jsonData[0]));
                    console.log('First 3 rows:', jsonData.slice(0, 3));
                    
                    // Show user what columns we found
                    const cols = Object.keys(jsonData[0]).join(', ');
                    const proceed = confirm('Found ' + jsonData.length + ' rows with these columns:\n\n' + cols + '\n\nProceed with import?');
                    
                    if (proceed) {
                        // Process and save imported data
                        processImportedData(jsonData);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error reading file: ' + error.message);
                }
            };
            reader.readAsArrayBuffer(file);
            document.getElementById('importFile').value = ''; // Reset file input
        }

        function processImportedData(jsonData) {
            let successCount = 0;
            let errorCount = 0;
            let skippedCount = 0;
            const totalRows = jsonData.length;
            const errors = [];

            // Log first row to see column names
            if (jsonData.length > 0) {
                console.log('=== EXCEL COLUMNS FOUND ===');
                console.log('All columns:', Object.keys(jsonData[0]));
                console.log('First row full data:', jsonData[0]);
                console.log('Second row data:', jsonData[1]);
            }

            // Detect SKU column dynamically - try all columns to find one with SKU-like data
            let skuColumnName = null;
            if (jsonData.length > 0) {
                const firstRow = jsonData[0];
                
                // First priority: columns with 'sku' or 'child' in name
                for (const colName of Object.keys(firstRow)) {
                    const lower = colName.toLowerCase();
                    if ((lower.includes('sku') || lower.includes('child')) && 
                        firstRow[colName] && 
                        firstRow[colName].toString().trim() !== '' &&
                        firstRow[colName].toString().trim() !== '__EMPTY' &&
                        firstRow[colName].toString().trim() !== '0') {
                        skuColumnName = colName;
                        console.log('✓ Found SKU column (priority): "' + skuColumnName + '" = "' + firstRow[colName] + '"');
                        break;
                    }
                }
                
                // Second priority: any column with actual data that looks like SKU
                if (!skuColumnName) {
                    for (const [colName, value] of Object.entries(firstRow)) {
                        const val = value ? value.toString().trim() : '';
                        if (val && val !== '__EMPTY' && val !== '0' && val !== '' && val.length > 2) {
                            skuColumnName = colName;
                            console.log('✓ Found SKU column (fallback): "' + skuColumnName + '" = "' + val + '"');
                            break;
                        }
                    }
                }
            }

            if (!skuColumnName) {
                console.error('❌ Available columns:', Object.keys(jsonData[0]));
                console.error('❌ First row values:', Object.values(jsonData[0]));
                alert('Error: Could not detect SKU column in Excel file.\nPlease check the console for available columns and their values.');
                return;
            }

            // Detect title columns - more flexible matching
            const titleColumns = {
                title150: null,
                title100: null,
                title80: null,
                title60: null
            };

            if (jsonData.length > 0) {
                const columns = Object.keys(jsonData[0]);
                for (const colName of columns) {
                    const lower = colName.toLowerCase();
                    
                    // Match Amazon/150 column
                    if (!titleColumns.title150 && (lower.includes('amazon') || lower.includes('150'))) {
                        titleColumns.title150 = colName;
                    }
                    // Match Shopify/100 column
                    else if (!titleColumns.title100 && (lower.includes('shopify') || (lower.includes('100') && !lower.includes('150')))) {
                        titleColumns.title100 = colName;
                    }
                    // Match eBay/80 column
                    else if (!titleColumns.title80 && (lower.includes('ebay') || (lower.includes('80') && !lower.includes('180')))) {
                        titleColumns.title80 = colName;
                    }
                    // Match Faire/60 column
                    else if (!titleColumns.title60 && (lower.includes('faire') || (lower.includes('60') && !lower.includes('160')))) {
                        titleColumns.title60 = colName;
                    }
                }
                console.log('✓ Detected title columns:', titleColumns);
            }

            const saveJobs = [];
            jsonData.forEach((row, index) => {
                const sku = row[skuColumnName];
                const skuStr = sku ? sku.toString().trim() : '';
                const isParentSKU = /\bPARENT\b/i.test(skuStr);

                if (!sku || skuStr === '' || sku === '__EMPTY' || skuStr === '0' || isParentSKU) {
                    skippedCount++;
                    if (skippedCount <= 3) {
                        const reason = !sku || skuStr === '' ? 'Empty' : isParentSKU ? 'Parent' : 'Invalid';
                        console.log('⊘ Skipped row ' + (index + 2) + ': "' + skuStr + '" (' + reason + ')');
                    }
                    return;
                }

                const title150 = titleColumns.title150 ? (row[titleColumns.title150] || '').toString().substring(0, 170) : '';
                const title100 = titleColumns.title100 ? (row[titleColumns.title100] || '').toString().substring(0, 100) : '';
                const title80 = titleColumns.title80 ? (row[titleColumns.title80] || '').toString().substring(0, 80) : '';
                const title60 = titleColumns.title60 ? (row[titleColumns.title60] || '').toString().substring(0, 60) : '';

                if (successCount + errorCount < 3) {
                    console.log('→ Processing row ' + (index + 2) + ': SKU="' + skuStr + '"');
                }

                saveJobs.push(function() {
                    return fetch('/title-master/save', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            sku: skuStr,
                            title150: title150,
                            title100: title100,
                            title80: title80,
                            title60: title60
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            successCount++;
                            if (successCount <= 3) {
                                console.log('✓ Row ' + (index + 2) + ' success: ' + skuStr);
                            }
                        } else {
                            errorCount++;
                            const errorMsg = 'Row ' + (index + 2) + ' (' + skuStr + '): ' + (data.message || 'Unknown error');
                            if (errorCount <= 10) {
                                console.error('✗ ' + errorMsg);
                                errors.push(errorMsg);
                            }
                        }
                    })
                    .catch(err => {
                        errorCount++;
                        const errorMsg = 'Row ' + (index + 2) + ' (' + skuStr + '): ' + err.message;
                        if (errorCount <= 10) {
                            console.error('✗ ' + errorMsg);
                            errors.push(errorMsg);
                        }
                    });
                });
            });

            const importConcurrency = 8;
            (async function runImportBatches() {
                for (let i = 0; i < saveJobs.length; i += importConcurrency) {
                    const batch = saveJobs.slice(i, i + importConcurrency).map(function(fn) { return fn(); });
                    await Promise.all(batch);
                }

                let message = `Import completed!\n\nSuccess: ${successCount}\nErrors: ${errorCount}\nSkipped (Parent/Empty): ${skippedCount}\nTotal: ${totalRows}`;

                if (errors.length > 0) {
                    message += '\n\nFirst errors:\n' + errors.join('\n');
                }

                console.log('=== IMPORT SUMMARY ===');
                console.log('Success:', successCount);
                console.log('Errors:', errorCount);
                console.log('Skipped:', skippedCount);
                console.log('Total:', totalRows);

                alert(message);

                if (successCount > 0) {
                    loadTitleData(1);
                }
            })();
        }

        let titleMasterFilterDebounce = null;

        function scheduleApplyFilters() {
            if (titleMasterFilterDebounce) {
                clearTimeout(titleMasterFilterDebounce);
            }
            titleMasterFilterDebounce = setTimeout(function() {
                titleMasterFilterDebounce = null;
                loadTitleData(1);
            }, 500);
        }

        /** Server-side filters — reload page 1 */
        function applyFilters() {
            loadTitleData(1);
        }

        function setupSearchHandlers() {
            const parentSearch = document.getElementById('parentSearch');
            const skuSearch = document.getElementById('skuSearch');

            parentSearch.addEventListener('input', scheduleApplyFilters);
            skuSearch.addEventListener('input', scheduleApplyFilters);

            document.getElementById('filterTitle150').addEventListener('change', function() {
                loadTitleData(1);
            });

            document.getElementById('filterTitle100').addEventListener('change', function() {
                loadTitleData(1);
            });

            document.getElementById('filterTitle80').addEventListener('change', function() {
                loadTitleData(1);
            });

            document.getElementById('filterTitle60').addEventListener('change', function() {
                loadTitleData(1);
            });

            document.getElementById('filterTitleInv')?.addEventListener('change', function() {
                loadTitleData(1);
            });
        }

        function filterTable() {
            loadTitleData(1);
        }

        // Check if value is missing (null, undefined, empty)
        function isMissing(value) {
            return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function showError(message) {
            alert(message);
        }
        @endverbatim
    </script>
@endsection
