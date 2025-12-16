@extends('layouts.vertical', ['title' => 'Listing eBay', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ========== TABLE STRUCTURE ========== */
        .table-container {
            overflow-x: auto;
            overflow-y: visible;
            position: relative;
            max-height: 600px;
        }

        .custom-resizable-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .custom-resizable-table th,
        .custom-resizable-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            position: relative;
            white-space: nowrap;
            overflow: visible !important;
        }

        .custom-resizable-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            user-select: none;
            position: sticky;
            top: 0;
            z-index: 10;
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
            background-color: rgba(69, 233, 255, 0.1) !important;
            /* Light blue background */
            font-weight: bold;
            /* Optional: Make the text bold */
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
            bottom: 100%;
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

        /* Highlight the selected dropdown option */
        .dropdown-item.active {
            background-color: #e9ecef;
            color: #495057;
            font-weight: bold;
        }

        /* Style for the filter selection text in buttons */
        .filter-selection {
            font-weight: bold;
            color: #0d6efd;
            margin-left: 4px;
        }

        /* Make dropdown buttons show their state */
        .btn-light.active-filter {
            background-color: #e2e6ea;
            border-color: #dae0e5;
        }

        /* ========== NR SELECT DROPDOWN ========== */
        .nr-select {
            font-weight: 500;
        }

        .nr-select option[value="NRL"] {
            background-color: #dc3545 !important;
            color: #ffffff !important;
        }

        .nr-select option[value="REQ"] {
            background-color: #28a745 !important;
            color: #ffffff !important;
        }

        /* When NRL is selected, the select itself should be red */
        .nr-select[data-value="NRL"] {
            background-color: #dc3545 !important;
            color: #ffffff !important;
        }

        /* When REQ is selected, the select itself should be green */
        .nr-select[data-value="REQ"] {
            background-color: #28a745 !important;
            color: #ffffff !important;
        }

        .listed-dropdown {
            width: 100%;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            text-align: center;
            color: white;
            border: none;
            cursor: pointer;
        }

        .listed-dropdown option {
            padding: 4px 8px;
            font-weight: bold;
        }

        .listed-dropdown .listed-option {
            background-color: #28a745;
            /* Green */
            color: white;
        }

        .listed-dropdown .pending-option {
            background-color: #dc3545;
            /* Red */
            color: white;
        }
        .nr-hide{
            display: none !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Listing eBay', 'sub_title' => 'eBay'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Controls row -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <!-- Left side controls -->
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="row-data-type" class="mr-2">Data Type:</label>
                                <select id="row-data-type" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="sku" selected>SKU (Child)</option>
                                    <option value="parent">Parent</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">   
                                <label for="combined-filter" class="mr-2">Show:</label>
                                <select id="combined-filter" class="form-control form-control-sm">
                                    <option value="all">All SKUs</option>
                                    <option value="inv">INV (All SKUs with Inventory)</option>
                                    <option value="req-nrl">RL-NRL (RL without Links)</option>
                                    <option value="pending" selected>Pending (Listed=Pending)</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="inv-filter" class="mr-2">INV:</label>
                                <select id="inv-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="inv-only">INV Only</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="nr-req-filter" class="mr-2">NRL/RL:</label>
                                <select id="nr-req-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="REQ">RL</option>
                                    <option value="NRL">NRL</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label for="link-filter" class="mr-2">LINK:</label>
                                <select id="link-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="with-link">With Link</option>
                                    <option value="without-link">Without Link</option>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label for="listed-filter" class="mr-2">Listed:</label>
                                <select id="listed-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="Listed">Listed</option>
                                    <option value="Pending">Pending</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex align-items-center mb-3 gap-2">

                            <!-- Import/Export buttons -->
                            <button type="button" class="btn btn-sm btn-primary mr-2" id="import-btn">Import</button>
                            <!-- <button type="button" class="btn btn-sm btn-success mr-3" id="export-btn">Export</button> -->
                            <a href="{{ route('listing_ebay.export') }}" class="btn btn-sm btn-success mr-3">Export</a>

                            <!-- Search on right -->
                            <div class="form-group mb-0 d-flex align-items-center ml-3">
                                <label for="search-input" class="mr-2 mb-0">Search:</label>
                                <input type="text" id="search-input" class="form-control form-control-sm"
                                    placeholder="Search all columns...">
                            </div>
                        </div>
                    </div>

                     <!-- Import Modal -->
                    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Import Editable Fields</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">

                                <a href="{{ asset('sample_excel/sample_listing_ebay_file.csv') }}" download class="btn btn-outline-secondary mb-3">📄 Download Sample File</a>

                                <input type="file" id="importFile" name="file" accept=".xlsx,.xls,.csv" class="form-control" />
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="confirmImportBtn">Import</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="custom-resizable-table" id="listing-table">
                            <thead>
                                <tr>
                                    <th data-field="parent" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center sortable-header">
                                                Parent <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="batch-total" 
                                                style="display:inline-block; background:#17a2b8; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px;">
                                                0
                                            </div>
                                            <div class="mt-1 dropdown-search-container">
                                                <input type="text" class="form-control form-control-sm parent-search"
                                                    placeholder="Search parent..." id="parentSearch">
                                                <div class="dropdown-search-results" id="parentSearchResults"></div>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="sku" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center sortable">
                                            <div class="d-flex align-items-center">
                                                Sku <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="total-sku" 
                                                style="display:inline-block; background:#007bff; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px;">
                                                0
                                            </div>
                                            <div class="mt-1 dropdown-search-container">
                                                <input type="text" class="form-control form-control-sm sku-search"
                                                    placeholder="Search SKU..." id="skuSearch">
                                                <div class="dropdown-search-results" id="skuSearchResults"></div>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="inv" style="vertical-align: middle; white-space: nowrap; text-align: center;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                INV <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="inv-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="nr_req" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                RL/NRL
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div style="display: flex; gap: 5px;">
                                                <div class="metric-total" id="rl-total"
                                                    style="display:inline-block; background:#28a745; color:white; border-radius:8px; padding:8px 12px; font-weight:600; font-size:15px;">
                                                    0</div>
                                                <div class="metric-total" id="nrl-total"
                                                    style="display:inline-block; background:#dc3545; color:white; border-radius:8px; padding:8px 12px; font-weight:600; font-size:15px;">
                                                    0</div>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="nr_req" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                LINK
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="without-link-total"
                                                style="display:inline-block; background:#dc3545; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px;">
                                                0
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="listed" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Listed/Pending
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div>
                                                <span class="metric-total" id="listed-total"
                                                    style="display:inline-block; background:#28a745; color:white; border-radius:8px; padding:4px 12px; font-weight:600; font-size:15px;">
                                                    0
                                                </span>
                                                <span class="metric-total" id="pending-total"
                                                    style="display:inline-block; background:#dc3545; color:white; border-radius:8px; padding:4px 12px; font-weight:600; font-size:15px; margin-left:6px;">
                                                    0
                                                </span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="listing_status" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Listing Status
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div style="display: flex; gap: 5px;">
                                                <div class="metric-total" id="active-status-total"
                                                    style="display:inline-block; background:#28a745; color:white; border-radius:8px; padding:4px 8px; font-weight:600; font-size:13px;">
                                                    0</div>
                                                <div class="metric-total" id="inactive-status-total"
                                                    style="display:inline-block; background:#dc3545; color:white; border-radius:8px; padding:4px 8px; font-weight:600; font-size:13px;">
                                                    0</div>
                                                <div class="metric-total" id="missing-status-total"
                                                    style="display:inline-block; background:#ffc107; color:#000; border-radius:8px; padding:4px 8px; font-weight:600; font-size:13px;">
                                                    0</div>
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

                    <!-- Pagination controls -->
                    <div class="pagination-controls mt-2">
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
                            <div class="loader-text">Loading Listing data...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="linkModal" class="modal fade" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Buyer and Seller Links</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="linkForm">
                        <div class="mb-3">
                            <label for="buyerLink" class="form-label">Buyer Link</label>
                            <input type="url" id="buyerLink" name="buyerLink" class="form-control"
                                placeholder="Enter Buyer Link" required>
                        </div>
                        <div class="mb-3">
                            <label for="sellerLink" class="form-label">Seller Link</label>
                            <input type="url" id="sellerLink" name="sellerLink" class="form-control"
                                placeholder="Enter Seller Link" required>
                        </div>
                        <input type="hidden" id="skuInput" name="sku">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="submitLinks">Submit</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.body.style.zoom = "80%";
        $(document).ready(function() {
                    // Cache system
                    const eBayListingDataCache = {
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
                        eBayListingDataCache.clear();
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
                    let currentDataTypeFilter = 'sku'; // Track data type filter (all, sku, parent)
                    let currentInvFilter = 'all'; // Track INV filter (all, inv-only)

                    let filterStates = {
                        inv: $('#inv-filter').val(),
                        nr_req: $('#nr-req-filter').val(),
                        link: $('#link-filter').val(),
                        listed: $('#listed-filter').val(),
                        rowType: $('#row-data-type').val()
                    };

                    function applyAllFilters() {
                        // Start with all data
                        filteredData = [...tableData];

                        // Apply Data Type filter (Parent/SKU/All)
                        if (currentDataTypeFilter === 'parent') {
                            filteredData = filteredData.filter(item => item.is_parent);
                        } else if (currentDataTypeFilter === 'sku') {
                            // For SKU: show only non-parent rows with INACTIVE or MISSING status
                            filteredData = filteredData.filter(item => 
                                !item.is_parent && 
                                (item.listing_status === 'INACTIVE' || !item.listing_status)
                            );
                        }
                        // else 'all' - no data type filtering

                        // Apply INV filter on top of data type filter
                        if (currentInvFilter === 'inv-only') {
                            filteredData = filteredData.filter(item => parseFloat(item.INV) > 0);
                        }
                        // else 'all' - no INV filtering

                        // Reset to first page and render
                        currentPage = 1;
                        renderTable();
                        calculateTotals();
                    }

                    // --- Dropdown Click Handler ---
                    $('.manual-dropdown-container .column-filter').on('click', function() {
                        const $dropdown = $(this).closest('.manual-dropdown-container').find('button');
                        const column = $dropdown.attr('data-column');
                        const value = $(this).text().trim();

                        if (column) {
                            // Update the filter state
                            columnFilters[column] = value;

                            // Update the dropdown button text
                            $dropdown.find('.filter-selection').text(value);

                            // Apply the filters to the table
                            applyColumnFilters();
                        }
                    });

                    // --- Filtering Logic ---
                    function applyColumnFilters() {
                        filteredData = tableData.filter(item => {
                            let pass = true;
                            for (const [col, filter] of Object.entries(columnFilters)) {
                                if (filter === 'ALL') continue;
                                if (filter === 'DONE' && !(item[col] === true || item[col] === 'true' || item[
                                        col] === 1)) pass = false;
                                if (filter === 'PENDING' && (item[col] === true || item[col] === 'true' || item[
                                        col] === 1)) pass = false;
                            }
                            return pass;
                        });
                        renderTable();
                        calculateTotals();
                    }

                    // Initialize everything
                    function initTable() {
                        loadData().then(() => {
                            renderTable();
                            initResizableColumns();
                            initSorting();
                            initPagination();
                            initSearch();
                            calculateTotals();
                            initEnhancedDropdowns();

                            // Set default INV filter to "INV Only" on page load
                            // $('#inv-filter').val('inv-only').trigger('change');
                        });
                    }

                    // Load data from server
                    function loadData() {
                        showLoader();
                        return $.ajax({
                            url: '/listing_ebay/view-data',
                            type: 'GET',
                            dataType: 'json',
                            success: function(response) {
                                // If response is an object with a data property, use that
                                if (Array.isArray(response)) {
                                    tableData = response;
                                } else if (Array.isArray(response.data)) {
                                    tableData = response.data;
                                } else {
                                    tableData = [];
                                }

                                // Use nr_req and listed values from ebay_data_view table
                                tableData = tableData.map(item => {
                                    const inv = parseFloat(item.INV) || 0;
                                    const isParent = item.sku && item.sku.toUpperCase().includes('PARENT');
                                    
                                    // Default logic: if INV > 0 and not parent, default to REQ (RL), otherwise NRL
                                    let defaultNrReq = 'NRL';
                                    if (inv > 0 && !isParent) {
                                        defaultNrReq = 'REQ';
                                    }
                                    
                                    return {
                                        ...item,
                                        nr_req: item.nr_req || defaultNrReq,
                                        listed: item.listed || 'Pending',
                                        is_parent: isParent
                                    };
                                });

                                // Set default to show all data initially
                                filteredData = [...tableData];
                            },
                            error: function(xhr, status, error) {
                                console.error('Error loading data:', error);
                                showNotification('danger', 'Failed to load data. Please try again.');
                                tableData = [];
                                filteredData = [];
                            },
                            complete: function() {
                                hideLoader();
                            }
                        });
                    }

                    // Render table with current data
                    function renderTable() {
                        const $tbody = $('#listing-table tbody');
                        $tbody.empty();

                        if (isLoading) {
                            $tbody.append('<tr><td colspan="7" class="text-center">Loading data...</td></tr>');
                            return;
                        }

                        // Include all rows without filtering by INV
                        const filteredRows = filteredData;

                        // Group data by parent
                        const groupedData = {};
                        filteredRows.forEach(item => {
                            if (!groupedData[item.parent]) {
                                groupedData[item.parent] = [];
                            }
                            groupedData[item.parent].push(item);
                        });

                        // Sort parents alphabetically
                        const sortedParents = Object.keys(groupedData).sort();

                        let rowIndex = 1;

                        // Iterate through each parent group
                        sortedParents.forEach(parent => {
                            const items = groupedData[parent];

                            // Sort items within the group so that the PARENT row appears last
                            const sortedItems = items.sort((a, b) => {
                                if (a.sku.includes('PARENT')) return 1; // Move PARENT to the end
                                if (b.sku.includes('PARENT')) return -1; // Move PARENT to the end
                                return 0; // Keep other rows in their original order
                            });

                            // Add all rows to the table
                            sortedItems.forEach(item => {
                                const $row = createTableRow(item, rowIndex++);
                                $tbody.append($row);
                            });
                        });

                        if ($tbody.children().length === 0) {
                            $tbody.append('<tr><td colspan="7" class="text-center">No matching records found</td></tr>');
                        }

                        updatePaginationInfo();
                        
                        // Show filter description with row count
                        const filterValue = $('#combined-filter').val();
                        let filterDesc = '';
                        switch(filterValue) {
                            case 'all':
                                filterDesc = 'All SKUs';
                                break;
                            case 'inv':
                                filterDesc = 'INV (All SKUs with Inventory)';
                                break;
                            case 'req-nrl':
                                filterDesc = 'RL-NRL (RL without Links)';
                                break;
                            case 'pending':
                                filterDesc = 'Pending (Listed=Pending)';
                                break;
                            default:
                                filterDesc = 'Filtered';
                        }
                        $('#visible-rows').text(`${filterDesc}: ${$tbody.children().length} rows`);
                    }


                    //open modal on click import button
                    $('#import-btn').on('click', function () {
                        $('#importModal').modal('show');
                    });


                    //import data
                    $(document).on('click', '#confirmImportBtn', function () {
                        let file = $('#importFile')[0].files[0];
                        if (!file) {
                            alert('Please select a file to import.');
                            return;
                        }

                        let formData = new FormData();
                        formData.append('file', file);

                        $.ajax({
                            url: "{{ route('listing_ebay.import') }}",
                            type: "POST",
                            data: formData,
                            processData: false,
                            contentType: false,
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            success: function (response) {
                                $('#importModal').modal('hide');
                                $('#importFile').val('');
                                showNotification('success', response.success);
                                location.reload(); // refresh your DataTable
                            },
                            error: function (xhr) {
                                showNotification('danger', xhr.responseJSON.error || 'Import failed');
                            }
                        });
                    });


                    // Helper function to create a table row
                    function createTableRow(item, index) {
                        const $row = $('<tr>');

                        // Add a blue background color if the SKU contains "PARENT"
                        if (item.sku.includes('PARENT')) {
                            $row.addClass('parent-row');
                        }

                        $row.append($('<td>').text(item.parent)); // Parent
                        $row.append($('<td>').text(item.sku)); // SKU
                        $row.append($('<td>').css('text-align', 'center').text(item.INV)); // INV

                        // NR/REQ dropdown only for non-parent rows
                        if (!item.sku.includes('PARENT')) {
                            const currentNR = item.nr_req ? item.nr_req : "REQ";
                            
                            // Determine colors based on current value
                            const bgColor = currentNR === 'NRL' ? '#dc3545' : '#28a745';
                            const textColor = '#ffffff';

                            const $dropdown = $(`
                                <select class="form-select form-select-sm nr-select" data-value="${currentNR}" style="min-width: 100px; background-color: ${bgColor} !important; color: ${textColor} !important;">
                                    <option value="NRL" style="background-color: #dc3545; color: #ffffff;" ${currentNR === 'NRL' ? 'selected' : ''}>NRL</option>
                                    <option value="REQ" style="background-color: #28a745; color: #ffffff;" ${currentNR === 'REQ' ? 'selected' : ''}>RL</option>
                                </select>
                            `);

                            $row.append($('<td>').append($dropdown));
                        } else {
                            $row.append($('<td>').text('')); // Empty cell for parent rows
                        }

                        // --- BUYER LINK, SELLER LINK, AND PEN ICON IN ONE TD ---
                        const $linkCell = $('<td>');

                        // Buyer Link
                        if (parseFloat(item.INV) > 0 && item.buyer_link) {
                            $linkCell.append(
                                `<a href="${item.buyer_link}" target="_blank" style="color:#007bff;text-decoration:underline;margin-right:8px;">Buyer</a>`
                            );
                        }

                        // Seller Link
                        if (parseFloat(item.INV) > 0 && item.seller_link) {
                            $linkCell.append(
                                `<a href="${item.seller_link}" target="_blank" style="color:#007bff;text-decoration:underline;margin-right:8px;">Seller</a>`
                            );
                        }

                        // Pen icon (always show for non-parent rows)
                        if (!item.sku.includes('PARENT')) {
                            $linkCell.append(
                                $('<i>')
                                .addClass('fas fa-pen text-primary link-edit-icon')
                                .css({
                                    cursor: 'pointer',
                                    marginLeft: '6px'
                                })
                                .attr('title', 'Edit Links')
                                .data('sku', item.sku)
                            );
                        }

                        $row.append($linkCell);

                        // Listed/Pending dropdown only for non-parent rows
                        if (!item.sku.includes('PARENT')) {
                            const $listedDropdown = $('<select>')
                                .addClass('listed-dropdown form-control form-control-sm')
                                .append('<option value="Listed" class="listed-option">Listed</option>')
                                .append('<option value="Pending" class="pending-option">Pending</option>')
                                .append('<option value="NRL" class="nrl-option">NRL</option>');

                            // If nr_req is 'NRL', automatically set listed to 'NRL'
                            const listedValue = (item.nr_req === 'NRL') ? 'NRL' : (item.listed || 'Pending');
                            $listedDropdown.val(listedValue);

                            if (listedValue === 'Listed') {
                                $listedDropdown.css('background-color', '#28a745').css('color', 'white');
                            } else if (listedValue === 'Pending') {
                                $listedDropdown.css('background-color', '#dc3545').css('color', 'white');
                            } else if (listedValue === 'NRL') {
                                $listedDropdown.css('background-color', '#6c757d').css('color', 'white');
                            }

                            $row.append($('<td>').append($listedDropdown));
                        } else {
                            $row.append($('<td>').text('')); // Empty cell for parent rows
                        }

                        // Listing Status column
                        const $statusCell = $('<td>').css('text-align', 'center');
                        if (item.listing_status) {
                            let statusBadge = '';
                            if (item.listing_status === 'ACTIVE') {
                                statusBadge = '<span style="background:#28a745; color:white; padding:4px 12px; border-radius:8px; font-weight:600; font-size:13px;">ACTIVE</span>';
                            } else if (item.listing_status === 'INACTIVE') {
                                statusBadge = '<span style="background:#dc3545; color:white; padding:4px 12px; border-radius:8px; font-weight:600; font-size:13px;">INACTIVE</span>';
                            } else {
                                statusBadge = '<span style="background:#6c757d; color:white; padding:4px 12px; border-radius:8px; font-weight:600; font-size:13px;">' + item.listing_status + '</span>';
                            }
                            $statusCell.html(statusBadge);
                        } else {
                            $statusCell.html('<span style="background:#ffc107; color:#000; padding:4px 12px; border-radius:8px; font-weight:600; font-size:13px;">MISSING</span>');
                        }
                        $row.append($statusCell);

                        return $row;
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
                        const $table = $('#listing-table');
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

                        $(document).on('mousemove', function(e) {
                            if (!isResizing) return;

                            const $resizer = $('.resize-handle.resizing');
                            if ($resizer.length) {
                                const $th = $resizer.parent();
                                const newWidth = startWidth + (e.pageX - startX);
                                $th.css('width', newWidth + 'px');
                                $th.css('min-width', newWidth + 'px');
                                $th.css('max-width', newWidth + 'px');
                            }
                        });

                        $(document).on('mouseup', function(e) {
                            if (!isResizing) return;

                            e.stopPropagation();
                            $('.resize-handle').removeClass('resizing');
                            $('body').css('user-select', '');
                            isResizing = false;
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
                                if (dataField === 'sl_no' || dataField === 'INV' || dataField ===
                                    'L30') {
                                    return (parseFloat(valA) - parseFloat(valB)) * currentSort
                                        .direction;
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
                                        if (typeof val === 'boolean' || val === null)
                                            return false;
                                        return val.toString().toLowerCase().includes(
                                            searchTerm);
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

                    // Calculate and display totals
                    function calculateTotals() {
                        try {
                            if (isLoading || tableData.length === 0) {
                                resetMetricsToZero();
                                return;
                            }

                            const metrics = {
                                totalSku: 0,
                                batchTotal: 0,
                                invTotal: 0,
                                reqTotal: 0,
                                rlTotal: 0,
                                nrlTotal: 0,
                                withoutLinkTotal: 0,
                                listedTotal: 0,
                                pendingTotal: 0,
                                activeStatusTotal: 0,
                                inactiveStatusTotal: 0,
                                missingStatusTotal: 0,
                                rowCount: 0
                            };

                            // Use tableData instead of filteredData to show totals for all data
                            tableData.forEach(item => {
                                // Count only non-parent rows for SKU total
                                if (!item.sku.includes('PARENT')) {
                                    metrics.totalSku++;
                                    
                                    // Count INV total only for rows with INV > 0
                                    if (parseFloat(item.INV) > 0) {
                                        metrics.invTotal += parseFloat(item.INV) || 0;
                                    }
                                    
                                    // For RL/NRL: Count based on nr_req field
                                    // nr_req = 'REQ' means RL (green)
                                    // nr_req = 'NRL' means NRL (red)
                                    if (item.nr_req === 'NRL') {
                                        metrics.nrlTotal++;
                                    } else {
                                        // Default to RL if nr_req is 'REQ' or any other value
                                        metrics.rlTotal++;
                                    }
                                    
                                    // Count missing links for all non-parent rows
                                    if (!item.buyer_link && !item.seller_link) {
                                        metrics.withoutLinkTotal++;
                                    }
                                    
                                    // Count Listed and Pending for ALL non-parent SKUs
                                    const listedValue = item.listed || 'Pending';
                                    if (listedValue === 'Listed') {
                                        metrics.listedTotal++;
                                    } else if (listedValue === 'Pending') {
                                        metrics.pendingTotal++;
                                    } else if (listedValue === 'NRL') {
                                        // Don't count NRL in listed/pending totals
                                    }
                                    
                                    // Count listing status
                                    if (item.listing_status === 'ACTIVE') {
                                        metrics.activeStatusTotal++;
                                    } else if (item.listing_status === 'INACTIVE') {
                                        metrics.inactiveStatusTotal++;
                                    } else if (!item.listing_status) {
                                        metrics.missingStatusTotal++;
                                    }
                                } else {
                                    // Count parent rows for Batch total
                                    metrics.batchTotal++;
                                }
                            });

                            $('#total-sku').text(metrics.totalSku);
                            $('#batch-total').text(metrics.batchTotal);
                            $('#inv-total').text(metrics.invTotal.toLocaleString());
                            $('#req-total').text(metrics.reqTotal);
                            $('#rl-total').text(metrics.rlTotal);
                            $('#nrl-total').text(metrics.nrlTotal);
                            $('#without-link-total').text(metrics.withoutLinkTotal);
                            $('#listed-total').text(metrics.listedTotal); // Green
                            $('#pending-total').text(metrics.pendingTotal); // Red
                            $('#active-status-total').text(metrics.activeStatusTotal); // Green
                            $('#inactive-status-total').text(metrics.inactiveStatusTotal); // Red
                            $('#missing-status-total').text(metrics.missingStatusTotal); // Yellow
                        } catch (error) {
                            console.error('Error in calculateTotals:', error);
                            resetMetricsToZero();
                        }
                    }

                    function resetMetricsToZero() {
                        $('#total-sku').text('0');
                        $('#batch-total').text('0');
                        $('#inv-total').text('0');
                        $('#rl-total').text('0');
                        $('#nrl-total').text('0');
                        $('#without-link-total').text('0');
                        $('#listed-total').text('0');
                        $('#pending-total').text('0');
                        $('#active-status-total').text('0');
                        $('#inactive-status-total').text('0');
                        $('#missing-status-total').text('0');
                    }

                    // Initialize enhanced dropdowns
                    function initEnhancedDropdowns() {
                        // Parent dropdown
                        const $parentSearch = $('#parentSearch');
                        const $parentResults = $('#parentSearchResults');

                        // SKU dropdown
                        const $skuSearch = $('#skuSearch');
                        const $skuResults = $('#skuSearchResults');

                        // Initialize both dropdowns
                        initEnhancedDropdown($parentSearch, $parentResults, 'parent');
                        initEnhancedDropdown($skuSearch, $skuResults, 'sku');

                        // Close dropdowns when clicking outside
                        $(document).on('click', function(e) {
                            if (!$(e.target).closest('.dropdown-search-container').length) {
                                $('.dropdown-search-results').hide();
                            }
                        });

                        $('#row-data-type').on('change', function() {
                            currentDataTypeFilter = $(this).val();
                            applyAllFilters();
                        });
                    }

                    // Calculate INV and L30 totals for each parent
                    function getParentTotals(parentName) {
                        let invTotal = 0;
                        let l30Total = 0;
                        filteredData.forEach(item => {
                            if (
                                item.parent === parentName &&
                                !item.is_parent // Only sum child rows
                            ) {
                                invTotal += parseFloat(item.INV) || 0;
                                l30Total += parseFloat(item.L30) || 0;
                            }
                        });
                        return {
                            inv: invTotal,
                            l30: l30Total
                        };
                    }

                    function initEnhancedDropdown($input, $results, field) {
                        let debounceTimer;

                        $input.on('input', function() {
                            clearTimeout(debounceTimer);
                            const searchTerm = $(this).val().toLowerCase();

                            debounceTimer = setTimeout(() => {
                                if (searchTerm.length === 0) {
                                    $results.hide();
                                    return;
                                }

                                updateDropdownResults($results, field, searchTerm);
                            }, 300);
                        });

                        $input.on('focus', function() {
                            const searchTerm = $(this).val().toLowerCase();
                            if (searchTerm.length > 0) {
                                updateDropdownResults($results, field, searchTerm);
                            }
                        });

                        $(document).on('click', function(e) {
                            if (!$input.is(e.target) && !$results.is(e.target) && $results.has(e.target).length === 0) {
                                $results.hide();
                            }
                        });

                        $results.on('click', '.dropdown-search-item', function() {
                            const value = $(this).text();
                            $input.val(value);
                            $results.hide();

                            // Filter data
                            if (value) {
                                filteredData = tableData.filter(item =>
                                    item[field] && item[field].toString().toLowerCase().includes(value.toLowerCase())
                                );
                            } else {
                                filteredData = [...tableData];
                            }

                            currentPage = 1;
                            renderTable();
                            calculateTotals();
                        });
                    }

                    function updateDropdownResults($results, field, searchTerm) {
                        const uniqueValues = [...new Set(
                            tableData
                                .map(item => item[field])
                                .filter(val => val && val.toString().toLowerCase().includes(searchTerm))
                        )].sort();

                        $results.empty();

                        if (uniqueValues.length === 0) {
                            $results.append('<div class="dropdown-search-item no-results">No results found</div>');
                        } else {
                            uniqueValues.forEach(value => {
                                const $item = $('<div>')
                                    .addClass('dropdown-search-item')
                                    .text(value);
                                $results.append($item);
                            });
                        }

                        $results.show();
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

                        // Initialize everything
                        initTable();

                        // Handle combined filter change
                        $('#combined-filter').on('change', function() {
                            const selectedValue = $(this).val();

                            if (selectedValue === 'all') {
                                // Show all rows
                                filteredData = [...tableData];
                            } else if (selectedValue === 'inv') {
                                // Show all rows with INV > 0
                                filteredData = tableData.filter(item => parseFloat(item.INV) > 0);
                            } else if (selectedValue === 'req-nrl') {
                                // Show all REQ rows without buyer_link AND seller_link
                                filteredData = tableData.filter(item => 
                                    item.nr_req === 'REQ' && !item.buyer_link && !item.seller_link
                                );
                            } else if (selectedValue === 'pending') {
                                // Show all rows with Listed = Pending
                                filteredData = tableData.filter(item => item.listed === 'Pending');
                            }

                            currentPage = 1;
                            renderTable();
                            calculateTotals();
                        });

                        // Handle INV filter change
                        $('#inv-filter').on('change', function() {
                            currentInvFilter = $(this).val();
                            applyAllFilters();
                        });

                        // Save Listed/Pending when dropdown changes (nr_req is handled separately)
                        $(document).on('change', '.listed-dropdown', function() {
                            const $row = $(this).closest('tr');
                            const sku = $row.find('td').eq(1).text().trim();
                            const listed = $(this).val();
                            
                            // Update color based on selection
                            if (listed === 'Listed') {
                                $(this).css('background-color', '#28a745').css('color', 'white');
                            } else if (listed === 'Pending') {
                                $(this).css('background-color', '#dc3545').css('color', 'white');
                            } else if (listed === 'NRL') {
                                $(this).css('background-color', '#6c757d').css('color', 'white');
                            }

                            saveStatusToDB(sku, '', listed, '', '');
                        });

                        // Handle nr_req dropdown color change
                        $(document).on('change', '.nr-select', function() {
                            const $row = $(this).closest('tr');
                            const sku = $row.find('td').eq(1).text().trim();
                            const nr_req = $(this).val();
                            const $listedDropdown = $row.find('.listed-dropdown');
                            const bgColor = nr_req === 'NRL' ? '#dc3545' : '#28a745';
                            const textColor = '#ffffff';

                            // Update select styling
                            $(this).attr('data-value', nr_req);
                            $(this).css('background-color', bgColor).css('color', textColor);

                            if (nr_req === 'NRL') {
                                // Automatically set listed to NRL when NRL is selected
                                $listedDropdown.val('NRL');
                                $listedDropdown.css('background-color', '#6c757d').css('color', 'white');
                                
                                // Update both nr_req and listed in the database
                                saveStatusToDB(sku, nr_req, 'NRL', '', '');
                                return; // Exit early since we're saving both values
                            }

                            // Save only nr_req when REQ is selected
                            saveStatusToDB(sku, nr_req, '', '', '');
                        });


                        // Save links when submitting the modal
                        $('#submitLinks').on('click', function(e) {
                            e.preventDefault();

                            const sku = $('#skuInput').val();
                            const buyer_link = $('#buyerLink').val();
                            const seller_link = $('#sellerLink').val();
                            const nr_req = 'REQ'; // Default or get from row
                            const listed = 'Pending'; // Default or get from row

                            saveStatusToDB(sku, nr_req, listed, buyer_link, seller_link);

                            $('#linkModal').modal('hide');
                        });

                        // Handle NR/REQ filter
                        $('#nr-req-filter').on('change', function() {
                            const selectedValue = $(this).val();

                            if (selectedValue === 'all') {
                                // Show all rows
                                filteredData = [...tableData];
                            } else {
                                // Filter rows based on NR/REQ value
                                filteredData = tableData.filter(item => item.nr_req === selectedValue);
                            }

                            currentPage = 1;
                            renderTable();
                            calculateTotals();
                        });

                        // Handle link edit icon click
                        $(document).on('click', '.link-edit-icon', function() {
                            const sku = $(this).data('sku');

                            // Find the item in tableData by SKU
                            const item = tableData.find(row => row.sku === sku);

                            // Set the values in the modal inputs
                            $('#skuInput').val(sku);
                            $('#buyerLink').val(item && item.buyer_link ? item.buyer_link : '');
                            $('#sellerLink').val(item && item.seller_link ? item.seller_link : '');

                            $('#linkModal').modal('show');
                        });

                        // Handle LINK filter
                        $('#link-filter').on('change', function() {
                            const selectedValue = $(this).val();

                            if (selectedValue === 'all') {
                                // Show all rows
                                filteredData = [...tableData];
                            } else if (selectedValue === 'with-link') {
                                // Filter rows with buyer or seller links
                                filteredData = tableData.filter(item => item.buyer_link || item.seller_link);
                            } else if (selectedValue === 'without-link') {
                                // Filter rows without buyer or seller links
                                filteredData = tableData.filter(item => !item.buyer_link && !item.seller_link);
                            }

                            currentPage = 1;
                            renderTable();
                            calculateTotals();
                        });

                        // Handle Listed filter
                        $('#listed-filter').on('change', function() {
                            const selectedValue = $(this).val();

                            if (selectedValue === 'all') {
                                filteredData = [...tableData];
                            } else {
                                filteredData = tableData.filter(item => item.listed === selectedValue);
                            }

                            currentPage = 1;
                            renderTable();
                            calculateTotals();
                        });

                        // AJAX function to save to DB
                        function saveStatusToDB(sku, nr_req, listed, buyer_link, seller_link) {
                            // Build data object with only non-empty values
                            const data = {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: sku
                            };
                            
                            if (nr_req) data.nr_req = nr_req;
                            if (listed) data.listed = listed;
                            if (buyer_link) data.buyer_link = buyer_link;
                            if (seller_link) data.seller_link = seller_link;
                            
                            $.ajax({
                                url: '/listing_ebay/update-status',
                                type: 'POST',
                                data: data,
                                success: function(response) {
                                    showNotification('success', response.message || 'Status updated successfully');

                                    // Update the tableData array with only provided values
                                    const itemIndex = tableData.findIndex(item => item.sku === sku);
                                    if (itemIndex !== -1) {
                                        if (nr_req) tableData[itemIndex].nr_req = nr_req;
                                        if (listed) tableData[itemIndex].listed = listed;
                                        if (buyer_link) tableData[itemIndex].buyer_link = buyer_link;
                                        if (seller_link) tableData[itemIndex].seller_link = seller_link;
                                    }

                                    // Re-render the table
                                    renderTable();
                                    calculateTotals();
                                },
                                error: function(xhr, status, error) {
                                    console.error('Error saving status:', error);
                                    showNotification('danger', 'Failed to save status. Please try again.');
                                }
                            });
                        }

                        $('#listed-filter').on('change', function() {
                            filterStates.listed = $(this).val();
                            applyAllFilters();
                        });
                    });
    </script>
@endsection
