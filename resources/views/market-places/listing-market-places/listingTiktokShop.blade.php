@extends('layouts.vertical', ['title' => 'Listing Tiktok Shop', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
            /* Green */
            color: white;
        }

        .nr-req-dropdown .nr-option {
            background-color: #dc3545;
            /* Red */
            color: white;
        }

        .nr-req-dropdown option {
            padding: 4px 8px;
            font-weight: bold;
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
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Listing Tiktok Shop', 'sub_title' => 'Tiktok Shop'])
    
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
                                    <option value="sku">SKU (Child)</option>
                                    <option value="parent">Parent</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="inv-filter" class="mr-2">INV:</label>
                                <select id="inv-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="inv-only">INV Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="nr-req-filter" class="mr-2">NRL/REQ:</label>
                                <select id="nr-req-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="REQ">REQ</option>
                                    <option value="NR">NRL</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="link-filter" class="mr-2">LINK:</label>
                                <select id="link-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="with-link">With Link</option>
                                    <option value="without-link">Without Link</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6">
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
                            <a href="{{ route('listing_tiktokshop.export') }}" class="btn btn-sm btn-success mr-3">Export</a>

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

                                <a href="{{ asset('sample_excel/sample_listing_file.csv') }}" download class="btn btn-outline-secondary mb-3">📄 Download Sample File</a>

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
                        <table class="custom-resizable-table" id="titokshopListing-table">
                            <thead>
                                <tr>
                                    <th data-field="sl_no">SL No. <span class="sort-arrow">↓</span></th>
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
                                        <div class="d-flex flex-column align-items-center sortable">
                                            <div class="d-flex align-items-center">
                                                Sku <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="mt-1 dropdown-search-container">
                                                <input type="text" class="form-control form-control-sm sku-search"
                                                    placeholder="Search SKU..." id="skuSearch">
                                                <div class="dropdown-search-results" id="skuSearchResults"></div>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="inv" style="vertical-align: middle; white-space: nowrap;">
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
                                                NRL/REQ
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="req-total"
                                                style="display:inline-block; background:#43dc35; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px;">
                                                0</div>
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
            const titokshopListingDataCache = {
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
                titokshopListingDataCache.clear();
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
                    $('#inv-filter').val('inv-only').trigger('change');
                });
            }

            // Load data from server
            function loadData() {
                showLoader();
                return $.ajax({
                    url: '/listing_tiktokshop/view-data',
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

                        // Set default value for nr_req if missing and INV > 0
                        tableData = tableData.map(item => ({
                            ...item,
                            nr_req: item.nr_req || (parseFloat(item.INV) > 0 ? 'REQ' :
                                'NR'),
                            listed: item.listed || (parseFloat(item.INV) > 0 ? 'Pending' :
                                'Listed')
                        }));

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
                const $tbody = $('#titokshopListing-table tbody');
                $tbody.empty();

                if (isLoading) {
                    $tbody.append('<tr><td colspan="5" class="text-center">Loading data...</td></tr>');
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
                    $tbody.append('<tr><td colspan="5" class="text-center">No matching records found</td></tr>');
                }

                updatePaginationInfo();
                $('#visible-rows').text(`Showing all ${$tbody.children().length} rows`);
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
                    url: "{{ route('listing_tiktokshop.import') }}",
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

                $row.append($('<td>').text(index)); // SL No.
                $row.append($('<td>').text(item.parent)); // Parent
                $row.append($('<td>').text(item.sku)); // SKU
                $row.append($('<td>').text(item.INV)); // INV

                // NR/REQ dropdown only for non-parent rows
                if (!item.sku.includes('PARENT')) {
                    const $dropdown = $('<select>')
                        .addClass('nr-req-dropdown form-control form-control-sm')
                        .append('<option value="REQ" class="req-option">REQ</option>')
                        .append('<option value="NR" class="nr-option">NRL</option>');

                    const initialValue = item.nr_req || 'REQ';
                    $dropdown.val(initialValue);

                    if (initialValue === 'REQ') {
                        $dropdown.css('background-color', '#28a745').css('color', 'white');
                    } else if (initialValue === 'NR') {
                        $dropdown.css('background-color', '#dc3545').css('color', 'white');
                    }

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

                // Pen icon (always show for non-parent rows, or adjust as needed)
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
                        .append('<option value="Pending" class="pending-option">Pending</option>');

                    const listedValue = item.listed || 'Pending';
                    $listedDropdown.val(listedValue);

                    if (listedValue === 'Listed') {
                        $listedDropdown.css('background-color', '#28a745').css('color', 'white');
                    } else if (listedValue === 'Pending') {
                        $listedDropdown.css('background-color', '#dc3545').css('color', 'white');
                    }

                    $row.append($('<td>').append($listedDropdown));
                } else {
                    $row.append($('<td>').text('')); // Empty cell for parent rows
                }

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
                const $table = $('#titokshopListing-table');
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
                    if (isLoading || filteredData.length === 0) {
                        resetMetricsToZero();
                        return;
                    }

                    const metrics = {
                        invTotal: 0,
                        reqTotal: 0,
                        withoutLinkTotal: 0,
                        listedTotal: 0, // Green
                        pendingTotal: 0, // Red
                        rowCount: 0
                    };

                    filteredData.forEach(item => {
                        if (parseFloat(item.INV) > 0 && !item.sku.includes('PARENT')) {
                            metrics.invTotal += parseFloat(item.INV) || 0;

                            if (item.nr_req === 'REQ') {
                                metrics.reqTotal++;
                            }
                            if (!item.buyer_link && !item.seller_link) {
                                metrics.withoutLinkTotal++;
                            }
                            // Count Listed and Pending rows
                            if (item.listed === 'Listed') {
                                metrics.listedTotal++;
                            }
                            if (item.listed === 'Pending' || !item.listed) {
                                metrics.pendingTotal++;
                            }
                        }
                    });

                    $('#inv-total').text(metrics.invTotal.toLocaleString());
                    $('#req-total').text(metrics.reqTotal);
                    $('#without-link-total').text(metrics.withoutLinkTotal);
                    $('#listed-total').text(metrics.listedTotal); // Green
                    $('#pending-total').text(metrics.pendingTotal); // Red
                } catch (error) {
                    console.error('Error in calculateTotals:', error);
                    resetMetricsToZero();
                }
            }

            function resetMetricsToZero() {
                $('#inv-total').text('0');
                $('#req-total').text('0');
                $('#without-link-total').text('0');
                $('#listed-total').text('0');
                $('#pending-total').text('0');
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
                initEnhancedDropdown($skuSearch, $skuResults, 'sku');

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

                $('#row-data-type').on('change', function() {
                    const filterType = $(this).val();
                    applyRowTypeFilter(filterType);
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
                    invTotal,
                    l30Total
                };
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

            // Handle INV filter change
            $('#inv-filter').on('change', function() {
                const selectedValue = $(this).val();

                if (selectedValue === 'all') {
                    // Show all rows, including rows with INV <= 0
                    filteredData = [...tableData];
                } else if (selectedValue === 'inv-only') {
                    // Show rows with INV > 0, but always include rows with "PARENT" in the SKU
                    filteredData = tableData.filter(item => {
                        return item.sku.includes('PARENT') || parseFloat(item.INV) > 0;
                    });
                }

                currentPage = 1; // Reset to the first page
                renderTable(); // Re-render the table
                calculateTotals(); // Recalculate totals
            });

            // Save NR/REQ or Listed/Pending when dropdown changes
            $(document).on('change', '.nr-req-dropdown, .listed-dropdown', function() {
                const $row = $(this).closest('tr');
                const sku = $row.find('td').eq(2).text().trim(); // Adjust index if needed
                const nr_req = $row.find('.nr-req-dropdown').val() || 'REQ';
                const listed = $row.find('.listed-dropdown').val() || 'Pending';

                // Optionally, get current links if you want to save them too
                const buyer_link = $row.data('buyer-link') || '';
                const seller_link = $row.data('seller-link') || '';

                saveStatusToDB(sku, nr_req, listed, buyer_link, seller_link);
            });

            // Save links when submitting the modal
            $('#submitLinks').on('click', function(e) {
                e.preventDefault();
                const sku = $('#skuInput').val();
                const buyer_link = $('#buyerLink').val();
                const seller_link = $('#sellerLink').val();

                // Only send the fields that have changed (example: always send both links)
                saveStatusToDB(sku, {
                    buyer_link,
                    seller_link
                });

                $('#linkModal').modal('hide');
            });

            // AJAX function to save to DB
            function saveStatusToDB(sku, data) {
                $.ajax({
                    url: '/listing_tiktokshop/save-status',
                    type: 'POST',
                    data: {
                        sku: sku,
                        ...data,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        showNotification('success', 'Saved!');
                        // Update the local tableData with new values
                        const item = tableData.find(row => row.sku === sku);
                        if (item) {
                            Object.assign(item, data);
                        }
                        calculateTotals(); // Recalculate totals after update
                        renderTable();     // Optionally re-render table if needed
                    },
                    error: function(xhr) {
                        showNotification('danger', 'Save failed!');
                    }
                });
            }

            $(document).on('change', '.nr-req-dropdown', function() {
                const $row = $(this).closest('tr');
                const sku = $row.find('td').eq(2).text().trim();
                const nr_req = $(this).val();

                saveStatusToDB(sku, {
                    nr_req
                });
            });

            $(document).on('change', '.listed-dropdown', function() {
                const $row = $(this).closest('tr');
                const sku = $row.find('td').eq(2).text().trim();
                const listed = $(this).val();

                saveStatusToDB(sku, {
                    listed
                });
            });

            $('#nr-req-filter').on('change', function() {
                const selectedValue = $(this).val();

                if (selectedValue === 'all') {
                    // Show all rows
                    filteredData = [...tableData];
                } else {
                    // Filter rows based on NR/REQ value
                    filteredData = tableData.filter(item => item.nr_req === selectedValue);
                }

                currentPage = 1; // Reset to the first page
                renderTable(); // Re-render the table
                calculateTotals(); // Recalculate totals
            });

            $(document).on('click', '.link-edit-icon', function() {
                const sku = $(this).data('sku'); // Get SKU from the clicked pen icon

                // Find the item in tableData by SKU
                const item = tableData.find(row => row.sku === sku);

                // Set the values in the modal inputs
                $('#skuInput').val(sku);
                $('#buyerLink').val(item && item.buyer_link ? item.buyer_link : '');
                $('#sellerLink').val(item && item.seller_link ? item.seller_link : '');

                $('#linkModal').modal('show'); // Open the modal
            });

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

                currentPage = 1; // Reset to the first page
                renderTable(); // Re-render the table
                calculateTotals(); // Recalculate totals
            });

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
        });
    </script>
@endsection
