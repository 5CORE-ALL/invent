@extends('layouts.vertical', ['title' => 'Amazon Low Visibility', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        /* ========== TABLE STRUCTURE ========== */
        .table-container {
            overflow-x: auto;
            overflow-y: visible;
            position: relative;
            max-height: 600px;
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            border: 1px solid #e5e7eb;
            background: #fff;
        }

        .custom-resizable-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .custom-resizable-table th,
        .custom-resizable-table td {
            padding: 14px 10px;
            text-align: center;
            border-bottom: 1px solid #262626;
            border-right: 1px solid #262626;
            position: relative;
            white-space: nowrap;
            overflow: visible !important;
            transition: background 0.18s, color 0.18s;
        }

        .custom-resizable-table th {
            background: linear-gradient(90deg, #D8F3F3 0%, #D8F3F3 100%);
            border-bottom: 1px solid #403f3f;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
            font-weight: 700;
            color: #1e293b;
            font-size: 1.08rem;
            letter-spacing: 0.02em;
            user-select: none;
            position: sticky;
            top: 0;
            z-index: 10;
            padding: 16px 10px;
        }

        .custom-resizable-table th:hover {
            background: #D8F3F3;
            color: #2563eb;
        }

        .custom-resizable-table tbody tr {
            background-color: #fff !important;
            transition: background 0.18s;
        }

        .custom-resizable-table tbody tr:nth-child(even) {
            background-color: #f8fafc !important;
        }

        .custom-resizable-table tbody tr:hover {
            background-color: #dbeafe !important;
        }

        .custom-resizable-table td {
            color: #22223b;
            font-size: 1rem;
            vertical-align: middle;
        }

        .custom-resizable-table td:focus {
            outline: 1px solid #262626;
            background: #e0eaff;
        }

        /* Left-align text columns headers (Parent, SKU, Reason, Action Required, Action Taken) */
        .custom-resizable-table th[data-field="parent"],
        .custom-resizable-table th[data-field="sku"],
        .custom-resizable-table th[data-field="reason"],
        .custom-resizable-table th[data-field="action_required"],
        .custom-resizable-table th[data-field="action_taken"] {
            text-align: left !important;
        }

        /* Left-align text columns - use class-based approach for better compatibility */
        /* Parent column (index 2 when R&A hidden, index 3 when R&A visible) */
        .custom-resizable-table tbody tr td:nth-child(2),
        /* SKU column (index 3 when R&A hidden, index 4 when R&A visible) */
        .custom-resizable-table tbody tr td:nth-child(3),
        /* Reason column (index 12 when R&A hidden, index 13 when R&A visible) */
        .custom-resizable-table tbody tr td:nth-child(12),
        .custom-resizable-table tbody tr td:nth-child(13),
        /* Action Required column (index 13 when R&A hidden, index 14 when R&A visible) */
        .custom-resizable-table tbody tr td:nth-child(14),
        /* Action Taken column (index 14 when R&A hidden, index 15 when R&A visible) */
        .custom-resizable-table tbody tr td:nth-child(15) {
            text-align: left !important;
        }

        /* Additional selector for when R&A is visible (covers both cases) */
        .custom-resizable-table tbody tr td.skuColumn,
        .custom-resizable-table tbody tr td .sku-text {
            text-align: left !important;
        }

        .custom-resizable-table th:last-child,
        .custom-resizable-table td:last-child {
            border-right: none;
        }

        /* ========== RESIZABLE COLUMNS ========== */
        .resize-handle {
            position: absolute;
            top: 0;
            right: 0;
            width: 5px;
            height: 100%;
            background: rgba(0, 0, 0, 0.1);
            cursor: col-resize;
            z-index: 100;
        }

        .resize-handle:hover,
        .resize-handle.resizing {
            background: rgba(0, 0, 0, 0.3);
        }

        /* ========== TOOLTIP SYSTEM ========== */
        .tooltip-container {
            position: relative;
            display: inline-block;
            margin-left: 8px;
        }

        .tooltip-icon {
            cursor: pointer;
            transform: translateY(1px);
        }

        .tooltip {
            z-index: 9999 !important;
            pointer-events: none;
        }

        .tooltip-inner {
            transform: translate(-5px, -5px) !important;
            max-width: 300px;
            padding: 6px 10px;
            font-size: 13px;
        }

        .bs-tooltip-top .tooltip-arrow {
            bottom: 0;
        }

        .bs-tooltip-top .tooltip-arrow::before {
            transform: translateX(5px) !important;
            border-top-color: var(--bs-tooltip-bg);
        }

        /* ========== COLOR CODED CELLS ========== */
        .dil-percent-cell {
            padding: 8px 4px !important;
        }

        .dil-percent-value {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .dil-percent-value.red {
            background-color: #dc3545;
            color: white;
        }

        .dil-percent-value.blue {
            background-color: #3591dc;
            color: white;
        }

        .dil-percent-value.yellow {
            background-color: #ffc107;
            color: #212529;
        }

        .dil-percent-value.green {
            background-color: #28a745;
            color: white;
        }

        .dil-percent-value.pink {
            background-color: #e83e8c;
            color: white;
        }

        .dil-percent-value.gray {
            background-color: #6c757d;
            color: white;
        }

        /* ========== TABLE CONTROLS ========== */
        .table-controls {
            position: sticky;
            bottom: 0;
            background: #f4f7fa;
            padding: 10px 0;
            border-top: 1px solid #262626;
        }

        .table-controls:hover {
            background: #e0eaff;
        }

        /* ========== SORTING ========== */
        .sortable {
            cursor: pointer;
        }

        .sortable:hover {
            background: #D8F3F3 !important;
            color: #2563eb;
        }

        .sort-arrow {
            display: inline-block;
            margin-left: 5px;
        }

        /* ========== PARENT ROWS ========== */
        .parent-row {
            background-color: #bde0ff !important;
            font-weight: bold !important;
        }

        /* ========== SKU TOOLTIPS ========== */
        .sku-tooltip-container {
            position: relative;
            display: inline-block;
        }

        .sku-tooltip {
            visibility: hidden;
            width: auto;
            min-width: 120px;
            background-color: #fff;
            color: #333;
            text-align: left;
            border-radius: 4px;
            padding: 8px;
            position: absolute;
            z-index: 1001;
            top: 100%;
            /* <-- changed from bottom: 100% */
            left: 50%;
            transform: translateX(-50%) translateY(8px);
            /* add a little gap below */
            opacity: 0;
            transition: opacity 0.3s;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
            white-space: nowrap;
        }

        .sku-tooltip-container:hover .sku-tooltip {
            visibility: visible;
            opacity: 1;
        }

        .sku-link {
            padding: 4px 0;
            white-space: nowrap;
        }

        .sku-link a {
            color: #0d6efd;
            text-decoration: none;
        }

        .sku-link a:hover {
            text-decoration: underline;
        }

        /* ========== DROPDOWNS ========== */
        .custom-dropdown {
            position: relative;
            display: inline-block;
        }

        .custom-dropdown-menu {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .custom-dropdown-menu.show {
            display: block;
        }

        .column-toggle-item {
            padding: 8px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
        }

        .column-toggle-item:hover {
            background-color: #f8f9fa;
        }

        .column-toggle-checkbox {
            margin-right: 8px;
        }

        /* ========== LOADER ========== */
        .card-loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 100;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 0.25rem;
        }

        .loader-content {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .loader-text {
            margin-top: 15px;
            font-weight: 500;
            color: #333;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* ========== CARD BODY ========== */
        .card-body {
            position: relative;
        }

        /* ========== SEARCH DROPDOWNS ========== */
        .dropdown-search-container {
            position: relative;
        }

        .dropdown-search-results {
            position: absolute;
            width: 100%;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .dropdown-search-item {
            padding: 8px 12px;
            cursor: pointer;
        }

        .dropdown-search-item:hover {
            background-color: #f8f9fa;
        }

        .no-results {
            color: #6c757d;
            font-style: italic;
        }

        /* ========== STATUS INDICATORS ========== */
        .status-circle {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
            vertical-align: middle;
            border: 1px solid #fff;
        }

        .status-circle.default {
            background-color: #6c757d;
        }

        .status-circle.red {
            background-color: #dc3545;
        }

        .status-circle.yellow {
            background-color: #ffc107;
        }

        .status-circle.blue {
            background-color: #007bff;
        }

        .status-circle.green {
            background-color: #28a745;
        }

        .status-circle.pink {
            background-color: #e83e8c;
        }

        /* ========== FILTER CONTROLS ========== */
        .d-flex.flex-wrap.gap-2 {
            gap: 0.5rem !important;
            margin-bottom: 1rem;
        }

        .btn-sm i.fas {
            margin-right: 5px;
        }

        .manual-dropdown-container {
            position: relative;
            display: inline-block;
        }

        .manual-dropdown-container .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            z-index: 1000;
            min-width: 160px;
            padding: 5px 0;
            margin: 2px 0 0;
            background-color: #fff;
            border: 1px solid rgba(0, 0, 0, .15);
            border-radius: 4px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, .175);
        }

        .manual-dropdown-container.show .dropdown-menu {
            display: block;
        }

        .dropdown-item {
            display: block;
            width: 100%;
            padding: 8px 16px;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
        }

        .dropdown-item:hover {
            color: #16181b;
            text-decoration: none;
            background-color: #f8f9fa;
        }

        /* ========== MODAL SYSTEM ========== */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1050;
            overflow: hidden;
            outline: 0;
            pointer-events: none;
        }

        .custom-modal.show {
            display: block;
        }

        .custom-modal-dialog {
            position: fixed;
            width: auto;
            min-width: 850px;
            max-width: 90vw;
            margin: 1.75rem auto;
            pointer-events: auto;
            z-index: 1051;
            transition: transform 0.3s ease-out;
            background-color: white;
            border-radius: 0.3rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .custom-modal-content {
            pointer-events: auto;
        }

        .custom-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            border-top-left-radius: 0.3rem;
            border-top-right-radius: 0.3rem;
            background-color: #f8f9fa;
        }

        .custom-modal-title {
            margin-bottom: 0;
            line-height: 1.5;
            font-size: 1.25rem;
        }

        .custom-modal-close {
            padding: 0;
            background-color: transparent;
            border: 0;
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            color: #000;
            text-shadow: 0 1px 0 #fff;
            opacity: 0.5;
            cursor: pointer;
        }

        .custom-modal-close:hover {
            opacity: 0.75;
        }

        .custom-modal-body {
            position: relative;
            flex: 1 1 auto;
            padding: 1rem;
            overflow-y: auto;
            max-height: 70vh;
        }

        /* Multiple Modal Stacking */
        .custom-modal:nth-child(1) .custom-modal-dialog {
            top: 20px;
            right: 20px;
            z-index: 1051;
        }

        .custom-modal:nth-child(2) .custom-modal-dialog {
            top: 40px;
            right: 40px;
            z-index: 1052;
        }

        .custom-modal:nth-child(3) .custom-modal-dialog {
            top: 60px;
            right: 60px;
            z-index: 1053;
        }

        .custom-modal:nth-child(4) .custom-modal-dialog {
            top: 80px;
            right: 80px;
            z-index: 1054;
        }

        .custom-modal:nth-child(5) .custom-modal-dialog {
            top: 100px;
            right: 100px;
            z-index: 1055;
        }

        /* For more than 5 modals - dynamic calculation */
        .custom-modal:nth-child(n+6) .custom-modal-dialog {
            top: calc(100px + (var(--modal-offset) * 20px));
            right: calc(100px + (var(--modal-offset) * 20px));
            z-index: calc(1055 + var(--modal-offset));
        }

        /* Animations */
        @keyframes modalSlideIn {
            from {
                transform: translateX(30px);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .custom-modal.show .custom-modal-dialog {
            animation: modalSlideIn 0.3s ease-out;
        }

        .custom-modal-backdrop.show {
            display: block;
            animation: modalFadeIn 0.15s linear;
        }

        /* Body scroll lock */
        body.custom-modal-open {
            overflow: hidden;
            padding-right: 15px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .custom-modal-dialog {
                min-width: 95vw;
                max-width: 95vw;
                margin: 0.5rem auto;
            }

            .custom-modal:nth-child(1) .custom-modal-dialog,
            .custom-modal:nth-child(2) .custom-modal-dialog,
            .custom-modal:nth-child(3) .custom-modal-dialog,
            .custom-modal:nth-child(4) .custom-modal-dialog,
            .custom-modal:nth-child(5) .custom-modal-dialog,
            .custom-modal:nth-child(n+6) .custom-modal-dialog {
                top: 10px;
                right: 10px;
                left: 10px;
                margin: 0 auto;
            }
        }

        /* Status color overlays */
        .custom-modal .card.card-bg-red {
            background: linear-gradient(135deg, rgba(245, 0, 20, 0.69), rgba(255, 255, 255, 0.85));
            border-color: rgba(220, 53, 70, 0.72);
        }

        .custom-modal .card.card-bg-green {
            background: linear-gradient(135deg, rgba(3, 255, 62, 0.424), rgba(255, 255, 255, 0.85));
            border-color: rgba(40, 167, 69, 0.3);
        }

        .custom-modal .card.card-bg-yellow {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(255, 193, 7, 0.3);
        }

        .custom-modal .card.card-bg-blue {
            background: linear-gradient(135deg, rgba(0, 123, 255, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(0, 123, 255, 0.3);
        }

        .custom-modal .card.card-bg-pink {
            background: linear-gradient(135deg, rgba(232, 62, 140, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(232, 62, 141, 0.424);
        }

        .custom-modal .card.card-bg-gray {
            background: linear-gradient(135deg, rgba(108, 117, 125, 0.15), rgba(255, 255, 255, 0.85));
            border-color: rgba(108, 117, 125, 0.3);
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .custom-modal.show .custom-modal-dialog {
            animation: slideInRight 0.3s ease-out;
        }

        /* Close All button */
        #close-all-modals {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1060;
        }

        .custom-modal-dialog {
            position: fixed !important;
            top: 20px;
            right: 20px;
            margin: 0 !important;
            transform: none !important;
            cursor: move;
        }

        .custom-modal-header {
            cursor: move;
        }


        /* ========== PLAY/PAUSE NAVIGATION BUTTONS ========== */
        .time-navigation-group {
            margin-left: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 50px;
            overflow: hidden;
            padding: 2px;
            background: #f8f9fa;
            display: inline-flex;
            align-items: center;
        }

        .time-navigation-group button {
            padding: 0;
            border-radius: 50% !important;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 3px;
            transition: all 0.2s ease;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }

        .time-navigation-group button:hover {
            background-color: #f1f3f5 !important;
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .time-navigation-group button:active {
            transform: scale(0.95);
        }

        .time-navigation-group button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .time-navigation-group button i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        /* Play button */
        #play-auto {
            color: #28a745;
        }

        #play-auto:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        /* Pause button */
        #play-pause {
            color: #ffc107;
            display: none;
        }

        #play-pause:hover {
            background-color: #ffc107 !important;
            color: white !important;
        }

        /* Navigation buttons */
        #play-backward,
        #play-forward {
            color: #007bff;
        }

        #play-backward:hover,
        #play-forward:hover {
            background-color: #007bff !important;
            color: white !important;
        }

        /* Button state colors - must come after hover styles */
        #play-auto.btn-success,
        #play-pause.btn-success {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning,
        #play-pause.btn-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger,
        #play-pause.btn-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }

        #play-auto.btn-light,
        #play-pause.btn-light {
            background-color: #f8f9fa !important;
            color: #212529 !important;
        }

        /* Ensure hover doesn't override state colors */
        #play-auto.btn-success:hover,
        #play-pause.btn-success:hover {
            background-color: #28a745 !important;
            color: white !important;
        }

        #play-auto.btn-warning:hover,
        #play-pause.btn-warning:hover {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        #play-auto.btn-danger:hover,
        #play-pause.btn-danger:hover {
            background-color: #dc3545 !important;
            color: white !important;
        }

        /* Active state styling */
        .time-navigation-group button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .time-navigation-group button {
                width: 36px;
                height: 36px;
            }

            .time-navigation-group button i {
                font-size: 1rem;
            }
        }

        /* Add to your CSS file or style section */
        .hide-column {
            display: none !important;
        }

        /*popup modal style*/

        .choose-file {
            background-color: #ff6b2c;
            color: white;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            width: 100%;
            display: block;
            transition: background-color 0.3s;
        }

        .choose-file:hover {
            background-color: #e65c1e;
        }

        .modal-content {
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
        }

        .form-label {
            font-weight: 600;
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
        }

        option[value="Todo"] {
            background-color: #2196f3;
        }

        option[value="Not Started"] {
            background-color: #ffff00;
            color: #000;
        }

        option[value="Working"] {
            background-color: #ff00ff;
        }

        option[value="In Progress"] {
            background-color: #f1c40f;
            color: #000;
        }

        option[value="Monitor"] {
            background-color: #5c6bc0;
        }

        option[value="Done"] {
            background-color: #00ff00;
            color: #000;
        }

        option[value="Need Help"] {
            background-color: #e91e63;
        }

        option[value="Review"] {
            background-color: #ffffff;
            color: #000;
        }

        option[value="Need Approval"] {
            background-color: #d4ff00;
            color: #000;
        }

        option[value="Dependent"] {
            background-color: #ff9999;
        }

        option[value="Approved"] {
            background-color: #ffeb3b;
            color: #000;
        }

        option[value="Hold"] {
            background-color: #ffffff;
            color: #000;
        }

        option[value="Rework"] {
            background-color: #673ab7;
        }

        option[value="Urgent"] {
            background-color: #f44336;
        }

        option[value="Q-Task"] {
            background-color: #ff00ff;
        }

        /*only for scouth view*/
        /* Add this to your CSS */
        /* Scouth Products View Specific Styling */
        div.custom-modal-content h5.custom-modal-title:contains("Scouth products view Details")+.custom-modal-body {
            padding: 15px;
            overflow: auto;
        }

        .scouth-header {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .scouth-header-item {
            font-weight: bold;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .scouth-table-container {
            display: flex;
            flex-direction: column;
            gap: 0;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .scouth-table-header {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .scouth-table-row {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            background: white;
        }

        .scouth-table-row:last-child {
            border-bottom: none;
        }

        .scouth-table-cell {
            padding: 10px 12px;
            min-width: 120px;
            flex: 1;
            border-right: 1px solid #dee2e6;
            word-break: break-word;
        }

        .scouth-table-cell:last-child {
            border-right: none;
        }

        .scouth-table-header .scouth-table-cell {
            font-weight: bold;
            color: #495057;
        }

        .scouth-table-row:hover {
            background-color: #f1f1f1;
        }

        .image-thumbnail {
            max-width: 100px;
            max-height: 100px;
            display: block;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .scouth-product-value a {
            color: #0d6efd;
            text-decoration: none;
        }

        .scouth-product-value a:hover {
            text-decoration: underline;
        }

        /* Custom Resizable Table */
        .custom-resizable-table th,
        .custom-resizable-table td {
            transition: width 0.2s, min-width 0.2s, max-width 0.2s;
        }

        .truncated-text {
            transition: all 0.2s;
            display: inline-block;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            vertical-align: middle;
        }
        .nr-hide{
            display: none !important;
        }
        
        /* ========== TABULATOR PAGINATION ========== */
        .tabulator .tabulator-footer {
            background: #f4f7fa;
            border-top: 1px solid #262626;
            font-size: 1rem;
            color: #4b5563;
            padding: 10px;
            min-height: 50px;
        }

        .tabulator .tabulator-footer:hover {
            background: #e0eaff;
        }

        /* Pagination button styling */
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #dee2e6;
            background: white;
            cursor: pointer;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
            background: #e0eaff;
            color: #2563eb;
            border-color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page-size {
            padding: 6px 12px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            font-size: 0.9rem;
        }

        /* Custom pagination label */
        .tabulator-paginator label {
            margin-right: 5px;
            font-weight: 500;
        }
        /*popup modal style end */
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Amazon Low Visibility', 'sub_title' => 'Amazon'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    {{-- <h4 class="header-title">Amazon Low Visibility</h4> --}}

                    <!-- Custom Dropdown Filters Row -->
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <!-- Dil% Filter -->
                        <div class="dropdown manual-dropdown-container">
                            <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                                <span class="status-circle default"></span> OV DIL%
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                                <li><a class="dropdown-item column-filter" href="#" data-column="ov_dil"
                                        data-color="all">
                                        <span class="status-circle default"></span> All OV DIL</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ov_dil"
                                        data-color="red">
                                        <span class="status-circle red"></span> Red</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ov_dil"
                                        data-color="yellow">
                                        <span class="status-circle yellow"></span> Yellow</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ov_dil"
                                        data-color="green">
                                        <span class="status-circle green"></span> Green</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ov_dil"
                                        data-color="pink">
                                        <span class="status-circle pink"></span> Pink</a></li>
                            </ul>
                        </div>

                        <!-- A Dil% Filter -->
                        <div class="dropdown manual-dropdown-container ">
                            <button class="btn btn-light dropdown-toggle" type="button" id="aDilFilterDropdown">
                                <span class="status-circle default"></span> A Dil%
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="aDilFilterDropdown">
                                <li><a class="dropdown-item column-filter" href="#" data-column="A Dil%"
                                        data-color="all">
                                        <span class="status-circle default"></span> All A Dil</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="A Dil%"
                                        data-color="red">
                                        <span class="status-circle red"></span> Red</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="A Dil%"
                                        data-color="yellow">
                                        <span class="status-circle yellow"></span> Yellow</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="A Dil%"
                                        data-color="green">
                                        <span class="status-circle green"></span> Green</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="A Dil%"
                                        data-color="pink">
                                        <span class="status-circle pink"></span> Pink</a></li>
                            </ul>
                        </div>

                        <!-- Task Board Button -->
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                            data-bs-target="#createTaskModal">
                            <i class="bi bi-plus-circle me-2"></i>Create Task
                        </button>

                        <!-- Low view div after create task -->
                        <div id="low-view-div"
                            style="display:inline-block; background:#dc3545; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px;">
                            Low view - 0
                        </div>

                        <!-- for popup modal start Modal -->
                        <div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel"
                            aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h4 class="modal-title" id="createTaskModalLabel">📝 Create New Task Ebay to Task
                                            Manager</h4>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                                            aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body">
                                        <form id="taskForm">
                                            <div class="form-section">
                                                <div class="row g-3">
                                                    <div class="col-md-12">
                                                        <label class="form-label">Group</label>
                                                        <input type="text" class="form-control"
                                                            placeholder="Enter Group">
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">Title<span
                                                                class="text-danger">*</span></label>
                                                        <input type="text" class="form-control"
                                                            placeholder="Enter Title">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Priority</label>
                                                        <select class="form-select">
                                                            <option>Low</option>
                                                            <option>Medium</option>
                                                            <option>High</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-section">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">Assignor<span
                                                                class="text-danger">*</span></label>
                                                        <select class="form-select">
                                                            <option selected disabled>Select Assignor</option>
                                                            <option>Srabani Ghosh</option>
                                                            <option>Rahul Mehta</option>
                                                            <option>Anjali Verma</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Status</label>
                                                        <select class="form-select">
                                                            <option disabled selected>Select Status</option>
                                                            <option value="Todo">Todo</option>
                                                            <option value="Not Started">Not Started</option>
                                                            <option value="Working">Working</option>
                                                            <option value="In Progress">In Progress</option>
                                                            <option value="Monitor">Monitor</option>
                                                            <option value="Done">Done</option>
                                                            <option value="Need Help">Need Help</option>
                                                            <option value="Review">Review</option>
                                                            <option value="Need Approval">Need Approval</option>
                                                            <option value="Dependent">Dependent</option>
                                                            <option value="Approved">Approved</option>
                                                            <option value="Hold">Hold</option>
                                                            <option value="Rework">Rework</option>
                                                            <option value="Urgent">Urgent</option>
                                                            <option value="Q-Task">Q-Task</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <label class="form-label">Assign To<span
                                                                class="text-danger">*</span></label>
                                                        <select class="form-select">
                                                            <option>Please Select</option>
                                                            <option>Dev Team</option>
                                                            <option>QA Team</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Duration<span
                                                                class="text-danger">*</span></label>
                                                        <input type="text" id="duration" class="form-control"
                                                            placeholder="Select start and end date/time">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-section">
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <label class="form-label">L1</label>
                                                        <input type="text" class="form-control"
                                                            placeholder="Enter L1">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">L2</label>
                                                        <input type="text" class="form-control"
                                                            placeholder="Enter L2">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Description</label>
                                                        <textarea class="form-control" rows="4" placeholder="Enter Description"></textarea>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label">Image</label>
                                                        <label class="choose-file">
                                                            Choose File
                                                            <input type="file" class="form-control d-none">
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary"
                                            data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" class="btn btn-warning text-white"
                                            id="createBtn">Create</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!--for popup modal -->

                        <!-- Close All Modals Button -->
                        <button id="close-all-modals" class="btn btn-danger btn-sm" style="display: none;">
                            <i class="fas fa-times"></i> Close All Modals
                        </button>
                    </div>

                    <!-- play backward forwad  -->
                    {{-- <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
                        <button id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                            <i class="fas fa-step-backward"></i>
                        </button>
                        <button id="play-pause" class="btn btn-light rounded-circle" title="Show all products"
                            style="display: none;">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button id="play-auto" class="btn btn-light rounded-circle" title="Show all products">
                            <i class="fas fa-play"></i>
                        </button>
                        <button id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                            <i class="fas fa-step-forward"></i>
                        </button>
                    </div> --}}

                    <!-- Controls row -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <!-- Left side controls -->
                        <div class="form-inline">
                            <div class="form-group mr-2">
                                <label for="row-data-type" class="mr-2">Data Type:</label>
                                <select id="row-data-type" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="sku">SKU (Child)</option>
                                    <option value="parent">Parent</option>
                                </select>
                            </div>
                            <div class="form-group ml-2">
                                <label for="inv-filter" class="mr-2">INV:</label>
                                <select id="inv-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="0">0</option>
                                    <option value="1-100+">1-100+</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <div class="form-group mr-2 custom-dropdown">
                                <button id="hideColumnsBtn" class="btn btn-sm btn-outline-secondary">
                                    Hide Columns
                                </button>
                                <div class="custom-dropdown-menu" id="columnToggleMenu">
                                    <!-- Will be populated by JavaScript -->
                                </div>
                            </div>
                            <div class="form-group">
                                <button id="showAllColumns" class="btn btn-sm btn-outline-secondary">
                                    Show All
                                </button>
                            </div>
                        </div>

                        <!-- Search on right -->
                        <div class="form-inline">
                            <div class="form-group">
                                <label for="search-input" class="mr-2">Search:</label>
                                <input type="text" id="search-input" class="form-control form-control-sm"
                                    placeholder="Search all columns...">
                            </div>
                        </div>
                    </div>

                    <div id="amazonLowVisibility-table-wrapper" style="min-height: 400px;">
                        <div id="amazonLowVisibility-table"></div>
                    </div>

                    <div id="data-loader" class="card-loader-overlay" style="display: none;">
                        <div class="loader-content">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="loader-text">Loading Amazon Low...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reason/Action Modal -->
    <div id="reasonActionModal" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Reason / Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="reasonActionForm">
                        <div class="mb-3">
                            <label for="modalReason" class="form-label">Reason</label>
                            <input type="text" class="form-control" id="modalReason" name="reason">
                        </div>
                        <div class="mb-3">
                            <label for="modalActionRequired" class="form-label">Action Required</label>
                            <input type="text" class="form-control" id="modalActionRequired" name="action_required">
                        </div>
                        <div class="mb-3">
                            <label for="modalActionTaken" class="form-label">Action Taken</label>
                            <input type="text" class="form-control" id="modalActionTaken" name="action_taken">
                        </div>
                        <input type="hidden" id="modalSlNo" name="sl_no">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveReasonActionBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!--for popup modal script-->
    <script>
        flatpickr("#duration", {
            enableTime: true,
            mode: "range",
            dateFormat: "M d, Y h:i K"
        });

        document.getElementById("createBtn").addEventListener("click", function() {
            const form = document.getElementById("taskForm");
            const title = form.querySelector('input[placeholder="Enter Title"]').value.trim();
            const assignor = form.querySelectorAll('select')[0].value;
            const assignee = form.querySelectorAll('select')[2].value;
            const duration = form.querySelector('#duration').value;

            if (!title || assignor === "Select Assignor" || assignee === "Please Select" || !duration) {
                alert("Please fill in all required fields marked with *");
                return;
            }

            alert("🎉 Task Created Successfully!");

            form.reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('createTaskModal'));
            modal.hide();
        });
    </script>
    <!--for popup modal script-->
    <script>
        document.body.style.zoom = "80%";
        $(document).ready(function() {
            $('#editPercentBtn').on('click', function() {
                var $input = $('#updateAllSkusPercent');
                var $icon = $(this).find('i');
                var originalValue = $input.val(); // Store original value

                if ($icon.hasClass('fa-pen')) {
                    // Enable editing
                    $input.prop('disabled', false).focus();
                    $icon.removeClass('fa-pen').addClass('fa-check');
                } else {
                    // Submit and disable editing
                    var percent = parseFloat($input.val());

                    // Validate input
                    if (isNaN(percent) || percent < 0 || percent > 100) {
                        showNotification('danger', 'Invalid percentage value. Must be between 0 and 100.');
                        $input.val(originalValue); // Restore original value
                        return;
                    }

                    $.ajax({
                        // url: '/amazon/zero/view-data',
                        type: 'POST',
                        data: {
                            percent: percent,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            showNotification('success', 'Percentage updated successfully!');
                            $input.prop('disabled', true);
                            $icon.removeClass('fa-check').addClass('fa-pen');
                        },
                        error: function(xhr) {
                            showNotification('danger', 'Error updating percentage.');
                            $input.val(originalValue); // Restore original value
                            $input.prop('disabled', true);
                            $icon.removeClass('fa-check').addClass('fa-pen');
                        }
                    });
                }
            });

            // Cache system
            const amazonLowVisibilityDataCache = {
                cache: {},

                set: function(id, data) {
                    this.cache[id] = JSON.parse(JSON.stringify(data));
                },

                get: function(id) {
                    return this.cache[id] ? JSON.parse(JSON.stringify(this.cache[id])) : null;
                },

                updateField: function(id, field, value) {
                    if (this.cache[id]) {
                        this.cache[id][field] = value;
                    }
                },

                clear: function() {
                    this.cache = {};
                }
            };

            // Clear cache on page load
            window.addEventListener('load', function() {
                amazonLowVisibilityDataCache.clear();
            });

            // Current state
            let currentPage = 1;
            let rowsPerPage = Infinity;
            let currentSort = {
                field: null,
                direction: 1
            };
            let tableData = [];
            let filteredData = [];
            let isResizing = false;
            let isLoading = false;
            let isEditMode = false;
            let currentEditingElement = null;
            let isNavigationActive = false; // Add this line
            let table = null; // Tabulator table instance

            // Parent Navigation System
            let currentParentIndex = -1; // -1 means showing all products
            let uniqueParents = [];
            let isPlaying = false;

            // Define status indicator fields for different modal types
            const statusIndicatorFields = {
                'price view': ['PFT_percentage', 'TPFT', 'ROI_percentage', 'Spft%'],
                'advertisement view': ['KwAcos60', 'KwAcos30', 'KwCvr60', 'KwCvr30',
                    'PtAcos60', 'PtAcos30', 'PtCvr60', 'PtCvr30',
                    'DspAcos60', 'DspAcos30', 'DspCvr60', 'DspCvr30',
                    'HdAcos60', 'HdAcos30', 'HdCvr60', 'HdCvr30',
                    'TAcos60', 'TAcos30', 'TCvr60', 'TCvr30'
                ],
                'conversion view': ['SCVR', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30',
                    'DspCvr60', 'DspCvr30', 'HdCvr60', 'HdCvr30',
                    'TCvr60', 'TCvr30'
                ]
            };

            // Filter state
            const state = {
                filters: {
                    'ov_dil': 'all',
                    'A Dil%': 'all',
                    'PFT_percentage': 'all',
                    'ROI_percentage': 'all',
                    'Tacos30': 'all',
                    'SCVR': 'all',
                    'entryType': 'all'
                }
            };

            // Modal System
            const ModalSystem = {
                modals: [],
                zIndex: 1050,

                createModal: function(id, title, content) {
                    // Remove existing modal if it exists
                    let existingModal = document.getElementById(id);
                    if (existingModal) {
                        existingModal.remove();
                        this.modals = this.modals.filter(m => m.id !== id);
                    }

                    // Create modal element
                    const modalElement = document.createElement('div');
                    modalElement.id = id;
                    modalElement.className = 'custom-modal fade';
                    modalElement.style.zIndex = this.zIndex++;

                    // Set modal HTML
                    modalElement.innerHTML = `
                        <div class="custom-modal-dialog">
                            <div class="custom-modal-content">
                                <div class="custom-modal-header">
                                    <h5 class="custom-modal-title">${title}</h5>
                                    <button type="button" class="custom-modal-close" data-modal-id="${id}">&times;</button>
                                </div>
                                <div class="custom-modal-body">${content}</div>
                            </div>
                        </div>
                    `;

                    document.body.appendChild(modalElement);

                    // Store modal reference
                    const modal = {
                        id: id,
                        element: modalElement,
                        zIndex: modalElement.style.zIndex
                    };
                    this.modals.push(modal);

                    // Setup events after a brief delay to ensure DOM is ready
                    setTimeout(() => {
                        this.setupModalEvents(modal);
                    }, 50);

                    return modal;
                },

                setupModalEvents: function(modal) {
                    const modalElement = modal.element;

                    // Find close button
                    const closeBtn = modalElement.querySelector('.custom-modal-close');
                    if (!closeBtn) {
                        console.error('Close button not found in modal', modal.id);
                        return;
                    }

                    // Setup close button click
                    closeBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.closeModal(modal.id);
                    });

                    // Make draggable
                    this.makeDraggable(modalElement);
                },

                makeDraggable(modalElement) {
                    if (!modalElement) {
                        console.error('Modal element not found');
                        return;
                    }

                    const header = modalElement.querySelector('.custom-modal-header');
                    const dialog = modalElement.querySelector('.custom-modal-dialog');

                    if (!header || !dialog) {
                        console.error('Could not find modal elements', {
                            header,
                            dialog
                        });
                        return;
                    }

                    let isDragging = false;
                    let startX, startY, initialLeft, initialTop;

                    const downHandler = (e) => {
                        if (e.button !== 0 || $(e.target).is('input, select, textarea, button, a')) return;

                        isDragging = true;
                        startX = e.clientX;
                        startY = e.clientY;

                        // Get current position
                        const rect = dialog.getBoundingClientRect();
                        initialLeft = rect.left;
                        initialTop = rect.top;

                        // Prevent text selection during drag
                        document.body.style.userSelect = 'none';
                        document.body.style.cursor = 'grabbing';

                        e.preventDefault();
                    };

                    const moveHandler = (e) => {
                        if (!isDragging) return;

                        // Calculate new position
                        const dx = e.clientX - startX;
                        const dy = e.clientY - startY;

                        // Apply new position
                        dialog.style.left = `${initialLeft + dx}px`;
                        dialog.style.top = `${initialTop + dy}px`;
                    };

                    const upHandler = () => {
                        if (!isDragging) return;

                        isDragging = false;
                        document.body.style.userSelect = '';
                        document.body.style.cursor = '';
                    };

                    // Add event listeners
                    header.addEventListener('mousedown', downHandler);
                    document.addEventListener('mousemove', moveHandler);
                    document.addEventListener('mouseup', upHandler);

                    // Store references for cleanup
                    modalElement._dragHandlers = {
                        downHandler,
                        moveHandler,
                        upHandler
                    };
                },

                cleanupDragHandlers(modalElement) {
                    if (!modalElement || !modalElement._dragHandlers) return;

                    const {
                        downHandler,
                        moveHandler,
                        upHandler
                    } = modalElement._dragHandlers;
                    const header = modalElement.querySelector('.custom-modal-header');

                    if (header) {
                        header.removeEventListener('mousedown', downHandler);
                    }

                    document.removeEventListener('mousemove', moveHandler);
                    document.removeEventListener('mouseup', upHandler);

                    // Reset cursor and selection
                    document.body.style.userSelect = '';
                    document.body.style.cursor = '';

                    delete modalElement._dragHandlers;
                },

                bringToFront: function(modal) {
                    modal.element.style.zIndex = this.zIndex++;
                    modal.zIndex = modal.element.style.zIndex;
                },

                showModal: function(id) {
                    const modal = this.modals.find(m => m.id === id);
                    if (!modal) return;

                    this.bringToFront(modal);

                    // Show modal
                    modal.element.classList.add('show');
                    modal.element.style.display = 'block';

                    // Show close all button if we have modals
                    if (this.modals.length > 0) {
                        $('#close-all-modals').show();
                    }
                },

                closeModal: function(id) {
                    const modalIndex = this.modals.findIndex(m => m.id === id);
                    if (modalIndex === -1) return;

                    const modal = this.modals[modalIndex];
                    this.cleanupDragHandlers(modal.element);
                    modal.element.classList.remove('show');

                    setTimeout(() => {
                        modal.element.style.display = 'none';

                        // Remove from array
                        this.modals.splice(modalIndex, 1);

                        // Hide close all button if no modals left
                        if (this.modals.length === 0) {
                            $('#close-all-modals').hide();
                        }
                    }, 300);
                },
                closeAllModals: function() {
                    // Close all modals from last to first to prevent z-index issues
                    while (this.modals.length > 0) {
                        const modal = this.modals.pop();
                        this.cleanupDragHandlers(modal.element);
                        modal.element.classList.remove('show');
                        setTimeout(() => {
                            modal.element.style.display = 'none';
                        }, 50);
                    }
                    $('#close-all-modals').hide();
                }
            };
            // Close all modals button handler
            $('#close-all-modals').on('click', function() {
                ModalSystem.closeAllModals();
            });

            function initPlaybackControls() {
                // Get all unique parent ASINs
                uniqueParents = [...new Set(tableData.map(item => item.Parent))];

                // Set up event handlers
                $('#play-forward').click(nextParent);
                $('#play-backward').click(previousParent);
                $('#play-pause').click(stopNavigation);
                $('#play-auto').click(startNavigation);

                // Initialize button states
                updateButtonStates();
            }

            function startNavigation() {
                if (uniqueParents.length === 0) return;

                isNavigationActive = true;
                currentParentIndex = 0;

                // Show R&A column in Tabulator
                if (table) {
                    const raColumn = table.getColumn("R&A");
                    if (raColumn) {
                        raColumn.show();
                    }
                }

                showCurrentParent();

                // Update button visibility
                $('#play-auto').hide();
                $('#play-pause').show()
                    .removeClass('btn-light'); // Ensure default color is removed

                // Set initial color
                checkParentRAStatus();
            }

            function stopNavigation() {
                isNavigationActive = false;
                currentParentIndex = -1;

                // Hide R&A column in Tabulator
                if (table) {
                    const raColumn = table.getColumn("R&A");
                    if (raColumn) {
                        raColumn.hide();
                    }
                }

                // Update button visibility and reset color
                $('#play-pause').hide();
                $('#play-auto').show()
                    .removeClass('btn-success btn-warning btn-danger')
                    .addClass('btn-light');

                // Show all products
                if (table) {
                    table.clearFilter();
                    table.redraw();
                }
                calculateTotals();
            }

            function nextParent() {
                if (!isNavigationActive) return;
                if (currentParentIndex >= uniqueParents.length - 1) return;

                currentParentIndex++;
                showCurrentParent();
            }

            function previousParent() {
                if (!isNavigationActive) return;
                if (currentParentIndex <= 0) return;

                currentParentIndex--;
                showCurrentParent();
            }

            function showCurrentParent() {
                if (!isNavigationActive || currentParentIndex === -1) return;

                // Filter data to show only current parent's products
                filteredData = tableData.filter(item => item.Parent === uniqueParents[currentParentIndex]);

                // Update UI
                if (table) {
                    table.setFilter([{field: "Parent", type: "=", value: uniqueParents[currentParentIndex]}]);
                    table.redraw();
                }
                calculateTotals();
                updateButtonStates();
                checkParentRAStatus(); // Add this line
            }

            function updateButtonStates() {
                // Enable/disable navigation buttons based on position
                $('#play-backward').prop('disabled', !isNavigationActive || currentParentIndex <= 0);
                $('#play-forward').prop('disabled', !isNavigationActive || currentParentIndex >= uniqueParents
                    .length - 1);

                // Update button tooltips
                $('#play-auto').attr('title', isNavigationActive ? 'Show all products' : 'Start parent navigation');
                $('#play-pause').attr('title', 'Stop navigation and show all');
                $('#play-forward').attr('title', isNavigationActive ? 'Next parent' : 'Start navigation first');
                $('#play-backward').attr('title', isNavigationActive ? 'Previous parent' :
                    'Start navigation first');

                // Update button colors based on state
                if (isNavigationActive) {
                    $('#play-forward, #play-backward').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#play-forward, #play-backward').removeClass('btn-primary').addClass('btn-light');
                }
            }

            function checkParentRAStatus() {
                if (!isNavigationActive || currentParentIndex === -1) return;

                const currentParent = uniqueParents[currentParentIndex];
                const parentRows = tableData.filter(item => item.Parent === currentParent);

                if (parentRows.length === 0) return;

                let checkedCount = 0;
                let rowsWithRAData = 0;

                parentRows.forEach(row => {
                    // Only count rows that have R&A data (not undefined/null/empty)
                    if (row['R&A'] !== undefined && row['R&A'] !== null && row['R&A'] !== '') {
                        rowsWithRAData++;
                        if (row['R&A'] === true || row['R&A'] === 'true' || row['R&A'] === '1') {
                            checkedCount++;
                        }
                    }
                });

                // Determine which button is currently visible
                const $activeButton = $('#play-pause').is(':visible') ? $('#play-pause') : $('#play-auto');

                // Remove all state classes first
                $activeButton.removeClass('btn-success btn-warning btn-danger btn-light');

                if (rowsWithRAData === 0) {
                    // No rows with R&A data at all (all empty)
                    $activeButton.addClass('btn-light');
                } else if (checkedCount === rowsWithRAData) {
                    // All rows with R&A data are checked (green)
                    $activeButton.addClass('btn-success');
                } else if (checkedCount > 0) {
                    // Some rows with R&A data are checked (yellow)
                    $activeButton.addClass('btn-warning');
                } else {
                    // No rows with R&A data are checked (red)
                    $activeButton.addClass('btn-danger');
                }
            }

            // Update the Low view count using tableData directly (no Sess30 or views field needed)
            function updateLowViewDiv() {
                // Count all rows in tableData
                const lowCount = tableData.length;
                $('#low-view-div').html(`low view - ${lowCount}`);
            }

            // Initialize everything
            function initTable() {
                loadData().then(() => {
                    initTabulatorTable();
                    initFilters();
                    calculateTotals();
                    initEnhancedDropdowns();
                    initManualDropdowns();
                    initModalTriggers();
                    initPlaybackControls();
                    initNREditHandlers();
                    updateLowViewDiv();
                });
            }

            // Initialize Tabulator table
            function initTabulatorTable() {
                const getDilColor = (value) => {
                    const percent = parseFloat(value) * 100;
                    if (percent < 16.66) return 'red';
                    if (percent >= 16.66 && percent < 25) return 'yellow';
                    if (percent >= 25 && percent < 50) return 'green';
                    return 'pink';
                };

                table = new Tabulator("#amazonLowVisibility-table", {
                    data: tableData,
                    layout: "fitDataStretch",
                    pagination: true,
                    paginationMode: "local",
                    paginationSize: 50,
                    paginationSizeSelector: [10, 25, 50, 100, 200],
                    paginationCounter: "rows",
                    langs: {
                        "default": {
                            "pagination": {
                                "page_size": "Show",
                                "first": "First",
                                "first_title": "First Page",
                                "last": "Last",
                                "last_title": "Last Page",
                                "prev": "Prev",
                                "prev_title": "Prev Page",
                                "next": "Next",
                                "next_title": "Next Page",
                                "counter": {
                                    "showing": "Showing",
                                    "of": "of",
                                    "rows": "rows"
                                }
                            }
                        }
                    },
                    initialSort: [{
                        column: "Sess30",
                        dir: "asc"
                    }],
                    rowFormatter: function(row) {
                        const data = row.getData();
                        if (data.is_parent) {
                            row.getElement().style.backgroundColor = "#bde0ff";
                            row.getElement().style.fontWeight = "bold";
                            row.getElement().classList.add("parent-row");   
                        }
                        if (data.NR === 'NR') {
                            row.getElement().classList.add("nr-hide");
                        }
                    },
                    columns: [
                        {
                            title: "Image",
                            field: "image_path",
                            hozAlign: "center",
                            formatter: function(cell) {
                                const imagePath = cell.getValue();
                                if (imagePath) {
                                    return `<img src="${imagePath}" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;" />`;
                                }
                                return '';
                            },
                            width: 80
                        },
                        {
                            title: "Parent",
                            field: "Parent",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search Parent...",
                            hozAlign: "left",
                            width: 150,
                            frozen: true
                        },
                        {
                            title: "SKU",
                            field: "(Child) sku",
                            headerFilter: "input",
                            headerFilterPlaceholder: "Search SKU...",
                            hozAlign: "left",
                            width: 200,
                            frozen: true,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const sku = cell.getValue();
                                const imageUrl = rowData.raw_data?.image_path || '';
                                const buyerLink = rowData.raw_data?.['AMZ LINK BL'];
                                const sellerLink = rowData.raw_data?.['AMZ LINK SL'];

                                function isValidUrl(url) {
                                    try {
                                        return Boolean(url && new URL(url));
                                    } catch (e) {
                                        return false;
                                    }
                                }

                                if (rowData.is_parent) {
                                    return `<strong>${sku}</strong>`;
                                }

                                if (buyerLink || sellerLink || imageUrl) {
                                    return `
                                        <div class="sku-tooltip-container" style="position:relative;display:inline-block;">
                                            <span class="sku-text">${sku}</span>
                                            <div class="sku-tooltip" style="display:none;">
                                                ${imageUrl ? `<img src="${imageUrl}" alt="SKU Image" style="max-width:120px;max-height:120px;border-radius:6px;display:block;margin:0 auto 6px auto;">` : ''}
                                                <div class="sku-link">
                                                    ${buyerLink !== undefined ? (isValidUrl(buyerLink) ? `<a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a>` : 'link invalid') : ''}
                                                </div>
                                                <div class="sku-link">
                                                    ${sellerLink !== undefined ? (isValidUrl(sellerLink) ? `<a href="${sellerLink}" target="_blank" rel="noopener noreferrer">Seller link</a>` : 'link invalid') : ''}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }
                                return sku;
                            }
                        },
                        {
                            title: "R&A",
                            field: "R&A",
                            hozAlign: "center",
                            width: 80,
                            visible: false,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const raValue = rowData['R&A'];
                                if (raValue !== undefined && raValue !== null && raValue !== '') {
                                    const checked = raValue === true || raValue === 'true' || raValue === '1';
                                    return `
                                        <div class="ra-edit-container d-flex align-items-center justify-content-center">
                                            <input type="checkbox" class="ra-checkbox" ${checked ? 'checked' : ''} disabled data-original-value="${raValue}">
                                            <i class="fas fa-pen edit-icon ml-2 text-primary" style="cursor:pointer;" title="Edit R&A"></i>
                                        </div>
                                    `;
                                }
                                return '&nbsp;';
                            }
                        },
                        {
                            title: "INV",
                            field: "INV",
                            hozAlign: "center",
                            width: 80,
                            sorter: "number",
                            bottomCalc: function(values) {
                                const total = values.reduce((sum, val) => sum + (parseFloat(val) || 0), 0);
                                $('#inv-total').text(total.toLocaleString());
                                return total.toLocaleString();
                            }
                        },
                        {
                            title: "OV L30",
                            field: "L30",
                            hozAlign: "center",
                            width: 80,
                            sorter: "number",
                            bottomCalc: function(values) {
                                const total = values.reduce((sum, val) => sum + (parseFloat(val) || 0), 0);
                                $('#ovl30-total').text(total.toLocaleString());
                                return total.toLocaleString();
                            }
                        },
                        {
                            title: "OV DIL",
                            field: "ov_dil",
                            hozAlign: "center",
                            width: 100,
                            sorter: "number",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const ovDil = parseFloat(cell.getValue()) || 0;
                                const color = getDilColor(ovDil);
                                const rawData = rowData.raw_data || {};
                                return `
                                    <span class="dil-percent-value ${color}">${Math.round(ovDil * 100)}%</span>
                                    <span class="text-info tooltip-icon wmpnm-view-trigger" 
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="left" 
                                        title="WMPNM View"
                                        data-item='${JSON.stringify(rawData)}'
                                        style="cursor:pointer; margin-left:5px;">W</span>
                                `;
                            },
                            bottomCalc: function(values, data) {
                                const invTotal = data.reduce((sum, row) => sum + (parseFloat(row.INV) || 0), 0);
                                const l30Total = data.reduce((sum, row) => sum + (parseFloat(row.L30) || 0), 0);
                                const dilTotal = invTotal > 0 ? (l30Total / invTotal) * 100 : 0;
                                $('#ovdil-total').text(Math.round(dilTotal) + '%');
                                return Math.round(dilTotal) + '%';
                            }
                        },
                        {
                            title: "AL 30",
                            field: "A L30",
                            hozAlign: "center",
                            width: 80,
                            sorter: "number",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const aL30 = cell.getValue();
                                const l60 = rowData.units_ordered_l60 || 0;
                                return `
                                    <div class="sku-tooltip-container">
                                        <span class="sku-text">${aL30}</span>
                                        <div class="sku-tooltip">
                                            <div class="sku-link"><strong>L60:</strong> ${l60}</div>
                                            <div class="sku-link"><strong>L7:</strong></div>
                                        </div>
                                    </div>
                                `;
                            },
                            bottomCalc: function(values) {
                                const total = values.reduce((sum, val) => sum + (parseFloat(val) || 0), 0);
                                $('#al30-total').text(total.toLocaleString());
                                return total.toLocaleString();
                            }
                        },
                        {
                            title: "REQ",
                            field: "NR",
                            hozAlign: "center",
                            width: 100,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                if (rowData.is_parent) return '';
                                const currentNR = cell.getValue() === 'REQ' || cell.getValue() === 'NR' ? cell.getValue() : 'REQ';
                                const bgColor = currentNR === 'NR' ? '#dc3545' : '#28a745';
                                const textColor = '#ffffff';
                                return `
                                    <select class="form-select form-select-sm nr-select" style="min-width: 100px; background-color: ${bgColor}; color: ${textColor};" data-sku="${rowData['(Child) sku']}">
                                        <option value="NR" ${currentNR === 'NR' ? 'selected' : ''}>NR</option>
                                        <option value="REQ" ${currentNR === 'REQ' ? 'selected' : ''}>REQ</option>
                                    </select>
                                `;
                            }
                        },
                        {
                            title: "VIEWS",
                            field: "Sess30",
                            hozAlign: "center",
                            width: 100,
                            sorter: "number",
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const sess30 = cell.getValue();
                                const rawData = rowData.raw_data || {};
                                return `
                                    <span>${Math.round(sess30)}</span>
                                    <span class="text-info tooltip-icon ad-view-trigger" 
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="left" 
                                        title="visibility View"
                                        data-item='${JSON.stringify(rawData)}'
                                        style="cursor:pointer; margin-left:5px;">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                `;
                            },
                            bottomCalc: function(values, data) {
                                const total = data.reduce((sum, row) => {
                                    if (row.NR !== 'NR') {
                                        return sum + (parseFloat(row.Sess30) || 0);
                                    }
                                    return sum;
                                }, 0);
                                $('#views-total').text(total.toLocaleString());
                                return total.toLocaleString();
                            }
                        },
                        {
                            title: "Reason",
                            field: "A_Z_Reason",
                            hozAlign: "left",
                            width: 200,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const reason = cell.getValue() || '';
                                const slNo = rowData['SL No.'];
                                return `
                                    <span class="truncated-text" title="${reason.replace(/"/g, '&quot;')}">${reason.length > 20 ? reason.substring(0, 20) + '...' : reason}</span>
                                    <i class="fas fa-plus reason-action-plus" style="cursor:pointer; color:#007bff; margin-left:8px;" 
                                        data-slno="${slNo}" data-type="reason"></i>
                                `;
                            }
                        },
                        {
                            title: "Action Required",
                            field: "A_Z_ActionRequired",
                            hozAlign: "left",
                            width: 200,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const actionRequired = cell.getValue() || '';
                                const slNo = rowData['SL No.'];
                                return `
                                    <span class="truncated-text" title="${actionRequired.replace(/"/g, '&quot;')}">${actionRequired.length > 20 ? actionRequired.substring(0, 20) + '...' : actionRequired}</span>
                                    <i class="fas fa-plus reason-action-plus" style="cursor:pointer; color:#007bff; margin-left:8px;" 
                                        data-slno="${slNo}" data-type="action_required"></i>
                                `;
                            }
                        },
                        {
                            title: "Action Taken",
                            field: "A_Z_ActionTaken",
                            hozAlign: "left",
                            width: 200,
                            formatter: function(cell) {
                                const rowData = cell.getRow().getData();
                                const actionTaken = cell.getValue() || '';
                                const slNo = rowData['SL No.'];
                                return `
                                    <span class="truncated-text" title="${actionTaken.replace(/"/g, '&quot;')}">${actionTaken.length > 20 ? actionTaken.substring(0, 20) + '...' : actionTaken}</span>
                                    <i class="fas fa-plus reason-action-plus" style="cursor:pointer; color:#007bff; margin-left:8px;" 
                                        data-slno="${slNo}" data-type="action_taken"></i>
                                `;
                            }
                        }
                    ]
                });

                // Setup event handlers after table is created
                table.on("dataLoaded", function() {
                    calculateTotals();
                    initTooltips();
                    setupTabulatorEventHandlers();
                });

                table.on("dataProcessed", function() {
                    calculateTotals();
                });

                // Setup search - Tabulator uses OR logic by default for array filters
                $('#search-input').on('keyup', function() {
                    const searchTerm = $(this).val();
                    if (searchTerm) {
                        table.setFilter(function(data) {
                            const searchLower = searchTerm.toLowerCase();
                            return (
                                (data.Parent && String(data.Parent).toLowerCase().includes(searchLower)) ||
                                (data['(Child) sku'] && String(data['(Child) sku']).toLowerCase().includes(searchLower)) ||
                                (data.A_Z_Reason && String(data.A_Z_Reason).toLowerCase().includes(searchLower)) ||
                                (data.A_Z_ActionRequired && String(data.A_Z_ActionRequired).toLowerCase().includes(searchLower)) ||
                                (data.A_Z_ActionTaken && String(data.A_Z_ActionTaken).toLowerCase().includes(searchLower))
                            );
                        });
                    } else {
                        table.clearFilter();
                    }
                });
            }

            function setupTabulatorEventHandlers() {
                // Handle SKU tooltip events
                setTimeout(() => {
                    $('.sku-tooltip-container').on('mouseenter', function() {
                        $(this).find('.sku-tooltip').stop(true, true).fadeIn(120);
                    }).on('mouseleave', function() {
                        const $tooltip = $(this).find('.sku-tooltip');
                        setTimeout(() => {
                            $tooltip.fadeOut(120);
                        }, 250);
                    });
                }, 100);

                // Handle R&A edit handlers
                $(document).off('click', '.edit-icon').on('click', '.edit-icon', function(e) {
                    e.stopPropagation();
                    const $icon = $(this);
                    const $checkbox = $icon.siblings('.ra-checkbox');
                    
                    if ($icon.hasClass('fa-pen')) {
                        $checkbox.prop('disabled', false).data('original-value', $checkbox.is(':checked'));
                        $icon.removeClass('fa-pen text-primary').addClass('fa-save text-success').attr('title', 'Save Changes');
                    } else {
                        const rowElement = $icon.closest('.tabulator-row');
                        const rowData = table.getRowFromElement(rowElement[0])?.getData();
                        if (rowData) {
                            const slNo = rowData['SL No.'];
                            const updatedValue = $checkbox.is(':checked') ? "true" : "false";
                            
                            $icon.html('<i class="fas fa-spinner fa-spin"></i>');
                            
                            saveChanges(null, "R&A", slNo, false, updatedValue, true, rowElement);
                            
                            $checkbox.prop('disabled', true);
                            $icon.removeClass('fa-save text-success').addClass('fa-pen text-primary');
                        }
                    }
                });
            }

            // Initialize modal triggers
            function initModalTriggers() {
                $(document).on('click', '.wmpnm-view-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'WMPNM view');
                    } else {
                        console.error("No data found for WMPNM view");
                    }
                });
                $(document).on('click', '.scouth-products-view-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'scouth products view');
                    } else {
                        console.error("No data found for Scouth Products View");
                    }
                });

                $(document).on('click', '.ad-view-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'visibility view');
                    } else {
                        console.error("No data found for Visibility view");
                    }
                });

                $(document).on('click', '.price-view-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'price view');
                    } else {
                        console.error("No data found for Price view");
                    }
                });

                $(document).on('click', '.advertisement-view-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'advertisement view');
                    } else {
                        console.error("No data found for Advertisement view");
                    }
                });

                $(document).on('click', '.conversion-view-trigger', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'conversion view');
                    } else {
                        console.error("No data found for Conversion view");
                    }
                });
            }

            // Load data from server
            function loadData() {
                showLoader();
                return $.ajax({
                    url: '/amazon/low-visibility/view-data',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.data) {
                            tableData = response.data.map((item, index) => {
                                // Calculate A Dil% as (A L30 / INV), handle division by zero
                                const inv = Number(item.INV) || 0;
                                const aL30 = Number(item['A_L30']) || 0;
                                const l30 = Number(item.L30) || 0;
                                const ovDil = inv > 0 ? l30 / inv : 0;
                                const aDil = inv > 0 ? aL30 / inv : 0;

                                return {
                                    sl_no: index + 1,
                                    'SL No.': item['SL No.'] || index + 1,
                                    Parent: item.Parent || item.parent || item.parent_asin ||
                                        item.Parent_ASIN || '(No Parent)',
                                    image_path: item.image_path || '',
                                    '(Child) sku': item['(Child) sku'] || '',
                                    'R&A': item['R&A'] !== undefined ? item['R&A'] : '',
                                    INV: inv,
                                    L30: item.L30 || 0,
                                    ov_dil: ovDil,
                                    'A L30': aL30,
                                    units_ordered_l60: item.units_ordered_l60 || 0,
                                    'A Dil%': aDil,
                                    Sess30: item.Sess30 || 0,
                                    price: Number(item.price) || 0,
                                    COMP: item.COMP || 0,
                                    min_price: item.scout_data ? item.scout_data.min_price : 0,
                                    all_products: item.scout_data ? item.scout_data.all_data :
                                        0,
                                    'PFT_percentage': item['PFT_percentage'] || 0,
                                    TPFT: item.TPFT || 0,
                                    ROI_percentage: item.ROI_percentage || 0,
                                    Tacos30: item.Tacos30 || 0,
                                    SCVR: item.SCVR || 0,
                                    LP: item.LP_productmaster || 0,
                                    SHIP: item.Ship_productmaster || 0,
                                    A_Z_Reason: item.A_Z_Reason || '',
                                    A_Z_ActionRequired: item.A_Z_ActionRequired || '',
                                    A_Z_ActionTaken: item.A_Z_ActionTaken || '',
                                    is_parent: item['(Child) sku'] ? item['(Child) sku']
                                        .toUpperCase().includes("PARENT") : false,
                                    NR: item.NRL || '',
                                    raw_data: item || {}
                                };
                            });

                            // console.log('Data loaded successfully:', tableData);
                            filteredData = [...tableData];

                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading data:', error);
                        showNotification('danger', 'Failed to load data. Please try again.');
                    },
                    complete: function() {
                        hideLoader();
                    }
                });
            }

            // renderTable() function removed - now using Tabulator which handles rendering automatically

            function initRAEditHandlers() {
                $(document).on('click', '.edit-icon', function(e) {
                    e.stopPropagation();
                    const $icon = $(this);
                    const $checkbox = $icon.siblings('.ra-checkbox');
                    const $row = $checkbox.closest('tr');
                    const rowData = filteredData.find(item => item['SL No.'] == $row.find('td:eq(0)')
                        .text());

                    if ($icon.hasClass('fa-pen')) {
                        // Enter edit mode
                        $checkbox.prop('disabled', false)
                            .data('original-value', $checkbox.is(':checked'));
                        $icon.removeClass('fa-pen text-primary')
                            .addClass('fa-save text-success')
                            .attr('title', 'Save Changes');
                    } else {
                        // Prepare data for saveChanges
                        const $cell = $checkbox.closest('.ra-cell');
                        const slNo = $row.find('td:eq(0)').text();
                        const title = "R&A";
                        const updatedValue = $checkbox.is(':checked') ? "true" : "false";

                        // Show saving indicator
                        $icon.html('<i class="fas fa-spinner fa-spin"></i>');

                        saveChanges(
                            $cell,
                            title,
                            slNo,
                            false, // isHyperlink
                            updatedValue,
                            true // isCheckbox
                        );

                        // Immediately disable checkbox after save
                        $checkbox.prop('disabled', true);
                        $icon.removeClass('fa-save text-success')
                            .addClass('fa-pen text-primary');
                    }
                });

                // Handle direct checkbox changes (for keyboard accessibility)
                $(document).on('change', '.ra-checkbox:not(:disabled)', function(e) {
                    e.stopPropagation();
                    $(this).siblings('.edit-icon').trigger('click');
                });
            }

            function initNREditHandlers() {
                $(document).on('change', '.nr-select', function () {
                    const $select = $(this);
                    const sku = $(this).data('sku');
                    const nrValue = $(this).val(); // 'NR' or 'REQ'
                    if (nrValue === 'NR') {
                        $select.css('background-color', '#dc3545').css('color', '#ffffff');
                    } else {
                        $select.css('background-color', '#28a745').css('color', '#ffffff');
                    }
                    // Use the same endpoint as listingAmazon for syncing
                    $.ajax({
                        url: '/listing_amazon/save-status', 
                        type: 'POST',
                        data: {
                            sku: sku,
                            nr_req: nrValue, // Send 'NR' or 'REQ' - backend will map NR->NRL
                            _token: $('meta[name="csrf-token"]').attr('content') // CSRF protection
                        },
                        success: function (res) {
                            showNotification('success', 'NR updated successfully');

                            // ✅ Update tableData and filteredData correctly
                            tableData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.NR = nrValue;
                                }
                            });
                            filteredData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.NR = nrValue;
                                }
                            });
                            // ✅ Update Tabulator data and recalculate
                            if (table) {
                                const row = table.getRows().find(r => r.getData()['(Child) sku'] === sku);
                                if (row) {
                                    row.update({NR: nrValue});
                                }
                            }
                            calculateTotals();
                        },
                        error: function (err) {
                            console.error('Error saving NR:', err);
                            showNotification('danger', 'Failed to update NR');
                        }
                    });
                }); 
            }

            window.openModal = function(selectedItem, type) {
                try {
                    // Handle both string and object inputs
                    let itemData;
                    if (typeof selectedItem === 'string') {
                        try {
                            itemData = JSON.parse(selectedItem);
                        } catch (e) {
                            try {
                                itemData = JSON.parse(decodeURIComponent(selectedItem));
                            } catch (e2) {
                                console.error("Error parsing item data:", e2);
                                showNotification('danger', 'Failed to open details view. Data format error.');
                                return;
                            }
                        }
                    } else {
                        itemData = selectedItem;
                    }


                    if (!itemData || typeof itemData !== 'object') {
                        console.error("Invalid item data:", itemData);
                        showNotification('danger', 'Failed to open details view. Invalid data.');
                        return;
                    }

                    const itemId = itemData['SL No.'] || `row-${Math.random().toString(36).substr(2, 9)}`;
                    const modalId = `modal-${itemId}-${type.replace(/\s+/g, '-').toLowerCase()}`;

                    // Check cache first - use the cached data if available
                    const cachedData = amazonLowVisibilityDataCache.get(itemId);
                    const dataToUse = cachedData || itemData;

                    // Store the data in cache if it wasn't already
                    if (!cachedData) {
                        amazonLowVisibilityDataCache.set(itemId, itemData);
                    }

                    // Check if this modal already exists
                    const existingModal = ModalSystem.modals.find(m => m.id === modalId);
                    if (existingModal) {
                        // Just bring it to front if it exists
                        ModalSystem.bringToFront(existingModal);
                        return;
                    }

                    // Special handling for Scouth products view
                    if (type.toLowerCase() === 'scouth products view') {
                        return openScouthProductsView(selectedItem, modalId);
                    }

                    // Create modal content based on type
                    const mainContainer = document.createElement('div');
                    mainContainer.className = 'd-flex flex-nowrap align-items-start gap-3 p-3 overflow-auto';

                    // Common fields for all modal types
                    const commonFields = [{
                            title: 'Parent',
                            content: dataToUse['Parent']
                        },
                        {
                            title: 'SKU',
                            content: dataToUse['(Child) sku']
                        }
                    ];

                    // Fields specific to each modal type
                    let fieldsToDisplay = [];
                    switch (type.toLowerCase()) {
                        case 'visibility view':
                            // Get raw_data if available for KW and Headline data
                            const rawData = dataToUse.raw_data || dataToUse || {};
                            
                            // Calculate organic views (Total - KW - PT - Headline)
                            const totalViews = parseFloat(dataToUse['Sess30'] || rawData['Sess30'] || 0);
                            const kwImpressions30 = parseFloat(rawData['KwImp30'] || rawData['kw_impr_L30'] || dataToUse['KwImp30'] || 0);
                            const ptImpressions30 = parseFloat(rawData['PtImp30'] || rawData['pt_impr_L30'] || dataToUse['PtImp30'] || 0);
                            const headlineImpressions30 = parseFloat(rawData['HdImp30'] || rawData['hd_impr_L30'] || dataToUse['HdImp30'] || 0);
                            const organicViews = Math.max(0, totalViews - kwImpressions30 - ptImpressions30 - headlineImpressions30);
                            
                            // Get KW data from raw data (fallback)
                            const kwClicks30Fallback = parseFloat(rawData['KwClks30'] || rawData['kw_clicks_L30'] || dataToUse['KwClks30'] || 0);
                            const kwSpend30Fallback = parseFloat(rawData['KwSpend30'] || rawData['kw_spend_L30'] || dataToUse['KwSpend30'] || 0);
                            
                            // Get Headline data from raw data (fallback)
                            const hdClicks30Fallback = parseFloat(rawData['HdClks30'] || rawData['hd_clicks_L30'] || dataToUse['HdClks30'] || 0);
                            const hdSpend30Fallback = parseFloat(rawData['HdSpend30'] || rawData['hd_spend_L30'] || dataToUse['HdSpend30'] || 0);
                            
                            // Create a structured modal content
                            const sku = dataToUse['(Child) sku'] || rawData['(Child) sku'] || 'N/A';
                            
                            // Show loading modal first
                            const loadingContent = `
                                <div class="visibility-view-modal" style="padding: 20px; text-align: center;">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-3">Loading campaign data...</p>
                                </div>
                            `;
                            
                            const modal = ModalSystem.createModal(modalId, 'Visibility View Details', loadingContent);
                            ModalSystem.showModal(modalId);
                            
                            // Fetch campaign clicks data from backend
                            $.ajax({
                                url: '/amazon/low-visibility/campaign-clicks',
                                type: 'GET',
                                data: { sku: sku },
                                success: function(response) {
                                    if (response.status === 200 && response.data) {
                                        const campaignData = response.data;
                                        
                                        // Use fetched data or fallback to raw data
                                        const kwClicks30 = campaignData.kw_clicks_l30 || kwClicks30Fallback;
                                        const kwImpressions30Final = campaignData.kw_impressions_l30 || kwImpressions30;
                                        const kwSpend30 = campaignData.kw_spend_l30 || kwSpend30Fallback;
                                        
                                        const ptClicks30 = campaignData.pt_clicks_l30 || 0;
                                        const ptImpressions30Final = campaignData.pt_impressions_l30 || ptImpressions30;
                                        const ptSpend30 = campaignData.pt_spend_l30 || 0;
                                        
                                        const hlClicks30 = campaignData.hl_clicks_l30 || hdClicks30Fallback;
                                        const hlImpressions30Final = campaignData.hl_impressions_l30 || headlineImpressions30;
                                        const hlSpend30 = campaignData.hl_spend_l30 || hdSpend30Fallback;
                                        
                                        // Use organic_views from API response (from amazon_datasheets.organic_views)
                                        // This matches the data shown in amazon-organic-views.blade.php
                                        const organicViewsFromDB = campaignData.organic_views !== undefined && campaignData.organic_views !== null 
                                            ? parseInt(campaignData.organic_views) 
                                            : organicViews;
                                        
                                        // Pass the totalViews and organicViewsFromDB to the chart function
                                        window.chartTotalViews = totalViews;
                                        window.chartOrganicViews = organicViewsFromDB;
                                        
                                        const modalContent = `
                                            <div class="visibility-view-modal" style="padding: 20px;">
                                                <div class="row mb-4">
                                                    <div class="col-12">
                                                        <h5 style="border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 20px;">
                                                            <i class="fas fa-eye text-info"></i> Visibility Details for SKU: <strong>${sku}</strong>
                                                        </h5>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mb-4">
                                                    <div class="col-md-12">
                                                        <div class="card">
                                                            <div class="card-header bg-info text-white">
                                                                <i class="fas fa-chart-line"></i> Last 30 Days Trend
                                                            </div>
                                                            <div class="card-body">
                                                                <canvas id="dailyViewsChart-${sku.replace(/\s+/g, '-')}" style="max-height: 400px;"></canvas>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <div class="card border-primary">
                                                            <div class="card-header bg-primary text-white">
                                                                <i class="fas fa-chart-line"></i> Total Views
                                                            </div>
                                                            <div class="card-body">
                                                                <h3 class="text-primary">${totalViews.toLocaleString()}</h3>
                                                                <small class="text-muted">Sessions (Last 30 Days)</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <div class="card border-success">
                                                            <div class="card-header bg-success text-white">
                                                                <i class="fas fa-leaf"></i> L30 Org Views
                                                            </div>
                                                            <div class="card-body">
                                                                <h3 class="text-success">${organicViewsFromDB.toLocaleString()}</h3>
                                                                <small class="text-muted">Organic Views (from amazon_datasheets.organic_views)</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mt-3">
                                                    <div class="col-md-12 mb-3">
                                                        <div class="card border-warning">
                                                            <div class="card-header bg-warning text-dark">
                                                                <i class="fas fa-key"></i> Keyword (KW) Campaign Data (Last 30 Days)
                                                                ${campaignData.kw_campaign_name ? `<small class="float-end">${campaignData.kw_campaign_name}</small>` : ''}
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-3">
                                                                        <strong>Impressions:</strong>
                                                                        <p class="mb-0">${kwImpressions30Final.toLocaleString()}</p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>Clicks:</strong>
                                                                        <p class="mb-0"><strong class="text-primary">${kwClicks30.toLocaleString()}</strong></p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>CTR:</strong>
                                                                        <p class="mb-0">${kwImpressions30Final > 0 ? ((kwClicks30 / kwImpressions30Final) * 100).toFixed(2) : '0.00'}%</p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>Spend:</strong>
                                                                        <p class="mb-0">$${kwSpend30.toFixed(2)}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mt-3">
                                                    <div class="col-md-12 mb-3">
                                                        <div class="card border-secondary">
                                                            <div class="card-header bg-secondary text-white">
                                                                <i class="fas fa-tag"></i> Product Targeting (PT) Campaign Data (Last 30 Days)
                                                                ${campaignData.pt_campaign_name ? `<small class="float-end">${campaignData.pt_campaign_name}</small>` : ''}
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-3">
                                                                        <strong>Impressions:</strong>
                                                                        <p class="mb-0">${ptImpressions30Final.toLocaleString()}</p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>Clicks:</strong>
                                                                        <p class="mb-0"><strong class="text-primary">${ptClicks30.toLocaleString()}</strong></p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>CTR:</strong>
                                                                        <p class="mb-0">${ptImpressions30Final > 0 ? ((ptClicks30 / ptImpressions30Final) * 100).toFixed(2) : '0.00'}%</p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>Spend:</strong>
                                                                        <p class="mb-0">$${ptSpend30.toFixed(2)}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mt-3">
                                                    <div class="col-md-12 mb-3">
                                                        <div class="card border-info">
                                                            <div class="card-header bg-info text-white">
                                                                <i class="fas fa-heading"></i> Headline (HL) Campaign Data (Last 30 Days)
                                                                ${campaignData.hl_campaign_name ? `<small class="float-end">${campaignData.hl_campaign_name}</small>` : ''}
                                                            </div>
                                                            <div class="card-body">
                                                                <div class="row">
                                                                    <div class="col-md-3">
                                                                        <strong>Impressions:</strong>
                                                                        <p class="mb-0">${hlImpressions30Final.toLocaleString()}</p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>Clicks:</strong>
                                                                        <p class="mb-0"><strong class="text-primary">${hlClicks30.toLocaleString()}</strong></p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>CTR:</strong>
                                                                        <p class="mb-0">${hlImpressions30Final > 0 ? ((hlClicks30 / hlImpressions30Final) * 100).toFixed(2) : '0.00'}%</p>
                                                                    </div>
                                                                    <div class="col-md-3">
                                                                        <strong>Spend:</strong>
                                                                        <p class="mb-0">$${hlSpend30.toFixed(2)}</p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="row mt-2">
                                                    <div class="col-md-12">
                                                        <small class="text-muted">
                                                            <i class="fas fa-info-circle"></i> 
                                                            Organic Views = Total Views - KW Impressions - PT Impressions - Headline Impressions
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        `;
                                        
                                        // Update modal content
                                        const modalBody = document.querySelector(`#${modalId} .custom-modal-body`);
                                        if (modalBody) {
                                            modalBody.innerHTML = modalContent;
                                            
                                            // Fetch and render daily views chart with fallback values
                                            fetchDailyViewsChart(sku, modalId, totalViews, organicViewsFromDB);
                                        }
                                    } else {
                                        // Fallback if API fails - use raw data
                                        const modalBody = document.querySelector(`#${modalId} .custom-modal-body`);
                                        if (modalBody) {
                                            modalBody.innerHTML = '<div class="alert alert-warning">Failed to load campaign data. Please try again.</div>';
                                        }
                                    }
                                },
                                error: function(xhr, status, error) {
                                    console.error('Error fetching campaign clicks:', error);
                                    // Show error message
                                    const modalBody = document.querySelector(`#${modalId} .custom-modal-body`);
                                    if (modalBody) {
                                        modalBody.innerHTML = '<div class="alert alert-danger">Error loading campaign data. Please try again later.</div>';
                                    }
                                }
                            });
                            return;
                        case 'wmpnm view':
                            fieldsToDisplay = [{
                                    title: 'HIDE',
                                    content: dataToUse['HIDE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'LISTING STATUS',
                                    isSectionHeader: true,
                                    children: [{
                                            title: 'LISTED',
                                            content: dataToUse['LISTED'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'LIVE / ACTIVE',
                                            content: dataToUse['LIVE / ACTIVE'],
                                            isCheckbox: true
                                        }
                                    ]
                                },
                                {
                                    title: '0 VISIBILITY ISSUE',
                                    isSectionHeader: true,
                                    children: [{
                                            title: 'VISIBILITY ISSUE',
                                            content: dataToUse['VISIBILITY ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'INV SYNCED',
                                            content: dataToUse['INV SYNCED'],
                                            isCheckbox: true
                                        }, {
                                            title: 'RIGHT CATEGORY',
                                            content: dataToUse['RIGHT CATEGORY'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'INCOMPLETE LISTING',
                                            content: dataToUse['INCOMPLETE LISTING'],
                                            isCheckbox: true
                                        }, {
                                            title: 'BUYBOX ISSUE',
                                            content: dataToUse['BUYBOX ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'SEO  (KW RICH) ISSUE',
                                            content: dataToUse['SEO  (KW RICH) ISSUE'],
                                            isCheckbox: true
                                        }, {
                                            title: 'TITLE ISSUEAD ISSUE',
                                            content: dataToUse['TITLE ISSUEAD ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'AD ISSUE',
                                            content: dataToUse['AD ISSUE'],
                                            isCheckbox: true
                                        },
                                    ]
                                },
                                {
                                    title: 'LOW VISIBILITY (1-300 clicks)',
                                    isSectionHeader: true,
                                    children: [{
                                            title: 'SEO  (KW RICH) ISSUE',
                                            content: dataToUse['SEO  (KW RICH) ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'TITLE ISSUE',
                                            content: dataToUse['TITLE ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'BP ISSUE',
                                            content: dataToUse['TBP ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'DESCR ISSUE',
                                            content: dataToUse['DESCR ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'SPECS ISSUE',
                                            content: dataToUse['SPECS ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'IMG ISSUE',
                                            content: dataToUse['IMG ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'AD ISSUE',
                                            content: dataToUse['AD ISSUE'],
                                            isCheckbox: true
                                        }
                                    ]
                                },
                                {
                                    title: 'CTR ISSUE (impressions but no clicks)',
                                    isSectionHeader: true,
                                    children: [{
                                            title: 'CATEGORY ISSUE',
                                            content: dataToUse['CATEGORY ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'TITILE ISSUE',
                                            content: dataToUse['TITILE ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'MAIN IMAGE ISSUE',
                                            content: dataToUse['MAIN IMAGE ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'PRICE ISSUE',
                                            content: dataToUse['PRICE ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'REVIEW ISSUE',
                                            content: dataToUse['REVIEW ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'WRONG KW IN LISTING',
                                            content: dataToUse['WRONG KW IN LISTING'],
                                            isCheckbox: true
                                        }
                                    ]
                                },
                                {
                                    title: 'CVR ISSUE',
                                    isSectionHeader: true,
                                    children: [{
                                            title: 'CVR ISSUE',
                                            content: dataToUse['CVR ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'PRICE ISSUE',
                                            content: dataToUse['PRICE ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'REV ISSUE',
                                            content: dataToUse['REV ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'IMAGE ISSUE',
                                            content: dataToUse['IMAGE ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'VID ISSUE',
                                            content: dataToUse['VID ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'BP ISSUE',
                                            content: dataToUse['BP ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'DESCR ISSUE',
                                            content: dataToUse['DESCR ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'USP HIGHLIGHT ISSUE',
                                            content: dataToUse['USP HIGHLIGHT ISSUE'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'SPECS ISSUES',
                                            content: dataToUse['SPECS ISSUES'],
                                            isCheckbox: true
                                        },
                                        {
                                            title: 'MISMATCH ISSUE',
                                            content: dataToUse['MISMATCH ISSUE'],
                                            isCheckbox: true
                                        }
                                    ]
                                },
                                {
                                    title: 'NOTES',
                                    content: dataToUse['NOTES']
                                },
                                {
                                    title: 'ACTION',
                                    content: dataToUse['ACTION']
                                },
                                {
                                    title: 'ACTION',
                                    content: dataToUse['ACTION']
                                },
                            ];
                            break;
                        default:
                            fieldsToDisplay = commonFields;
                    }

                    // Combine common fields with type-specific fields
                    fieldsToDisplay = [...commonFields, ...fieldsToDisplay];

                    // Create cards for each field
                    fieldsToDisplay.forEach(field => {
                        if (field.isSectionHeader) {
                            // Create section container
                            const sectionContainer = document.createElement('div');
                            sectionContainer.className = 'd-flex flex-column';

                            // Section header
                            const header = document.createElement('div');
                            header.className = 'fw-bold text-nowrap';
                            header.style.cssText = `
                                padding-left: 8px;
                                margin-bottom: 3px;
                                color: rgb(73, 80, 87);
                                position: relative;
                                z-index: 1;
                            `;
                            header.textContent = field.title;
                            sectionContainer.appendChild(header);

                            // Cards row
                            const cardsRow = document.createElement('div');
                            cardsRow.className = 'd-flex flex-nowrap gap-2';
                            cardsRow.style.marginTop = '2px';

                            // Create cards for each child field
                            field.children.forEach(childField => {
                                const card = createFieldCard(childField, dataToUse, type,
                                    itemId);
                                cardsRow.appendChild(card);
                            });

                            sectionContainer.appendChild(cardsRow);
                            mainContainer.appendChild(sectionContainer);
                        } else {
                            // Create standalone card
                            const card = createFieldCard(field, dataToUse, type, itemId);
                            mainContainer.appendChild(card);
                        }
                    });

                    // Create modal with the content
                    const modal = ModalSystem.createModal(
                        modalId,
                        `${type.charAt(0).toUpperCase() + type.slice(1)} Details`,
                        mainContainer.outerHTML
                    );

                    // Show the modal
                    ModalSystem.showModal(modalId);

                    // Setup edit handlers after modal is shown
                    setTimeout(() => {
                        setupEditHandlers(modalId);
                    }, 100);
                } catch (error) {
                    console.error("Error in openModal:", error);
                    showNotification('danger', 'Failed to open details view. Please try again.');
                }
            };

            // New function to handle Scouth products view specifically
            function openScouthProductsView(data, modalId) {
                if (!data.scout_data || !data.scout_data.all_data) {
                    const modal = ModalSystem.createModal(
                        modalId,
                        'Scouth Products View Details',
                        '<div class="alert alert-warning">No scout data available</div>'
                    );
                    ModalSystem.showModal(modalId);
                    return;
                }

                // Sort products by price (lowest first)
                const sortedProducts = [...data.scout_data.all_data].sort((a, b) => {
                    const priceA = parseFloat(a.price) || Infinity;
                    const priceB = parseFloat(b.price) || Infinity;
                    return priceA - priceB;
                });

                // Create header with Parent and SKU
                const header = document.createElement('div');
                header.className = 'scouth-header';
                header.innerHTML = `
                    <div class="scouth-header-item">
                        <div class="scouth-product-label">Parent</div>
                        <div class="scouth-product-value">${data.Parent || 'N/A'}</div>
                    </div>
                    <div class="scouth-header-item">
                        <div class="scouth-product-label">SKU</div>
                        <div class="scouth-product-value">${data['(Child) sku'] || 'N/A'}</div>
                    </div>
                `;

                // Create table wrapper
                const tableWrapper = document.createElement('div');
                tableWrapper.className = 'scouth-table-wrapper';
                tableWrapper.style.height = '425px';
                tableWrapper.style.overflowY = 'auto';

                // Create table header
                const tableHeader = document.createElement('div');
                tableHeader.className = 'scouth-table-header';
                tableHeader.style.position = 'sticky';
                tableHeader.style.top = '0';
                tableHeader.style.backgroundColor = '#fff';
                tableHeader.style.zIndex = '10';
                tableHeader.innerHTML = `
                    <div class="scouth-table-cell">ID</div>
                    <div class="scouth-table-cell">Price</div>
                    <div class="scouth-table-cell">Category</div>
                    <div class="scouth-table-cell">Dimensions</div>
                    <div class="scouth-table-cell">Image</div>
                    <div class="scouth-table-cell">Quality Score</div>
                    <div class="scouth-table-cell">Parent ASIN</div>
                    <div class="scouth-table-cell">Product Rank</div>
                    <div class="scouth-table-cell">Rating</div>
                    <div class="scouth-table-cell">Reviews</div>
                    <div class="scouth-table-cell">Weight</div>
                `;

                // Create table body
                const tableBody = document.createElement('div');
                tableBody.className = 'scouth-table-body';

                // Add CSS for image thumbnails
                const style = document.createElement('style');
                style.textContent = `
                    .scouth-image-link {
                        display: inline-block;
                    }
                    .scouth-image-thumbnail {
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        object-fit: cover;
                        cursor: pointer;
                        border: 2px solid #ddd;
                        transition: transform 0.2s;
                    }
                    .scouth-image-thumbnail:hover {
                        transform: scale(1.1);
                        border-color: #aaa;
                    }
                `;
                document.head.appendChild(style);

                // Add product rows
                sortedProducts.forEach(product => {
                    const row = document.createElement('div');
                    row.className = 'scouth-table-row';

                    let imageCellContent = 'N/A';
                    if (product.image_url) {
                        const link = document.createElement('a');
                        link.className = 'scouth-image-link';
                        link.href = product.image_url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';

                        const thumbnail = document.createElement('img');
                        thumbnail.className = 'scouth-image-thumbnail';
                        thumbnail.src = product.image_url;
                        thumbnail.alt = 'Product image';

                        link.appendChild(thumbnail);
                        imageCellContent = link.outerHTML;
                    }

                    row.innerHTML = `
                        <div class="scouth-table-cell">${product.id || 'N/A'}</div>
                        <div class="scouth-table-cell">${product.price ? '$' + parseFloat(product.price).toFixed(2) : 'N/A'}</div>
                        <div class="scouth-table-cell">${product.category || 'N/A'}</div>
                        <div class="scouth-table-cell">${product.dimensions || 'N/A'}</div>
                        <div class="scouth-table-cell">${imageCellContent}</div>
                        <div class="scouth-table-cell">${product.listing_quality_score || 'N/A'}</div>
                        <div class="scouth-table-cell">${product.parent_asin || 'N/A'}</div>
                        <div class="scouth-table-cell">${product.product_rank || 'N/A'}</div>
                        <div class="scouth-table-cell">${product.rating || 'N/A'}</div>
                        <div class="scouth-table-cell">${product.reviews || 'N/A'}</div>
                        <div class="scouth-table-cell">${product.weight || 'N/A'}</div>
                    `;
                    tableBody.appendChild(row);
                });

                // Assemble table
                tableWrapper.appendChild(tableHeader);
                tableWrapper.appendChild(tableBody);

                // Create main container
                const mainContainer = document.createElement('div');
                mainContainer.appendChild(header);
                mainContainer.appendChild(tableWrapper);

                // Create modal
                const modal = ModalSystem.createModal(
                    modalId,
                    'Scouth Products View Details (Sorted by Lowest Price)',
                    mainContainer.outerHTML
                );

                // Show the modal
                ModalSystem.showModal(modalId);
            }

            // Helper function to create a field card
            function createFieldCard(field, data, type, itemId) {
                const hyperlinkFields = ['LINK 1', 'LINK 2', 'LINK 3', 'LINK 4', 'LINK 5'];

                const editableFields = [
                    'SPRICE', 'Tannishtha done', 'LMP 1', 'LINK 1', 'LMP 2', 'LINK 2',
                    'LMP3', 'LINK 3', 'LMP 4', 'LINK 4', 'LMP 5', 'LINK 5',
                    'HIDE', 'LISTED', 'LIVE / ACTIVE', 'VISIBILITY ISSUE', 'INV SYNCED',
                    'RIGHT CATEGORY', 'INCOMPLETE LISTING', 'BUYBOX ISSUE', 'SEO  (KW RICH) ISSUE',
                    'TITLE ISSUEAD ISSUE', 'AD ISSUE', 'BP ISSUE', 'DESCR ISSUE', 'SPECS ISSUE',
                    'IMG ISSUE', 'CATEGORY ISSUE', 'MAIN IMAGE ISSUE', 'PRICE ISSUE',
                    'REVIEW ISSUE', 'WRONG KW IN LISTING', 'CVR ISSUE', 'REV ISSUE',
                    'IMAGE ISSUE', 'VID ISSUE', 'USP HIGHLIGHT ISSUE', 'SPECS ISSUES',
                    'MISMATCH ISSUE', 'NOTES', 'ACTION', 'TITLE ISSUE'
                ];

                const percentageFields = ['KwCtr60', 'KwCtr30', 'PtCtr60', 'PtCtr30', 'DspCtr60',
                    'DspCtr30',
                    'HdCtr60', 'HdCtr30', 'SCVR', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30',
                    'DspCvr60', 'DspCvr30', 'HdCvr60', 'HdCvr30', 'TCvr60', 'TCvr30',
                    'KwAcos60', 'KwAcos30', 'PtAcos60', 'PtAcos30', 'DspAcos60', 'DspAcos30',
                    'HdAcos60', 'HdAcos30', 'TCtr60', 'TCtr30', 'TAcos60', 'TAcos30',
                    'Tacos60', 'Tacos30', 'PFT_percentage', 'TPFT', 'ROI_percentage'
                ];

                const getIndicatorColor = (fieldTitle, fieldValue) => {
                    // Handle both string percentages (like "15%") and raw numbers
                    let value;
                    if (typeof fieldValue === 'string' && fieldValue.includes('%')) {
                        value = parseFloat(fieldValue.replace('%', ''));
                    } else {
                        value = parseFloat(fieldValue);
                    }

                    // Handle NaN cases (invalid numbers)
                    if (isNaN(value)) {
                        return 'gray';
                    }

                    // Price view specific colors
                    if (type.toLowerCase() === 'price view') {
                        if (['PFT_percentage', 'TPFT'].includes(fieldTitle)) {
                            if (value < 10) return 'red';
                            if (value >= 10 && value < 15) return 'yellow';
                            if (value >= 15 && value < 20) return 'blue';
                            if (value >= 20 && value < 40) return 'green';
                            return 'pink'; // 40 and above
                        }
                        if (fieldTitle === 'ROI_percentage') {
                            if (value <= 50) return 'red';
                            if (value > 50 && value <= 75) return 'yellow';
                            if (value > 75 && value <= 100) return 'green';
                            return 'pink'; // Above 100
                        }
                        if (fieldTitle === 'Spft%') {
                            // Convert to percentage for easier comparison
                            const percentValue = Math.abs(value) < 100 ? value * 100 : value;
                            if (percentValue < 0) return 'red'; // Negative values (loss)
                            if (percentValue < 10) return 'red'; // Less than 10%
                            if (percentValue < 15) return 'yellow'; // 10-14.99%
                            if (percentValue < 20) return 'blue'; // 15-19.99%
                            if (percentValue < 40) return 'green'; // 20-39.99%
                            return 'pink'; // 40% and above
                        }
                    }

                    // Advertisement view specific colors
                    if (type.toLowerCase() === 'advertisement view') {
                        if (['KwAcos60', 'KwAcos30', 'PtAcos60', 'PtAcos30', 'DspAcos60',
                                'DspAcos30', 'TAcos60', 'TAcos30'
                            ].includes(fieldTitle)) {
                            if (value === 0) return 'red';
                            if (value > 0.01 && value <= 7) return 'pink';
                            if (value > 7 && value <= 14) return 'green';
                            if (value > 14 && value <= 21) return 'blue';
                            if (value > 21 && value <= 28) return 'yellow';
                            if (value > 28) return 'red';
                        }
                        if (['KwCvr60', 'KwCvr30', 'PtCvr60', 'DspCvr60', 'PtCvr30',
                                'DspCvr30', 'HdAcos60', 'HdAcos30', 'HdCvr60', 'HdCvr30',
                                'TCvr60', 'TCvr30'
                            ].includes(fieldTitle)) {
                            if (value <= 7) return 'red';
                            if (value > 7 && value <= 13) return 'green';
                            return fieldTitle.includes('PtCvr') || fieldTitle.includes('DspCvr') ||
                                fieldTitle.includes('HdCvr') || fieldTitle.includes('TCvr') ? 'pink' : 'gray';
                        }
                    }

                    // Conversion view specific colors
                    if (type.toLowerCase() === 'conversion view') {
                        if (['Scvr', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30',
                                'DspCvr60', 'DspCvr30', 'HdCvr60', 'HdCvr30',
                                'TCvr60', 'TCvr30'
                            ].includes(fieldTitle)) {
                            if (value <= 7) return 'red';
                            if (value > 7 && value <= 13) return 'green';
                            return 'pink';
                        }
                    }

                    // Default color for all other cases
                    return 'gray';
                };

                let content = field.content === null || field.content === undefined || field.content === '' ? ' ' :
                    field.content;
                const showStatusIndicator = statusIndicatorFields[type]?.includes(field.title) || false;
                const indicatorColor = showStatusIndicator ? getIndicatorColor(field.title, content) : '';
                const isHyperlink = hyperlinkFields.includes(field.title) || field.isHyperlink;
                const isCheckbox = field.isCheckbox || false;

                // Create card element
                const card = document.createElement('div');
                card.className =
                    `card flex-shrink-0 position-relative ${showStatusIndicator ? 'card-bg-' + indicatorColor : ''}`;
                card.style.cssText = `
                    min-width: 160px;
                    width: auto;
                    max-width: 100%;
                    margin-top: 0px;
                    border-radius: 8px;
                    box-shadow: rgba(0, 0, 0, 0.1) 0px 2px 4px;
                `;

                // Add hidden SL No input
                const slInput = document.createElement('input');
                slInput.type = 'hidden';
                slInput.className = 'hidden-sl-no';
                slInput.value = itemId;
                card.appendChild(slInput);

                // Add hidden field name input
                const fieldInput = document.createElement('input');
                fieldInput.type = 'hidden';
                fieldInput.className = 'hidden-field-name';
                fieldInput.value = field.title;
                card.appendChild(fieldInput);

                if (percentageFields.includes(field.title) && typeof content === 'number') {
                    // Skip multiplication for PFT_percentage and ROI_percentage
                    if (!['PFT_percentage', 'ROI_percentage'].includes(field.title)) {
                        content = `${(content * 100).toFixed(2)}%`;
                    } else {
                        content = `${content.toFixed(2)}%`;
                    }
                }

                // Add edit icon if field is editable
                if (editableFields.includes(field.title)) {
                    const editIcon = document.createElement('div');
                    editIcon.className = 'position-absolute top-0 end-0 p-2 edit-icon';
                    editIcon.style.cssText = 'cursor:pointer; z-index: 1;';
                    editIcon.innerHTML = '<i class="fas fa-pen text-primary"></i>';
                    card.appendChild(editIcon);
                }

                const cardBody = document.createElement('div');
                cardBody.className = 'card-body';
                cardBody.style.padding = '0.75rem';
                cardBody.style.position = 'relative';

                // Add card title
                const cardTitle = document.createElement('h6');
                cardTitle.className = 'card-title';
                cardTitle.style.cssText = `
                    font-size: 0.85rem;
                    margin-bottom: 0.5rem;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    padding-right: 24px;
                `;
                cardTitle.textContent = field.title;
                cardBody.appendChild(cardTitle);

                // Add card content
                const cardContent = document.createElement('p');
                cardContent.className = 'card-text editable-content';
                cardContent.setAttribute('data-is-hyperlink', isHyperlink);

                if (isCheckbox) {
                    // Create checkbox container
                    const checkboxContainer = document.createElement('div');
                    checkboxContainer.className = 'form-check form-switch';

                    // Create checkbox input
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'form-check-input';
                    checkbox.checked = content === true || content === 'true';
                    checkbox.disabled = true;

                    checkboxContainer.appendChild(checkbox);
                    cardContent.appendChild(checkboxContainer);
                } else if (isHyperlink) {
                    const link = document.createElement('a');
                    link.href = content;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = content.length > 30 ?
                        content.substring(0, 15) + '...' + content.slice(-10) :
                        content;
                    cardContent.appendChild(link);
                } else {
                    cardContent.textContent = content;
                }

                cardBody.appendChild(cardContent);
                card.appendChild(cardBody);

                return card;
            }

            function setupEditHandlers(modalId) {
                const modalElement = document.getElementById(modalId);
                if (!modalElement) return;

                $(modalElement).off('click', '.edit-icon, .save-icon');

                $(modalElement).on('click', '.edit-icon', function(e) {
                    e.stopPropagation();

                    const icon = $(this);
                    const card = icon.closest('.card');
                    const contentElement = card.find('.editable-content');
                    const checkbox = contentElement.find('.form-check-input');
                    const title = card.find('.card-title').text().trim();

                    // If it's a checkbox field, enable it and change to save icon
                    if (checkbox.length > 0) {
                        checkbox.prop('disabled', false);
                        icon.html('<i class="fas fa-check text-success"></i>')
                            .removeClass('edit-icon')
                            .addClass('save-icon');
                        return;
                    }

                    const isHyperlink = contentElement.data('is-hyperlink');
                    const slNo = card.find('.hidden-sl-no').val();

                    if (currentEditingElement && currentEditingElement.is(contentElement)) {
                        return;
                    }

                    if (isEditMode && currentEditingElement) {
                        exitEditMode(currentEditingElement);
                    }

                    let originalContent = contentElement.text().trim();
                    if (isHyperlink && contentElement.find('a').length) {
                        originalContent = contentElement.find('a').attr('href');
                    }

                    contentElement.data('original-content', originalContent)
                        .html(originalContent)
                        .attr('contenteditable', 'true')
                        .addClass('border border-primary')
                        .focus();

                    isEditMode = true;
                    currentEditingElement = contentElement;

                    icon.html('<i class="fas fa-check text-success"></i>')
                        .removeClass('edit-icon')
                        .addClass('save-icon');
                });

                // Save handler
                $(modalElement).on('click', '.save-icon', function(e) {
                    e.stopPropagation();
                    const icon = $(this);
                    const card = icon.closest('.card');
                    const contentElement = card.find('.editable-content');
                    const checkbox = contentElement.find('.form-check-input');
                    const title = card.find('.card-title').text().trim();
                    const slNo = card.find('.hidden-sl-no').val();
                    const isHyperlink = contentElement.data('is-hyperlink');

                    // Get the updated value
                    let updatedValue;
                    if (checkbox.length > 0) {
                        updatedValue = checkbox.prop('checked') ? "true" : "false";
                        checkbox.prop('disabled', true);
                    } else {
                        updatedValue = contentElement.text().trim();
                        if (isHyperlink && contentElement.find('a').length) {
                            updatedValue = contentElement.find('a').attr('href');
                        }
                    }

                    saveChanges(contentElement, title, slNo, isHyperlink, updatedValue, checkbox.length >
                        0);
                });
            }

            function exitEditMode(contentElement) {
                const isCheckbox = contentElement.find('.form-check-input').length > 0;

                if (isCheckbox) {
                    contentElement.find('.form-check-input').prop('disabled', true);
                } else {
                    const isHyperlink = contentElement.data('is-hyperlink');
                    const originalContent = contentElement.data('original-content');

                    if (originalContent) {
                        if (isHyperlink) {
                            const displayText = originalContent.length > 30 ?
                                originalContent.substring(0, 15) + '...' + originalContent.slice(-10) :
                                originalContent;
                            contentElement.html(
                                `<a href="${originalContent}" target="_blank" rel="noopener noreferrer">${displayText}</a>`
                            );
                        } else {
                            contentElement.text(originalContent);
                        }
                    }

                    contentElement.attr('contenteditable', 'false')
                        .removeClass('border border-primary');
                }

                const card = contentElement.closest('.card');
                card.find('.save-icon').html('<i class="fas fa-pen text-primary"></i>')
                    .removeClass('save-icon')
                    .addClass('edit-icon');

                isEditMode = false;
                currentEditingElement = null;
            }

            function saveChanges(contentElement, title, slNo, isHyperlink, updatedValue, isCheckbox, rowElement) {
                const card = contentElement.closest('.card') || contentElement.closest('tr');
                const itemId = card.find('.hidden-sl-no').val() || slNo;
                const saveIcon = card.find('.save-icon') || card.find('.edit-icon');

                // Prepare data for API call (only for the original field)
                const data = {
                    slNo: parseInt(itemId),
                    updates: {
                        [title]: updatedValue
                    }
                };

                // Show loading indicator
                if (saveIcon) {
                    saveIcon.html('<i class="fas fa-spinner fa-spin text-primary"></i>');
                }

                // 1. First update the cache immediately
                const cacheUpdateValue = isCheckbox ? (updatedValue === "true") : updatedValue;
                amazonLowVisibilityDataCache.updateField(itemId, title, cacheUpdateValue);

                // 2. Update the filteredData array to reflect the change
                const index = filteredData.findIndex(item => item['SL No.'] == itemId);
                if (index !== -1) {
                    filteredData[index][title] = cacheUpdateValue;

                    // If this is an SPRICE update, calculate and update Spft% in cache using new formula
                    if (title === 'SPRICE' && filteredData[index].raw_data) {
                        const item = filteredData[index];
                        const price = parseFloat(item.price) || 0;
                        const SHIP = parseFloat(item.raw_data.SHIP) || 0;
                        const LP = parseFloat(item.raw_data.LP) || 0;
                        const SPRICE = parseFloat(updatedValue) || 0;

                        // Calculate Spft% using new formula: (SPRICE * price - SHIP - LP) / SPRICE
                        let Spft = 0;
                        if (SPRICE !== 0) {
                            Spft = (SPRICE * 0.71 - SHIP - LP) / SPRICE;

                        }

                        // Update Spft% in cache and local data
                        amazonLowVisibilityDataCache.updateField(itemId, 'Spft%', Spft);
                        filteredData[index]['Spft%'] = Spft;
                        filteredData[index].raw_data['Spft%'] = Spft;
                    }

                    // If this is an R&A update, ensure the raw_data is also updated
                    if (title === 'R&A' && filteredData[index].raw_data) {
                        filteredData[index].raw_data[title] = cacheUpdateValue;
                    }
                }

                // 3. Update the UI immediately
                if (rowElement) {
                    // For table rows (R&A column)
                    const checkbox = rowElement.find('.ra-checkbox');
                    if (checkbox.length) {
                        checkbox.prop('checked', cacheUpdateValue);
                    }
                } else {
                    // For modal cards
                    if (isCheckbox) {
                        contentElement.find('.form-check-input').prop('checked', cacheUpdateValue);
                    } else if (isHyperlink) {
                        const displayText = cacheUpdateValue.length > 30 ?
                            cacheUpdateValue.substring(0, 15) + '...' + cacheUpdateValue.slice(-10) :
                            cacheUpdateValue;
                        contentElement.html(
                            `<a href="${cacheUpdateValue}" target="_blank" rel="noopener noreferrer">${displayText}</a>`
                        );
                    } else {
                        contentElement.text(cacheUpdateValue);
                    }
                }

                // 4. If we updated SPRICE, update the Spft% card in the modal if it's open
                if (title === 'SPRICE') {
                    const modalId = `modal-${itemId}-price-view`;
                    const modalElement = document.getElementById(modalId);
                    if (modalElement) {
                        // Find the Spft% card
                        const cards = modalElement.querySelectorAll('.card');
                        for (let card of cards) {
                            const cardTitle = card.querySelector('.card-title');
                            if (cardTitle && cardTitle.textContent.trim() === 'Spft%') {
                                const contentElement = card.querySelector('.card-text');
                                if (contentElement) {
                                    // Format Spft% for display
                                    const SpftValue = filteredData[index]['Spft%'] || 0;
                                    const absValue = Math.abs(SpftValue);
                                    let displayValue;

                                    if (absValue < 100) {
                                        displayValue = (SpftValue * 100).toFixed(2);
                                    } else {
                                        displayValue = SpftValue.toFixed(2);
                                    }

                                    contentElement.textContent = displayValue + ' %';
                                }
                                break;
                            }
                        }
                    }
                }

                // 5. Send the update to the server ONLY for the original field
                $.ajax({
                    method: 'POST',
                    // url: window.location.origin + (window.location.pathname.includes('/public') ?
                    //     '/public' : '') + '/api/update-amazon-column',
                    data: JSON.stringify(data),
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        // Update was already done in cache, just show success
                        if (saveIcon) {
                            saveIcon.html('<i class="fas fa-pen text-primary"></i>')
                                .removeClass('save-icon')
                                .addClass('edit-icon');
                        }

                        // Make the field uneditable again
                        if (!rowElement) {
                            if (isCheckbox) {
                                contentElement.find('.form-check-input').prop('disabled', true);
                            } else {
                                contentElement.attr('contenteditable', 'false')
                                    .removeClass('border border-primary');
                            }
                        }

                        showNotification('success', `${title} Updated Successfully`);

                        // If this was an R&A update from the table, ensure UI is consistent
                        if (rowElement && table) {
                            checkParentRAStatus();
                            table.redraw();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Update failed:', {
                            status: xhr.status,
                            error: error,
                            response: xhr.responseText
                        });

                        // Revert changes on error
                        const originalValue = contentElement.data('original-value');

                        // Revert cache
                        amazonLowVisibilityDataCache.updateField(itemId, title, originalValue);

                        // Revert filteredData
                        if (index !== -1) {
                            filteredData[index][title] = originalValue;
                            if (title === 'R&A' && filteredData[index].raw_data) {
                                filteredData[index].raw_data[title] = originalValue;
                            }

                            // If this was an SPRICE update, revert Spft% as well
                            if (title === 'SPRICE') {
                                // We don't have original Spft% value, so we'll need to recalculate it
                                const item = filteredData[index];
                                const SHIP = parseFloat(item.raw_data.SHIP) || 0;
                                const LP = parseFloat(item.raw_data.LP) || 0;
                                const SPRICE = parseFloat(originalValue) || 0;

                                let Spft = 0;
                                if (SPRICE !== 0) {
                                    Spft = (SPRICE * price - SHIP - LP) / SPRICE;
                                }

                                amazonLowVisibilityDataCache.updateField(itemId, 'Spft%', Spft);
                                filteredData[index]['Spft%'] = Spft;
                                filteredData[index].raw_data['Spft%'] = Spft;
                            }
                        }

                        // Revert UI
                        if (rowElement) {
                            const checkbox = rowElement.find('.ra-checkbox');
                            if (checkbox.length) {
                                checkbox.prop('checked', originalValue);
                            }
                            if (table) table.redraw();
                        } else {
                            if (isCheckbox) {
                                contentElement.find('.form-check-input')
                                    .prop('checked', originalValue)
                                    .prop('disabled', true);
                            } else if (isHyperlink) {
                                const displayText = originalValue.length > 30 ?
                                    originalValue.substring(0, 15) + '...' + originalValue.slice(-10) :
                                    originalValue;
                                contentElement.html(
                                    `<a href="${originalValue}" target="_blank" rel="noopener noreferrer">${displayText}</a>`
                                );
                            } else {
                                contentElement.text(originalValue);
                            }
                        }

                        if (saveIcon) {
                            saveIcon.html('<i class="fas fa-pen text-primary"></i>')
                                .removeClass('save-icon')
                                .addClass('edit-icon');
                        }

                        // Make sure field is uneditable after error
                        if (!rowElement && !isCheckbox) {
                            contentElement.attr('contenteditable', 'false')
                                .removeClass('border border-primary');
                        }

                        let errorMessage = 'Update failed - please try again';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        showNotification('danger', errorMessage, title);
                    }
                });
            }

            // Initialize tooltips
            function initTooltips() {
                $('[data-bs-toggle="tooltip"]').tooltip({
                    trigger: 'hover',
                    placement: 'top',
                    boundary: 'window',
                    container: 'body',
                    offset: [0, 5],
                    template: '<div class="tooltip" role="tooltip">' +
                        '<div class="tooltip-arrow"></div>' +
                        '<div class="tooltip-inner"></div></div>'
                });
            }

            // Make columns resizable
            function initResizableColumns() {
                const $table = $('#amazonLowVisibility-table');
                const $headers = $table.find('th');
                let startX, startWidth, columnIndex;

                $headers.each(function() {
                    $(this).append('<div class="resize-handle"></div>');
                });

                $table.on('mousedown', '.resize-handle', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    isResizing = true;
                    $(this).addClass('resizing');

                    const $th = $(this).parent();
                    columnIndex = $th.index();
                    startX = e.pageX;
                    startWidth = $th.outerWidth();

                    $('body').css('user-select', 'none');
                });

                let resizeDebounceTimer = null;

                $(document).on('mousemove', function(e) {
                    if (!isResizing) return;

                    const $resizer = $('.resize-handle.resizing');
                    if ($resizer.length) {
                        const $th = $resizer.parent();
                        const columnIndex = $th.index();
                        const newWidth = startWidth + (e.pageX - startX);
                        $th.css('width', newWidth + 'px');
                        $th.css('min-width', newWidth + 'px');
                        $th.css('max-width', newWidth + 'px');

                        // Debounced update for truncated text
                        if ([10, 11, 12].includes(columnIndex)) {
                            clearTimeout(resizeDebounceTimer);
                            resizeDebounceTimer = setTimeout(() => {
                                const $table = $('#amazonLowVisibility-table');
                                $table.find('tbody tr').each(function() {
                                    const $cell = $(this).find('td').eq(columnIndex);
                                    const $span = $cell.find('.truncated-text');
                                    if ($span.length) {
                                        const fullText = $span.attr('title') || $span
                                            .text();
                                        let minLetters = 10;
                                        let letters = Math.max(minLetters, Math.floor(
                                            newWidth / 12));
                                        const truncated = fullText.length > letters ?
                                            fullText.substring(0, letters) + '...' :
                                            fullText;
                                        $span.text(truncated);
                                    }
                                });
                            }, 50); // 50ms debounce
                        }
                    }
                });

                $(document).on('mouseup', function(e) {
                    if (!isResizing) return;

                    e.stopPropagation();
                    $('.resize-handle').removeClass('resizing');
                    $('body').css('user-select', '');
                    isResizing = false;

                    // Get the new width of the resized column
                    const $table = $('#amazonLowVisibility-table');
                    const $headers = $table.find('th');
                    const newWidth = $headers.eq(columnIndex).outerWidth() || 120;

                    // Only update truncated text for Reason, Action Required, Action Taken columns
                    // Adjust columnIndex if needed (here: 10, 11, 12)
                    if ([10, 11, 12].includes(columnIndex)) {
                        $table.find('tbody tr').each(function() {
                            const $cell = $(this).find('td').eq(columnIndex);
                            const $span = $cell.find('.truncated-text');
                            if ($span.length) {
                                // Get full text from title attribute
                                const fullText = $span.attr('title') || $span.text();
                                // Update truncated text
                                let minLetters = 10;
                                let letters = Math.max(minLetters, Math.floor(newWidth / 12));
                                const truncated = fullText.length > letters ? fullText.substring(0,
                                    letters) + '...' : fullText;
                                $span.text(truncated);
                            }
                        });
                    }
                });
            }

            // Initialize sorting functionality
            function initSorting() {
                $('th[data-field]').addClass('sortable').on('click', function(e) {
                    if (isResizing) {
                        e.stopPropagation();
                        return;
                    }

                    // Prevent sorting when clicking on search inputs
                    if ($(e.target).is('input') || $(e.target).closest('.position-relative').length) {
                        return;
                    }

                    const th = $(this).closest('th');
                    const thField = th.data('field');
                    const dataField = thField === 'parent' ? 'Parent' : thField;

                    // Toggle direction if clicking same column, otherwise reset to ascending
                    if (currentSort.field === dataField) {
                        currentSort.direction *= -1;
                    } else {
                        currentSort.field = dataField;
                        currentSort.direction = 1;
                    }

                    // Update UI arrows
                    $('.sort-arrow').html('↓');
                    $(this).find('.sort-arrow').html(currentSort.direction === 1 ? '↑' : '↓');

                    // Sort with fresh data
                    const freshData = [...tableData];
                    freshData.sort((a, b) => {
                        const valA = a[dataField] || '';
                        const valB = b[dataField] || '';

                        // Numeric comparison for numeric fields
                        if (dataField === 'sl_no' || dataField === 'INV' || dataField === 'L30') {
                            return (parseFloat(valA) - parseFloat(valB)) * currentSort.direction;
                        }

                        // String comparison for other fields
                        return String(valA).localeCompare(String(valB)) * currentSort.direction;
                    });

                    // Tabulator handles sorting automatically, no need to manually sort
                    if (table) {
                        table.setSort(currentSort.field, currentSort.direction === 1 ? 'asc' : 'desc');
                    }
                });
            }

            // Initialize pagination - Tabulator handles this automatically
            function initPagination() {
                // Tabulator handles pagination, no custom code needed

                // Similar modifications for other pagination buttons...
                // But since we're showing all rows, you might want to disable pagination completely
            }

            function updatePaginationInfo() {
                // Since we're showing all rows, you can either:
                // Option 1: Hide pagination completely
                $('.pagination-controls').hide();

                // Option 2: Show "Showing all rows" message
                $('#page-info').text('Showing all rows');
                $('#first-page, #prev-page, #next-page, #last-page').prop('disabled', true);
            }

            // Initialize search functionality - Tabulator handles search in initTabulatorTable(), this function kept for compatibility
            function initSearch() {
                // Search is handled in initTabulatorTable() via $('#search-input').on('keyup')
                // No additional code needed here
            }

            // Initialize column toggle functionality for Tabulator
            function initColumnToggle() {
                if (!table) return;

                const $menu = $('#columnToggleMenu');
                const $dropdownBtn = $('#hideColumnsBtn');

                // Load saved visibility from localStorage
                let amazonZeroViewVisibility = JSON.parse(localStorage.getItem('amazonZeroViewVisibility')) || {};

                $menu.empty();

                // Get columns from Tabulator
                const columns = table.getColumns();
                columns.forEach(function(column) {
                    const field = column.getField();
                    const title = column.getDefinition().title || field;
                    const isChecked = amazonZeroViewVisibility.hasOwnProperty(field) ? amazonZeroViewVisibility[field] : true;

                    // Set initial visibility
                    column.hide(!isChecked);

                    const $item = $(`
                        <div class="column-toggle-item">
                            <input type="checkbox" class="column-toggle-checkbox" 
                                id="toggle-${field}" data-field="${field}" ${isChecked ? 'checked' : ''}>
                            <label for="toggle-${field}">${title}</label>
                        </div>
                    `);

                    $menu.append($item);
                });

                $dropdownBtn.on('click', function(e) {
                    e.stopPropagation();
                    $menu.toggleClass('show');
                });

                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.custom-dropdown').length) {
                        $menu.removeClass('show');
                    }
                });

                $menu.on('change', '.column-toggle-checkbox', function() {
                    const field = $(this).data('field');
                    const isVisible = $(this).is(':checked');

                    // Save state in localStorage
                    amazonZeroViewVisibility[field] = isVisible;
                    localStorage.setItem('amazonZeroViewVisibility', JSON.stringify(amazonZeroViewVisibility));

                    // Toggle column visibility in Tabulator
                    const column = table.getColumn(field);
                    if (column) {
                        if (isVisible) {
                            column.show();
                        } else {
                            column.hide();
                        }
                    }
                });

                $('#showAllColumns').on('click', function() {
                    $menu.find('.column-toggle-checkbox').prop('checked', true).trigger('change');
                    $menu.removeClass('show');

                    // Reset all to visible in localStorage
                    columns.forEach(function(column) {
                        const field = column.getField();
                        amazonZeroViewVisibility[field] = true;
                    });
                    localStorage.setItem('amazonZeroViewVisibility', JSON.stringify(amazonZeroViewVisibility));
                });
            }


            // Initialize filters
            function initFilters() {
                $('.dropdown-menu').on('click', '.column-filter', function(e) {
                    e.preventDefault();
                    const $this = $(this);
                    const column = $this.data('column');
                    const color = $this.data('color');
                    const text = $this.find('span').text().trim();

                    $this.closest('.dropdown')
                        .find('.dropdown-toggle')
                        .html(`<span class="status-circle ${color}"></span> ${column} (${text})`);

                    state.filters[column] = color;
                    $this.closest('.dropdown-menu').removeClass('show');
                    applyColumnFilters();
                });

                // Entry type filter
                $('.entry-type-filter').on('click', function(e) {
                    e.preventDefault();
                    const value = $(this).data('value');
                    const text = $(this).text();

                    $('#entryTypeFilter').html(`Entry Type: ${text}`);
                    state.filters.entryType = value;
                    $('.dropdown-menu').removeClass('show');
                    applyColumnFilters();
                });
            }

            // Add this script after your other filter initializations:
            $('#inv-filter').on('change', function() {
                applyColumnFilters();
            });


            function applyColumnFilters() {
                if (!table) return;

                const filters = [];

                // Apply row type filter
                const rowTypeFilter = $('#row-data-type').val();
                if (rowTypeFilter === 'parent') {
                    filters.push({field: "is_parent", type: "=", value: true});
                } else if (rowTypeFilter === 'sku') {
                    filters.push({field: "is_parent", type: "=", value: false});
                }

                // Apply INV filter
                const invFilter = $('#inv-filter').val();
                if (invFilter && invFilter !== 'all') {
                    if (invFilter === '0') {
                        filters.push({field: "INV", type: "=", value: 0});
                    } else if (invFilter === '1-100+') {
                        filters.push({field: "INV", type: ">", value: 0});
                    }
                }

                // Apply color-based filters
                Object.entries(state.filters).forEach(([column, filterValue]) => {
                    if (filterValue === 'all') return;

                    if (column === 'ov_dil' || column === 'A Dil%') {
                        // Custom filter function for color-based filtering
                        table.setFilter(function(data) {
                            const color = getColorForColumn(column, data);
                            return color === filterValue;
                        });
                    }
                });

                // Apply all filters
                if (filters.length > 0) {
                    table.setFilter(filters);
                } else {
                    table.clearFilter();
                }

                calculateTotals();
            }


            // Get color for column based on value
            function getColorForColumn(column, rowData) {
                if (!rowData || rowData[column] === undefined || rowData[column] === null || rowData[column] ===
                    '') {
                    return '';
                }

                // Only multiply by 100 for columns that are stored as decimals
                let value = parseFloat(rowData[column]);
                if (['ov_dil', 'A Dil%', 'Tacos30', 'SCVR'].includes(column)) {
                    value = value * 100;
                }

                // Special cases for numeric columns that must be valid numbers
                const numericColumns = ['PFT_percentage', 'ROI_percentage', 'Tacos30', 'SCVR'];
                if (numericColumns.includes(column) && isNaN(value)) {
                    return '';
                }

                const colorRules = {
                    'ov_dil': {
                        ranges: [16.66, 25, 50], // Key change here
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'A Dil%': {
                        ranges: [16.66, 25, 50],
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'PFT_percentage': {
                        ranges: [10, 15, 20, 40],
                        colors: ['red', 'yellow', 'blue', 'green', 'pink']
                    },
                    'ROI_percentage': {
                        ranges: [50, 75, 100],
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'Tacos30': {
                        ranges: [5, 10, 15, 20],
                        colors: ['pink', 'green', 'blue', 'yellow', 'red']
                    },
                    'SCVR': {
                        ranges: [7, 13],
                        colors: ['red', 'green', 'pink']
                    }
                };

                const rule = colorRules[column] || {};
                if (!rule.ranges) return '';

                let colorIndex = rule.ranges.length; // Default to last color
                for (let i = 0; i < rule.ranges.length; i++) {
                    if (value < rule.ranges[i]) {
                        colorIndex = i;
                        break;
                    }
                }

                return rule.colors[colorIndex] || '';
            }

            // Calculate and display totals
            function calculateTotals() {
                try {
                    if (!table) {
                        resetMetricsToZero();
                        return;
                    }

                    // Get filtered data from Tabulator
                    const activeData = table.getData("active");
                    
                    if (isLoading || activeData.length === 0) {
                        resetMetricsToZero();
                        return;
                    }

                    const metrics = {
                        invTotal: 0,
                        ovL30Total: 0,
                        ovDilTotal: 0,
                        el30Total: 0,
                        eDilTotal: 0,
                        viewsTotal: 0,
                        pftSum: 0,
                        roiSum: 0,
                        tacosTotal: 0,
                        scvrSum: 0,
                        rowCount: 0,
                        totalPftSum: 0,
                        totalSalesL30Sum: 0,
                        totalCogsSum: 0
                    };

                    activeData.forEach(item => {
                        metrics.invTotal += parseFloat(item.INV) || 0;
                        metrics.ovL30Total += parseFloat(item.L30) || 0;
                        metrics.el30Total += parseFloat(item['A L30']) || 0;
                        metrics.eDilTotal += parseFloat(item['A Dil%']) || 0;
                        metrics.viewsTotal += parseFloat(item.Sess30) || 0;
                        let views = parseFloat(item.Sess30) || 0;
                        if (item.NR !== 'NR') {
                            metrics.viewsTotal += views;
                        }
                        metrics.tacosTotal += parseFloat(item.Tacos30) || 0;
                        metrics.pftSum += parseFloat(item['PFT_percentage']) || 0;
                        metrics.roiSum += parseFloat(item.ROI_percentage) || 0;
                        metrics.scvrSum += parseFloat(item.SCVR) || 0;
                        metrics.rowCount++;

                        // Only sum for child rows (not parent rows)
                        if (
                            item['(Child) sku'] &&
                            typeof item['(Child) sku'] === 'string' &&
                            !item['(Child) sku'].toUpperCase().includes('PARENT')
                        ) {
                            const totalPft = parseFloat(item.raw_data['Total_pft']) || 0;
                            const t_sale_l30 = parseFloat(item.raw_data['T_Sale_l30']) || 0;
                            const cogs = parseFloat(item.raw_data['T_COGS']) || 0;

                            metrics.totalPftSum += totalPft;
                            metrics.totalSalesL30Sum += t_sale_l30;
                            metrics.totalCogsSum += cogs;
                        }
                    });

                    metrics.ovDilTotal = metrics.invTotal > 0 ?
                        (metrics.ovL30Total / metrics.invTotal) * 100 : 0;

                    // --- A Dil% metric: (sum of A L30) / (sum of INV) * 100 ---
                    let aDilTotalDisplay = '0%';
                    if (metrics.invTotal > 0) {
                        aDilTotalDisplay = Math.round((metrics.el30Total / metrics.invTotal) * 100) + '%';
                    }

                    const divisor = metrics.rowCount || 1;

                    // Update metric displays
                    $('#inv-total').text(metrics.invTotal.toLocaleString());
                    $('#ovl30-total').text(metrics.ovL30Total.toLocaleString());
                    $('#ovdil-total').text(Math.round(metrics.ovDilTotal) + '%');
                    $('#al30-total').text(metrics.el30Total.toLocaleString());
                    $('#lDil-total').text(aDilTotalDisplay);
                    $('#views-total').text(metrics.viewsTotal.toLocaleString());

                    // --- Custom PFT TOTAL calculation ---
                    let pftTotalDisplay = '0%';
                    if (metrics.totalSalesL30Sum > 0) {
                        pftTotalDisplay = Math.round(metrics.totalPftSum / metrics.totalSalesL30Sum * 100) + '%';
                    }
                    $('#pft-total').text(pftTotalDisplay);

                    // --- ROI TOTAL calculation ---
                    let roiTotalDisplay = '0%';
                    if (metrics.totalCogsSum > 0) {
                        roiTotalDisplay = Math.round(metrics.totalPftSum / metrics.totalCogsSum * 100) + '%';
                    }
                    $('#roi-total').text(roiTotalDisplay);

                    $('#tacos-total').text(Math.round(metrics.tacosTotal / divisor * 100) + '%');
                    $('#cvr-total').text(Math.round(metrics.scvrSum / divisor * 100) + '%');

                } catch (error) {
                    console.error('Error in calculateTotals:', error);
                    resetMetricsToZero();
                }
            }

            function resetMetricsToZero() {
                $('#inv-total').text('0');
                $('#ovl30-total').text('0');
                $('#ovdil-total').text('0%');
                $('#al30-total').text('0');
                $('#lDil-total').text('0%');
                $('#views-total').text('0');
                $('#pft-total').text('0%');
                $('#roi-total').text('0%');
                $('#tacos-total').text('0%');
                $('#cvr-total').text('0%');
            }

            // Initialize enhanced dropdowns
            function initEnhancedDropdowns() {
                // Define constants at the function level
                const minSearchLength = 1;

                // Parent dropdown
                const $parentSearch = $('#parentSearch');
                const $parentResults = $('#parentSearchResults');

                // SKU dropdown
                const $skuSearch = $('#skuSearch');
                const $skuResults = $('#skuSearchResults');

                // Initialize both dropdowns
                initEnhancedDropdown($parentSearch, $parentResults, 'Parent');
                initEnhancedDropdown($skuSearch, $skuResults, '(Child) sku');

                // Close dropdowns when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.dropdown-search-container').length) {
                        $('.dropdown-search-results').hide();
                    }
                });

                // Function to update dropdown results
                function updateDropdownResults($results, field, searchTerm) {
                    if (!tableData.length) return;

                    $results.empty();

                    // Get unique values for the field
                    const uniqueValues = [...new Set(tableData.map(item => String(item[field] || '')))];

                    // Filter based on search term if provided
                    const filteredValues = searchTerm.length >= minSearchLength ?
                        uniqueValues.filter(value =>
                            value.toLowerCase().includes(searchTerm.toLowerCase())
                        ) :
                        uniqueValues;

                    if (filteredValues.length) {
                        filteredValues.sort().forEach(value => {
                            if (value) {
                                $results.append(
                                    `<div class="dropdown-search-item" tabindex="0" data-value="${value}">${value}</div>`
                                );
                            }
                        });
                    } else {
                        $results.append('<div class="dropdown-search-item no-results">No matches found</div>');
                    }

                    $results.show();
                }

                // Function to filter the table by column value using Tabulator
                function filterByColumn(column, value) {
                    if (!table) return;
                    
                    if (value === '') {
                        table.clearFilter();
                    } else {
                        // Use exact match filter
                        table.setFilter(function(data) {
                            return String(data[column] || '').toLowerCase() === value.toLowerCase();
                        });
                    }
                    calculateTotals();
                }

                // Initialize a single dropdown
                function initEnhancedDropdown($input, $results, field) {
                    let timeout;

                    // Show dropdown when input is focused
                    $input.on('focus', function(e) {
                        e.stopPropagation();
                        updateDropdownResults($results, field, $(this).val().trim().toLowerCase());
                    });

                    // Handle input events
                    $input.on('input', function() {
                        clearTimeout(timeout);
                        const searchTerm = $(this).val().trim().toLowerCase();

                        timeout = setTimeout(() => {
                            updateDropdownResults($results, field, searchTerm);
                        }, 300);
                    });

                    // Handle item selection
                    $results.on('click', '.dropdown-search-item:not(.no-results)', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const value = $(this).data('value');
                        $input.val(value);
                        filterByColumn(field, value);
                        $results.hide();
                    });

                    // Handle keyboard navigation
                    $input.on('keydown', function(e) {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            const $firstItem = $results.find('.dropdown-search-item').first();
                            if ($firstItem.length) {
                                $firstItem.focus();
                                $results.show();
                            }
                        } else if (e.key === 'Escape') {
                            $results.hide();
                        }
                    });

                    $results.on('keydown', '.dropdown-search-item', function(e) {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            $(this).next('.dropdown-search-item').focus();
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            const $prev = $(this).prev('.dropdown-search-item');
                            if ($prev.length) {
                                $prev.focus();
                            } else {
                                $input.focus();
                            }
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            $(this).click();
                            $results.hide();
                        } else if (e.key === 'Escape') {
                            $results.hide();
                            $input.focus();
                        }
                    });
                }

                $('#row-data-type').on('change', function() {
                    const filterType = $(this).val();
                    applyRowTypeFilter(filterType);
                });
            }

            function initEnhancedDropdown($input, $results, field) {
                let timeout;
                const minSearchLength = 1;

                // Show dropdown when input is clicked
                $input.on('click', function(e) {
                    e.stopPropagation();
                    updateDropdownResults($results, field, $(this).val().trim().toLowerCase());
                });

                // Handle input events
                $input.on('input', function() {
                    clearTimeout(timeout);
                    const searchTerm = $(this).val().trim().toLowerCase();

                    // If search is cleared, trigger filtering immediately
                    if (searchTerm === '') {
                        filterByColumn(field, '');
                        return;
                    }

                    timeout = setTimeout(() => {
                        updateDropdownResults($results, field, searchTerm);
                    }, 300);
                });

                // Handle item selection
                $results.on('click', '.dropdown-search-item', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const value = $(this).data('value');
                    $input.val(value);
                    filterByColumn(field, value);

                    // Close the dropdown after selection
                    $results.hide();

                    // If you want to clear the filter when clicking the same value again
                    if ($input.data('last-value') === value) {
                        $input.val('');
                        filterByColumn(field, '');
                    }
                    $input.data('last-value', value);
                });

                // Handle keyboard navigation
                $input.on('keydown', function(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const $firstItem = $results.find('.dropdown-search-item').first();
                        if ($firstItem.length) {
                            $firstItem.focus();
                        }
                    }
                });

                $results.on('keydown', '.dropdown-search-item', function(e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        $(this).next('.dropdown-search-item').focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const $prev = $(this).prev('.dropdown-search-item');
                        if ($prev.length) {
                            $prev.focus();
                        } else {
                            $input.focus();
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        $(this).click();
                        $results.hide();
                    } else if (e.key === 'Escape') {
                        $results.hide();
                        $input.focus();
                    }
                });
            }

            function updateDropdownResults($results, field, searchTerm) {
                if (!tableData.length) return;

                $results.empty();

                if (searchTerm.length < minSearchLength) {
                    // Show all unique values when search is empty
                    const uniqueValues = [...new Set(tableData.map(item => String(item[field] || '')))];
                    uniqueValues.sort().forEach(value => {
                        if (value) {
                            $results.append(
                                `<div class="dropdown-search-item" data-value="${value}">${value}</div>`
                            );
                        }
                    });
                } else {
                    // Filter results based on search term
                    const matches = tableData.filter(item =>
                        String(item[field] || '').toLowerCase().includes(searchTerm)
                    );

                    if (matches.length) {
                        const uniqueMatches = [...new Set(matches.map(item => String(item[field] || '')))];
                        uniqueMatches.sort().forEach(value => {
                            if (value) {
                                $results.append(
                                    `<div class="dropdown-search-item" data-value="${value}">${value}</div>`
                                );
                            }
                        });
                    } else {
                        $results.append('<div class="dropdown-search-item no-results">No matches found</div>');
                    }
                }

                $results.show();
            }

            function applyRowTypeFilter(filterType) {
                // Reset to all data first
                filteredData = [...tableData];

                // Apply the row type filter
                if (filterType === 'parent') {
                    filteredData = filteredData.filter(item => item.is_parent);
                } else if (filterType === 'sku') {
                    filteredData = filteredData.filter(item => !item.is_parent);
                }
                // else 'all' - no filtering needed

                // Apply filter using Tabulator
                if (table) {
                    if (filterType === 'parent') {
                        table.setFilter([{field: "is_parent", type: "=", value: true}]);
                    } else if (filterType === 'sku') {
                        table.setFilter([{field: "is_parent", type: "=", value: false}]);
                    } else {
                        table.clearFilter();
                    }
                }
                calculateTotals();
            }

            // Initialize manual dropdowns
            function initManualDropdowns() {
                // Toggle dropdown when any filter button is clicked
                $(document).on('click', '.dropdown-toggle', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).next('.dropdown-menu').toggleClass('show');

                    // Close other open dropdowns
                    $('.dropdown-menu').not($(this).next('.dropdown-menu')).removeClass('show');
                });

                // Close dropdown when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.dropdown').length) {
                        $('.dropdown-menu').removeClass('show');
                    }
                });

                // Handle dropdown item selection for all filters
                $(document).on('click', '.dropdown-item', function(e) {
                    e.preventDefault();
                    const $dropdown = $(this).closest('.dropdown');

                    // Update button text
                    const color = $(this).data('color');
                    const text = $(this).text().trim();
                    $dropdown.find('.dropdown-toggle').html(
                        `<span class="status-circle ${color}"></span> ${text.split(' ')[0]}`
                    );

                    // Close dropdown
                    $dropdown.find('.dropdown-menu').removeClass('show');

                    // Apply filter logic
                    const column = $(this).data('column');
                    state.filters[column] = color;
                    applyColumnFilters();
                });

                // Keyboard navigation for dropdowns
                $(document).on('keydown', '.dropdown', function(e) {
                    const $menu = $(this).find('.dropdown-menu');
                    const $items = $menu.find('.dropdown-item');
                    const $active = $items.filter(':focus');

                    switch (e.key) {
                        case 'Escape':
                            $menu.removeClass('show');
                            $(this).find('.dropdown-toggle').focus();
                            break;
                        case 'ArrowDown':
                            if ($menu.hasClass('show')) {
                                e.preventDefault();
                                $active.length ? $active.next().focus() : $items.first().focus();
                            }
                            break;
                        case 'ArrowUp':
                            if ($menu.hasClass('show')) {
                                e.preventDefault();
                                $active.length ? $active.prev().focus() : $items.last().focus();
                            }
                            break;
                        case 'Enter':
                            if ($active.length) {
                                e.preventDefault();
                                $active.click();
                            }
                            break;
                    }
                });
            }

            // Show notification
            function showNotification(type, message) {
                const notification = $(`
                    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                `);

                $('body').append(notification);

                setTimeout(() => {
                    notification.find('.alert').alert('close');
                }, 3000);
            }

            // Loader functions
            function showLoader() {
                $('#data-loader').fadeIn();
            }

            function hideLoader() {
                $('#data-loader').fadeOut();
            }

            // Handle plus icon click to open modal
            $(document).on('click', '.reason-action-plus', function() {
                const slNo = $(this).data('slno');
                const row = filteredData.find(item => item['SL No.'] == slNo);

                // Fill modal fields with current values using the correct keys
                $('#modalReason').val(row.A_Z_Reason || '');
                $('#modalActionRequired').val(row.A_Z_ActionRequired || '');
                $('#modalActionTaken').val(row.A_Z_ActionTaken || '');
                $('#modalSlNo').val(slNo);

                // Show modal
                $('#reasonActionModal').modal('show');
            });

            // Save button in modal
            $('#saveReasonActionBtn').on('click', function() {
                const slNo = $('#modalSlNo').val();
                const reason = $('#modalReason').val();
                const actionRequired = $('#modalActionRequired').val();
                const actionTaken = $('#modalActionTaken').val();

                // Find the SKU for this row
                const row = filteredData.find(item => item['SL No.'] == slNo);
                const sku = row ? row['(Child) sku'] : null;

                // Update data in filteredData and tableData
                [filteredData, tableData].forEach(arr => {
                    const row = arr.find(item => item['SL No.'] == slNo);
                    if (row) {
                        row.A_Z_Reason = reason;
                        row.A_Z_ActionRequired = actionRequired;
                        row.A_Z_ActionTaken = actionTaken;
                    }
                });

                // Save to backend via AJAX
                if (sku) {
                    $.ajax({
                        url: '/amazon-low-visibility/reason-action/update',
                        method: 'POST',
                        data: {
                            sku: sku,
                            reason: reason,
                            action_required: actionRequired,
                            action_taken: actionTaken,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            showNotification('success', 'Reason/Action updated successfully!');
                            if (table) table.redraw();
                            $('#reasonActionModal').modal('hide');
                        },
                        error: function(xhr) {
                            showNotification('danger', 'Failed to update Reason/Action.');
                        }
                    });
                } else {
                    if (table) table.redraw();
                    $('#reasonActionModal').modal('hide');
                }
            });

            // Function to fetch and render daily views chart
            function fetchDailyViewsChart(sku, modalId, fallbackTotalViews, fallbackOrganicViews) {
                fallbackTotalViews = fallbackTotalViews || 0;
                fallbackOrganicViews = fallbackOrganicViews || 0;
                
                $.ajax({
                    url: '/amazon/low-visibility/daily-views-data',
                    type: 'GET',
                    data: { sku: sku },
                    success: function(response) {
                        console.log('Daily views data response:', response);
                        if (response.status === 200 && response.data) {
                            console.log('Chart data:', response.data);
                            console.log('Debug info:', response.data.debug);
                            console.log('Dates:', response.data.dates);
                            console.log('Total Views:', response.data.total_views);
                            console.log('L30 Units:', response.data.l30_units);
                            console.log('Organic Views:', response.data.organic_views);
                            console.log('Fallback Total Views:', fallbackTotalViews);
                            console.log('Fallback Organic Views:', fallbackOrganicViews);
                            
                            // Check if all values are 0 and we have fallback values to use
                            const allViewsZero = response.data.total_views.every(v => v === 0);
                            const allOrganicZero = response.data.organic_views.every(v => v === 0);
                            
                            if ((allViewsZero || allOrganicZero) && (fallbackTotalViews > 0 || fallbackOrganicViews > 0)) {
                                console.log('All API values are 0, using fallback values to distribute across 30 days');
                                // Use fallback values: distribute across 30 days
                                const chartData = {
                                    dates: response.data.dates,
                                    total_views: Array(30).fill(Math.round(fallbackTotalViews / 30)),
                                    l30_units: response.data.l30_units, // Keep original (may be 0)
                                    organic_views: Array(30).fill(Math.round(fallbackOrganicViews / 30))
                                };
                                renderDailyViewsChart(chartData, sku, modalId);
                            } else {
                                renderDailyViewsChart(response.data, sku, modalId);
                            }
                        } else {
                            console.warn('No daily data available for chart', response);
                            // Use fallback if available
                            if (fallbackTotalViews > 0 || fallbackOrganicViews > 0) {
                                const dates = [];
                                const today = new Date();
                                for (let i = 1; i <= 30; i++) {
                                    const date = new Date(today);
                                    date.setDate(date.getDate() - (30 - i));
                                    dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                                }
                                const fallbackData = {
                                    dates: dates,
                                    total_views: Array(30).fill(Math.round(fallbackTotalViews / 30)),
                                    l30_units: Array(30).fill(0),
                                    organic_views: Array(30).fill(Math.round(fallbackOrganicViews / 30))
                                };
                                renderDailyViewsChart(fallbackData, sku, modalId);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching daily views data:', error, xhr.responseText);
                        // Use fallback if available
                        if (fallbackTotalViews > 0 || fallbackOrganicViews > 0) {
                            const dates = [];
                            const today = new Date();
                            for (let i = 1; i <= 30; i++) {
                                const date = new Date(today);
                                date.setDate(date.getDate() - (30 - i));
                                dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
                            }
                            const fallbackData = {
                                dates: dates,
                                total_views: Array(30).fill(Math.round(fallbackTotalViews / 30)),
                                l30_units: Array(30).fill(0),
                                organic_views: Array(30).fill(Math.round(fallbackOrganicViews / 30))
                            };
                            renderDailyViewsChart(fallbackData, sku, modalId);
                        }
                    }
                });
            }

            // Function to render the daily views chart
            function renderDailyViewsChart(chartData, sku, modalId) {
                const canvasId = `dailyViewsChart-${sku.replace(/\s+/g, '-')}`;
                const canvas = document.getElementById(canvasId);
                
                if (!canvas) {
                    console.error('Canvas element not found:', canvasId);
                    return;
                }

                // Destroy existing chart if it exists
                if (window.dailyViewsCharts && window.dailyViewsCharts[canvasId]) {
                    window.dailyViewsCharts[canvasId].destroy();
                }

                // Initialize charts object if it doesn't exist
                if (!window.dailyViewsCharts) {
                    window.dailyViewsCharts = {};
                }

                const ctx = canvas.getContext('2d');
                window.dailyViewsCharts[canvasId] = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.dates || [],
                        datasets: [
                            {
                                label: 'Total Views (L30)',
                                data: chartData.total_views || [],
                                borderColor: 'rgb(0, 123, 255)',
                                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'L30 Units Ordered',
                                data: chartData.l30_units || [],
                                borderColor: 'rgb(40, 167, 69)',
                                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                                tension: 0.4,
                                fill: true
                            },
                            {
                                label: 'Organic Views',
                                data: chartData.organic_views || [],
                                borderColor: 'rgb(255, 193, 7)',
                                backgroundColor: 'rgba(255, 193, 7, 0.1)',
                                tension: 0.4,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            }

            // Initialize everything
            initTable();
        });
    </script>
@endsection
