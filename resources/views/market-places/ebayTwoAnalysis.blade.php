@extends('layouts.vertical', ['title' => 'eBay2', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">
<div id="messageArea" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1055;"></div>
<div class="toast-container"></div>


@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/ebay-table-compact.css') }}">
    <style>
        /* ========== TABLE STRUCTURE ========== */
        #ebay-table-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 200px);
        }
        
        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            position: relative;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            flex: 1;
            /* Ensure this is the scrolling container */
            -webkit-overflow-scrolling: touch;
        }

        .custom-resizable-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            /* Remove any overflow that might interfere */
        }

        .custom-resizable-table th,
        .custom-resizable-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            border-right: 1px solid #f0f0f0;
            position: relative;
            white-space: nowrap;
            overflow: visible !important;
            transition: background-color 0.2s ease;
        }

        .custom-resizable-table th:last-child,
        .custom-resizable-table td:last-child {
            border-right: none;
        }

        .custom-resizable-table tbody tr:hover {
            background-color: #f8f9ff;
        }

        .custom-resizable-table thead {
            display: table-header-group;
            background: #00d5d5;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .custom-resizable-table thead tr {
            background: #00d5d5;
        }

        .custom-resizable-table th {
            background: #00d5d5 !important;
            font-weight: 700;
            font-size: 13px;
            color: #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            user-select: none;
            position: -webkit-sticky;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 2px solid #3b82f6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        /* Ensure parent containers don't interfere with sticky */
        .card-body[style*="padding: 0"] {
            overflow: visible !important;
        }

        /* ========== RESIZABLE COLUMNS ========== */
        .resize-handle {
            position: absolute;
            top: 0;
            right: -3px;
            width: 6px;
            height: 100%;
            background: linear-gradient(180deg, rgba(59, 130, 246, 0.3) 0%, rgba(59, 130, 246, 0.5) 100%);
            cursor: col-resize;
            z-index: 1000;
            border-radius: 3px;
            opacity: 0;
            transition: all 0.25s ease;
        }

        .custom-resizable-table th:hover .resize-handle {
            opacity: 1;
        }

        .resize-handle:hover,
        .resize-handle.resizing {
            background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
            opacity: 1;
            width: 8px;
            right: -4px;
            box-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
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
            padding: 5px 10px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.25s ease;
            cursor: default;
        }

        .dil-percent-value:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .dil-percent-value.red {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .dil-percent-value.blue {
            background: linear-gradient(135deg, #3591dc 0%, #2875b8 100%);
            color: white;
        }

        .dil-percent-value.yellow {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: #212529;
        }

        .dil-percent-value.green {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }

        .dil-percent-value.pink {
            background: linear-gradient(135deg, #e83e8c 0%, #d02670 100%);
            color: white;
        }

        .dil-percent-value.gray {
            background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);
            color: white;
        }

        /* ========== TABLE CONTROLS ========== */
        .table-controls {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 10px 0;
            border-top: 1px solid #ddd;
        }

        /* ========== SORTING ========== */
        .sortable {
            cursor: pointer;
        }

        .sortable:hover {
            background-color: #f1f1f1;
        }

        .sort-arrow {
            display: inline-block;
            margin-left: 5px;
        }

        /* ========== PARENT ROWS ========== */
        .parent-row {
            background: linear-gradient(135deg, rgba(69, 233, 255, 0.15) 0%, rgba(69, 233, 255, 0.05) 100%) !important;
            font-weight: 600;
            border-left: 4px solid #3b82f6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .parent-row:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.08) 100%) !important;
            border-left-color: #2563eb;
            box-shadow: 0 2px 6px rgba(59, 130, 246, 0.15);
            transform: translateX(2px);
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
            left: 50%;
            transform: translateX(-50%);
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

        .dropdown-menu.show {
            display: block !important;
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
        
        /* ========== RESIZING FEEDBACK ========== */
        .custom-resizable-table.resizing-active {
            pointer-events: none;
        }
        
        .custom-resizable-table.resizing-active .resize-handle.resizing {
            pointer-events: all;
        }
        
        .custom-resizable-table th {
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
            min-width: 600px;
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

        .nr-hide {
            display: none !important;
        }

        /* NRL/REQ Dropdown Styling */
        .nr-req-dropdown {
            width: 100%;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
            color: white;
            border: none;
            cursor: pointer;
        }

        .nr-req-dropdown .req-option {
            background-color: #28a745;
            color: white;
        }

        .nr-req-dropdown .nr-option {
            background-color: #dc3545;
            color: white;
        }

        .nr-req-dropdown option {
            padding: 4px 8px;
            font-weight: bold;
        }

        /* Product Image Hover Effect */
        .product-image-thumbnail {
            cursor: pointer;
            display: block;
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 4px;
            transition: none !important;
        }
        
        .product-image-enlarged {
            position: fixed !important;
            z-index: 99999 !important;
            width: 400px !important;
            height: 400px !important;
            left: 50% !important;
            top: 50% !important;
            transform: translate(-50%, -50%) !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5) !important;
            border: 3px solid #fff !important;
            object-fit: contain !important;
            background: white !important;
            padding: 5px !important;
            border-radius: 8px !important;
        }
        
        .image-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 99998;
            display: none;
        }
        
        .image-overlay.active {
            display: block;
        }

        /*popup modal style end */
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'eBay2', 'sub_title' => 'eBay2 Analysis'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex align-items-center" style="gap: 12px;">
                    <div id="percent-edit-div" class="d-flex align-items-center">
                        <div class="input-group" style="width: 150px;">
                            <input type="number" id="updateAllSkusPercent" class="form-control" min="0"
                                max="100" value="{{ $ebayTwoPercentage }}" step="0.01" title="Percent" disabled />
                            <span class="input-group-text">%</span>
                        </div>
                        <button id="editPercentBtn" class="btn btn-outline-primary ms-2">
                            <i class="fa fa-pen"></i>
                        </button>
                    </div>

                    
                    <div id="" class="d-flex align-items-right">
                        <button id="hideSkuBtn" class="btn btn-outline-danger ms-2">
                            <i class="fa fa-eye-slash"></i> Hide SKU
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hide SKU Modal -->
    <!-- Hide SKU Modal -->
    <div id="customHideSkuModal" class="custom-modal" style="display:none;">
        <div class="custom-modal-dialog" style="min-width:600px;">
            <div class="custom-modal-content">
                <div class="custom-modal-header">
                    <h5 class="custom-modal-title">Hide/Unhide SKUs</h5>
                    <button id="updateSelectedHideBtn" class="btn btn-primary mt-2">Update Selected</button>
                    <button type="button" class="custom-modal-close" id="closeHideSkuModal">&times;</button>
                </div>
                <div class="custom-modal-body">
                    <table class="table table-bordered" id="hideSkuTable">
                        <thead>
                            <tr>
                                <th>Parent</th>
                                <th>SKU</th>
                                <th>Hide</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>eBay2 Product Analysis</h4>
                <div class="d-flex align-items-center flex-wrap gap-2">
                        <!-- Left side: Filter buttons and Create Task -->
                        <div class="d-flex flex-wrap gap-2 align-items-center">
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
                                <button class="btn btn-light dropdown-toggle" type="button" id="eDilFilterDropdown">
                                    <span class="status-circle default"></span> E Dil%
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="eDilFilterDropdown">
                                    <li><a class="dropdown-item column-filter" href="#" data-column="A Dil%"
                                            data-color="all">
                                            <span class="status-circle default"></span> All E Dil</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="E Dil%"
                                            data-color="red">
                                            <span class="status-circle red"></span> Red</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="E Dil%"
                                            data-color="yellow">
                                            <span class="status-circle yellow"></span> Yellow</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="E Dil%"
                                            data-color="green">
                                            <span class="status-circle green"></span> Green</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="E Dil%"
                                            data-color="pink">
                                            <span class="status-circle pink"></span> Pink</a></li>
                                </ul>
                            </div>

                            <!-- A Dil% Filter -->
                            <div class="dropdown manual-dropdown-container ">
                                <button class="btn btn-light dropdown-toggle" type="button" id="ovClicksFilterDropdown">
                                    <span class="status-circle default"></span> views
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="ovClicksFilterDropdown">
                                    <li><a class="dropdown-item column-filter" href="#" data-column="views"
                                            data-color="all">
                                            <span class="status-circle default"></span> All OV CLICKS</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="views"
                                            data-color="red">
                                            <span class="status-circle red"></span> Red</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="views"
                                            data-color="green">
                                            <span class="status-circle green"></span> Green </a></li>
                                </ul>
                            </div>

                            <!-- PFT % Filter -->
                            <div class="dropdown manual-dropdown-container">
                                <button class="btn btn-light dropdown-toggle" type="button" id="pftFilterDropdown">
                                    <span class="status-circle default"></span> PFT%
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="pftFilterDropdown">
                                    <li><a class="dropdown-item column-filter" href="#" data-column="PFT %"
                                            data-color="all">
                                            <span class="status-circle default"></span> All PFT</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="PFT %"
                                            data-color="red">
                                            <span class="status-circle red"></span> Red</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="PFT %"
                                            data-color="yellow">
                                            <span class="status-circle yellow"></span> Yellow</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="PFT %"
                                            data-color="blue">
                                            <span class="status-circle blue"></span> Blue</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="PFT %"
                                            data-color="green">
                                            <span class="status-circle green"></span> Green</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="PFT %"
                                            data-color="pink">
                                            <span class="status-circle pink"></span> Pink</a></li>
                                </ul>
                            </div>

                            <!-- ROI Filter -->
                            <div class="dropdown manual-dropdown-container">
                                <button class="btn btn-light dropdown-toggle" type="button" id="roiFilterDropdown">
                                    <span class="status-circle default"></span> ROI
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="roiFilterDropdown">
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Roi"
                                            data-color="all">
                                            <span class="status-circle default"></span> All ROI</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Roi"
                                            data-color="red">
                                            <span class="status-circle red"></span> Red</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Roi"
                                            data-color="yellow">
                                            <span class="status-circle yellow"></span> Yellow</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Roi"
                                            data-color="green">
                                            <span class="status-circle green"></span> Green</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Roi"
                                            data-color="pink">
                                            <span class="status-circle pink"></span> Pink</a></li>
                                </ul>
                            </div>

                            <!-- Tacos Filter -->
                            <div class="dropdown manual-dropdown-container">
                                <button class="btn btn-light dropdown-toggle" type="button" id="tacosFilterDropdown">
                                    <span class="status-circle default"></span> TACOS
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="tacosFilterDropdown">
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Tacos30"
                                            data-color="all">
                                            <span class="status-circle default"></span> All TACOS</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Tacos30"
                                            data-color="pink">
                                            <span class="status-circle pink"></span> Pink</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Tacos30"
                                            data-color="green">
                                            <span class="status-circle green"></span> Green</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Tacos30"
                                            data-color="blue">
                                            <span class="status-circle blue"></span> Blue</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Tacos30"
                                            data-color="yellow">
                                            <span class="status-circle yellow"></span> Yellow</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="Tacos30"
                                            data-color="red">
                                            <span class="status-circle red"></span> Red</a></li>
                                </ul>
                            </div>

                            <!-- CVR Filter -->
                            <div class="dropdown manual-dropdown-container">
                                <button class="btn btn-light dropdown-toggle" type="button" id="scvrFilterDropdown">
                                    <span class="status-circle default"></span> SCVR
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="scvrFilterDropdown">
                                    <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                            data-color="all">
                                            <span class="status-circle default"></span> All CVR</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                            data-color="red">
                                            <span class="status-circle red"></span> Red</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                            data-color="yellow">
                                            <span class="status-circle yellow"></span> Yellow</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                            data-color="green">
                                            <span class="status-circle green"></span> Green</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                            data-color="pink">
                                            <span class="status-circle pink"></span> Pink</a></li>
                                    <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                            data-color="blue">
                                            <span class="status-circle blue"></span> Low SCVR</a></li>
                                </ul>
                            </div>

                            <!-- NRA Filter -->
                            <div class="dropdown manual-dropdown-container">
                                <button class="btn btn-light dropdown-toggle" type="button" id="nraFilterDropdown">
                                    <span class="status-circle default"></span> NRA
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="nraFilterDropdown">
                                    <li><a class="dropdown-item nra-filter" href="#" data-value="all">
                                            <span class="status-circle default"></span> All</a></li>
                                    <li><a class="dropdown-item nra-filter" href="#" data-value="RA">
                                            <span class="status-circle green"></span> RA</a></li>
                                    <li><a class="dropdown-item nra-filter" href="#" data-value="NRA">
                                            <span class="status-circle red"></span> NRA</a></li>
                                </ul>
                            </div>

                            <!-- NRL/REQ Filter -->
                            <div class="dropdown manual-dropdown-container">
                                <button class="btn btn-light dropdown-toggle" type="button" id="nrlReqFilterDropdown">
                                    <span class="status-circle default"></span> NRL/REQ
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="nrlReqFilterDropdown">
                                    <li><a class="dropdown-item nrl-req-filter" href="#" data-value="all">
                                            <span class="status-circle default"></span> All</a></li>
                                    <li><a class="dropdown-item nrl-req-filter" href="#" data-value="REQ">
                                            <span class="status-circle green"></span> REQ</a></li>
                                    <li><a class="dropdown-item nrl-req-filter" href="#" data-value="NR">
                                            <span class="status-circle red"></span> NRL</a></li>
                                </ul>
                            </div>

                            <!-- Task Board Button -->
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#createTaskModal">
                                <i class="bi bi-plus-circle me-2"></i>Create Task
                            </button>

                            <!-- for popup modal start Modal -->
                            <div class="modal fade" id="createTaskModal" tabindex="-1"
                                aria-labelledby="createTaskModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title" id="createTaskModalLabel">📝 Create New Task Ebay2 to
                                                Task
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
                            <button id="close-all-modals" class="btn btn-sm btn-danger" style="display: none;">
                                <i class="fas fa-times"></i> Close All Modals
                            </button>
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <!-- Export Button -->
                            <a href="{{ route('ebay2.analytics.export') }}" class="btn btn-sm btn-success">
                                <i class="fa fa-file-excel"></i> Export
                            </a>

                            <!-- Import Button -->
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                                data-bs-target="#ebayImportModal">
                                <i class="fas fa-file-import"></i> Import
                            </button>
                        </div>
                    </div>

                    <!-- Ebay Import Modal -->
                    <div class="modal fade" id="ebayImportModal" tabindex="-1" aria-labelledby="ebayImportModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog">
                            <form action="{{ route('ebay2.analytics.import') }}" method="POST"
                                enctype="multipart/form-data" class="modal-content" id="ebayImportForm">
                                @csrf
                                <div class="modal-header">
                                    <h5 class="modal-title" id="ebayImportModalLabel">Import Ebay2 Data</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- File Input -->
                                    <div class="mb-3">
                                        <label for="reverbExcelFile" class="form-label">Select Excel File</label>
                                        <input type="file" class="form-control" id="reverbExcelFile"
                                            name="excel_file" accept=".xlsx,.xls,.csv" required>
                                    </div>

                                    <!-- Sample File Section -->
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-info-circle me-1"></i>
                                            Download the sample file to see the required format. Columns should be: SKU, Listed, Live.
                                        </small>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-info" id="downloadSampleBtn">
                                                <i class="fas fa-download me-1"></i> Download Sample File
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-file-import me-1"></i> Import
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>


                    <!-- play backward forwad  -->
                    <div class="btn-group time-navigation-group" role="group" aria-label="Parent navigation">
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
                    </div>

                    <!-- Controls row -->
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <!-- Left side controls -->
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <select id="row-data-type" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                <option value="all">Data Type: All</option>
                                <option value="sku">SKU (Child)</option>
                                <option value="parent">Parent</option>
                            </select>
                            <select id="ovl30-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                <option value="all">OV L30: All</option>
                                <option value="0">0</option>
                                <option value="1-100+">1-100+</option>
                            </select>
                            <select id="inv-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                <option value="all">INV: All</option>
                                <option value="0">0</option>
                                <option value="1-100+">1-100+</option>
                            </select>
                            <select id="el30-filter" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                <option value="all">EL 30: All</option>
                                <option value="0">0</option>
                                <option value="1-100+">1-100+</option>
                            </select>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <!-- Column Visibility Dropdown -->
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                    id="hideColumnsBtn" aria-expanded="false">
                                    <i class="fa fa-eye"></i> Columns
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="hideColumnsBtn" id="columnToggleMenu"
                                    style="max-height: 400px; overflow-y: auto; display: none;">
                                    <!-- Will be populated by JavaScript -->
                                </ul>
                            </div>
                            <button id="showAllColumns" class="btn btn-sm btn-outline-secondary">
                                <i class="fa fa-eye"></i> Show All
                            </button>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                        <h6 class="mb-3">All Calculations Summary</h6>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-primary fs-5 p-2" id="total-sales-summary-badge" style="color: black; font-weight: bold;">Total Sales: $0.00</span>
                            <span class="badge bg-success fs-5 p-2" id="total-pft-summary-badge" style="color: black; font-weight: bold;">Total PFT: 0%</span>
                            <span class="badge bg-info fs-5 p-2" id="total-grpft-summary-badge" style="color: black; font-weight: bold;">Total GRPFT: 0%</span>
                            <span class="badge bg-warning fs-5 p-2" id="total-ad-spend-summary-badge" style="color: black; font-weight: bold;">Total AD Spend: $0.00</span>
                            <span class="badge bg-danger fs-5 p-2" id="total-el30-summary-badge" style="color: black; font-weight: bold;">Total EL 30: 0</span>
                            <span class="badge bg-danger fs-5 p-2" id="zero-sold-count-summary" style="color: white; font-weight: bold;">0 SOLD: 0</span>
                            <span class="badge bg-primary fs-5 p-2" id="sold-count-summary" style="color: white; font-weight: bold;">SOLD: 0</span>
                            <span class="badge bg-danger fs-5 p-2" id="red-margin-count-summary" style="color: white; font-weight: bold;">RED MARGIN: 0</span>
                            <span class="badge bg-secondary fs-5 p-2" id="nrl-count-summary" style="color: white; font-weight: bold;">NRL: 0</span>
                            <span class="badge bg-success fs-5 p-2" id="req-count-summary" style="color: white; font-weight: bold;">REQ: 0</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div id="ebay-table-wrapper">
                    <!-- SKU Search -->
                    <div class="p-2 bg-light border-bottom">
                        <input type="text" id="search-input" class="form-control" placeholder="Search SKU...">
                    </div>
                    <!-- Table body (scrollable section) -->
                    <div class="table-container">
                        <table class="custom-resizable-table" id="ebay-table">
                            <thead>
                                <tr>
                                    <th data-field="image" style="min-width: 80px; width: 80px; text-align: center;">IMG</th>
                                    <th data-field="parent" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center sortable-header">
                                                Parent <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="mt-1 dropdown-search-container">
                                                <input type="text" class="form-control form-control-sm parent-search"
                                                    placeholder="Search parent..." id="parentSearch">
                                                <div class="dropdown-search-results" id="parentSearchResults"></div>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="sku" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center sortable" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Sku <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="mt-1 dropdown-search-container">
                                                <input type="text" class="form-control form-control-sm sku-search"
                                                    placeholder="Search SKU..." id="skuSearch">
                                                <div class="dropdown-search-results" id="skuSearchResults"></div>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="sku-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="inv" style="vertical-align: middle; white-space: nowrap; min-width: 80px; width: 80px;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                INV <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="inv-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ov_l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                OV L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ovl30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ov_dil" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                OV DIL <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ovdil-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="el_30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                EL 30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="el30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="e_dil" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                E DIL <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="eDil-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="nr_req" style="vertical-align: middle; white-space: nowrap; min-width: 100px; width: 100px;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                NRL/REQ
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="req-total"
                                                style="display:inline-block; background:#43dc35; color:white; border-radius:8px; padding:6px 12px; font-weight:600; font-size:13px;">
                                                0</div>
                                        </div>
                                    </th>

                                    <th data-field="views" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                views <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="views-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="price"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                PRICE <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="grpft" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                GRPFT
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="grpft-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="pft" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                PFT
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="pft-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="tprft" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                TPRFT
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="tprft-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="ad-spend" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Ad Spend
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="cps" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                CPS
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="roi" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                ROI <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="roi-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="tacos" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                TACOS <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="tacos-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="cvr" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                SCVR <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="cvr-total">0%</div>
                                        </div>
                                    </th>

                                    <th data-field="sprice" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                SPRICE <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="pft-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="sprofit" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                SPROFIT <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="pft-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="sroi" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                SROI <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="pft-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="salesTotal" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                TOTAL SALES <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="sale-total">0</div>
                                        </div>
                                    </th>

                                    <th data-field="ship" class="ship_col" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Ebay 2 Ship
                                            </div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination controls -->
                <div class="pagination-controls p-2 bg-light border-top">
                        <div class="form-group">
                            <span id="visible-rows" class="badge badge-light" style="color: #dc3545;">Showing 1-25 of
                                150</span>
                        </div>
                        <button id="first-page" class="btn btn-sm btn-outline-secondary mr-1">First</button>
                        <button id="prev-page" class="btn btn-sm btn-outline-secondary mr-1">Previous</button>
                        <span id="page-info" class="mx-2">Page 1 of 6</span>
                        <button id="next-page" class="btn btn-sm btn-outline-secondary ml-1">Next</button>
                        <button id="last-page" class="btn btn-sm btn-outline-secondary ml-1">Last</button>
                    </div>

                    <div id="data-loader" class="card-loader-overlay" style="display: none;">
                        <div class="loader-content">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="loader-text">Loading eBay data...</div>
                        </div>
                    </div>
                </div>
            </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $(document).on('dblclick', '.sPriceText', function() {
                const $text = $(this);
                const $input = $text.siblings('.sPriceInput');

                $text.hide();
                $input.show().focus();
            });

            $(document).on('blur', '.sPriceInput', function() {
                const $input = $(this);
                const newValue = $input.val();
                const $text = $input.siblings('.sPriceText');

                $text.text(newValue).show();
                $input.hide();
            });
            $(document).on("change", ".sPriceInput", function() {
                var sku = $(this).data('sku');
                var val = $(this).val();


                $.ajax({
                    url: "/update-ebay-sku-pricing",
                    type: "POST",
                    data: {
                        "_token": "{{ csrf_token() }}",
                        "sku": sku,
                        "price": val,
                    },
                    success: function(info) {
                        alert("Request Sent to Ebay2, Pls Wait to Reflect Everywhere");
                    }
                });

            });
        });
    </script>
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
                        url: '/update-all-ebay2-skus',
                        type: 'POST',
                        data: {
                            percent: percent,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            showNotification('success', 'Percentage updated successfully!');
                            $input.prop('disabled', true);
                            $icon.removeClass('fa-check').addClass('fa-pen');
                            // Reload the data table if needed
                            loadData();
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
            const ebayViewDataCache = {
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
                ebayViewDataCache.clear();
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

            // Parent Navigation System
            let currentParentIndex = -1; // -1 means showing all products
            let uniqueParents = [];
            let isPlaying = false;

            // Define status indicator fields for different modal types
            const statusIndicatorFields = {
                'price view': ['PFT %', 'TPFT', 'ROI%', 'Spft%', 'a+spft', 'a+ROI'],
                'advertisement view': [
                    'KwCtrL60', 'KwCtrL30', 'KwCtrL7',
                    'KwAcosL60', 'KwAcosL30', 'KwAcosL7',
                    'KwCvrL30', 'KwCvrL7',
                    'Ub 7', 'Ub yes',
                    'PmtCtrL30', 'PmtCtrL7',
                    'PmtAcosL30', 'PmtAcosL7',
                    'PmtCvrL30', 'PmtCvrL7',
                    'Pmt%',
                    'TacosL30'
                ],
                'conversion view': ['SCVR', 'KwCvrL60', 'KwCvrL30', 'KwCvrL7', 'PmtCvrL30', 'PmtCvrL7'],
                'visibility view': ['KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PmtCtrL30', 'PmtCtrL7']
            };

            // Filter state
            const state = {
                filters: {
                    'ov_dil': 'all',
                    'E Dil%': 'all',
                    'views': 'all',
                    'PFT %': 'all',
                    'Roi': 'all',
                    'Tacos30': 'all',
                    'SCVR': 'all',
                    'NRA': 'all',
                    'NRL_REQ': 'all',
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


                // Update button visibility and reset color
                $('#play-pause').hide();
                $('#play-auto').show()
                    .removeClass('btn-success btn-warning btn-danger')
                    .addClass('btn-light');

                // Show all products (filtered to REQ only)
                filteredData = tableData.filter(item => {
                    const nrReq = (item.nr_req || '').toUpperCase();
                    return nrReq === 'REQ' || !item.nr_req; // Show REQ or items without nr_req set (defaults to REQ)
                });
                currentPage = 1;
                renderTable();
                calculateTotals();

                // Reapply column visibility
                applyColumnVisibility();
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
                currentPage = 1;
                renderTable();
                calculateTotals();
                // Reapply column visibility
                applyColumnVisibility();
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

            // Initialize everything
            function initTable() {
                loadData().then(() => {
                    renderTable();
                    initResizableColumns();
                    initSorting();
                    initPagination();
                    initSearch();
                    initColumnToggle();
                    initFilters();
                    calculateTotals();
                    initEnhancedDropdowns();
                    initManualDropdowns();
                    initModalTriggers();
                    initPlaybackControls();
                    initRAEditHandlers(); // Add this line
                    initCheckBoxEditHandlers();
                    initNRSelectChangeHandler();
                    initNRReqChangeHandler();

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
                    url: '/ebay2-data-view',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.data) {
                            tableData = response.data.map((item, index) => {
                                const inv = Number(item.INV) || 0;
                                const l30 = Number(item.L30) || 0;
                                const ovDil = inv > 0 ? l30 / inv : 0;
                                const valueJson = item.value ? JSON.parse(item.value) : {};
                                const listedVal = valueJson.Listed !== undefined ? parseInt(
                                    valueJson.Listed) : 0;
                                const liveVal = valueJson.Live !== undefined ? parseInt(
                                    valueJson.Live) : 0;


                                // Calculate SCVR as eBay L30 / views
                                let scvr = 0;
                                if (Number(item['views']) > 0) {
                                    scvr = Number(item['eBay L30']) / Number(item[
                                        'views']);
                                }
                                return {
                                    'image_src': item['image_path'] || '',
                                    Parent: item.Parent || item.parent || item.parent_asin ||
                                        item.Parent_ASIN || '(No Parent)',
                                    '(Child) sku': item['(Child) sku'] || '',
                                    'R&A': item['R&A'] !== undefined ? item['R&A'] : '',
                                    INV: item.INV || 0,
                                    L30: item.L30 || 0,
                                    ov_dil: ovDil,
                                    'eBay L30': item['eBay L30'] || 0,
                                    'E Dil%': item['E Dil%'] || 0,
                                    'views': item['views'] || 0,
                                    'eBay Price': item['eBay Price'] || 0,
                                    'PFT %': item['PFT %'] || 0,
                                    Profit: item.Profit || item['Profit'] || item['profit'] ||
                                        item['PFT'] || 0,
                                    'Sales L30': item['Sales L30'] || item['sales_l30'] || item[
                                        'L30'] || 0,
                                    Roi: item['ROI%'] || 0,
                                    Tacos30: item.TacosL30 || 0,
                                    SCVR: scvr, // <-- use calculated value
                                    is_parent: item['(Child) sku'] ? item['(Child) sku']
                                        .toUpperCase().includes("PARENT") : false,
                                    raw_data: item || {},
                                    NR: item.NR || '',
                                    UI_NR: item.NR || 'RA', // Initialize UI_NR with NR value or default to 'RA'
                                    nr_req: item.nr_req || 'REQ', // Default to 'REQ' if not set
                                    listed: listedVal,
                                    live: liveVal,
                                    Hide: item.Hide !== undefined ? item.Hide : '',
                                    SPRICE: (item.SPRICE !== null && !isNaN(parseFloat(item
                                        .SPRICE))) ? parseFloat(item.SPRICE) : 0,
                                    SPFT: (item.SPFT !== null && !isNaN(parseFloat(item
                                        .SPFT))) ? parseFloat(item.SPFT) : 0,
                                    SROI: (item.SROI !== null && !isNaN(parseFloat(item
                                        .SROI))) ? parseFloat(item.SROI) : 0,

                                    LP: item.LP_productmaster || 0,
                                    SHIP: item.Ship_productmaster || 0,
                                    spend_l30: item.AD_Spend_L30 || 0,
                                    'AD%': item['AD%'] || 0,
                                };
                            });


                            // Filter to show only REQ data
                            filteredData = tableData.filter(item => {
                                const nrReq = (item.nr_req || '').toUpperCase();
                                return nrReq === 'REQ' || !item.nr_req; // Show REQ or items without nr_req set (defaults to REQ)
                            });

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

            // Add this function to update 0 SOLD and SOLD counts
            function updateSoldCounts() {
                let zeroSold = 0;
                let totalSku = 0;
                let lowProfitCount = 0;
                let nrlCount = 0;
                let reqCount = 0;

                filteredData.forEach(item => {
                    if (!item.is_parent) {
                        const l30 = parseFloat(item['eBay L30']) || 0;
                        const inv = parseFloat(item.INV) || 0;
                        const pftDecimal = parseFloat(item['PFT %']) || 0;
                        const pftPercentage = pftDecimal * 100;

                        if (l30 === 0 && inv > 0) zeroSold++;
                        totalSku++;
                        if (pftPercentage < 10) {
                            lowProfitCount++;
                        }
                        if (item.nr_req === 'NR') {
                            nrlCount++;
                        }
                        if (item.nr_req === 'REQ' || !item.nr_req) {
                            reqCount++;
                        }
                    }
                });

                $('#zero-sold-count').text(zeroSold);
                $('#sold-count').text(totalSku - zeroSold);
                $('#red-margin-count').text(lowProfitCount);
                
                // Update summary badges
                $('#zero-sold-count-summary').text('0 SOLD: ' + zeroSold);
                $('#sold-count-summary').text('SOLD: ' + (totalSku - zeroSold));
                $('#red-margin-count-summary').text('RED MARGIN: ' + lowProfitCount);
                $('#nrl-count-summary').text('NRL: ' + nrlCount);
                $('#req-count-summary').text('REQ: ' + reqCount);

                updateRedMarginDataToChannelMaster(lowProfitCount);
            }

            function updateRedMarginDataToChannelMaster(lowProfitCount) {
                console.log(lowProfitCount);

                fetch('/ebay2/save-low-profit-count', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            count: lowProfitCount
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('Saved low profit count:', data);
                    })
                    .catch(error => {
                        console.error('Error saving low profit count:', error);
                    });
            }



            // Render table with current data
            function renderTable() {
                const $tbody = $('#ebay-table tbody');
                $tbody.empty();

                if (isLoading) {
                    $tbody.append('<tr><td colspan="16" class="text-center">Loading data...</td></tr>');
                    return;
                }

                if (filteredData.length === 0) {
                    $tbody.append('<tr><td colspan="16" class="text-center">No matching records found</td></tr>');
                    return;
                }

                filteredData.forEach(item => {
                    const $row = $('<tr>');
                    if (item.is_parent) {
                        $row.addClass('parent-row');
                    }
                    // if (item.NR === 'NRA') {
                    //     $row.addClass('nr-hide');
                    // }

                    let rawData = {};
                    if (typeof item.raw_data === 'string') {
                        try {
                            rawData = JSON.parse(item.raw_data || '{}');
                        } catch (e) {
                            console.error('Invalid JSON in raw_data for SKU', item['(Child) sku'], e);
                        }
                    } else if (typeof item.raw_data === 'object' && item.raw_data !== null) {
                        rawData = item.raw_data;
                    }

                    // Helper functions for color coding
                    const getDilColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 16.66) return 'red';
                        if (percent >= 16.66 && percent < 25) return 'yellow';
                        if (percent >= 25 && percent < 50) return 'green';
                        return 'pink'; // 50 and above
                    };

                    const getEDilColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 12.5) return 'red';
                        if (percent >= 12.5 && percent < 16.66) return 'yellow';
                        if (percent >= 16.66 && percent < 25) return 'blue';
                        if (percent >= 25 && percent < 50) return 'green';
                        return 'pink'; // 50 and above
                    };

                    const getViewColor = (value) => {
                        const percent = parseFloat(value);

                        if (percent >= 30) return 'green';
                        return 'red';
                    };

                    const getPftColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 10) return 'red';
                        if (percent >= 10 && percent < 15) return 'yellow';
                        if (percent >= 15 && percent < 20) return 'blue';
                        if (percent >= 20 && percent <= 40) return 'green';
                        return 'pink';
                    };

                    const getRoiColor = (value) => {
                        const percent = parseFloat(value);
                        if (percent < 50) return 'red';
                        if (percent >= 50 && percent < 75) return 'yellow';
                        if (percent >= 75 && percent <= 125) return 'green';
                        return 'pink';
                    };

                    const getTacosColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent <= 7) return 'pink';
                        if (percent > 7 && percent <= 14) return 'green';
                        if (percent > 14 && percent <= 21) return 'yellow';
                        return 'red';
                    };

                    const getCvrColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent <= 4) return 'red';
                        if (percent > 4 && percent <= 7) return 'yellow';
                        if (percent > 7 && percent <= 10) return 'green';
                        return 'pink';
                    };

                    // Add image cell
                    const imageSrc = item.image_src || item.raw_data?.image_path || '';
                    const $imageCell = $('<td>').attr('data-field', 'image').css({
                        'text-align': 'center',
                        'vertical-align': 'middle'
                    });
                    
                    if (imageSrc) {
                        $imageCell.html(`<img src="${imageSrc}" alt="Product Image" class="product-image-thumbnail" data-image-src="${imageSrc}">`);
                    } else {
                        $imageCell.html('<span class="text-muted">N/A</span>');
                    }
                    $row.append($imageCell);
                    
                    $row.append($('<td>').attr('data-field', 'parent').text(item.Parent));

                    // SKU with hover content for links and copy button
                    const $skuCell = $('<td>').attr('data-field', 'sku').addClass('skuColumn').css('position', 'relative');
                    const skuValue = item['(Child) sku'];
                    
                    if (item.is_parent) {
                        $skuCell.html(`
                            <div class="d-flex align-items-center justify-content-between">
                                <strong>${skuValue}</strong>
                                <button class="btn btn-sm btn-outline-secondary copy-sku-btn" 
                                        data-sku="${skuValue}" 
                                        title="Copy SKU"
                                        style="padding: 2px 6px; margin-left: 5px;">
                                    <i class="fas fa-copy" style="font-size: 10px;"></i>
                                </button>
                            </div>
                        `);
                    } else {
                        const buyerLink = item.raw_data['B Link'] || '';
                        if (buyerLink) {
                            $skuCell.html(`
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="sku-tooltip-container">
                                        <span class="sku-text">${skuValue}</span>
                                        <div class="sku-tooltip">
                                            <div class="sku-link"><a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a></div>
                                        </div>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary copy-sku-btn" 
                                            data-sku="${skuValue}" 
                                            title="Copy SKU"
                                            style="padding: 2px 6px; margin-left: 5px;">
                                        <i class="fas fa-copy" style="font-size: 10px;"></i>
                                    </button>
                                </div>
                            `);
                        } else {
                            $skuCell.html(`
                                <div class="d-flex align-items-center justify-content-between">
                                    <span>${skuValue}</span>
                                    <button class="btn btn-sm btn-outline-secondary copy-sku-btn" 
                                            data-sku="${skuValue}" 
                                            title="Copy SKU"
                                            style="padding: 2px 6px; margin-left: 5px;">
                                        <i class="fas fa-copy" style="font-size: 10px;"></i>
                                    </button>
                                </div>
                            `);
                        }
                    }
                    $row.append($skuCell);  

                    $row.append($('<td>').attr('data-field', 'inv').text(item.INV));
                    $row.append($('<td>').attr('data-field', 'ov_l30').text(item.L30));

                    // OV DIL with color coding and WMPNM tooltip
                    $row.append($('<td>').attr('data-field', 'ov_dil').html(
                        `<span class="dil-percent-value ${getDilColor(item.ov_dil)}">${Math.round(item.ov_dil * 100)}%</span>
                         <span class="text-info tooltip-icon wmpnm-view-trigger" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="left" 
                               title="WMPNM View"
                               data-item='${JSON.stringify(item.raw_data)}'>W</span>`
                    ));

                    $row.append($('<td>').attr('data-field', 'el_30').text(item['eBay L30']));

                    // A DIL with color coding
                    $row.append($('<td>').attr('data-field', 'e_dil').html(
                        `<span class="dil-percent-value ${getEDilColor(item['E Dil%'])}">${Math.round(item['E Dil%'] * 100)}%</span>`
                    ));

                    // NRL/REQ dropdown - only for non-parent rows
                    if (item.is_parent) {
                        $row.append($('<td>').attr('data-field', 'nr_req')); // Empty cell for parent
                    } else {
                        // Set default value for nr_req if missing
                        let currentNrReq = (item.nr_req === 'REQ' || item.nr_req === 'NR') ? item.nr_req : 'REQ';

                        const $nrReqSelect = $(`
                            <select class="form-select form-select-sm nr-req-dropdown" style="min-width: 100px;">
                                <option value="REQ" class="req-option">REQ</option>
                                <option value="NR" class="nr-option">NRL</option>
                            </select>
                        `);

                        // Set initial value
                        $nrReqSelect.val(currentNrReq);

                        // Set background color based on value
                        if (currentNrReq === 'REQ') {
                            $nrReqSelect.css('background-color', '#28a745');
                            $nrReqSelect.css('color', '#ffffff');
                        } else if (currentNrReq === 'NR') {
                            $nrReqSelect.css('background-color', '#dc3545');
                            $nrReqSelect.css('color', '#ffffff');
                        }

                        $nrReqSelect.data('sku', item['(Child) sku']);
                        $row.append($('<td>').attr('data-field', 'nr_req').append($nrReqSelect));
                    }

                    // views with tooltip icon (no color coding)
                    $row.append($('<td>').attr('data-field', 'views').html(
                        `<span class="dil-percent-value ${getViewColor(item['views'])}">${Math.round(item['views'])}</span>
                         <span class="text-info tooltip-icon ad-view-trigger" 
                               data-bs-toggle="tooltip" 
                               data-bs-placement="left" 
                               title="visibility View"
                               data-item='${JSON.stringify(item.raw_data)}'>V</span>`
                    ));

                    //price with tooltip
                    // Replace the existing price section with this:
                    $row.append($('<td>').attr('data-field', 'price').html(
                        `$${(parseFloat(item['eBay Price']) || 0).toFixed(2)}
                            <span class="tooltip-container" style="margin-left:8px">
                                <i class="fas fa-tag text-warning price-view-trigger" 
                                style="transform:translateY(1px)"
                                data-bs-toggle="tooltip" 
                                data-bs-placement="top-end" 
                                title="Pricing view"
                                data-item='${JSON.stringify(item.raw_data)}'"></i>
                            </span>`
                    ));
                    
                    const price = Number(item['eBay Price']) || 0;
                    const ship = Number(item.SHIP || item.Ship_productmaster) || 0;
                    const lp = Number(item.LP || item.LP_productmaster) || 0;
                    const spend = Number(item.spend_l30) || 0;
                    const eL30 = Number(item['eBay L30']) || 0;
                    // Use percentage from item if available, otherwise use global percentage
                    const ebayPercentage = {{ $ebayTwoPercentage ?? 0}};
                    const totalSalesData = eL30 * price;
                    let tacos = Number(item.TacosL30) || 0;
                    const adPercent = Number(item['AD%'] || 0) / 100; // Convert AD% to decimal

                    // GRPFT% = ((Price * percentage - Ship - LP) / Price) * 100
                    let grpft = ((price * (ebayPercentage / 100)) - ship - lp) / price;
                    if(isNaN(grpft) || !isFinite(grpft)) {
                        grpft = 0;
                    }

                    // PFT% = GRPFT% - AD%
                    let pft = grpft - adPercent;
                    if(isNaN(pft) || !isFinite(pft)) {
                        pft = 0;
                    }

                    // TPRFT% = GRPFT% - TacosL30 (TacosL30 is already a decimal ratio)
                    let tprft = grpft - tacos;
                    
                    if(isNaN(tprft) || !isFinite(tprft)) {
                        tprft = 0;
                    }

                    // GRPFT with color coding
                    $row.append($('<td>').attr('data-field', 'pft').html(
                        `
                            <span class="dil-percent-value ${getPftColor(grpft * 100)}">
                                ${(grpft * 100).toFixed(0)}%
                            </span>
                        `
                    ));

                    // PFT with color coding
                    $row.append($('<td>').attr('data-field', 'gpft').html(
                        `
                            <span class="dil-percent-value ${getPftColor(pft * 100)}">
                                ${(pft * 100).toFixed(0)}%
                            </span>
                        ` 
                    ));

                    // TPRFT with color coding
                    $row.append($('<td>').attr('data-field', 'tprft').html(
                        `
                            <span class="dil-percent-value ${getPftColor(tprft)}">
                                ${tprft.toFixed(2)}%
                            </span>
                        ` 
                    ));
                    
                    // Auto-save TPFT only if SKU exists and TPFT has a valid value
                    if (item['(Child) sku'] && !isNaN(tprft) && isFinite(tprft) && tprft !== 0) {
                        $.ajax({
                            url: '/update-ebay2-nr-data',
                            type: 'POST',
                            data: {
                                sku: item['(Child) sku'],
                                field: 'TPFT',
                                value: tprft.toFixed(2),
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(res) {
                                // Silent success
                            },
                            error: function(err) {
                                // Silent error - don't pollute console on every render
                            }
                        });
                    }

                    $row.append($('<td>').attr('data-field', 'ad-spend').html(
                        `${item.spend_l30.toFixed(2)}`
                    ));
                    // Cost per sale (CPS) calculation
                    let cps = item['eBay L30'] > 0 ? (item.spend_l30 / item['eBay L30']).toFixed(2) : 0;
                    $row.append($('<td>').attr('data-field', 'cps').html(
                        `${cps}`
                    ));

                    // ROI with color coding
                    $row.append($('<td>').attr('data-field', 'roi').html(
                        typeof item.Roi === 'number' && !isNaN(item.Roi) ?
                        `<span class="dil-percent-value ${getRoiColor(item.Roi)}">${Math.round(item.Roi)}%</span>` :
                        ''
                    ));

                    // TACOS with color coding and tooltip
                    $row.append($('<td>').attr('data-field', 'tacos').html(
                        `<span class="dil-percent-value ${getTacosColor(item.Tacos30)}">${(item.Tacos30 * 100).toFixed(0)}%</span>
                         <i class="fas fa-a text-info tooltip-icon advertisement-view-trigger" 
                            data-bs-toggle="tooltip" data-bs-placement="bottom" title="Advertisement view"
                            data-item='${JSON.stringify(item.raw_data)}'></i>`
                    ));

                    // CVR with color coding and tooltip
                    let scvrValue = 0;
                    if (Number(item['views']) > 0) {
                        scvrValue = (Number(item['eBay L30']) / Number(item['views'])) * 100;
                    }
                    let ebayL30 = Number(item['eBay L30']) || 0;
                    let ebaySess30 = Number(item['views']) || 0;

                    let ebayCvrPercent = 0;
                    if (ebaySess30 > 0) {
                        ebayCvrPercent = (ebayL30 / ebaySess30) * 1000 / 10; // 15 / 441 = 0.034 → 34%
                    }

                    $row.append($('<td>').attr('data-field', 'cvr').html(
                        `<span class="dil-percent-value" style="color: ${getCvrColor(ebayCvrPercent)}">
                            ${ebayCvrPercent.toFixed(0)}%
                        </span>`
                    ));



                    // SPRICE + Edit Button (no decimals)
                    $row.append($('<td>').attr('data-field', 'sprice').html(
                        item.SPRICE !== null && !isNaN(parseFloat(item.SPRICE)) ?
                        `
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary s_price" 
                                style="font-size:16px; padding:8px 14px; border-radius:8px;">
                                $${Math.round(parseFloat(item.SPRICE))}
                            </span>
                            <div class="btn-group" role="group">
                                <!-- Edit Button -->
                                <button class="btn btn-outline-primary openPricingBtn"
                                    style="font-size:15px; padding:6px 12px; border-radius:8px;"
                                    title="Edit SPRICE"
                                    data-lp="${item.LP}"
                                    data-ship="${item.SHIP}"
                                    data-sku="${item["(Child) sku"]}">
                                    <i class="fa fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        ` : ''
                    ));


                    // ✅ SPFT (with coloring logic + inline style)
                    $row.append($('<td>').attr('data-field', 'sprofit').attr('id', `spft-${item["(Child) sku"]}`).html(
                        item.SPFT !== null && !isNaN(parseFloat(item.SPFT)) ?
                        `<span style="
                            font-size:14px; 
                            padding:6px 12px; 
                            border-radius:8px; 
                            color:#fff; 
                            background-color:${
                                parseFloat(item.SPFT) <= 10 
                                    ? '#dc3545'   // 🔴 red
                                    : parseFloat(item.SPFT) <= 15 
                                        ? '#ffc107'   // 🟡 yellow
                                        : parseFloat(item.SPFT) <= 20 
                                            ? '#0d6efd'   // 🔵 blue
                                            : '#198754'   // 🟢 green
                            };">
                            ${(parseFloat(item.SPFT) - Math.floor(parseFloat(item.SPFT)) >= 0.5 
                                ? Math.ceil(parseFloat(item.SPFT)) 
                                : Math.floor(parseFloat(item.SPFT)))}%
                        </span>` :
                        ''
                    ));

                    // ✅ SROI (with coloring logic + inline style)
                    $row.append($('<td>').attr('data-field', 'sroi').attr('id', `sroi-${item["(Child) sku"]}`).html(
                        item.SROI !== null && !isNaN(parseFloat(item.SROI)) ?
                        `<span style="
                            font-size:14px; 
                            padding:6px 12px; 
                            border-radius:8px; 
                            color:#fff; 
                            background-color:${
                                parseFloat(item.SROI) <= 50 
                                    ? '#dc3545'   // 🔴 red
                                    : parseFloat(item.SROI) <= 100 
                                        ? '#ffc107'   // 🟡 yellow
                                        : parseFloat(item.SROI) <= 150 
                                            ? '#198754'   // 🟢 green
                                            : '#6f42c1'   // 🟣 purple
                            };">
                            ${(parseFloat(item.SROI) - Math.floor(parseFloat(item.SROI)) >= 0.5 
                                ? Math.ceil(parseFloat(item.SROI)) 
                                : Math.floor(parseFloat(item.SROI)))}%
                        </span>` : ''
                    ));

                    $row.append($('<td>').attr('data-field', 'salesTotal').attr('id', `total-sales`).html(
                        ((item['eBay L30']) * (parseFloat(item['eBay Price']) || 0)).toFixed(2)
                    ));

                    // Add Ebay 2 Ship column
                    if (item.is_parent) {
                        $row.append($('<td>').attr('data-field', 'ship').addClass('ship_col')); // Empty cell for parent
                    } else {
                        $row.append($('<td>').attr('data-field', 'ship').addClass('ship_col').text(item.SHIP || 0));
                    }

                    $tbody.append($row);
                });

                updatePaginationInfo();
                $('#visible-rows').text(`Showing all ${filteredData.length} rows`);
                // Initialize tooltips
                initTooltips();
                updateSoldCounts();
                
                // Apply column visibility after rendering
                applyColumnVisibility();
            }

            function initRAEditHandlers() {
                $(document).on('click', '.edit-icon', function(e) {
                    e.stopPropagation();
                    const $icon = $(this);
                    const $checkbox = $icon.siblings('.ra-checkbox');
                    const $row = $checkbox.closest('tr');
                    const rowData = filteredData.find(item => item['Sl'] == $row.find('td:eq(0)')
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

            function initCheckBoxEditHandlers() {
                // Handles both NR and Hide columns
                $(document).off('click',
                    '.nr-edit-container .nr-edit-icon, .nr-edit-container .fa-save, .hide-edit-container .hide-edit-icon, .hide-edit-container .fa-save'
                );
                $(document).on('click',
                    '.nr-edit-container .nr-edit-icon, .nr-edit-container .fa-save, .hide-edit-container .hide-edit-icon, .hide-edit-container .fa-save',
                    function(e) {
                        e.stopPropagation();
                        const $icon = $(this);
                        const $container = $icon.closest('.nr-edit-container, .hide-edit-container');
                        const isNR = $container.hasClass('nr-edit-container');
                        const $checkbox = isNR ? $container.find('.nr-checkbox') : $container.find(
                            '.hide-checkbox');
                        const $row = $checkbox.closest('tr');
                        const slNo = $row.find('td:eq(0)').text();
                        const rowData = filteredData.find(item => item['Sl'] == slNo || item['SL No.'] == slNo);
                        const sku = rowData ? rowData['(Child) sku'] : null;

                        if ($icon.hasClass('fa-pen')) {
                            $checkbox.prop('disabled', false)
                                .data('original-value', $checkbox.is(':checked'));
                            $icon.removeClass('fa-pen text-primary')
                                .addClass('fa-save text-success')
                                .attr('title', 'Save Changes');
                        } else if ($icon.hasClass('fa-save')) {
                            // Save
                            const updatedValue = $checkbox.is(':checked');
                            $icon.removeClass('fa-save text-success')
                                .addClass('fa-spinner fa-spin text-primary')
                                .attr('title', 'Saving...');

                            // --- Save to database via AJAX ---
                            let data = {
                                sku: sku,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            };
                            if (isNR) {
                                data.nr = updatedValue;
                            } else {
                                data.hide = updatedValue;
                            }

                            $.ajax({
                                url: '/ebay/save-nr',
                                type: 'POST',
                                data: data,
                                success: function(response) {
                                    showNotification('success', (isNR ? 'NR' : 'Hide') +
                                        ' updated successfully!');
                                    $checkbox.prop('disabled', true);
                                    $icon.removeClass('fa-spinner fa-spin text-primary')
                                        .addClass('fa-pen text-primary')
                                        .attr('title', isNR ? 'Edit NR' : 'Edit Hide');

                                    // --- ADD THIS: update tableData and filteredData ---
                                    if (sku) {
                                        tableData.forEach(item => {
                                            if (item['(Child) sku'] === sku) {
                                                if (isNR) {
                                                    item.NR = updatedValue;
                                                } else {
                                                    item.Hide = updatedValue;
                                                }
                                            }
                                        });
                                        filteredData.forEach(item => {
                                            if (item['(Child) sku'] === sku) {
                                                if (isNR) {
                                                    item.NR = updatedValue;
                                                } else {
                                                    item.Hide = updatedValue;
                                                }
                                            }
                                        });
                                    }
                                },
                                error: function(xhr) {
                                    showNotification('danger', 'Failed to update ' + (isNR ? 'NR' :
                                        'Hide') + '.');
                                    $checkbox.prop('checked', $checkbox.data('original-value'))
                                        .prop('disabled', true);
                                    $icon.removeClass('fa-spinner fa-spin text-primary')
                                        .addClass('fa-pen text-primary')
                                        .attr('title', isNR ? 'Edit NR' : 'Edit Hide');
                                }
                            });
                        }
                    });

                // Allow clicking the checkbox directly to enter edit mode (like R&A)
                $(document).off('click', '.nr-checkbox:disabled, .hide-checkbox:disabled');
                $(document).on('click', '.nr-checkbox:disabled', function(e) {
                    e.stopPropagation();
                    $(this).closest('.nr-edit-container').find('.nr-edit-icon').trigger('click');
                });
                $(document).on('click', '.hide-checkbox:disabled', function(e) {
                    e.stopPropagation();
                    $(this).closest('.hide-edit-container').find('.hide-edit-icon').trigger('click');
                });
            }

            function initNRSelectChangeHandler() {
                $(document).on('change', '.nr-select', function() {
                    const $select = $(this);
                    const newValue = $select.val();
                    const sku = $select.data('sku');

                    // Change background color based on selected value
                    if (newValue === 'NRA') {
                        $select.css('background-color', '#dc3545').css('color', '#ffffff');
                    } else {
                        $select.css('background-color', '#28a745').css('color', '#ffffff');
                    }

                    // Send AJAX
                    $.ajax({
                        url: '/ebay/save-nr',
                        type: 'POST',
                        data: {
                            sku: sku,
                            nr: newValue,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            showNotification('success', 'NR updated successfully!');

                            // Update tableData and filteredData - Update both NR and UI_NR
                            tableData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.NR = newValue;
                                    item.UI_NR = newValue; // Update UI_NR as well
                                }
                            });
                            filteredData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.NR = newValue;
                                    item.UI_NR = newValue; // Update UI_NR as well
                                }
                            });
                            calculateTotals();
                            renderTable();
                        },
                        error: function(xhr) {
                            showNotification('danger', 'Failed to update NR.');
                        }
                    });
                });
            }

            function initNRReqChangeHandler() {
                $(document).on('change', '.nr-req-dropdown', function() {
                    const $select = $(this);
                    const newValue = $select.val();
                    const sku = $select.data('sku');

                    // Change background color based on selected value
                    if (newValue === 'REQ') {
                        $select.css('background-color', '#28a745').css('color', '#ffffff');
                    } else if (newValue === 'NR') {
                        $select.css('background-color', '#dc3545').css('color', '#ffffff');
                    }

                    // Send AJAX to save NRL/REQ status
                    $.ajax({
                        url: '/listing_ebaytwo/save-status',
                        type: 'POST',
                        data: {
                            sku: sku,
                            nr_req: newValue,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            showNotification('success', 'NRL/REQ updated successfully!');

                            // Update tableData and filteredData
                            tableData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.nr_req = newValue;
                                }
                            });
                            filteredData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.nr_req = newValue;
                                }
                            });
                            calculateTotals();
                            renderTable();
                        },
                        error: function(xhr) {
                            showNotification('danger', 'Failed to update NRL/REQ.');
                        }
                    });
                });
            }


            $(document).on('change', '.listed-checkbox, .live-checkbox', function() {
                const $cb = $(this);
                const sku = $cb.data('sku');
                const field = $cb.hasClass('listed-checkbox') ? 'Listed' : 'Live';
                const value = $cb.is(':checked') ? 1 : 0;

                $.ajax({
                    url: '/ebay2/update-listed-live',
                    method: 'POST',
                    data: {
                        sku: sku,
                        field: field,
                        value: value,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(res) {
                        // Update local filteredData to reflect the change for realtime totals
                        let updated = false;
                        for (let i = 0; i < filteredData.length; i++) {
                            if (filteredData[i]['(Child) sku'] === sku) {
                                let raw = filteredData[i].raw_data;
                                if (typeof raw === 'string') {
                                    try {
                                        raw = JSON.parse(raw || '{}');
                                    } catch (e) {
                                        raw = {};
                                    }
                                } else if (typeof raw !== 'object' || raw === null) {
                                    raw = {};
                                }
                                raw[field] = value;
                                filteredData[i].raw_data =
                                    raw;
                                updated = true;
                                break;
                            }
                        }

                        if (updated) {
                            calculateTotals();
                        }

                        console.log(`${field} updated for SKU ${sku}`);
                    },
                    error: function(err) {
                        console.error('Update failed', err);
                        alert('Failed to update. Try again.');
                        $cb.prop('checked', !value); // revert on error
                    }
                });
            });


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

                    const itemId = itemData['Sl'] || `row-${Math.random().toString(36).substr(2, 9)}`;
                    const modalId = `modal-${itemId}-${type.replace(/\s+/g, '-').toLowerCase()}`;

                    // Check cache first - use the cached data if available
                    const cachedData = ebayViewDataCache.get(itemId);
                    const dataToUse = cachedData || itemData;

                    // Store the data in cache if it wasn't already
                    if (!cachedData) {
                        ebayViewDataCache.set(itemId, itemData);
                    }

                    // Check if this modal already exists
                    const existingModal = ModalSystem.modals.find(m => m.id === modalId);
                    if (existingModal) {
                        // Just bring it to front if it exists
                        ModalSystem.bringToFront(existingModal);
                        return;
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
                        case 'conversion view':
                            fieldsToDisplay = [{
                                    title: 'views',
                                    content: selectedItem['views']
                                },
                                {
                                    title: 'SCVR',
                                    content: (Number(selectedItem['views']) > 0) ?
                                        ((Number(selectedItem['eBay L30']) / Number(selectedItem[
                                            'views'])) * 100).toFixed(2) + '%' : '0%'
                                },
                                {
                                    title: 'KwClkL60',
                                    content: selectedItem['KwClkL60']
                                },
                                {
                                    title: 'KwClkL30',
                                    content: selectedItem['KwClkL30']
                                },
                                {
                                    title: 'KwClkL7',
                                    content: selectedItem['KwClkL7']
                                },
                                {
                                    title: 'KwSldL60',
                                    content: selectedItem['KwSldL60']
                                },
                                {
                                    title: 'KwSldL30',
                                    content: selectedItem['KwSldL30']
                                },
                                {
                                    title: 'KwSldL7',
                                    content: selectedItem['KwSldL7']
                                },
                                {
                                    title: 'KwCvrL60',
                                    content: selectedItem['KwCvrL60']
                                },
                                {
                                    title: 'KwCvrL30',
                                    content: selectedItem['KwCvrL30']
                                },
                                {
                                    title: 'KwCvrL7',
                                    content: selectedItem['KwCvrL7']
                                },
                                {
                                    title: 'views',
                                    content: selectedItem['views']
                                },
                                {
                                    title: 'PmtClkL7',
                                    content: selectedItem['PmtClkL7']
                                },
                                {
                                    title: 'PmtSldL30',
                                    content: selectedItem['PmtSldL30']
                                },
                                {
                                    title: 'PmtSldL7',
                                    content: selectedItem['PmtSldL7']
                                },
                                {
                                    title: 'PmtCvrL30',
                                    content: selectedItem['PmtCvrL30']
                                },
                                {
                                    title: 'PmtCvrL7',
                                    content: selectedItem['PmtCvrL7']
                                }
                            ];
                            break;
                        case 'visibility view':
                            fieldsToDisplay = [{
                                    title: 'views',
                                    content: selectedItem['views']
                                },
                                {
                                    title: 'KwImpL60',
                                    content: selectedItem['KwImpL60']
                                },
                                {
                                    title: 'KwImpL30',
                                    content: selectedItem['KwImpL30']
                                },
                                {
                                    title: 'KwImpL7',
                                    content: selectedItem['KwImpL7']
                                },
                                {
                                    title: 'KwClkL60',
                                    content: selectedItem['KwClkL60']
                                },
                                {
                                    title: 'KwClkL30',
                                    content: selectedItem['KwClkL30']
                                },
                                {
                                    title: 'KwClkL7',
                                    content: selectedItem['KwClkL7']
                                },
                                {
                                    title: 'KwCtrL60',
                                    content: selectedItem['KwCtrL60']
                                },
                                {
                                    title: 'KwCtrL30',
                                    content: selectedItem['KwCtrL30']
                                },
                                {
                                    title: 'KwCtrL7',
                                    content: selectedItem['KwCtrL7']
                                },
                                {
                                    title: 'PmtImpL30',
                                    content: selectedItem['PmtImpL30']
                                },
                                {
                                    title: 'PmtImpL7',
                                    content: selectedItem['PmtImpL7']
                                },
                                {
                                    title: 'views',
                                    content: selectedItem['views']
                                },
                                {
                                    title: 'PmtClkL7',
                                    content: selectedItem['PmtClkL7']
                                },
                                {
                                    title: 'PmtCtrL30',
                                    content: selectedItem['PmtCtrL30']
                                },
                                {
                                    title: 'PmtCtrL7',
                                    content: selectedItem['PmtCtrL7']
                                }
                            ];
                            break;
                        case 'price view':
                            fieldsToDisplay = [{
                                    title: 'eBay LIVE Price',
                                    content: selectedItem['eBay LIVE Price']
                                },
                                {
                                    title: 'eBay Price',
                                    content: selectedItem['eBay Price']
                                },
                                {
                                    title: 'PFT %',
                                    content: selectedItem['PFT %']
                                },
                                {
                                    title: 'ROI%',
                                    content: selectedItem['ROI%']
                                },
                                {
                                    title: 'sprice',
                                    content: dataToUse['sprice']
                                },
                                {
                                    title: 'Spft%',
                                    content: dataToUse['Spft%'] !== 0 ?
                                        Math.round(dataToUse['Spft%'] * 100) : 0
                                },
                                {
                                    title: 'ad cost',
                                    content: selectedItem['ad cost']
                                },
                                {
                                    title: 'a+spft',
                                    content: selectedItem['a+spft']
                                },
                                {
                                    title: 'a+ROI',
                                    content: selectedItem['a+ROI']
                                },
                                {
                                    title: 'LMP 1',
                                    content: selectedItem['LMP 1']
                                },
                                {
                                    title: 'link 1',
                                    content: dataToUse['link 1']
                                },
                                {
                                    title: 'lmp 2',
                                    content: dataToUse['lmp 2']
                                },
                                {
                                    title: 'link 2',
                                    content: dataToUse['link 2']
                                },
                                {
                                    title: 'lmp 3',
                                    content: dataToUse['lmp 3']
                                },
                                {
                                    title: 'link 3',
                                    content: dataToUse['link 3']
                                }
                            ];
                            break;
                        case 'advertisement view':
                            fieldsToDisplay = [
                                // Core TACOS Metrics
                                {
                                    title: 'KwImpL60',
                                    content: selectedItem['KwImpL60']
                                },
                                {
                                    title: 'KwImpL30',
                                    content: selectedItem['KwImpL30']
                                },
                                {
                                    title: 'KwImpL7',
                                    content: selectedItem['KwImpL7']
                                },
                                {
                                    title: 'KwClkL60',
                                    content: selectedItem['KwClkL60']
                                },
                                {
                                    title: 'KwClkL30',
                                    content: selectedItem['KwClkL30']
                                },
                                {
                                    title: 'KwClkL7',
                                    content: selectedItem['KwClkL7']
                                },
                                {
                                    title: 'KwCtrL60',
                                    content: selectedItem['KwCtrL60']
                                },
                                {
                                    title: 'KwCtrL30',
                                    content: selectedItem['KwCtrL30']
                                },
                                {
                                    title: 'KwCtrL7',
                                    content: selectedItem['KwCtrL7']
                                },
                                {
                                    title: 'KwSpndL60',
                                    content: selectedItem['KwSpndL60']
                                },
                                {
                                    title: 'KwSpndL30',
                                    content: selectedItem['KwSpndL30']
                                },
                                {
                                    title: 'KwSpndL7',
                                    content: selectedItem['KwSpndL7']
                                },
                                {
                                    title: 'KwSpndL1',
                                    content: selectedItem['KwSpndL1']
                                },
                                {
                                    title: 'KwSldL60',
                                    content: selectedItem['KwSldL60']
                                },
                                {
                                    title: 'KwSldL30',
                                    content: selectedItem['KwSldL30']
                                },
                                {
                                    title: 'KwSldL7',
                                    content: selectedItem['KwSldL7']
                                },
                                {
                                    title: 'KwSlsL60',
                                    content: selectedItem['KwSlsL60']
                                },
                                {
                                    title: 'KwSlsL30',
                                    content: selectedItem['KwSlsL30']
                                },
                                {
                                    title: 'KwSlsL7',
                                    content: selectedItem['KwSlsL7']
                                },
                                {
                                    title: 'KwCpcL60',
                                    content: selectedItem['KwCpcL60']
                                },
                                {
                                    title: 'KwCpcL30',
                                    content: selectedItem['KwCpcL30']
                                },
                                {
                                    title: 'KwCpcL7',
                                    content: selectedItem['KwCpcL7']
                                },
                                {
                                    title: 'KwCpcL1',
                                    content: selectedItem['KwCpcL1']
                                },
                                {
                                    title: 'KwAcosL60',
                                    content: selectedItem['KwAcosL60']
                                },
                                {
                                    title: 'KwAcosL30',
                                    content: selectedItem['KwAcosL30']
                                },
                                {
                                    title: 'KwAcosL7',
                                    content: selectedItem['KwAcosL7']
                                },
                                {
                                    title: 'KwCvrL30',
                                    content: selectedItem['KwCvrL30']
                                },
                                {
                                    title: 'KwCvrL7',
                                    content: selectedItem['KwCvrL7']
                                },
                                {
                                    title: 'Ub 7',
                                    content: selectedItem['Ub 7']
                                },
                                {
                                    title: 'Ub yes',
                                    content: selectedItem['Ub yes']
                                },
                                {
                                    title: 'PmtImpL30',
                                    content: selectedItem['PmtImpL30']
                                },
                                {
                                    title: 'PmtImpL7',
                                    content: selectedItem['PmtImpL7']
                                },
                                {
                                    title: 'views',
                                    content: selectedItem['views']
                                },
                                {
                                    title: 'PmtClkL7',
                                    content: selectedItem['PmtClkL7']
                                },
                                {
                                    title: 'PmtCtrL30',
                                    content: selectedItem['PmtCtrL30']
                                },
                                {
                                    title: 'PmtCtrL7',
                                    content: selectedItem['PmtCtrL7']
                                },
                                {
                                    title: 'PmtSpndL30',
                                    content: selectedItem['PmtSpndL30']
                                },
                                {
                                    title: 'PmtSpndL7',
                                    content: selectedItem['PmtSpndL7']
                                },
                                {
                                    title: 'PmtSldL30',
                                    content: selectedItem['PmtSldL30']
                                },
                                {
                                    title: 'PmtSldL7',
                                    content: selectedItem['PmtSldL7']
                                },
                                {
                                    title: 'PmtSlsL30',
                                    content: selectedItem['PmtSlsL30']
                                },
                                {
                                    title: 'PmtSlsL7',
                                    content: selectedItem['PmtSlsL7']
                                },
                                {
                                    title: 'PmtAcosL30',
                                    content: selectedItem['PmtAcosL30']
                                },
                                {
                                    title: 'PmtAcosL7',
                                    content: selectedItem['PmtAcosL7']
                                },
                                {
                                    title: 'PmtCvrL30',
                                    content: selectedItem['PmtCvrL30']
                                },
                                {
                                    title: 'PmtCvrL7',
                                    content: selectedItem['PmtCvrL7']
                                },
                                {
                                    title: 'Pmt%',
                                    content: selectedItem['Pmt%']
                                },
                                {
                                    title: 'TSpendL30',
                                    content: selectedItem['TSpendL30']
                                },
                                {
                                    title: 'TacosL30',
                                    content: selectedItem['TacosL30']
                                },
                            ];
                            break;
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
                                            title: 'TITLE ISSUE',
                                            content: dataToUse['TITLE ISSUE'],
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

            if (!document.getElementById('pricingModal')) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                $('body').append(`
                <div class="modal fade" id="pricingModal" tabindex="-1" aria-labelledby="pricingModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content p-3">
                            <div class="modal-header">
                                <h5 class="modal-title">SPRICE Calculator</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="pricingForm" method="POST" >
                                    @csrf
                                    <input type="hidden" id="skuInput" name="sku">

                                    <div class="mb-2">
                                        <label>SPRICE ($)</label>
                                        <input type="number" step="0.01" class="form-control" id="sprPriceInput" name="sprice">
                                    </div>
                                    <div class="mb-2">
                                        <label>SPFT%</label>
                                        <input type="text" class="form-control" id="spftPercentInput" name="spft_percent" readonly>
                                    </div>
                                    <div class="mb-2">
                                        <label>SROI%</label>
                                        <input type="text" class="form-control" id="sroiPercentInput" name="sroi_percent" readonly>
                                    </div>
<button type="button" id="savePricingBtn" class="btn btn-primary">Save</button>
                                </form>

                            </div>
                        </div>
                    </div>
                </div>
    `);
            }

            // Helper function to create a field card
            function createFieldCard(field, data, type, itemId) {
                const hyperlinkFields = ['link 1', 'link 2', 'link 3'];

                const percentageFields = ['KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PFT %', 'ROI%',
                    'a+spft', 'a+ROI', 'SCVR', 'KwCvrL60', 'KwCvrL30', 'KwCvrL7',
                    'PmtCvrL30', 'PmtCvrL7', 'KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PmtCtrL30',
                    'PmtCtrL7', 'KwAcosL60', 'KwAcosL30', 'KwAcosL7', 'KwCvrL30', 'KwCvrL7',
                    'Ub 7',
                    'Ub yes',
                    'PmtCtrL30', 'PmtCtrL7', 'PmtAcosL30', 'PmtAcosL7', 'PmtCvrL30',
                    'PmtCvrL7',
                    'Pmt%', 'TacosL30'
                ];

                const getIndicatorColor = (fieldTitle, fieldValue) => {
                    const value = (fieldValue * 100).toFixed(2) || 0;

                    if (type === 'price view') {
                        if (['PFT %', 'Spft%'].includes(fieldTitle)) {
                            if (value < 10) return 'red';
                            if (value >= 10 && value < 15) return 'yellow';
                            if (value >= 15 && value < 20) return 'blue';
                            if (value >= 20 && value < 40) return 'green';
                            if (value >= 40) return 'pink';
                        }

                        if (fieldTitle === 'ROI%') {
                            if (value < 50) return 'red';
                            if (value >= 50 && value < 75) return 'yellow';
                            if (value >= 75 && value < 125) return 'green';
                            if (value >= 125) return 'pink';
                        }

                        if (['a+spft', 'a+ROI'].includes(fieldTitle)) {
                            return 'gray'; // Missing in sheet
                        }

                        return 'gray';
                    }

                    if (type === 'visibility view') {
                        if (['KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PmtCtrL30', 'PmtCtrL7'].includes(fieldTitle)) {
                            return 'gray'; // Marked as missing
                        }

                        return 'gray';
                    }

                    if (type === 'advertisement view') {
                        if (['KwAcosL60', 'KwAcosL30', 'KwAcosL7', 'TacosL30'].includes(fieldTitle)) {
                            if (value == 0 || value == 100) return 'red';
                            if (value > 0 && value <= 7) return 'pink';
                            if (value > 7 && value <= 14) return 'green';
                            if (value > 14 && value <= 21) return 'yellow';
                            if (value > 21) return 'red';
                        }

                        if (['KwCvrL30', 'KwCvrL7'].includes(fieldTitle)) {
                            if (value < 7) return 'red';
                            if (value > 7 && value <= 13) return 'green';
                            if (value > 13) return 'pink';
                        }

                        if (['Ub 7', 'Ub yes'].includes(fieldTitle)) {
                            if (value < 50) return 'red';
                            if (value >= 50 && value <= 90) return 'green';
                            if (value > 90) return 'pink';
                        }

                        if (['PmtAcosL30', 'PmtAcosL7'].includes(fieldTitle)) {
                            if (value == 0) return 'red';
                            if (value > 0 && value <= 10) return 'pink';
                            if (value > 10 && value <= 20) return 'green';
                            if (value > 20) return 'red';
                        }

                        if (fieldTitle === 'PmtCvrL30') {
                            if (value < 7) return 'red';
                            if (value > 7 && value < 13) return 'green';
                            if (value >= 13) return 'pink';
                        }

                        if (fieldTitle === 'PmtCvrL7') {
                            if (value < 7) return 'red';
                            if (value > 7 && value < 14) return 'green';
                            if (value >= 14) return 'pink';
                        }

                        if (['KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PmtCtrL30', 'PmtCtrL7', 'Pmt%'].includes(
                                fieldTitle)) {
                            return 'gray'; // Missing in sheet
                        }

                        return 'gray';
                    }

                    if (type === 'conversion view') {
                        if (['Scvr', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30', 'DspCvr60', 'DspCvr30',
                                'HdCvr60',
                                'HdCvr30', 'TCvr60', 'TCvr30'
                            ].includes(fieldTitle)) {
                            if (value <= 7) return 'red';
                            if (value > 7 && value <= 13) return 'green';
                            if (value > 13) return 'pink';
                        }
                        return 'gray';
                    }

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

                if (percentageFields.includes(field.title) && typeof content ===
                    'number') {
                    content = `${(content * 100).toFixed(2)}%`;
                }

                // Add edit icon if field is editable
                // if (editableFields.includes(field.title)) {
                //     const editIcon = document.createElement('div');
                //     editIcon.className = 'position-absolute top-0 end-0 p-2 edit-icon';
                //     editIcon.style.cssText = 'cursor:pointer; z-index: 1;';
                //     editIcon.innerHTML = '<i class="fas fa-pen text-primary"></i>';
                //     card.appendChild(editIcon);
                // }

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

                // Get editable fields from the same array used in createFieldCard
                const editableFields = ['eBay Price', 'sprice', 'LMP 1', 'link 1', 'lmp 2', 'link 2', 'lmp 3',
                    'link 3',
                    'HIDE', 'LISTED', 'LIVE / ACTIVE', 'VISIBILITY ISSUE', 'INV SYNCED',
                    'RIGHT CATEGORY', 'INCOMPLETE LISTING', 'BUYBOX ISSUE', 'SEO  (KW RICH) ISSUE',
                    'TITLE ISSUEAD ISSUE', 'AD ISSUE', 'BP ISSUE', 'DESCR ISSUE', 'SPECS ISSUE',
                    'IMG ISSUE', 'CATEGORY ISSUE', 'MAIN IMAGE ISSUE', 'PRICE ISSUE',
                    'REVIEW ISSUE', 'WRONG KW IN LISTING', 'CVR ISSUE', 'REV ISSUE',
                    'IMAGE ISSUE', 'VID ISSUE', 'USP HIGHLIGHT ISSUE', 'SPECS ISSUES',
                    'MISMATCH ISSUE', 'NOTES', 'ACTION', 'TITLE ISSUE'
                ];

                // Remove all edit/save icons
                $(modalElement).find('.edit-icon, .save-icon').remove();

                // Enable only editable fields
                $(modalElement).find('.card').each(function() {
                    const $card = $(this);
                    const title = $card.find('.card-title').text().trim();
                    if (!editableFields.includes(title)) return;

                    const $content = $card.find('.editable-content');
                    const $checkbox = $content.find('.form-check-input');
                    const isHyperlink = $content.data('is-hyperlink');

                    if ($checkbox.length) {
                        $checkbox.prop('disabled', false);
                    } else {
                        let originalContent = $content.text().trim();
                        if (isHyperlink && $content.find('a').length) {
                            originalContent = $content.find('a').attr('href');
                            $content.html(originalContent);
                        }
                        $content
                            .attr('contenteditable', 'true')
                            .addClass('border border-primary')
                            .data('original-content', originalContent);
                    }
                });

                // Save on Enter for text fields
                $(modalElement).off('keydown', '.editable-content[contenteditable="true"]');
                $(modalElement).on('keydown', '.editable-content[contenteditable="true"]', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const $content = $(this);
                        const $card = $content.closest('.card');
                        const title = $card.find('.card-title').text().trim();
                        const slNo = $card.find('.hidden-sl-no').val();
                        const isHyperlink = $content.data('is-hyperlink');
                        let updatedValue = $content.text().trim();
                        if (isHyperlink) updatedValue = updatedValue;
                        saveChanges($content, title, slNo, isHyperlink, updatedValue, false);
                    }
                });

                // Save on Enter for checkboxes
                $(modalElement).off('keydown', '.form-check-input');
                $(modalElement).on('keydown', '.form-check-input', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const $checkbox = $(this);
                        const $card = $checkbox.closest('.card');
                        const $content = $card.find('.editable-content');
                        const title = $card.find('.card-title').text().trim();
                        const slNo = $card.find('.hidden-sl-no').val();
                        const isHyperlink = $content.data('is-hyperlink');
                        let updatedValue = $checkbox.prop('checked') ? "true" : "false";
                        saveChanges($content, title, slNo, isHyperlink, updatedValue, true);
                    }
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

                // Prepare data for API call
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
                ebayViewDataCache.updateField(itemId, title, cacheUpdateValue);

                // 2. Update the filteredData array to reflect the change
                const index = filteredData.findIndex(item => item['Sl'] == itemId);
                if (index !== -1) {
                    filteredData[index][title] = cacheUpdateValue;

                    // If this is an SPRICE update, calculate and update Spft% in cache
                    if (title === 'sprice' && filteredData[index].raw_data) {
                        const item = filteredData[index];
                        const AMZ = parseFloat(item.AMZ) || 0;
                        const SH = parseFloat(item.raw_data.SH) || 0;
                        const LP = parseFloat(item.raw_data.LP) || 0;
                        const SPRICE = parseFloat(updatedValue) || 0;

                        // Calculate Spft% using formula: (SPRICE * 0.77 - LP - SH) / SPRICE
                        let Spft = 0;
                        if (SPRICE !== 0) {
                            Spft = (SPRICE * 0.74 - LP - SH) / SPRICE;
                        }

                        // Update Spft% in cache and local data
                        ebayViewDataCache.updateField(itemId, 'Spft%', Spft);
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
                if (title === 'sprice') {
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

                // 5. Send the update to the server
                // If eBay Price, use a separate route
                let ajaxUrl = '/api/update-ebay-column';
                let ajaxData = JSON.stringify(data);
                let ajaxContentType = 'application/json';

                if (title === 'eBay Price') {
                    // Find eBay_item_id from filteredData or cache
                    let ebayItemId = null;
                    const found = filteredData.find(item => String(item['Sl']) == String(itemId));
                    if (found && found['eBay_item_id']) {
                        ebayItemId = found['eBay_item_id'];
                    }
                    if (!ebayItemId && ebayViewDataCache.get(itemId) && ebayViewDataCache.get(itemId)[
                            'eBay_item_id']) {
                        ebayItemId = ebayViewDataCache.get(itemId)['eBay_item_id'];
                    }

                    if (!ebayItemId) {
                        showNotification('danger', 'eBay Item ID is not available for this SKU.');
                        if (saveIcon) {
                            saveIcon.html('<i class="fas fa-pen text-primary"></i>')
                                .removeClass('save-icon')
                                .addClass('edit-icon');
                        }
                        return; // Do not proceed with AJAX
                    }

                    // Direct POST to the correct route
                    ajaxUrl = '/ebay-product-price-update';
                    ajaxData = JSON.stringify({
                        item_id: ebayItemId,
                        price: updatedValue
                    });
                    ajaxContentType = 'application/json';
                }

                $.ajax({
                    method: 'POST',
                    url: ajaxUrl,
                    data: ajaxData,
                    contentType: ajaxContentType,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (saveIcon) {
                            saveIcon.html('<i class="fas fa-pen text-primary"></i>')
                                .removeClass('save-icon')
                                .addClass('edit-icon');
                        }

                        if (!rowElement) {
                            if (isCheckbox) {
                                contentElement.find('.form-check-input').prop('disabled', true);
                            } else {
                                contentElement.attr('contenteditable', 'false')
                                    .removeClass('border border-primary');
                            }
                        }

                        showNotification('success', `${title} Updated Successfully`);

                        if (rowElement) {
                            checkParentRAStatus();
                            renderTable();
                        }

                        // --- Update eBay Price in all data and re-render table ---
                        if (title === 'eBay Price') {
                            // After successful update, reload the table data
                            loadData().then(() => {
                                renderTable();
                                showNotification('success', `${title} Updated Successfully`);
                            });
                            return; // Prevent further UI updates in this handler
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
                        ebayViewDataCache.updateField(itemId, title, originalValue);

                        // Revert filteredData
                        if (index !== -1) {
                            filteredData[index][title] = originalValue;

                            // If this was an SPRICE update, revert Spft% as well
                            if (title === 'sprice') {
                                const item = filteredData[index];
                                const SH = parseFloat(item.raw_data.SH) || 0;
                                const LP = parseFloat(item.raw_data.LP) || 0;
                                const SPRICE = parseFloat(originalValue) || 0;

                                let Spft = 0;
                                if (SPRICE !== 0) {
                                    Spft = (SPRICE * 0.74 - LP - SH) / SPRICE;
                                }

                                ebayViewDataCache.updateField(itemId, 'Spft%', Spft);
                                filteredData[index]['Spft%'] = Spft;
                                filteredData[index].raw_data['Spft%'] = Spft;
                            }

                            if (title === 'R&A' && filteredData[index].raw_data) {
                                filteredData[index].raw_data[title] = originalValue;
                            }
                        }

                        // Revert UI
                        if (rowElement) {
                            const checkbox = rowElement.find('.ra-checkbox');
                            if (checkbox.length) {
                                checkbox.prop('checked', originalValue);
                            }
                            renderTable();
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
                const $table = $('#ebay-table');
                const $headers = $table.find('th');
                let startX, startWidth, $currentTh, $currentTd;
                let minColumnWidth = 80;

                // Add resize handles to headers
                $headers.each(function(index) {
                    const $th = $(this);
                    // Don't add resize handle to last column
                    if (index < $headers.length - 1) {
                        $th.css('position', 'relative');
                        $th.append('<div class="resize-handle"></div>');
                    }
                });

                // Mouse down on resize handle
                $table.on('mousedown', '.resize-handle', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    isResizing = true;
                    $(this).addClass('resizing');
                    $currentTh = $(this).parent();
                    
                    startX = e.pageX;
                    startWidth = $currentTh.outerWidth();
                    
                    // Find corresponding td elements
                    const columnIndex = $currentTh.index();
                    $currentTd = $table.find('tbody tr:first td').eq(columnIndex);
                    
                    // Disable text selection
                    $('body').css({
                        'user-select': 'none',
                        'cursor': 'col-resize'
                    });
                    
                    // Add visual feedback
                    $table.addClass('resizing-active');
                });

                // Mouse move - resize column
                $(document).on('mousemove.resize', function(e) {
                    if (!isResizing || !$currentTh) return;
                    
                    e.preventDefault();
                    const deltaX = e.pageX - startX;
                    const newWidth = Math.max(minColumnWidth, startWidth + deltaX);
                    
                    // Apply width to header
                    $currentTh.css({
                        'width': newWidth + 'px',
                        'min-width': newWidth + 'px',
                        'max-width': newWidth + 'px'
                    });
                    
                    // Apply width to all cells in this column
                    const columnIndex = $currentTh.index();
                    $table.find('tbody tr').each(function() {
                        $(this).find('td').eq(columnIndex).css({
                            'width': newWidth + 'px',
                            'min-width': newWidth + 'px',
                            'max-width': newWidth + 'px'
                        });
                    });
                });

                // Mouse up - finish resizing
                $(document).on('mouseup.resize', function(e) {
                    if (!isResizing) return;
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Clean up
                    $('.resize-handle').removeClass('resizing');
                    $table.removeClass('resizing-active');
                    $('body').css({
                        'user-select': '',
                        'cursor': ''
                    });
                    
                    // Reset variables
                    isResizing = false;
                    $currentTh = null;
                    $currentTd = null;
                    
                    // Save column widths to localStorage
                    saveColumnWidths();
                });
                
                // Load saved column widths
                loadColumnWidths();
            }
            
            // Save column widths to localStorage
            function saveColumnWidths() {
                const $table = $('#ebay-table');
                const $headers = $table.find('th[data-field]');
                const widths = {};
                
                $headers.each(function() {
                    const field = $(this).data('field');
                    const width = $(this).outerWidth();
                    if (field && width) {
                        widths[field] = width;
                    }
                });
                
                localStorage.setItem('ebayTwoTableColumnWidths', JSON.stringify(widths));
            }
            
            // Load column widths from localStorage
            function loadColumnWidths() {
                const saved = localStorage.getItem('ebayTwoTableColumnWidths');
                if (!saved) return;
                
                try {
                    const widths = JSON.parse(saved);
                    const $table = $('#ebay-table');
                    
                    Object.entries(widths).forEach(([field, width]) => {
                        const $th = $table.find(`th[data-field="${field}"]`);
                        if ($th.length) {
                            $th.css({
                                'width': width + 'px',
                                'min-width': width + 'px',
                                'max-width': width + 'px'
                            });
                            
                            // Apply to corresponding cells
                            const columnIndex = $th.index();
                            $table.find('tbody tr').each(function() {
                                $(this).find('td').eq(columnIndex).css({
                                    'width': width + 'px',
                                    'min-width': width + 'px',
                                    'max-width': width + 'px'
                                });
                            });
                        }
                    });
                } catch (e) {
                    console.error('Error loading column widths:', e);
                }
            }

            // Initialize sorting functionality
            function initSorting() {
                $('th[data-field]').addClass('sortable').on('click', function(e) {
                    // Prevent sorting when resizing
                    if (isResizing) {
                        e.stopPropagation();
                        return;
                    }
                    
                    // Prevent sorting when clicking on resize handle
                    if ($(e.target).hasClass('resize-handle') || $(e.target).closest('.resize-handle').length) {
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

                    filteredData = freshData;
                    currentPage = 1;
                    renderTable();
                });
            }

            // Initialize pagination
            function initPagination() {
                // Remove rows-per-page related code

                // Keep these but modify to work with all rows
                $('#first-page').on('click', function() {
                    currentPage = 1;
                    renderTable();
                });

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

            // Initialize search functionality
            function initSearch() {
                $('#search-input').on('keyup', function() {
                    const searchTerm = $(this).val().toLowerCase();

                    if (searchTerm) {
                        filteredData = tableData.filter(item => {
                            return Object.values(item).some(val => {
                                if (typeof val === 'boolean' || val === null) return false;
                                return val.toString().toLowerCase().includes(searchTerm);
                            });
                        });
                    } else {
                        filteredData = [...tableData];
                    }

                    currentPage = 1;
                    renderTable();
                    calculateTotals();
                });
            }

            // Load hidden columns from localStorage
            function loadHiddenColumns() {
                const stored = localStorage.getItem('ebayTwoHiddenColumns');
                return stored ? new Set(JSON.parse(stored)) : new Set();
            }

            let hiddenColumns = loadHiddenColumns();

            function applyColumnVisibility() {
                const $table = $('#ebay-table');
                const $headers = $table.find('th[data-field]');

                $headers.each(function() {
                    const field = $(this).data('field');
                    const isHidden = hiddenColumns.has(field);
                    
                    // Hide/show TH
                    $(this).toggle(!isHidden);
                    
                    // Hide/show all TDs with matching data-field
                    $table.find(`td[data-field="${field}"]`).toggle(!isHidden);

                    // Update checkbox state in menu
                    $(`#toggle-${field}`).prop('checked', !isHidden);
                });
            }

            function initColumnToggle() {
                const $table = $('#ebay-table');
                const $headers = $table.find('th[data-field]');
                const $menu = $('#columnToggleMenu');
                const $dropdownBtn = $('#hideColumnsBtn');

                $menu.empty();

                $headers.each(function() {
                    const $th = $(this);
                    const field = $th.data('field');
                    const title = $th.text().trim().replace(' ↓', '');

                    const $item = $(`
                        <div class="column-toggle-item">
                            <input type="checkbox" class="column-toggle-checkbox" 
                                id="toggle-${field}" data-field="${field}">
                            <label for="toggle-${field}">${title}</label>
                        </div>
                    `);
                    $menu.append($item);
                });

                // Apply hidden columns after table is rendered
                applyColumnVisibility();

                // Dropdown toggle - prevent Bootstrap from handling it, use custom logic
                $dropdownBtn.off('click').on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $menu.toggleClass('show');
                });

                // Close menu if clicked outside
                $(document).off('click.columnToggle').on('click.columnToggle', function(e) {
                    if (!$(e.target).closest('.dropdown').length && !$(e.target).closest('#columnToggleMenu').length) {
                        $menu.removeClass('show');
                    }
                });

                $menu.on('change', '.column-toggle-checkbox', function() {
                    const field = $(this).data('field');
                    const isVisible = $(this).is(':checked');

                    // Toggle TH with matching data-field
                    $table.find(`th[data-field="${field}"]`).toggle(isVisible);
                    
                    // Toggle all TDs with matching data-field
                    $table.find(`td[data-field="${field}"]`).toggle(isVisible);

                    // Save hidden columns
                    if (!isVisible) hiddenColumns.add(field);
                    else hiddenColumns.delete(field);

                    localStorage.setItem('ebayTwoHiddenColumns', JSON.stringify([...hiddenColumns]));
                });

                $('#showAllColumns').on('click', function() {
                    // Show all TH elements
                    $table.find('th[data-field]').show();
                    
                    // Show all TD elements with data-field
                    $table.find('td[data-field]').show();
                    
                    // Update checkboxes
                    $menu.find('.column-toggle-checkbox').prop('checked', true);
                    
                    // Clear hiddenColumns and save
                    hiddenColumns.clear();
                    localStorage.setItem('ebayTwoHiddenColumns', JSON.stringify([...hiddenColumns]));
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

                // NRA filter
                $('.dropdown-menu').on('click', '.nra-filter', function(e) {
                    e.preventDefault();
                    const $this = $(this);
                    const value = $this.data('value');
                    const text = $this.text().trim();
                    
                    // Get the color class from the status circle
                    const $statusCircle = $this.find('.status-circle');
                    const colorClass = $statusCircle.attr('class').replace('status-circle', '').trim() || 'default';

                    $this.closest('.dropdown')
                        .find('.dropdown-toggle')
                        .html(`<span class="status-circle ${colorClass}"></span> NRA (${text})`);

                    state.filters.NRA = value;
                    $this.closest('.dropdown-menu').removeClass('show');
                    applyColumnFilters();
                });

                // NRL/REQ filter
                $('.dropdown-menu').on('click', '.nrl-req-filter', function(e) {
                    e.preventDefault();
                    const $this = $(this);
                    const value = $this.data('value');
                    const text = $this.text().trim();
                    
                    // Get the color class from the status circle
                    const $statusCircle = $this.find('.status-circle');
                    const colorClass = $statusCircle.attr('class').replace('status-circle', '').trim() || 'default';

                    $this.closest('.dropdown')
                        .find('.dropdown-toggle')
                        .html(`<span class="status-circle ${colorClass}"></span> NRL/REQ (${text})`);

                    state.filters.NRL_REQ = value;
                    $this.closest('.dropdown-menu').removeClass('show');
                    applyColumnFilters();
                });
            }

            // Add this script after your other filter initializations:
            $('#inv-filter, #ovl30-filter, #el30-filter').on('change', function() {
                applyColumnFilters();
            });

            // Apply column filters
            function applyColumnFilters() {
                // Default: show all rows
                filteredData = [...tableData];

                // Apply row type filter first
                const rowTypeFilter = $('#row-data-type').val();
                if (rowTypeFilter === 'parent') {
                    filteredData = filteredData.filter(item => item.is_parent);
                } else if (rowTypeFilter === 'sku') {
                    filteredData = filteredData.filter(item => !item.is_parent);
                }

                // Apply INV filter
                const invFilter = $('#inv-filter').val();
                if (invFilter && invFilter !== 'all') {
                    filteredData = filteredData.filter(item => {
                        const inv = Number(item.INV) || 0;
                        if (invFilter === '0') return inv === 0;
                        if (invFilter === '1-100+') return inv >= 1;
                        return true;
                    });
                }

                // Apply OV L30 filter
                const ovl30Filter = $('#ovl30-filter').val();
                if (ovl30Filter && ovl30Filter !== 'all') {
                    filteredData = filteredData.filter(item => {
                        const ovl30 = Number(item.L30) || 0;
                        if (ovl30Filter === '0') return ovl30 === 0;
                        if (ovl30Filter === '1-100+') return ovl30 >= 1;
                        return true;
                    });
                }
                // Apply EL 30 filter
                const el30Filter = $('#el30-filter').val();
                if (el30Filter && el30Filter !== 'all') {
                    filteredData = filteredData.filter(item => {
                        const el30 = Number(item['eBay L30']) || 0;
                        if (el30Filter === '0') return el30 === 0;
                        if (el30Filter === '1-100+') return el30 >= 1;
                        return true;
                    });
                }

                // Apply other filters
                Object.entries(state.filters).forEach(([column, filterValue]) => {
                    // Skip if column or filterValue is undefined or null
                    if (!column || column === 'undefined' || !filterValue || filterValue === 'undefined') {
                        return;
                    }
                    
                    if (filterValue === 'all') return;

                    // special-case: entryType
                    if (column === 'entryType') {
                        if (filterValue === 'parent') {
                            filteredData = filteredData.filter(item => item.is_parent);
                        } else if (filterValue === 'child') {
                            filteredData = filteredData.filter(item => !item.is_parent);
                        }
                        return;
                    }

                    // special-case: NRA -> use UI_NR (front-end selected value) only
                    if (column === 'NRA') {
                        filteredData = filteredData.filter(item => {
                            // Skip parent rows - they don't have NRA dropdown
                            if (item.is_parent) {
                                return true; // Always show parent rows
                            }
                            
                            // Only check UI_NR for child rows - this is the frontend selected value
                            const uiNR = item.UI_NR || item.NR || 'RA';
                            return uiNR === filterValue;
                        });
                        return;
                    }

                    // special-case: NRL_REQ -> filter based on nr_req field
                    if (column === 'NRL_REQ') {
                        filteredData = filteredData.filter(item => {
                            // Skip parent rows - they don't have NRL/REQ dropdown
                            if (item.is_parent) {
                                return true; // Always show parent rows
                            }
                            
                            // Check nr_req for child rows
                            const nrReq = item.nr_req || 'NR';
                            return nrReq === filterValue;
                        });
                        return;
                    }

                    // default: use getColorForColumn
                    filteredData = filteredData.filter(item => {
                        const color = getColorForColumn(column, item);
                        return color === filterValue;
                    });
                });


                currentPage = 1;
                renderTable();
                calculateTotals();
            }

            // Get color for column based on value
            function getColorForColumn(column, rowData) {
                if (!rowData || rowData[column] === undefined || rowData[column] === null || rowData[column] ===
                    '') {
                    return '';
                }

                // For views, use the raw value (not percentage)
                if (column === 'views') {
                    const value = parseInt(rowData[column]) || 0;
                    return value >= 30 ? 'green' : 'red';
                }

                // For SCVR, always use the calculated value
                if (column === 'SCVR') {
                    let scvr = 0;
                    if (Number(rowData['views']) > 0) {
                        scvr = Number(rowData['eBay L30']) / Number(rowData['views']);
                    }
                    const value = scvr * 100;
                    if (value <= 4) return 'red';
                    if (value > 4 && value <= 7) return 'yellow';
                    if (value > 7 && value <= 10) return 'green';
                    return 'pink';
                }

                const value = parseFloat(rowData[column]) * 100;

                // Special cases for numeric columns that must be valid numbers
                const numericColumns = ['PFT %', 'Roi', 'Tacos30', 'SCVR']; // Add other numeric columns as needed
                if (numericColumns.includes(column) && isNaN(value)) {
                    return '';
                }


                const colorRules = {
                    'ov_dil': {
                        ranges: [16.66, 25, 50],
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'E Dil%': {
                        ranges: [12.5, 16.66, 25, 50],
                        colors: ['red', 'yellow', 'blue', 'green', 'pink']
                    },
                    'PFT %': {
                        ranges: [10, 15, 20, 40],
                        colors: ['red', 'yellow', 'blue', 'green', 'pink']
                    },
                    'Roi': {
                        ranges: [50, 75, 125],
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'Tacos30': {
                        ranges: [7, 14, 21],
                        colors: ['pink', 'green', 'yellow', 'red']
                    },
                    'SCVR': {
                        ranges: [4, 7, 10],
                        colors: ['red', 'yellow', 'green', 'pink']
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
                    if (isLoading || filteredData.length === 0) {
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
                        profitSum: 0, // <-- new
                        salesL30Sum: 0, // <-- new
                        roiSum: 0,
                        roiCount: 0, // <-- ROI count for average calculation
                        tacosTotal: 0,
                        tacosCount: 0, // <-- Tacos count for average calculation
                        scvrSum: 0,
                        rowCount: 0,
                        listedCount: 0,
                        liveCount: 0,
                        totalSalesTotal: 0,
                        reqCount: 0, // <-- REQ counter
                        nrCount: 0, // <-- NR counter
                        nraColCount: 0, // <-- NRA column counter
                        raColCount: 0, // <-- RA column counter
                        grpftTotal: 0, // <-- GRPFT total
                        grpftCount: 0, // <-- GRPFT count for average calculation
                        pftTotal: 0, // <-- PFT total
                        pftCount: 0, // <-- PFT count for average calculation
                        tprftTotal: 0, // <-- TPRFT total
                        tprftCount: 0, // <-- TPRFT count for average calculation
                        totalPftAmount: 0, // <-- Total PFT Amount
                        totalAdSpend: 0, // <-- Total AD Spend
                        totalGrpftAmount: 0 // <-- Total GRPFT Amount
                    };

                    filteredData.forEach(item => {

                        let rawData = {};
                        if (typeof item.raw_data === 'string') {
                            try {
                                rawData = JSON.parse(item.raw_data || '{}');
                            } catch (e) {
                                console.error(`Invalid JSON in raw_data for SKU ${item['(Child) sku']}`, e);
                            }
                        } else if (typeof item.raw_data === 'object' && item.raw_data !== null) {
                            rawData = item.raw_data;
                        }

                        // Count listed checkboxes
                        if (rawData.Listed === true || rawData.Listed === 'true' || rawData.Listed === 1 ||
                            rawData.Listed === '1') {
                            metrics.listedCount++;
                        }

                        // Count Live checkboxes
                        if (rawData.Live === true || rawData.Live === 'true' || rawData.Live === 1 ||
                            rawData.Live === '1') {
                            metrics.liveCount++;
                        }

                        // Count NRL/REQ entries (only for non-parent rows)
                        if (!item.is_parent) {
                            const nrReq = item.nr_req || 'NR';
                            if (nrReq === 'REQ') {
                                metrics.reqCount++;
                            } else if (nrReq === 'NR') {
                                metrics.nrCount++;
                            }
                        }
                        
                        // Count NRA column entries based on UI_NR (only for non-parent rows)
                        if (!item.is_parent) {
                            const currentNR = item.UI_NR || item.NR || 'RA';
                            if (currentNR === 'NRA') {
                                metrics.nraColCount++;
                            } else if (currentNR === 'RA') {
                                metrics.raColCount++;
                            }
                        }

                        const profit = parseFloat(item.Profit) || 0;
                        const salesL30 = parseFloat(item['Sales L30']) || 0;
                        // Only add if both values are > 0
                        if (profit > 0 && salesL30 > 0) {
                            metrics.profitSum += profit;
                            metrics.salesL30Sum += salesL30;
                        }
                        metrics.invTotal += parseFloat(item.INV) || 0;
                        metrics.ovL30Total += parseFloat(item.L30) || 0;
                        metrics.el30Total += parseFloat(item['eBay L30']) || 0;
                        metrics.viewsTotal += parseFloat(item['views']) || 0;
                        let views = parseFloat(item['views']) || 0;
                        if (item.NR !== 'NRA') {
                            metrics.viewsTotal += views;
                        }
                        // Only count ROI when we have valid price and LP (similar to PFT/GRPFT)
                        const itemPriceForRoi = parseFloat(item['eBay Price']) || 0;
                        const itemLpForRoi = parseFloat(item.LP || item.LP_productmaster) || 0;
                        const roiValue = parseFloat(item.Roi) || 0;
                        if (itemPriceForRoi > 0 && itemLpForRoi > 0 && !isNaN(roiValue) && isFinite(roiValue)) {
                            metrics.roiSum += roiValue;
                            metrics.roiCount++;
                        }
                        const tacosValue = parseFloat(item.Tacos30) || 0;
                        if (!isNaN(tacosValue) && isFinite(tacosValue)) {
                            metrics.tacosTotal += tacosValue;
                            metrics.tacosCount++;
                        }
                        metrics.totalSalesTotal += ((item['eBay L30']) * (parseFloat(item['eBay Price']) || 0));
                        metrics.scvrSum += (Number(item['views']) > 0) ?
                            (Number(item['eBay L30']) / Number(item['views'])) :
                            0;
                        
                        // Calculate Total PFT Amount and AD Spend
                        metrics.totalPftAmount += parseFloat(item.Profit) || 0;
                        metrics.totalAdSpend += parseFloat(item.AD_Spend_L30 || item.spend_l30 || 0);
                        
                        // Calculate GRPFT, PFT, and TPRFT for totals
                        const itemPrice = parseFloat(item['eBay Price']) || 0;
                        const itemShip = parseFloat(item.SHIP || item.Ship_productmaster) || 0;
                        const itemLp = parseFloat(item.LP || item.LP_productmaster) || 0;
                        const itemTacos = parseFloat(item.TacosL30) || 0;
                        // Use percentage from item if available, otherwise use global percentage
                        const itemPercentage = {{ $ebayTwoPercentage ?? 0}};
                        const ebayPct = itemPercentage;
                        
                        if (itemPrice > 0) {
                            // GRPFT% = ((Price * percentage - Ship - LP) / Price) * 100
                            const itemGrpft = (itemPrice * (ebayPct / 100) - itemShip - itemLp) / itemPrice;
                            // Get AD% from item (convert from percentage to decimal)
                            const itemAdPercent = (parseFloat(item['AD%'] || 0) / 100);
                            // PFT% = GRPFT% - AD%
                            const itemPft = itemGrpft - itemAdPercent;
                            // TPRFT% = GRPFT% - TacosL30 (both are decimals, so direct subtraction)
                            const itemTprft = itemGrpft - itemTacos;
                            
                            if (!isNaN(itemPft) && isFinite(itemPft)) {
                                metrics.pftTotal += itemPft * 100;
                                metrics.pftCount++;
                            }
                            if (!isNaN(itemGrpft) && isFinite(itemGrpft)) {
                                metrics.grpftTotal += itemGrpft * 100;
                                metrics.grpftCount++;
                                // Calculate GRPFT amount: (price * percentage - ship - lp) * L30
                                const itemL30 = parseFloat(item['eBay L30']) || 0;
                                const grpftAmount = ((itemPrice * (ebayPct / 100)) - itemShip - itemLp) * itemL30;
                                if (!isNaN(grpftAmount) && isFinite(grpftAmount)) {
                                    metrics.totalGrpftAmount += grpftAmount;
                                }
                            }
                            if (!isNaN(itemTprft) && isFinite(itemTprft)) {
                                metrics.tprftTotal += itemTprft * 100;
                                metrics.tprftCount++;
                            }
                        }
                        
                        metrics.rowCount++;
                    });

                    // Calculate percentages
                    metrics.ovDilTotal = metrics.invTotal > 0 ?
                        (metrics.ovL30Total / metrics.invTotal) * 100 : 0;
                    metrics.eDilTotal = metrics.ovL30Total > 0 ? (metrics.el30Total / metrics.ovL30Total) * 100 : 0;

                    const divisor = metrics.rowCount || 1;

                    // Update metric displays with correct calculations
                    $('#sku-total').text(filteredData.length.toLocaleString());
                    $('#inv-total').text(metrics.invTotal.toLocaleString());
                    $('#ovl30-total').text(metrics.ovL30Total.toLocaleString());
                    $('#ovdil-total').text(Math.round(metrics.ovDilTotal) + '%');
                    $('#el30-total').text(metrics.el30Total.toLocaleString());
                    $('#eDil-total').text(Math.round(metrics.eDilTotal) + '%');
                    $('#views-total').text(metrics.viewsTotal.toLocaleString());
                    $('#listed-total').text(metrics.listedCount.toLocaleString());
                    $('#live-total').text(metrics.liveCount.toLocaleString());
                    
                    // Display NRL/REQ count based on active filter
                    const nrlReqFilter = state.filters.NRL_REQ || 'all';
                    if (nrlReqFilter === 'REQ') {
                        $('#req-total').text(metrics.reqCount.toLocaleString());
                    } else if (nrlReqFilter === 'NR') {
                        $('#req-total').text(metrics.nrCount.toLocaleString());
                    } else {
                        // When filter is 'all', show total REQ count
                        $('#req-total').text(metrics.reqCount.toLocaleString());
                    }
                    
                    // Display NRA column count based on active filter
                    const nraFilter = state.filters.NRA || 'all';
                    // If NRA filter is active, show count based on that filter
                    if (nraFilter === 'RA') {
                        $('#nra-col-count').text(metrics.raColCount.toLocaleString());
                    } else if (nraFilter === 'NRA') {
                        $('#nra-col-count').text(metrics.nraColCount.toLocaleString());
                    } else {
                        // When NRA filter is 'all', check if there are more RA or NRA in filtered data
                        // This handles cases when other filters (like NRL/REQ) are active
                        if (metrics.raColCount > 0 && metrics.nraColCount === 0) {
                            // Only RA rows in filtered data
                            $('#nra-col-count').text(metrics.raColCount.toLocaleString());
                        } else if (metrics.nraColCount > 0 && metrics.raColCount === 0) {
                            // Only NRA rows in filtered data
                            $('#nra-col-count').text(metrics.nraColCount.toLocaleString());
                        } else {
                            // Mixed or default - show NRA count
                            $('#nra-col-count').text(metrics.nraColCount.toLocaleString());
                        }
                    }
                    
                    $('#sale-total').text(metrics.totalSalesTotal.toFixed(2));

                    $.ajax({
                        url: "{{ route('adv-ebay2.total-sale.save-data') }}",
                        method: 'GET',
                        data: {
                            totalSales: metrics.totalSalesTotal
                        },
                        success: function(response) {
                        },
                        error: function(xhr) {
                        }
                    });


                    // Calculate and display averages
                    // Calculate average PFT (old calculation for backward compatibility)
                    let pftTotalAvg = 0;
                    if (metrics.salesL30Sum > 0) {
                        pftTotalAvg = (metrics.profitSum / metrics.salesL30Sum) * 100;
                    }

                    // Calculate averages for GRPFT, PFT, and TPRFT
                    const grpftAvg = metrics.grpftCount > 0 ? (metrics.grpftTotal / metrics.grpftCount) : 0;
                    const pftAvg = metrics.pftCount > 0 ? (metrics.pftTotal / metrics.pftCount) : 0;
                    const tprftAvg = metrics.tprftCount > 0 ? (metrics.tprftTotal / metrics.tprftCount) : 0;

                    $('#grpft-total').text(grpftAvg.toFixed(0) + '%');
                    $('#pft-total').text(pftAvg.toFixed(0) + '%');
                    $('#tprft-total').text(tprftAvg.toFixed(0) + '%');
                    const roiAvg = metrics.roiCount > 0 ? (metrics.roiSum / metrics.roiCount) : 0;
                    $('#roi-total').text(Math.round(roiAvg) + '%');
                    const tacosAvg = metrics.tacosCount > 0 ? (metrics.tacosTotal / metrics.tacosCount) : 0;
                    $('#tacos-total').text(Math.round(tacosAvg * 100) + '%');
                    $('#cvr-total').text(Math.round((metrics.scvrSum / divisor) * 100) + '%');
                    
                    // Update Summary Stats badges
                    $('#total-pft-summary-badge').text('Total PFT: ' + pftAvg.toFixed(0) + '%');
                    $('#total-sales-summary-badge').text('Total Sales: $' + metrics.totalSalesTotal.toFixed(2));
                    $('#total-grpft-summary-badge').text('Total GRPFT: ' + grpftAvg.toFixed(0) + '%');
                    $('#total-ad-spend-summary-badge').text('Total AD Spend: $' + metrics.totalAdSpend.toFixed(2));
                    $('#total-el30-summary-badge').text('Total EL 30: ' + metrics.el30Total.toLocaleString());

                } catch (error) {
                    console.error('Error in calculateTotals:', error);
                    resetMetricsToZero();
                }
            }

            function resetMetricsToZero() {
                $('#sku-total').text('0');
                $('#inv-total').text('0');
                $('#ovl30-total').text('0');
                $('#ovdil-total').text('0%');
                $('#el30-total').text('0');
                $('#eDil-total').text('0%');
                $('#views-total').text('0');
                $('#grpft-total').text('0%');
                $('#pft-total').text('0%');
                $('#tprft-total').text('0%');
                $('#roi-total').text('0%');
                $('#tacos-total').text('0%');
                $('#cvr-total').text('0%');
                $('#listed-total').text('0');
                $('#live-total').text('0');
                
                // Reset Summary Stats badges
                $('#total-pft-summary-badge').text('Total PFT: 0%');
                $('#total-sales-summary-badge').text('Total Sales: $0.00');
                $('#total-grpft-summary-badge').text('Total GRPFT: 0%');
                $('#total-ad-spend-summary-badge').text('Total AD Spend: $0.00');
                $('#total-el30-summary-badge').text('Total EL 30: 0');
                $('#zero-sold-count-summary').text('0 SOLD: 0');
                $('#sold-count-summary').text('SOLD: 0');
                $('#red-margin-count-summary').text('RED MARGIN: 0');
                $('#nrl-count-summary').text('NRL: 0');
                $('#req-total').text('0');
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

                // Function to filter the table by column value
                function filterByColumn(column, value) {
                    if (value === '') {
                        filteredData = [...tableData];
                    } else {
                        filteredData = tableData.filter(item =>
                            String(item[column] || '').toLowerCase() === value.toLowerCase()
                        );
                    }

                    currentPage = 1;
                    renderTable();
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

                // Reset to first page and render
                currentPage = 1;
                renderTable();
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


            // Loader functions
            function showLoader() {
                $('#data-loader').fadeIn();
            }

            function hideLoader() {
                $('#data-loader').fadeOut();
            }





            // Show the custom modal
            function showHideSkuModal() {
                const $dialog = $('#customHideSkuModal .custom-modal-dialog');
                $dialog.css({
                    left: '',
                    top: '',
                    position: ''
                }); // Reset position
                $('#customHideSkuModal').fadeIn(150);
                $('body').addClass('custom-modal-open');
            }

            // Hide the custom modal
            function hideHideSkuModal() {
                $('#customHideSkuModal').fadeOut(150);
                $('body').removeClass('custom-modal-open');
            }

            // Open modal on button click
            $(document).on('click', '#hideSkuBtn', function() {
                // Populate the modal table
                const $tbody = $('#hideSkuTable tbody');
                $tbody.empty();
                tableData.forEach(item => {
                    if (!item.is_parent) {
                        $tbody.append(`
                <tr>
                    <td>${item.Parent}</td>
                    <td>${item['(Child) sku']}</td>
                    <td>
                        <input type="checkbox" class="hide-sku-checkbox" data-sku="${item['(Child) sku']}" ${item.Hide === true || item.Hide === 'true' || item.Hide === '1' ? 'checked' : ''}>
                    </td>
                </tr>
            `);
                    }
                });
                if ($('#hideSkuParentSearch').length === 0) {
                    $('#hideSkuTable').before(`
                        <div class="d-flex gap-2 mb-2">
                            <input type="text" id="hideSkuParentSearch" class="form-control form-control-sm" style="max-width:180px" placeholder="Search parent...">
                            <input type="text" id="hideSkuSkuSearch" class="form-control form-control-sm" style="max-width:180px" placeholder="Search SKU...">
                            <select id="hideSkuStatusFilter" class="form-control form-control-sm" style="max-width:140px">
                                <option value="all">All</option>
                                <option value="checked">Hide Checked</option>
                                <option value="unchecked">Hide Unchecked</option>
                            </select>
                        </div>
                    `);
                }
                $('#hideSkuParentSearch, #hideSkuSkuSearch').val('');
                $('#hideSkuStatusFilter').val('checked'); // <-- Checked by default
                showHideSkuModal();
                filterHideSkuModalTable(); // Apply filter immediately
            });

            // Filtering logic for modal table
            function filterHideSkuModalTable() {
                const parentTerm = $('#hideSkuParentSearch').val().toLowerCase();
                const skuTerm = $('#hideSkuSkuSearch').val().toLowerCase();
                const hideStatus = $('#hideSkuStatusFilter').val();

                $('#hideSkuTable tbody tr').each(function() {
                    const parentText = $(this).find('td').eq(0).text().toLowerCase();
                    const skuText = $(this).find('td').eq(1).text().toLowerCase();
                    const $checkbox = $(this).find('.hide-sku-checkbox');
                    const isChecked = $checkbox.prop('checked');

                    let show = (!parentTerm || parentText.includes(parentTerm)) &&
                        (!skuTerm || skuText.includes(skuTerm));

                    if (hideStatus === 'checked' && !isChecked) show = false;
                    if (hideStatus === 'unchecked' && isChecked) show = false;

                    $(this).toggle(show);
                });
            }
            $(document).on('input change', '#hideSkuParentSearch, #hideSkuSkuSearch, #hideSkuStatusFilter',
                filterHideSkuModalTable);

            // Close modal on close button or clicking outside dialog
            $(document).on('click', '#closeHideSkuModal', hideHideSkuModal);
            $(document).on('mousedown', function(e) {
                const $modal = $('#customHideSkuModal');
                if ($modal.is(':visible') && !$(e.target).closest('.custom-modal-dialog').length && !$(e
                        .target).is('#hideSkuBtn')) {
                    hideHideSkuModal();
                }
            });

            // Prevent modal click from closing when clicking inside dialog
            $(document).on('mousedown', '.custom-modal-dialog', function(e) {
                e.stopPropagation();
            });

            // Enable/disable update button based on changes
            function updateHideBtnState() {
                let changed = false;
                $('#hideSkuTable .hide-sku-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    const checked = $(this).prop('checked');
                    const original = tableData.find(item => item['(Child) sku'] === sku)?.Hide;
                    if ((checked && !original) || (!checked && original)) {
                        changed = true;
                        return false;
                    }
                });
                $('#updateSelectedHideBtn').prop('disabled', !changed);
            }

            // After populating modal table
            updateHideBtnState();
            $(document).on('change', '#hideSkuTable .hide-sku-checkbox', updateHideBtnState);

            // Update selected handler
            $(document).on('click', '#updateSelectedHideBtn', function() {
                const skusToUpdate = [];
                const hideValues = {};
                $('#hideSkuTable .hide-sku-checkbox').each(function() {
                    const sku = $(this).data('sku');
                    const checked = $(this).prop('checked');
                    skusToUpdate.push(sku);
                    hideValues[sku] = checked;
                });
                if (skusToUpdate.length === 0) {
                    showNotification('warning', 'No SKUs found.');
                    return;
                }

                // Change button to loader
                const $btn = $(this);
                $btn.prop('disabled', true)
                    .html(
                        '<span class="spinner-border spinner-border-sm me-2"></span>Updating Selected...');

                $.ajax({
                    url: '/ebay/save-nr',
                    type: 'POST',
                    data: {
                        skus: skusToUpdate,
                        hideValues: JSON.stringify(hideValues),
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        showNotification('success', 'Selected SKUs updated!');
                        hideHideSkuModal();
                        // Update tableData and filteredData for Hide values
                        $('#hideSkuTable .hide-sku-checkbox').each(function() {
                            const sku = $(this).data('sku');
                            const checked = $(this).prop('checked');
                            tableData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.Hide = checked;
                                }
                            });
                            filteredData.forEach(item => {
                                if (item['(Child) sku'] === sku) {
                                    item.Hide = checked;
                                }
                            });
                        });
                        renderTable();
                        // Restore button state
                        $btn.prop('disabled', false).html('Update Selected');
                    },
                    error: function() {
                        showNotification('danger', 'Failed to update SKUs.');
                        // Restore button state
                        $btn.prop('disabled', false).html('Update Selected');
                    }
                });
            });

            $(document).on('click', '.openPricingBtn', function() {
                const LP = parseFloat($(this).data('lp')) || 0;
                const SHIP = parseFloat($(this).data('ship')) || 0;
                const SKU = $(this).data('sku') || '';

                $('#skuInput').val(SKU);

                const $sprInput = $('#sprPriceInput');
                const $spftInput = $('#spftPercentInput');
                const $sroiInput = $('#sroiPercentInput');

                // Reset values
                $sprInput.val('');
                $spftInput.val('');
                $sroiInput.val('');

                $sprInput.off('input').on('input', function() {
                    const SPRICE = parseFloat(this.value) || 0;

                    if (SPRICE > 0) {
                        const SPFT = ((SPRICE * 0.74) - LP - SHIP) / SPRICE;
                        const SROI = ((SPRICE * 0.74) - LP - SHIP) / LP;

                        $spftInput.val((SPFT * 100).toFixed(2) + '%');
                        $sroiInput.val(isFinite(SROI) ? (SROI * 100).toFixed(2) + '%' : '∞');
                    } else {
                        $spftInput.val('');
                        $sroiInput.val('');
                    }
                });

                $('#pricingModal').modal('show');
            });

            $(document).on('click', '#savePricingBtn', function() {
                const sku = $('#skuInput').val()?.trim();
                const spriceVal = $('#sprPriceInput').val();
                const spft = parseFloat($('#spftPercentInput').val()?.replace('%', '')) || 0;
                const sroi = parseFloat($('#sroiPercentInput').val()?.replace('%', '')) || 0;

                const sprice = spriceVal !== '' ? parseFloat(spriceVal) : null;

                if (!sku || !sprice) {
                    alert('SKU and SPRICE are required.');
                    return;
                }

                $.ajax({
                    url: '/ebay/save-sprice',
                    type: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        sku: sku,
                        sprice: sprice,
                        spft_percent: spft,
                        sroi_percent: sroi
                    },
                    dataType: 'json',
                    beforeSend: function() {
                        $('#savePricingBtn').html(
                            '<i class="fa fa-spinner fa-spin"></i> Saving...');
                    },
                    success: function(response) {
                        showNotification('success', 'Data updated successfully...');
                        $('#pricingModal').modal('hide');
                        tableData.forEach(item => {
                            if (item['(Child) sku'] === sku) {
                                item.SPRICE = sprice;
                                item.SPFT = spft;
                                item.SROI = sroi;
                            }
                        });

                        filteredData.forEach(item => {
                            if (item['(Child) sku'] === sku) {
                                item.SPRICE = sprice;
                                item.SPFT = spft;
                                item.SROI = sroi;
                            }
                        });
                        renderTable();
                    },
                    error: function(xhr) {
                        alert('Error saving SPRICE.');
                        console.error(xhr.responseText);
                    },
                    complete: function() {
                        $('#savePricingBtn').html('Save');
                    }
                });
            });

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
            // SKU Copy functionality
            $(document).on('click', '.copy-sku-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const sku = $(this).data('sku');
                const button = $(this);
                const originalIcon = button.find('i');
                
                // Copy to clipboard
                if (navigator.clipboard && window.isSecureContext) {
                    // Modern browsers
                    navigator.clipboard.writeText(sku).then(() => {
                        // Change icon to checkmark temporarily
                        originalIcon.removeClass('fa-copy').addClass('fa-check');
                        button.removeClass('btn-outline-secondary').addClass('btn-success');
                        
                        setTimeout(() => {
                            originalIcon.removeClass('fa-check').addClass('fa-copy');
                            button.removeClass('btn-success').addClass('btn-outline-secondary');
                        }, 1000);
                        
                        showNotification('success', `SKU "${sku}" copied to clipboard!`);
                    }).catch(() => {
                        showNotification('danger', 'Failed to copy SKU to clipboard');
                    });
                } else {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = sku;
                    document.body.appendChild(textArea);
                    textArea.select();
                    try {
                        document.execCommand('copy');
                        // Change icon to checkmark temporarily
                        originalIcon.removeClass('fa-copy').addClass('fa-check');
                        button.removeClass('btn-outline-secondary').addClass('btn-success');
                        
                        setTimeout(() => {
                            originalIcon.removeClass('fa-check').addClass('fa-copy');
                            button.removeClass('btn-success').addClass('btn-outline-secondary');
                        }, 1000);
                        
                        showNotification('success', `SKU "${sku}" copied to clipboard!`);
                    } catch (err) {
                        showNotification('danger', 'Failed to copy SKU to clipboard');
                    }
                    document.body.removeChild(textArea);
                }
            });

            // Download Sample functionality
            $(document).on('click', '#downloadSampleBtn', function(e) {
                e.preventDefault();
                
                const button = $(this);
                const originalText = button.html();
                
                // Show loading state
                button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Downloading...');
                
                // Create a temporary link and trigger download
                const downloadUrl = '{{ route("ebay2.analytics.sample") }}';
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'ebay2_sample.xlsx';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Reset button state after a short delay
                setTimeout(() => {
                    button.prop('disabled', false).html(originalText);
                }, 1500);
            });

            // Handle import form submission with AJAX
            $('#ebayImportForm').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const formData = new FormData(this);
                const submitBtn = form.find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                
                // Validate file is selected
                const fileInput = form.find('#reverbExcelFile')[0];
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    showNotification('danger', 'Please select a file to import.');
                    return;
                }
                
                // Disable submit button and show loading
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Importing...');
                
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        let message = 'File imported successfully!';
                        if (response && response.message) {
                            message = response.message;
                        } else if (typeof response === 'string') {
                            message = response;
                        }
                        showNotification('success', message);
                        $('#ebayImportModal').modal('hide');
                        form[0].reset();
                        
                        // Reload data after successful import
                        if (typeof loadData === 'function') {
                            loadData();
                        }
                    },
                    error: function(xhr) {
                        let errorMessage = 'Error importing file.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage = response.message || errorMessage;
                            } catch (e) {
                                // Check if it's HTML response with error message
                                const match = xhr.responseText.match(/Error importing file: ([^<]+)/);
                                if (match) {
                                    errorMessage = match[1];
                                } else {
                                    errorMessage = 'Error importing file. Please check the file format.';
                                }
                            }
                        }
                        showNotification('danger', errorMessage);
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false).html(originalBtnText);
                    }
                });
            });

            // Initialize image hover functionality
            function initImageHover() {
                let $currentEnlargedImage = null;
                let $overlay = null;
                
                // Create and append overlay
                if (!$('#imageOverlay').length) {
                    $overlay = $('<div id="imageOverlay" class="image-overlay"></div>');
                    $('body').append($overlay);
                } else {
                    $overlay = $('#imageOverlay');
                }
                
                // Function to remove enlarged image
                function removeEnlargedImage() {
                    if ($currentEnlargedImage) {
                        $currentEnlargedImage.remove();
                        $currentEnlargedImage = null;
                    }
                    $overlay.removeClass('active');
                }
                
                // Remove any existing event handlers to prevent duplicates
                $(document).off('mouseenter.imageHover mouseleave.imageHover click.imageHover');
                $overlay.off('click');
                
                // Mouse enter - show enlarged image
                $(document).on('mouseenter.imageHover', '.product-image-thumbnail', function() {
                    const imageSrc = $(this).attr('data-image-src') || $(this).attr('src');
                    if (!imageSrc) return;
                    
                    // Remove any existing enlarged image
                    removeEnlargedImage();
                    
                    // Create new enlarged image
                    $currentEnlargedImage = $('<img>', {
                        src: imageSrc,
                        class: 'product-image-enlarged',
                        alt: 'Product'
                    });
                    
                    // Append to body and show overlay
                    $('body').append($currentEnlargedImage);
                    $overlay.addClass('active');
                });
                
                // Mouse leave - hide enlarged image after delay
                $(document).on('mouseleave.imageHover', '.product-image-thumbnail', function() {
                    setTimeout(() => {
                        // Check if mouse is over enlarged image
                        if (!$('.product-image-enlarged:hover').length && !$('.product-image-thumbnail:hover').length) {
                            removeEnlargedImage();
                        }
                    }, 100);
                });
                
                // When mouse leaves enlarged image, remove it
                $(document).on('mouseleave.imageHover', '.product-image-enlarged', function() {
                    removeEnlargedImage();
                });
                
                // Click overlay to close
                $overlay.on('click', function() {
                    removeEnlargedImage();
                });
                
                // Also allow clicking the enlarged image itself to close
                $(document).on('click.imageHover', '.product-image-enlarged', function(e) {
                    e.stopPropagation();
                    removeEnlargedImage();
                });
            }

            // Initialize everything
            initTable();
            initImageHover();
            // Make the static Hide SKU modal draggable using the existing logic
            ModalSystem.makeDraggable(document.getElementById('customHideSkuModal'));
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Fix for Bootstrap modal backdrop issue
            const modalElements = document.querySelectorAll('.modal');
            modalElements.forEach(modal => {
                modal.addEventListener('show.bs.modal', function() {
                    // Ensure backdrop is properly initialized
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            // Reinitialize if needed
                            modalInstance._initializeBackDrop();
                        }
                    }
                });
            });

            // Close all modals button functionality
            const closeAllModalsBtn = document.getElementById('close-all-modals');
            if (closeAllModalsBtn) {
                closeAllModalsBtn.addEventListener('click', function() {
                    const modals = document.querySelectorAll('.modal');
                    modals.forEach(modal => {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        }
                    });
                });
            }
        });
    </script>
@endsection
