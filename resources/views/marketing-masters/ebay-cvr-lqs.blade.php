@extends('layouts.vertical', ['title' => 'Ebay Cvr LQS Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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
            transform: translateX(-50%);
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

        /*popup modal style end */
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Ebay Cvr LQS Master',
        'sub_title' => 'Product Master',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- <h4 class="header-title">Cvr LQS Master</h4> -->

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

                        <!-- PFT % Filter -->
                        <div class="dropdown manual-dropdown-container">
                            <button class="btn btn-light dropdown-toggle" type="button" id="pftFilterDropdown">
                                <span class="status-circle default"></span> PFT%
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="pftFilterDropdown">
                                <li><a class="dropdown-item column-filter" href="#" data-column="PFT_percentage"
                                        data-color="all">
                                        <span class="status-circle default"></span> All PFT</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="PFT_percentage"
                                        data-color="red">
                                        <span class="status-circle red"></span> Red</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="PFT_percentage"
                                        data-color="yellow">
                                        <span class="status-circle yellow"></span> Yellow</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="PFT_percentage"
                                        data-color="blue">
                                        <span class="status-circle blue"></span> Blue</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="PFT_percentage"
                                        data-color="green">
                                        <span class="status-circle green"></span> Green</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="PFT_percentage"
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
                                <li><a class="dropdown-item column-filter" href="#" data-column="ROI_percentage"
                                        data-color="all">
                                        <span class="status-circle default"></span> All ROI</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ROI_percentage"
                                        data-color="red">
                                        <span class="status-circle red"></span> Red</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ROI_percentage"
                                        data-color="yellow">
                                        <span class="status-circle yellow"></span> Yellow</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ROI_percentage"
                                        data-color="green">
                                        <span class="status-circle green"></span> Green</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="ROI_percentage"
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
                                <span class="status-circle default"></span> CVR
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="scvrFilterDropdown">
                                <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                        data-color="all">
                                        <span class="status-circle default"></span> All CVR</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                        data-color="red">
                                        <span class="status-circle red"></span> Red</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                        data-color="green">
                                        <span class="status-circle green"></span> Green</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="SCVR"
                                        data-color="pink">
                                        <span class="status-circle pink"></span> Pink</a></li>
                            </ul>
                        </div>

                        <!-- Task Board Button -->
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                            data-bs-target="#createTaskModal">
                            <i class="bi bi-plus-circle me-2"></i>Create Task
                        </button>

                        <!-- for popup modal start Modal -->
                        <div class="modal fade" id="createTaskModal" tabindex="-1"
                            aria-labelledby="createTaskModalLabel" aria-hidden="true">
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

                    <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                        
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

                        <div class="d-flex align-items-center gap-2">
                            <button id="exportBtn" class="btn btn-success">Export</button>
                            <!-- <input type="file" id="importFile" accept=".xlsx, .xls, .csv" style="display:none;" /> -->
                            <button id="importBtn" class="btn btn-primary">Import </button>
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

                            <a href="{{ asset('sample_excel/sample_cvr_lqs_file.csv') }}" download class="btn btn-outline-secondary mb-3">📄 Download Sample File</a>

                            <input type="file" id="importFile" name="file" accept=".xlsx,.xls,.csv" class="form-control" />
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" id="confirmImportBtn">Import</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                        </div>
                    </div>
                    </div>


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

                            <div class="form-group mr-2">
                                <label for="nr-filter" class="mr-2">NRL:</label>
                                <select id="nr-filter" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="NR">NR</option>
                                    <option value="REQ">REQ</option>
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

                    <div class="table-container">
                        <table class="custom-resizable-table" id="cvrLQS-table">
                            <thead>
                                <tr>
                                    <!-- <th data-field="sl_no">SL No. <span class="sort-arrow">↓</span></th> -->
                                    <th data-field="sl_no" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                SL No. <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="slno-total">0</div>
                                        </div>
                                    </th>
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
                                                SKU <span class="sort-arrow">↓</span>
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
                                    <th data-field="al30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                E L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ovl30-total">0</div> -->
                                        </div>
                                    </th>
                                    <th data-field="adil" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                E DIL <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ovdil-total">0%</div> -->
                                        </div>
                                    </th>
                                    <th data-field="nr" style="vertical-align: middle; white-space: nowrap;">
                                        NRL
                                    </th>
                                    <th data-field="views" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                VIEWS <span class="sort-arrow">↓</span>
                                            </div>
                                            {{-- <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> --}}
                                            {{-- <div class="metric-total" id="views-total">0</div> --}}
                                        </div>
                                    </th>
                                    <th data-field="cvr" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                CVR <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="cvr-total">0</div> -->
                                        </div>
                                    </th>
                                    {{-- <th data-field="lqs_jungle" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                LQS JUNGLE <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div> -->
                                            <!-- <div class="metric-total" id="lqs-total">0</div> -->
                                        </div>
                                    </th>
                                    <th data-field="lqs" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                LQS <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="lqsc" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                LQS C <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="lqsc-total">0</div>
                                        </div>
                                    </th> --}}
                                    <th data-field="comp"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                COMP <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="link"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                C LINK <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>

                                    <th data-field="status" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Processed/Pending
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div>
                                                <span class="metric-total" id="processed-total"
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
                                    
                                    <th data-field="action"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                ACTION <span class="sort-arrow">↓</span>
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
                            <div class="loader-text">Loading Ebay Cvr LQS data...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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
                        // url: '/update-all-cvrLQS-skus',
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
            const cvrLqsDataCache = {
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
                cvrLqsDataCache.clear();
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

                // Show R&A column
                $('th[data-field="r&a"], td:nth-child(4)').removeClass('hide-column');

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

                // Hide R&A column
                $('th[data-field="r&a"], td:nth-child(4)').addClass('hide-column');

                // Update button visibility and reset color
                $('#play-pause').hide();
                $('#play-auto').show()
                    .removeClass('btn-success btn-warning btn-danger')
                    .addClass('btn-light');

                // Show all products
                filteredData = [...tableData];
                currentPage = 1;
                renderTable();
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
                currentPage = 1;
                renderTable();
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

            // Initialize everything
            function initTable() {
                loadData().then(() => {
                    // Hide R&A column initially
                    $('th[data-field="r&a"], td:nth-child(4)').addClass('hide-column');
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
                    initNREditHandlers();
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
                    url: '/ebaycvrLQS/view-data',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {

                        if (response && response.data) {
                            tableData = response.data.map((item, index) => {

                                // Calculate A Dil% as (A L30 / INV), handle division by zero
                                const inv = Number(item.INV) || 0;
                                const aL30 = Number(item['E_L30']) || 0;
                                const l30 = Number(item.L30) || 0;
                                const ovDil = inv > 0 ? l30 / inv : 0;
                                const aDil = inv > 0 ? aL30 / inv : 0;

                                let scvr = 0;
                                if (Number(item['views']) > 0) {
                                    scvr = Number(item['E_L30']) / Number(item[
                                        'views']);
                                }

                                return {
                                    sl_no: index + 1,
                                    'SL No.': item['SL No.'] || index + 1,
                                    Parent: item.Parent || item.parent || item.parent_asin ||
                                        item.Parent_ASIN || '(No Parent)',
                                    '(Child) sku': item['(Child) sku'] || '',
                                    'R&A': item['R&A'] !== undefined ? item['R&A'] : '',
                                    INV: inv,
                                    L30: item.L30 || 0,
                                    ov_dil: ovDil,
                                    'A L30': aL30,
                                    units_ordered_l60: item.units_ordered_l60 || 0,
                                    'A Dil%': aDil,
                                    views: item.views || 0,
                                    price: Number(item.price) || 0,
                                    COMP: item.COMP || 0,
                                    min_price: item.scout_data ? item.scout_data.min_price : 0,
                                    all_products: item.scout_data ? item.scout_data.all_data :
                                        0,
                                    'PFT_percentage': item['PFT_percentage'] || 0,
                                    TPFT: item.TPFT || 0,
                                    ROI_percentage: item.ROI_percentage || 0,
                                    Tacos30: item.Tacos30 || 0,
                                    // SCVR: item.SCVR || 0,
                                    LP: item.LP_productmaster || 0,
                                    SHIP: item.Ship_productmaster || 0,
                                    'ad cost/ pc': item['ad cost/ pc'] || '',
                                    is_parent: item['(Child) sku'] ? item['(Child) sku']
                                        .toUpperCase().includes("PARENT") : false,
                                    listing_quality_score: item.listing_quality_score || '',
                                    listing_quality_score_c: item.listing_quality_score_c || '',
                                    c_link: item.link || '',
                                    comp: item.comp || '',
                                    lqs_jungle: item.lqs_jungle || '',
                                    raw_data: item || {},
                                    NR: item.NR !== undefined ? item.NR : '',
                                    status: item.status || (item.value && item.value.status) || 'Pending',
                                    SCVR: scvr,

                                };
                            });

                            // console.log('Data loaded successfully:', tableData);
                            // filteredData = [...tableData];
                            filteredData = tableData.filter(row => row.SCVR * 100 < 7);


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

            // Render table with current data
            function renderTable() {
                const $tbody = $('#cvrLQS-table tbody');
                $tbody.empty();

                if (isLoading) {
                    $tbody.append('<tr><td colspan="10" class="text-center">Loading data...</td></tr>');
                    return;
                }

                if (filteredData.length === 0) {
                    $tbody.append('<tr><td colspan="10" class="text-center">No matching records found</td></tr>');
                    return;
                }

                filteredData.forEach(item => {
                    
                    const $row = $('<tr>');
                    if (item.is_parent) {
                        $row.addClass('parent-row');
                    }

                    // SL No.
                    $row.append($('<td>').text(item['SL No.']));

                    // Parent
                    $row.append($('<td>').text(item.Parent));

                    // SKU with hover content for links
                    const $skuCell = $('<td>').addClass('skuColumn').css('position', 'static');
                    if (item.is_parent) {
                        $skuCell.html(`<strong>${item['(Child) sku']}</strong>`);
                    } else {
                        const imageUrl = item.raw_data.image_path || '';
                        const buyerLink = item.raw_data['AMZ LINK BL'] || '';
                        const sellerLink = item.raw_data['AMZ LINK SL'] || '';

                        if (buyerLink || sellerLink || imageUrl) {
                            $skuCell.html(`
                                <div class="sku-tooltip-container">
                                    <span class="sku-text">${item['(Child) sku']}</span>
                                    <div class="sku-tooltip">
                                        ${imageUrl ? `<img src="${imageUrl}" alt="SKU Image" style="max-width:120px;max-height:120px;border-radius:6px;display:block;margin:0 auto 6px auto;">` : ''}
                                        ${buyerLink ? `<div class="sku-link"><a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a></div>` : ''}
                                        ${sellerLink ? `<div class="sku-link"><a href="${sellerLink}" target="_blank" rel="noopener noreferrer">Seller link</a></div>` : ''}
                                    </div>
                                </div>
                            `);
                        } else {
                            $skuCell.text(item['(Child) sku']);
                        }
                    }
                    $row.append($skuCell);

                    // INV
                    $row.append($('<td>').text(item.INV));

                    // OV L30
                    $row.append($('<td>').text(item.L30));

                    // OV DIL with color coding and WMPNM tooltip
                    const getDilColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 16.66) return 'red';
                        if (percent >= 16.66 && percent < 25) return 'yellow';
                        if (percent >= 25 && percent < 50) return 'green';
                        return 'pink'; // 50 and above
                    };
                    $row.append($('<td>').html(
                        `<span class="dil-percent-value ${getDilColor(item.ov_dil)}">${Math.round(item.ov_dil * 100)}%</span>
                            <span class="text-info tooltip-icon wmpnm-view-trigger" 
                            data-bs-toggle="tooltip" 
                                data-bs-placement="left" 
                                title="WMPNM View"
                                data-item='${JSON.stringify(item.raw_data)}'>W</span>`
                    ));

                    // AMZ L30
                    $row.append($('<td>').text(item['A L30']));

                    // AMZ DIL with color coding and WMPNM tooltip
                    const getDilColor1 = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 16.66) return 'red';
                        if (percent >= 16.66 && percent < 25) return 'yellow';
                        if (percent >= 25 && percent < 50) return 'green';
                        return 'pink'; // 50 and above
                    };
                    $row.append($('<td>').html(
                        `<span class="dil-percent-value ${getDilColor1(item['A Dil%'])}">${Math.round(item['A Dil%'] * 100)}%</span>
                            <span class="text-info tooltip-icon wmpnm-view-trigger" 
                            data-bs-toggle="tooltip"
                                data-bs-placement="left" 
                                title="WMPNM View"
                                data-item='${JSON.stringify(item.raw_data)}'>W</span>`
                    ));

                    // --- NR column ---
                    if (item.is_parent) {
                        $row.append($('<td>')); // Empty cell for parent
                    } else {
                        const currentNR = item.NR === 'REQ' || item.NR === 'NR' ? item.NR :
                            'REQ'; // default to REQ
                        // const currentNR = ['REQ', 'NR'].includes(item.NR) ? item.NR : 'REQ';
                        const $select = $(`
                            <select class="form-select form-select-sm nr-select" style="min-width: 100px;">
                                <option value="NR" ${currentNR === 'NR' ? 'selected' : ''}>NR</option>
                                <option value="REQ" ${currentNR === 'REQ' ? 'selected' : ''}>REQ</option>
                            </select>
                        `);

                        // Set background color based on value
                        if (currentNR === 'NR') {
                            $select.css('background-color', '#dc3545');
                            $select.css('color', '#ffffff');
                        } else if (currentNR === 'REQ') {
                            $select.css('background-color', '#28a745');
                            $select.css('color', '#ffffff');
                        }

                        $select.data('sku', item['(Child) sku']);
                        $row.append($('<td>').append($select));
                    }

                    // Views (Sess30) with tooltip icon
                    $row.append($('<td>').html(
                        `<span>${Math.round(item.views)}</span>
                            <span class="text-info tooltip-icon ad-view-trigger" 
                                data-bs-toggle="tooltip" 
                                data-bs-placement="left" 
                                title="visibility View"
                                data-item='${JSON.stringify(item.raw_data)}'>V</span>`
                    ));

                    // CVR
                    // $row.append($('<td>').text(item.SCVR));
                    // $row.append($('<td>').text(parseFloat(item.SCVR).toFixed(2)));
                    // CVR with color coding and tooltip
                    const getCvrColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 5) return 'red';
                        if (percent >= 5 && percent < 10) return 'green';
                        return 'pink'; // 10 and above
                    };

                    $row.append($('<td>').html(
                        `<span class="dil-percent-value ${getCvrColor(item.SCVR)}">${Math.round(item.SCVR * 100)}%</span>`
                    ));

                     // LQS Jungle (read-only)
                    // $row.append($('<td>').html(`
                    //     <span>${item.lqs_jungle !== null && item.lqs_jungle !== '' ? item.lqs_jungle : '-'}</span>
                    // `));

                    // LQS (just show value, adjust as needed)
                    // $row.append($('<td>').text(item.listing_quality_score || ''));
                    // const lqsValue = item.raw_data?.listing_quality_score || '';
                    
                    // $row.append($('<td>').html(`
                    //     <input type="number"
                    //         class="form-control form-control-sm lqs-input" 
                    //         value="${lqsValue}" 
                    //         data-sku="${item['(Child) sku']}" 
                    //         style="width: 80px;" />
                    // `));

                    //LQSC
                    // const lqscValue = item.raw_data?.listing_quality_score_c || '';
                    // $row.append($('<td>').html(`
                    //     <input type="number" 
                    //         class="form-control form-control-sm lqsc-input" 
                    //         value="${lqscValue}" 
                    //         data-sku="${item['(Child) sku']}" 
                    //         style="width: 80px;" />
                    // `));

                    // COMP
                    // $row.append($('<td>').text(item.COMP));
                    // $row.append($('<td>').html(
                    //     `<span>$${item.COMP || 0}</span>
                    //     <span class="text-info tooltip-icon scouth-products-view-trigger" 
                    //         data-bs-toggle="tooltip" 
                    //         data-bs-placement="left" 
                    //         title="Scouth Products View"
                    //         data-item='${JSON.stringify(item.raw_data)}'>P</span>`
                    // ));
                    const comp = item.raw_data?.comp || '';
                    $row.append($('<td>').html(`
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <input type="text"
                                class="form-control form-control-sm clink-input"
                                value="${comp}"
                                data-sku="${item['(Child) sku']}"
                                style="width: 100px;" />
                        </div>
                    `));

                    //LINK
                    const cLink = item.raw_data?.link || '';
                    $row.append($('<td>').html(`
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <input type="text"
                                class="form-control form-control-sm clink-input"
                                value="${cLink}"
                                data-sku="${item['(Child) sku']}"
                                style="width: 100px;" />
                            ${cLink ? `<a href="${cLink}" target="_blank" style="font-size: 14px;">🔗</a>` : ''}
                        </div>
                    `));

                    // --- STATUS column ---
                    if (item.is_parent) {
                        $row.append($('<td>')); // Empty for parent
                    } else {
                        const currentStatus = item.status === 'Processed' ? 'Processed' : 'Pending';
                        const $statusSelect = $(`
                            <select class="form-select form-select-sm status-select" style="min-width: 110px;">
                                <option value="Pending" ${currentStatus === 'Pending' ? 'selected' : ''}>Pending</option>
                                <option value="Processed" ${currentStatus === 'Processed' ? 'selected' : ''}>Processed</option>
                            </select>
                        `);

                        // Set background color based on value
                        if (currentStatus === 'Pending') {
                            $statusSelect.css({ "background-color": "#dc3545", "color": "#fff" }); // red
                        } else {
                            $statusSelect.css({ "background-color": "#28a745", "color": "#fff" }); // green
                        }

                        $statusSelect.data('sku', item['(Child) sku']);
                        $row.append($('<td>').append($statusSelect));
                    }


                    // Get the full action text from raw_data
                    const actionText = item.raw_data && item.raw_data['action'] ? String(item.raw_data[
                        'action']) : '';
                    const words = actionText.split(/\s+/);
                    const shortText = words.length > 3 ? words.slice(0, 3).join(' ') + ' ...' : actionText;

                    // Use a custom hover effect instead of Bootstrap tooltip
                    $row.append($('<td>').html(`
                        <span class="action-value-text action-hover"
                            style="margin-left:8px; color:#333; cursor:pointer; position:relative;">
                            ${$('<div>').text(shortText).html()}
                            ${actionText && words.length > 3 ? `
                                            <span class="action-full-text" style="
                                                display:none;
                                                position:absolute;
                                                left:0;
                                                top:100%;
                                                background:#fff;
                                                color:#333;
                                                border:1px solid #ccc;
                                                border-radius:4px;
                                                padding:6px 12px;
                                                white-space:pre-line;
                                                z-index:1000;
                                                min-width:180px;
                                                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                                            ">${$('<div>').text(actionText).html()}</span>
                                        ` : ''}
                        </span>
                        <span class="action-plus-icon" style="cursor:pointer; color:#28a745; font-size:1.3em; margin-left:6px;" title="Add Action">
                            <i class="fas fa-plus-circle"></i>
                        </span>
                    `));

                    $tbody.append($row);
                });

                updatePaginationInfo();
                $('#visible-rows').text(`Showing all ${filteredData.length} rows`);
                // Initialize tooltips
                initTooltips();
            }


            // Handle status dropdown change
            $(document).off('change', '.status-select').on('change', '.status-select', function () {
                const $select = $(this);
                const newStatus = $select.val();
                const sku = $select.data('sku');
                console.log("Status changed for", sku, "->", newStatus);

                // Change background color immediately
                if (newStatus === 'Processed') {
                    $select.css({ "background-color": "#28a745", "color": "#fff" }); // green
                } else {
                    $select.css({ "background-color": "#dc3545", "color": "#fff" }); // red
                }

                // Save to backend
                $.ajax({
                    url: '/ebay-cvr-lqs/save-action',
                    type: 'POST',
                    data: {
                        sku: sku,
                        status: newStatus,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        console.log("Ajax success:", response);
                        showNotification('success', 'Status updated!');
                    },
                    error: function (xhr) {
                        console.error("Ajax error:", xhr.responseText);
                        showNotification('danger', 'Failed to update status.');
                    }
                });
            });




            $('#exportBtn').on('click', function () {
                const exportData = tableData.map(row => ({
                    'SL No.': row['SL No.'],
                    'Parent': row.Parent,
                    'SKU': row['(Child) sku'],
                    'INV': row.INV,
                    'L30': row.L30,
                    'OV Dil%': (row.ov_dil * 100).toFixed(2),
                    'A L30': row['A L30'],
                    'A Dil%': (row['A Dil%'] * 100).toFixed(2),
                    'NR': row.NR,
                    'VIEWS': row.views,
                    'SCVR%': (row.SCVR * 100).toFixed(2),
                    // 'lqs_jungle': row.lqs_jungle,
                    'COMP': row.comp,
                    // 'LQS': row.listing_quality_score,
                    // 'LQSC': row.listing_quality_score_c,
                    'C link': row.c_link,
                    'STATUS': row.status,
                }));

                const worksheet = XLSX.utils.json_to_sheet(exportData);
                const workbook = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(workbook, worksheet, "CVR Data");

                XLSX.writeFile(workbook, "ebay_cvr_data_export.xlsx");
            });


            // Show modal on import click
            $('#importBtn').on('click', function () {
                $('#importModal').modal('show');
            });

            // $('#confirmImportBtn').on('click', function () {
            //     const fileInput = document.getElementById('importFile');
            //     // const file = fileInput;
            //     const file = $('#importFile')[0].files[0];
            //      console.log('Selected file:', file);

            //     if (!file) {
            //         alert('Please select a file.');
            //         return;
            //     }

            //     const formData = new FormData();
            //     formData.append('file', file);

            //     $.ajax({
            //         url: '/import-cvr-data',
            //         type: 'POST',
            //         data: formData,
            //         processData: false,
            //         contentType: false,
            //         headers: {
            //             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            //         },
            //         success: function (response) {
            //             alert('Import successful!');
            //             $('#importModal').modal('hide');
            //             loadData(); // reload your DataTable if needed
            //         },
            //         error: function (xhr) {
            //             alert('Import failed. Please check file format.');
            //         }
            //     });
            // });

            // document.getElementById('confirmImportBtn').addEventListener('click', function () {
            //     const fileInput = document.getElementById('importFile');
            //     const file = fileInput.files[0];

            //     if (!file) {
            //         alert("Please select a file first.");
            //         return;
            //     }

            //     const formData = new FormData();
            //     formData.append('file', file);
            //     // formData.append('_token', '{{ csrf_token() }}');

            //     fetch('{{ route("import.cvr") }}', {
            //         method: 'POST',
            //         headers: {
            //             'X-CSRF-TOKEN': "{{ csrf_token() }}"
            //         },
            //         body: formData,
            //     })
            //     .then(response => response.json())
            //     .then(data => {
            //         alert(data.message || "Import completed");
            //         // Optionally reload table or close modal
            //         location.reload();
            //     })
            //     .catch(error => {
            //         console.error(error);
            //         alert("Something went wrong");
            //     });
            // });
            $('#confirmImportBtn').on('click', function () {
                const fileInput = document.getElementById('importFile');
                const file = fileInput.files[0];

                if (!file) {
                    alert('Please select a file first.');
                    return;
                }

                const formData = new FormData();
                formData.append('file', file);

                $.ajax({
                    url: '/import-ebay-cvr-data',
                    method: 'POST',
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    processData: false,
                    contentType: false,
                    success: function (res) {
                        alert('Imported successfully!');
                        $('#importModal').modal('hide');
                        location.reload();
                    },
                    error: function (err) {
                        console.error(err);
                        alert('Failed to import.');
                    }
                });
            });




            $(document).off('mouseenter mouseleave', '.action-hover').on('mouseenter',
                '.action-hover',
                function() {
                    $(this).find('.action-full-text').stop(true, true).fadeIn(120);
                }).on('mouseleave', '.action-hover', function() {
                $(this).find('.action-full-text').stop(true, true).fadeOut(120);
            });

            $(document).on('click', '.action-plus-icon', function(e) {
                e.stopPropagation();
                const $row = $(this).closest('tr');
                const slNo = $row.find('td').eq(0).text();
                const rowData = filteredData.find(item => item['SL No.'] == slNo);


                // Use the correct action value (from .action or .ACTION for backward compatibility)
                const actionValue = rowData && rowData.raw_data && (rowData.raw_data.action || rowData
                        .raw_data.ACTION) ?
                    (rowData.raw_data.action || rowData.raw_data.ACTION) : '';

                // Build modal HTML
                const modalId = 'action-input-modal';
                let $modal = $('#' + modalId);
                if ($modal.length) $modal.remove(); // Remove if already exists

                $modal = $(`
                    <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}-label" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="${modalId}-label">Add Action for SKU: <span style="color:#007bff">${rowData ? rowData['(Child) sku'] : ''}</span></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="text" class="form-control" id="action-input-field" placeholder="Enter action..." value="${actionValue}">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="save-action-btn">Save</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);

                $('body').append($modal);
                const bsModal = new bootstrap.Modal(document.getElementById(modalId));
                bsModal.show();

                // Save handler
                $modal.off('click', '#save-action-btn').on('click', '#save-action-btn', function() {
                    const newActionValue = $('#action-input-field').val().trim();
                    console.log("Saving action for SKU:", rowData ? rowData['(Child) sku'] : '', "->", newActionValue);
                    
                    const sku = rowData ? rowData['(Child) sku'] : '';

                    $.ajax({
                        url: '/ebay-cvr-lqs/save-action',
                        type: 'POST',
                        data: {
                            sku: sku,
                            action: newActionValue,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            showNotification('success', 'Action saved!');
                            if (rowData) {
                                rowData.action = newActionValue;
                                // update cache and UI as needed
                                $row.find('.action-value-text').text(newActionValue);
                            }
                            // Properly close the modal
                            const modalEl = document.getElementById(modalId);
                            const bsModal = bootstrap.Modal.getOrCreateInstance(
                            modalEl);
                            bsModal.hide();
                        },
                        error: function(xhr) {
                            showNotification('danger', 'Failed to save action.');
                        }
                    });
                });

                // Remove modal from DOM after close
                $modal.on('hidden.bs.modal', function() {
                    $modal.remove();
                });
            });

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
                    const nrValue = $(this).val();
                    if (nrValue === 'NR') {
                        $select.css('background-color', '#dc3545').css('color', '#ffffff');
                    } else {
                        $select.css('background-color', '#28a745').css('color', '#ffffff');
                    }
                    $.ajax({
                        url: '/amazon/save-nr', 
                        type: 'POST',
                        data: {
                            sku: sku,
                            nr: JSON.stringify({ NR: nrValue }),
                            _token: $('meta[name="csrf-token"]').attr('content') // CSRF protection
                        },
                        success: function (res) {
                            showNotification('success', 'NR updated successfully');

                            // Update tableData and filteredData correctly
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
                            // Recalculate & re-render
                            calculateTotals();
                            renderTable();
                        },
                        error: function (err) {
                            console.error('Error saving NR:', err);
                            showNotification('danger', 'Failed to update NR');
                        }
                    });
                });
            }

            // function initNREditHandlers() {
            //     $(document).off('click', '.nr-edit-container .nr-edit-icon, .nr-edit-container .fa-save');
            //     $(document).on('click', '.nr-edit-container .nr-edit-icon, .nr-edit-container .fa-save', function(
            //         e) {
            //         e.stopPropagation();
            //         const $icon = $(this);
            //         const $checkbox = $icon.siblings('.nr-checkbox');
            //         const $row = $checkbox.closest('tr');
            //         const rowData = filteredData.find(item => item['SL No.'] == $row.find('td:eq(0)')
            //             .text());
            //         const sku = rowData ? rowData['(Child) sku'] : null;

            //         if ($icon.hasClass('fa-pen')) {
            //             // Enter edit mode
            //             $checkbox.prop('disabled', false)
            //                 .data('original-value', $checkbox.is(':checked'));
            //             $icon.removeClass('fa-pen text-primary')
            //                 .addClass('fa-save text-success')
            //                 .attr('title', 'Save Changes');
            //         } else if ($icon.hasClass('fa-save')) {
            //             // Save
            //             const updatedValue = $checkbox.is(':checked') ? "true" : "false";
            //             $icon.removeClass('fa-save text-success')
            //                 .addClass('fa-spinner fa-spin text-primary')
            //                 .attr('title', 'Saving...');

            //             // --- Save to database via AJAX ---
            //             $.ajax({
            //                 url: '/amazon/save-nr',
            //                 type: 'POST',
            //                 data: {
            //                     sku: sku,
            //                     nr: JSON.stringify({
            //                         NR: updatedValue
            //                     }),
            //                     _token: $('meta[name="csrf-token"]').attr('content')
            //                 },
            //                 success: function(response) {
            //                     showNotification('success', 'NR updated successfully!');
            //                     $checkbox.prop('disabled', true);
            //                     $icon.removeClass('fa-spinner fa-spin text-primary')
            //                         .addClass('fa-pen text-primary')
            //                         .attr('title', 'Edit NR');
            //                 },
            //                 error: function(xhr) {
            //                     showNotification('danger', 'Failed to update NR.');
            //                     $checkbox.prop('checked', $checkbox.data('original-value'))
            //                         .prop('disabled', true);
            //                     $icon.removeClass('fa-spinner fa-spin text-primary')
            //                         .addClass('fa-pen text-primary')
            //                         .attr('title', 'Edit NR');
            //                 }
            //             });
            //         }
            //     });

            //     // Allow clicking the checkbox directly to enter edit mode (like R&A)
            //     $(document).off('click', '.nr-checkbox:disabled');
            //     $(document).on('click', '.nr-checkbox:disabled', function(e) {
            //         e.stopPropagation();
            //         $(this).siblings('.nr-edit-icon').trigger('click');
            //     });
            // }

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
                    const cachedData = cvrLqsDataCache.get(itemId);
                    const dataToUse = cachedData || itemData;

                    // Store the data in cache if it wasn't already
                    if (!cachedData) {
                        cvrLqsDataCache.set(itemId, itemData);
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
                        case 'conversion view':
                            fieldsToDisplay = [{
                                    title: 'Sess30',
                                    content: selectedItem['Sess30']
                                },
                                {
                                    title: 'SCVR',
                                    content: selectedItem['SCVR']
                                },
                                {
                                    title: 'KwClks60',
                                    content: selectedItem['KwClks60']
                                },
                                {
                                    title: 'KwClks30',
                                    content: selectedItem['KwClks30']
                                },
                                {
                                    title: 'KwSld60',
                                    content: selectedItem['KwSld60']
                                },
                                {
                                    title: 'KwSld30',
                                    content: selectedItem['KwSld30']
                                },
                                {
                                    title: 'KwCvr60',
                                    content: selectedItem['KwCvr60']
                                },
                                {
                                    title: 'KwCvr30',
                                    content: selectedItem['KwCvr30']
                                },
                                {
                                    title: 'PtClks60',
                                    content: selectedItem['PtClks60']
                                },
                                {
                                    title: 'PtClks30',
                                    content: selectedItem['PtClks30']
                                },
                                {
                                    title: 'PtSld60',
                                    content: selectedItem['PtSld60']
                                },
                                {
                                    title: 'PtSld30',
                                    content: selectedItem['PtSld30']
                                },
                                {
                                    title: 'PtCvr60',
                                    content: selectedItem['PtCvr60']
                                },
                                {
                                    title: 'PtCvr30',
                                    content: selectedItem['PtCvr30']
                                },
                                {
                                    title: 'DspClks60',
                                    content: selectedItem['DspClks60']
                                },
                                {
                                    title: 'DspClks30',
                                    content: selectedItem['DspClks30']
                                },
                                {
                                    title: 'DspSld60',
                                    content: selectedItem['DspSld60']
                                },
                                {
                                    title: 'DspSld30',
                                    content: selectedItem['DspSld30']
                                },
                                {
                                    title: 'DspCvr60',
                                    content: selectedItem['DspCvr60']
                                },
                                {
                                    title: 'DspCvr30',
                                    content: selectedItem['DspCvr30']
                                },
                                {
                                    title: 'HdClks60',
                                    content: selectedItem['HdClks60']
                                },
                                {
                                    title: 'HdClks30',
                                    content: selectedItem['HdClks30']
                                },
                                {
                                    title: 'HdSld60',
                                    content: selectedItem['HdSld60']
                                },
                                {
                                    title: 'HdSld30',
                                    content: selectedItem['HdSld30']
                                },
                                {
                                    title: 'HdCvr60',
                                    content: selectedItem['HdCvr60']
                                },
                                {
                                    title: 'HdCvr30',
                                    content: selectedItem['HdCvr30']
                                },
                                {
                                    title: 'TClks60',
                                    content: selectedItem['TClks60']
                                },
                                {
                                    title: 'TClks30',
                                    content: selectedItem['TClks30']
                                },
                                {
                                    title: 'TSld60',
                                    content: selectedItem['TSld60']
                                },
                                {
                                    title: 'TSld30',
                                    content: selectedItem['TSld30']
                                },
                                {
                                    title: 'TCvr60',
                                    content: selectedItem['TCvr60']
                                },
                                {
                                    title: 'TCvr30',
                                    content: selectedItem['TCvr30']
                                }
                            ];
                            break;
                        case 'visibility view':
                            fieldsToDisplay = [{
                                    title: 'Sess30',
                                    content: selectedItem['Sess30']
                                },
                                {
                                    title: 'Sessl60',
                                    content: selectedItem['sessions_l60']
                                },
                                {
                                    title: 'KwImp60',
                                    content: selectedItem['KwImp60']
                                },
                                {
                                    title: 'KwImp30',
                                    content: selectedItem['KwImp30']
                                },
                                {
                                    title: 'KwClks60',
                                    content: selectedItem['KwClks60']
                                },
                                {
                                    title: 'KwClks30',
                                    content: selectedItem['KwClks30']
                                },
                                {
                                    title: 'KwCtr60',
                                    content: selectedItem['KwCtr60']
                                },
                                {
                                    title: 'KwCtr30',
                                    content: selectedItem['KwCtr30']
                                },
                                {
                                    title: 'PtImp60',
                                    content: selectedItem['PtImp60']
                                },
                                {
                                    title: 'PtImp30',
                                    content: selectedItem['PtImp30']
                                },
                                {
                                    title: 'PtClks60',
                                    content: selectedItem['PtClks60']
                                },
                                {
                                    title: 'PtClks30',
                                    content: selectedItem['PtClks30']
                                },
                                {
                                    title: 'PtCtr60',
                                    content: selectedItem['PtCtr60']
                                },
                                {
                                    title: 'PtCtr30',
                                    content: selectedItem['PtCtr30']
                                },
                                {
                                    title: 'DspImp60',
                                    content: selectedItem['DspImp60']
                                },
                                {
                                    title: 'DspImp30',
                                    content: selectedItem['DspImp30']
                                },
                                {
                                    title: 'DspClks60',
                                    content: selectedItem['DspClks60']
                                },
                                {
                                    title: 'DspClks30',
                                    content: selectedItem['DspClks30']
                                },
                                {
                                    title: 'DspCtr60',
                                    content: selectedItem['DspCtr60']
                                },
                                {
                                    title: 'DspCtr30',
                                    content: selectedItem['DspCtr30']
                                },
                                {
                                    title: 'HdImp60',
                                    content: selectedItem['HdImp60']
                                },
                                {
                                    title: 'HdImp30',
                                    content: selectedItem['HdImp30']
                                },
                                {
                                    title: 'HdClks60',
                                    content: selectedItem['HdClks60']
                                },
                                {
                                    title: 'HdClks30',
                                    content: selectedItem['HdClks30']
                                },
                                {
                                    title: 'HdCtr60',
                                    content: selectedItem['HdCtr60']
                                },
                                {
                                    title: 'HdCtr30',
                                    content: selectedItem['HdCtr30']
                                },
                                {
                                    title: 'TImp60',
                                    content: selectedItem['TImp60']
                                },
                                {
                                    title: 'TImp30',
                                    content: selectedItem['TImp30']
                                },
                                {
                                    title: 'TClks60',
                                    content: selectedItem['TClks60']
                                },
                                {
                                    title: 'TClks30',
                                    content: selectedItem['TClks30']
                                },
                                {
                                    title: 'TCtr60',
                                    content: selectedItem['TCtr60']
                                },
                                {
                                    title: 'TCtr30',
                                    content: selectedItem['TCtr30']
                                }
                            ];
                            break;
                        case 'price view':
                            fieldsToDisplay = [{
                                    title: 'MSRP',
                                    content: selectedItem['MSRP']
                                },
                                {
                                    title: 'price',
                                    content: selectedItem['price']
                                },
                                {
                                    title: 'PFT_percentage',
                                    content: selectedItem['PFT_percentage']
                                },
                                {
                                    title: 'TPFT',
                                    content: selectedItem['TPFT']
                                },
                                {
                                    title: 'ROI_percentage',
                                    content: selectedItem['ROI_percentage']
                                },
                                {
                                    title: 'SPRICE',
                                    content: dataToUse['SPRICE']
                                },
                                {
                                    title: 'Spft%',
                                    content: (function() {
                                        const spftValue = dataToUse['Spft%'];

                                        // Check if value exists and is a valid number
                                        if (typeof spftValue !== 'number' || isNaN(spftValue)) {
                                            return '0 %'; // Fallback for invalid data
                                        }

                                        // Handle zero case
                                        if (spftValue === 0) {
                                            return '0 %';
                                        }

                                        // Format the number (ensure it's a number before using .toFixed)
                                        const absValue = Math.abs(spftValue);
                                        const formattedValue = (absValue < 100) ?
                                            (spftValue * 100).toFixed(2) :
                                            spftValue.toFixed(2);

                                        return formattedValue + ' %';
                                    })()
                                },
                                {
                                    title: 'Tannishtha done',
                                    content: dataToUse['Tannishtha done']
                                },
                                {
                                    title: 'LMP 1',
                                    content: dataToUse['LMP 1']
                                },
                                {
                                    title: 'LINK 1',
                                    content: dataToUse['LINK 1']
                                },
                                {
                                    title: 'LMP 2',
                                    content: dataToUse['LMP 2']
                                },
                                {
                                    title: 'LINK 2',
                                    content: dataToUse['LINK 2']
                                },
                                {
                                    title: 'LMP3',
                                    content: dataToUse['LMP3']
                                },
                                {
                                    title: 'LINK 3',
                                    content: dataToUse['LINK 3']
                                },
                                {
                                    title: 'LMP 4',
                                    content: dataToUse['LMP 4']
                                },
                                {
                                    title: 'LINK 4',
                                    content: dataToUse['LINK 4']
                                },
                                {
                                    title: 'LMP 5',
                                    content: dataToUse['LMP 5']
                                },
                                {
                                    title: 'LINK 5',
                                    content: dataToUse['LINK 5']
                                }
                            ];
                            break;
                        case 'advertisement view':
                            fieldsToDisplay = [{
                                    title: 'KwImp60',
                                    content: selectedItem['KwImp60']
                                },
                                {
                                    title: 'KwImp30',
                                    content: selectedItem['KwImp30']
                                },
                                {
                                    title: 'KwClks60',
                                    content: selectedItem['KwClks60']
                                },
                                {
                                    title: 'KwClks30',
                                    content: selectedItem['KwClks30']
                                },
                                {
                                    title: 'KwCtr60',
                                    content: selectedItem['KwCtr60']
                                },
                                {
                                    title: 'KwCtr30',
                                    content: selectedItem['KwCtr30']
                                },
                                {
                                    title: 'KwSpnd60',
                                    content: selectedItem['KwSpnd60']
                                },
                                {
                                    title: 'KwSpnd30',
                                    content: selectedItem['KwSpnd30']
                                },
                                {
                                    title: 'KwSls60',
                                    content: selectedItem['KwSls60']
                                },
                                {
                                    title: 'KwSls30',
                                    content: selectedItem['KwSls30']
                                },
                                {
                                    title: 'KwSld60',
                                    content: selectedItem['KwSld60']
                                },
                                {
                                    title: 'KwSld30',
                                    content: selectedItem['KwSld30']
                                },
                                {
                                    title: 'KwAcos60',
                                    content: selectedItem['KwAcos60']
                                },
                                {
                                    title: 'KwAcos30',
                                    content: selectedItem['KwAcos30']
                                },
                                {
                                    title: 'KwCvr60',
                                    content: selectedItem['KwCvr60']
                                },
                                {
                                    title: 'KwCvr30',
                                    content: selectedItem['KwCvr30']
                                },

                                {
                                    title: 'PtImp60',
                                    content: selectedItem['PtImp60']
                                },
                                {
                                    title: 'PtImp30',
                                    content: selectedItem['PtImp30']
                                },
                                {
                                    title: 'PtClks60',
                                    content: selectedItem['PtClks60']
                                },
                                {
                                    title: 'PtClks30',
                                    content: selectedItem['PtClks30']
                                },
                                {
                                    title: 'PtCtr60',
                                    content: selectedItem['PtCtr60']
                                },
                                {
                                    title: 'PtCtr30',
                                    content: selectedItem['PtCtr30']
                                },
                                {
                                    title: 'PtSpnd60',
                                    content: selectedItem['PtSpnd60']
                                },
                                {
                                    title: 'PtSpnd30',
                                    content: selectedItem['PtSpnd30']
                                },
                                {
                                    title: 'PtSls60',
                                    content: selectedItem['PtSls60']
                                },
                                {
                                    title: 'PtSls30',
                                    content: selectedItem['PtSls30']
                                },
                                {
                                    title: 'PtSld60',
                                    content: selectedItem['PtSld60']
                                },
                                {
                                    title: 'PtSld30',
                                    content: selectedItem['PtSld30']
                                },
                                {
                                    title: 'PtAcos60',
                                    content: selectedItem['PtAcos60']
                                },
                                {
                                    title: 'PtAcos30',
                                    content: selectedItem['PtAcos30']
                                },
                                {
                                    title: 'PtCvr60',
                                    content: selectedItem['PtCvr60']
                                },
                                {
                                    title: 'PtCvr30',
                                    content: selectedItem['PtCvr30']
                                },

                                {
                                    title: 'DspImp60',
                                    content: selectedItem['DspImp60']
                                },
                                {
                                    title: 'DspImp30',
                                    content: selectedItem['DspImp30']
                                },
                                {
                                    title: 'DspClks60',
                                    content: selectedItem['DspClks60']
                                },
                                {
                                    title: 'DspClks30',
                                    content: selectedItem['DspClks30']
                                },
                                {
                                    title: 'DspCtr60',
                                    content: selectedItem['DspCtr60']
                                },
                                {
                                    title: 'DspCtr30',
                                    content: selectedItem['DspCtr30']
                                },
                                {
                                    title: 'DspSpnd60',
                                    content: selectedItem['DspSpnd60']
                                },
                                {
                                    title: 'DspSpnd30',
                                    content: selectedItem['DspSpnd30']
                                },
                                {
                                    title: 'DspSls60',
                                    content: selectedItem['DspSls60']
                                },
                                {
                                    title: 'DspSls30',
                                    content: selectedItem['DspSls30']
                                },
                                {
                                    title: 'DspSld60',
                                    content: selectedItem['DspSld60']
                                },
                                {
                                    title: 'DspSld30',
                                    content: selectedItem['DspSld30']
                                },
                                {
                                    title: 'DspAcos60',
                                    content: selectedItem['DspAcos60']
                                },
                                {
                                    title: 'DspAcos30',
                                    content: selectedItem['DspAcos30']
                                },
                                {
                                    title: 'DspCvr60',
                                    content: selectedItem['DspCvr60']
                                },
                                {
                                    title: 'DspCvr30',
                                    content: selectedItem['DspCvr30']
                                },

                                {
                                    title: 'HdImp60',
                                    content: selectedItem['HdImp60']
                                },
                                {
                                    title: 'HdImp30',
                                    content: selectedItem['HdImp30']
                                },
                                {
                                    title: 'HdClks60',
                                    content: selectedItem['HdClks60']
                                },
                                {
                                    title: 'HdClks30',
                                    content: selectedItem['HdClks30']
                                },
                                {
                                    title: 'HdCtr60',
                                    content: selectedItem['HdCtr60']
                                },
                                {
                                    title: 'HdCtr30',
                                    content: selectedItem['HdCtr30']
                                },
                                {
                                    title: 'HdSpnd60',
                                    content: selectedItem['HdSpnd60']
                                },
                                {
                                    title: 'HdSpnd30',
                                    content: selectedItem['HdSpnd30']
                                },
                                {
                                    title: 'HdSls60',
                                    content: selectedItem['HdSls60']
                                },
                                {
                                    title: 'HdSls30',
                                    content: selectedItem['HdSls30']
                                },
                                {
                                    title: 'HdSld60',
                                    content: selectedItem['HdSld60']
                                },
                                {
                                    title: 'HdSld30',
                                    content: selectedItem['HdSld30']
                                },
                                {
                                    title: 'HdAcos60',
                                    content: selectedItem['HdAcos60']
                                },
                                {
                                    title: 'HdAcos30',
                                    content: selectedItem['HdAcos30']
                                },
                                {
                                    title: 'HdCvr60',
                                    content: selectedItem['HdCvr60']
                                },
                                {
                                    title: 'HdCvr30',
                                    content: selectedItem['HdCvr30']
                                },

                                {
                                    title: 'TImp60',
                                    content: selectedItem['TImp60']
                                },
                                {
                                    title: 'TImp30',
                                    content: selectedItem['TImp30']
                                },
                                {
                                    title: 'TClks60',
                                    content: selectedItem['TClks60']
                                },
                                {
                                    title: 'TClks30',
                                    content: selectedItem['TClks30']
                                },
                                {
                                    title: 'TCtr60',
                                    content: selectedItem['TCtr60']
                                },
                                {
                                    title: 'TCtr30',
                                    content: selectedItem['TCtr30']
                                },
                                {
                                    title: 'TSpnd60',
                                    content: selectedItem['TSpnd60']
                                },
                                {
                                    title: 'TSpnd30',
                                    content: selectedItem['TSpnd30']
                                },
                                {
                                    title: 'TSls60',
                                    content: selectedItem['TSls60']
                                },
                                {
                                    title: 'TSls30',
                                    content: selectedItem['TSls30']
                                },
                                {
                                    title: 'TSld60',
                                    content: selectedItem['TSld60']
                                },
                                {
                                    title: 'TSld30',
                                    content: selectedItem['TSld30']
                                },
                                {
                                    title: 'TAcos60',
                                    content: selectedItem['TAcos60']
                                },
                                {
                                    title: 'TAcos30',
                                    content: selectedItem['TAcos30']
                                },
                                {
                                    title: 'TCvr60',
                                    content: selectedItem['TCvr60']
                                },
                                {
                                    title: 'TCvr30',
                                    content: selectedItem['TCvr30']
                                }
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
                        <div class="scouth-table-cell">${product.cvr_quality_score || 'N/A'}</div>
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
                cvrLqsDataCache.updateField(itemId, title, cacheUpdateValue);

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
                        cvrLqsDataCache.updateField(itemId, 'Spft%', Spft);
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
                    url: window.location.origin + (window.location.pathname.includes('/public') ?
                        '/public' : '') + '/api/update-amazon-column',
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
                        if (rowElement) {
                            checkParentRAStatus();
                            renderTable();
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
                        cvrLqsDataCache.updateField(itemId, title, originalValue);

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

                                cvrLqsDataCache.updateField(itemId, 'Spft%', Spft);
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
                const $table = $('#cvrLQS-table');
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

            // Initialize column toggle functionality
            function initColumnToggle() {
                const $table = $('#cvrLQS-table');
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
                                   id="toggle-${field}" data-field="${field}" checked>
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

                    const colIndex = $headers.filter(`[data-field="${field}"]`).index();
                    $table.find('tr').each(function() {
                        $(this).find('td, th').eq(colIndex).toggle(isVisible);
                    });
                });

                $('#showAllColumns').on('click', function() {
                    $menu.find('.column-toggle-checkbox').prop('checked', true).trigger('change');
                    $menu.removeClass('show');
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
            $('#inv-filter, #nr-filter').on('change', function() {
                applyColumnFilters();
            });


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

                const nrlFilter = $('#nr-filter').val();
                console.log('nrrrr',nrlFilter);

                if (nrlFilter && nrlFilter !== 'all') {
                    filteredData = filteredData.filter(item => {
                        const nrl = (item.NR || '').toUpperCase();
                        return nrl === nrlFilter;
                    });
                }
                
                // Apply other filters
                Object.entries(state.filters).forEach(([column, filterValue]) => {
                    if (filterValue === 'all') return;

                    filteredData = filteredData.filter(item => {
                        if (column === 'entryType') {
                            if (filterValue === 'parent') return item.is_parent;
                            if (filterValue === 'child') return !item.is_parent;
                            return true;
                        }

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
                        ranges: [5, 10],
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
                        pftSum: 0,
                        roiSum: 0,
                        tacosTotal: 0,
                        scvrSum: 0,
                        rowCount: 0,
                        totalPftSum: 0,
                        totalSalesL30Sum: 0,
                        totalCogsSum: 0,
                        ProcessedTotal: 0, // Green
                        pendingTotal: 0, // Red
                    };

                    filteredData.forEach(item => {
                        
                        metrics.invTotal += parseFloat(item.INV) || 0;
                        metrics.ovL30Total += parseFloat(item.L30) || 0;
                        metrics.el30Total += parseFloat(item['A L30']) || 0;
                        metrics.eDilTotal += parseFloat(item['A Dil%']) || 0;
                        metrics.viewsTotal += parseFloat(item.views) || 0;
                        metrics.tacosTotal += parseFloat(item.Tacos30) || 0;
                        metrics.pftSum += parseFloat(item['PFT_percentage']) || 0;
                        metrics.roiSum += parseFloat(item.ROI_percentage) || 0;
                        metrics.scvrSum += parseFloat(item.SCVR) || 0;
                        metrics.rowCount++;

                        // ---  Listed & Pending counters ---
                        const statusVal = (item.status || item.raw_data?.status || '').toString();
                        const nrVal = (item.NR || item.raw_data?.NR || '').toString().trim();
                        // if (nrVal !== 'NR') {
                            if (statusVal === 'Processed') {
                                metrics.ProcessedTotal++;
                            } else if (statusVal === 'Pending') {
                                metrics.pendingTotal++;
                            }
                        // }

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
                    $('#slno-total').text(metrics.rowCount.toLocaleString());

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
                    $('#processed-total').text(metrics.ProcessedTotal);
                    $('#pending-total').text(metrics.pendingTotal);


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
                $('#slno-total').text('0');
                $('#processed-total').text('0');
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
        });
    </script>
@endsection
