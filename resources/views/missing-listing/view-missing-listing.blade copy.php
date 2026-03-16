@extends('layouts.vertical', ['title' => 'View Missing Listing', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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


        /*popup modal style end */

  #inventory-table thead{
     position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 11;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  }

        #inventory-table thead tr#summaryRow th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 11;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        /* Make the main header row stick below summary row */

        #inventory-table thead tr:nth-child(3) th {
            position: sticky;
            top: 36px; /* height of summary row */
            background: #ffffff;
            z-index: 10;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }


        /* Optional: ensure headers are tall enough */
        #inventory-table thead th {
            height: 56px;
            text-align: center;
            vertical-align: middle;
        }
        
        #inventory-table {
        color: #000 !important; /* Force dark black font */

        .img-wrapper {
            position: relative;
            display: inline-block;
        }

        .thumbnail-img {
            width: 30px;
            height: auto;
            border-radius: 4px;
            cursor: pointer;
        }

        .popup-img {
            display: none;
            position: absolute;
            top: -10px;
            left: 30px;
            z-index: 999;
            border: 1px solid #ccc;
            background: #fff;
            padding: 4px;
            box-shadow: 0 0 6px rgba(0,0,0,0.2);
        }

        .popup-img img {
            width: 300px;
            height: auto;
            border-radius: 4px;
        }

        .img-wrapper:hover .popup-img {
            display: block;
        }

    }
        
    /* image preview */
    #image-hover-preview {
    pointer-events: none;
    position: absolute;
    z-index: 9999;
}

    .image-preview-container {
            width: 100px;
            height: 100px;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .image-preview-container:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .image-preview-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: none; /* JS se toggle hoga */
            cursor: pointer;
        }
    /* image preview */


    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'View Missing Listing', 'sub_title' => 'Missing Stock'])
  

    <div class="row">
        <div class="col-xxl-3 col-sm-6">
            <button id="NotListed" class="text-danger btn btn-primary btn-sm" style="font-size:22px;background:#fff;cursor: pointer;margin:3px;">Not Listed Total: <span id="totalNotListed"></span></button>
            
            <div class="card widget-flat text-bg-danger" style="display:none;" id="showNotListed">
                <div class="card-body">
                    {{-- <div class="float-end">
                        <i class="ri-eye-line widget-icon"></i>
                    </div> --}}
                    <h4 class="my-3">Not Listed</h4>
                    <div class="row">
                        <div class="col">
                            <h5 class="text-uppercase mt-0" title="Shopify Not Listed">Shopify: <span id="shopifynotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Amazon: <span id="amazonnotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Walmart: <span id="walmartnotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Reverb: <span id="reverbnotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Shein: <span id="sheinnotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Doba: <span id="dobanotlisted"></span></h5>
                        </div>
                        <div class="col">
                            <h5 class="text-uppercase mt-0" title="Customers">Temu: <span id="temunotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Macy: <span id="macynotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Ebay1: <span id="ebay1notlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Ebay2: <span id="ebay2notlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Ebay3: <span id="ebay3notlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">BestBuyUSA: <span id="bestbuynotlisted"></span></h5>
                            <h5 class="text-uppercase mt-0" title="Customers">Tiendamia: <span id="tiendamianotlisted"></span></h5>
                            {{-- <h5 class="text-uppercase mt-0" title="Customers">Ebay3: <span id="ebay3notlisted"></span></h5> --}}
                        </div>
                    </div>
                    
                    
                    <!-- <p class="mb-0">
                        <span class="badge bg-white bg-opacity-10 me-1">2.97%</span>
                        <span class="text-nowrap">Since last month</span>
                    </p> -->
                </div>
            </div>
        </div> <!-- end col-->

        {{-- <div class="col-xxl-3 col-sm-6">
            <div class="card widget-flat text-bg-danger">
                <div class="card-body">
                    <h4 class="my-3">Not Matching</h4>
                    <h6 class="text-uppercase mt-0" title="Customers">Shopify: <span id="shopifynotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Amazon: <span id="amazonnotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Walmart: <span id="walmartnotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Reverb: <span id="reverbnotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Shein: <span id="sheinnotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Doba: <span id="dobanotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Temu: <span id="temunotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Macy: <span id="macynotmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Ebay1: <span id="ebay1notmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Ebay2: <span id="ebay2notmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Ebay3: <span id="ebay3notmatching"></span></h6>
                </div>
            </div>
        </div> --}}
         <!-- end col-->

         
        <div class="col-xxl-3 col-sm-6 d-none">
            <div class="card widget-flat text-bg-success">
                <div class="card-body">
                    <h4 class="my-3">Listed</h4>
                    <div class="row">
    <div class="col">
        <h5 class="text-uppercase mt-0" title="Customers">Shopify: <span id="shopifylisted"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Amazon: <span id="amazonlisted"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Walmart: <span id="walmartlisted"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Reverb: <span id="reverblisted"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Shein: <span id="sheinlisted"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Doba: <span id="dobalisted"></span></h5>
    </div>
    <div class="col">
                    <h5 class="text-uppercase mt-0" title="Customers">Temu: <span id="temulisted"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Macy: <span id="macylisted"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Ebay1: <span id="ebay1listed"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Ebay2: <span id="ebay2listed"></span></h5>
                    <h5 class="text-uppercase mt-0" title="Customers">Ebay3: <span id="ebay3listed"></span></h5>
    </div>
</div>
                    
                    
                </div>
            </div>
        </div> <!-- end col-->

        {{-- <div class="col-xxl-3 col-sm-6">
            <div class="card widget-flat text-bg-success">
                <div class="card-body">
                    <h4 class="my-3">Matching</h4>
                    <h6 class="text-uppercase mt-0" title="Customers">Shopify: <span id="shopifymatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Amazon: <span id="amazonmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Walmart: <span id="walmartmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Reverb: <span id="reverbmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Shein: <span id="sheinmatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Doba: <span id="dobamatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Temu: <span id="temumatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Macy: <span id="macymatching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Ebay1: <span id="ebay1matching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Ebay2: <span id="ebay2matching"></span></h6>
                    <h6 class="text-uppercase mt-0" title="Customers">Ebay3: <span id="ebay3matching"></span></h6>
                </div>
            </div>
        </div> --}}
         <!-- end col-->
    </div>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-2"><h4 class="header-title">View Missing Listing</h4></div>
                        <div class="col text-start">
                            <button id="withzero" class="btn btn-primary btn-sm">With Zero Inventory</button>
                            <button id="withoutzero" class="btn btn-primary btn-sm">Without Zero Inventory</button>
                        </div>
                    </div>
                    
                    
                   
                   
                    <!-- Controls row -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <!-- Left side controls -->
                        {{-- <div class="form-inline">
                            <div class="form-group mr-2">
                                <label for="row-data-type" class="mr-2">Filter Type:</label>
                                <select id="row-data-type" class="form-control form-control-sm">
                                    <option value="all">All</option>
                                    <option value="matching">Matching</option>
                                    <option value="notmatching">Mismatch</option>                                  
                                </select>
                            </div>
                        </div> --}}
                       

                    <!-- Search on right -->
                        <div class="form-inline">
                            <div class="form-group">
                                <label for="search-input" class="mr-2">Search:</label>
                                <input type="text" id="search-input" class="form-control form-control-sm"
                                    placeholder="Search all columns">
                            </div>
                        </div>
                        <div class="form-inline">
                            {{-- <button id="updatenotrequired" class="btn btn-primary btn-sm">Update Not Required</button> --}}
                            {{-- <button id="reFetchliveData" class="btn btn-primary btn-sm">Refech ALL Live Data</button> --}}

                            <div class="row">
                                <div class="col">
                                    <select id="refetchFilter" class="form-select-sm border border-primary" style="width: 150px;">
                                        <option>🔁 Fetch For</option>
                                        <option value="sku">🔹 SKU (Child)</option>
                                        <option value="shopify">🔸Shopify</option>
                                        <option value="amazon">🔹Amazon</option>
                                        <option value="walmart">🔸Walmart</option>
                                        <option value="reverb">🔹Reverb</option>
                                        <option value="shein">🔸Shein</option>
                                        <option value="doba">🔹Doba</option>
                                        <option value="temu">🔸Temu</option>
                                        <option value="macy">🔹Macy</option>
                                        <option value="ebay1">🔸Ebay1</option>
                                        <option value="ebay2">🔹Ebay2</option>
                                        <option value="ebay3">🔸Ebay3</option>
                                        <option value="bestbuy">🔹BestBuyUSA</option>
                                        <option value="tiendamia">🔸Tiendamia</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <button id="reFetchliveDataFor" class="btn btn-primary btn-sm">Refetch For</button>
                                </div>
                            </div>
                            
                            {{-- <button id="reFetchliveData1" data-source="shopify" class="btn btn-primary btn-sm reFetchliveData">Refech Live Shopify Data</button>
                            <button id="reFetchliveData2" data-source="amazon" class="btn btn-primary btn-sm reFetchliveData">Refech Live Amazon Data</button>
                            <button id="reFetchliveData3" data-source="walmart" class="btn btn-primary btn-sm reFetchliveData">Refech Live Walmart Data</button>
                            <button id="reFetchliveData4" data-source="reverb" class="btn btn-primary btn-sm reFetchliveData">Refech Live Reverb Data</button>
                            <button id="reFetchliveData5" data-source="shein" class="btn btn-primary btn-sm reFetchliveData">Refech Live Shein Data</button>
                            <button id="reFetchliveData6" data-source="doba" class="btn btn-primary btn-sm reFetchliveData">Refech Live Doba Data</button>
                            <button id="reFetchliveData7" data-source="temu" class="btn btn-primary btn-sm reFetchliveData">Refech Live Temu Data</button>
                            <button id="reFetchliveData8" data-source="macy" class="btn btn-primary btn-sm reFetchliveData">Refech Live Macy Data</button>
                            <button id="reFetchliveData9" data-source="ebay1" class="btn btn-primary btn-sm reFetchliveData">Refech Live Ebay1 Data</button>
                            <button id="reFetchliveData10" data-source="ebay2" class="btn btn-primary btn-sm reFetchliveData">Refech Live Ebay2 Data</button>
                            <button id="reFetchliveData11" data-source="ebay3" class="btn btn-primary btn-sm reFetchliveData">Refech Live Ebay3 Data</button> --}}

                        </div>
                    </div>
                    <div class="row">
                        {{-- inventory --}}
                        <div class="col-12">
                            <div class="table-container">
                        <table class="custom-resizable-table" id="inventory-table">
                            <thead>
                                <tr><th colspan="17" class="text-center text-bg-success"><b>Inventory</b></th></tr>                                
                                <tr>
                                     <th style="max-width: 30px;">Not Required</th>
                                    <th style="max-width: 30px;">Image</th>
                                    <th style="max-width: 30px;">Parent</th>
                                    <th data-field="SKU" style="vertical-align: middle; white-space: nowrap;">
                                            <div class="d-flex flex-column align-items-center sortable">
                                                <div class="d-flex align-items-left"> SKU <span class="sort-arrow">↓</span></div>                                                                                       
                                            </div>                                        
                                    </th>

                                                              
                                    {{-- <th data-field="" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                
                                            </div>
                                        </div>
                                    </th> --}}

                                     <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_shopify" class="d-flex flex-column align-items-center sortable">
                                            <div class="d-flex align-items-left">
                                                 Shopify <span class="sort-arrow">↓</span>
                                            </div>
                                                <a href="{{ route('listing.shopifyb2c') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hShopifynl"></span></a>
                                            </div>
                                        </div>
                                    </th>

                                  
                                  
                                     <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_amazon" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Amazon <span class="sort-arrow">↓</span>
                                            </div>                                           
                                            <a href="{{ route('listing.amazon') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hAmazonnl"></span></a>
                                        </div>

                                    </th>
                                     <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_wallmart" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Walmart <span class="sort-arrow">↓</span>
                                            </div>
                                            <a href="{{ route('listing.walmart') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hWalmartnl"></span></a>
                                        </div>
                                    </th>
                                     <th style="vertical-align: middle; white-space: nowrap;">                                        
                                        <div data-field="INV_reverb" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Reverb <span class="sort-arrow">↓</span>
                                            </div>                                            
                                        </div>
                                        <a href="{{ route('listing.reverb') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hReverbnl"></span></a>
                                    </th>
                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_shein" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Shein <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.shein') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hSheinnl"></span></a>
                                        </div>
                                    </th>
                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_doba" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Doba <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.doba') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hDobanl"></span></a>
                                        </div>
                                    </th>
                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_temu" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Temu <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.temu') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hTemunl"></span></a>
                                        </div>
                                    </th>
                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_macy" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Macy <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.macys') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hMacynl"></span></a>
                                        </div>
                                    </th>
                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_ebay1" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Ebay1 <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.ebay') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hEbay1nl"></span></a>
                                        </div>
                                    </th>
                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_ebay2" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Ebay2 <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.ebayTwo') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hEbay2nl"></span></a>
                                        </div>
                                    </th>
                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_ebay3" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Ebay3 <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.ebayThree') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hEbay3nl"></span></a>
                                        </div>
                                    </th>

                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_bestbuy" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                BestBuyUSA <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.bestbuyusa') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="hbestbuynl"></span></a>
                                        </div>
                                    </th>

                                    <th style="vertical-align: middle; white-space: nowrap;">
                                        <div data-field="INV_tiendamia" class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Tiendamia <span class="sort-arrow">↓</span>
                                            </div>                                            
                                            <a href="{{ route('listing.tiendamia') }}" target="_blank"><span class="text-danger text-center mx-auto d-block" id="htiendamianl"></span></a>
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <th>&nbsp;</th>
                                    <th>&nbsp;</th>
                                    <th>&nbsp;</th>
                                    <th>&nbsp;</th>
                                    <th><div class="mt-1 dropdown-search-container">
                                               <select id="filter-shopify" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>                                  
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div> 
                                        <span class="text-danger text-center mx-auto d-block" id="filter-shopify-count"></span>
                                        </th>
                                    <th><div class="d-flex align-items-center">
                                                <select id="filter-amazon" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                        <span class="text-danger text-center mx-auto d-block" id="filter-amazon-count"></span>
                                        </th>
                                            <th><div class="d-flex align-items-center">
                                                <select id="filter-walmart" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>                                                                                                
                                            </div>
                                            <span class="text-danger text-center mx-auto d-block" id="filter-walmart-count"></span>
                                        </th>
                                              <th><div class="d-flex align-items-center">
                                                <select id="filter-reverb" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                            <span class="text-danger text-center mx-auto d-block" id="filter-reverb-count"></span>
                                        </th>
                                             <th><div class="d-flex align-items-center">
                                                <select id="filter-shein" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                        <span class="text-danger text-center mx-auto d-block" id="filter-shein-count"></span>
                                        </th>
                                            <th><div class="d-flex align-items-center">
                                                <select id="filter-doba" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                        <span class="text-danger text-center mx-auto d-block" id="filter-doba-count"></span>
                                        </th>
                                            <th><div class="d-flex align-items-center">
                                                <select id="filter-temu" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                        <span class="text-danger text-center mx-auto d-block" id="filter-temu-count"></span>
                                        </th>
                                              <th><div class="d-flex align-items-center">
                                                <select id="filter-macy" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                            <span class="text-danger text-center mx-auto d-block" id="filter-macy-count"></span>
                                        </th>
                                             <th><div class="d-flex align-items-center">
                                                <select id="filter-ebay1" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                            <span class="text-danger text-center mx-auto d-block" id="filter-ebay1-count"></span>
                                        </th>
                                            <th><div class="d-flex align-items-center">
                                                <select id="filter-ebay2" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                            <span class="text-danger text-center mx-auto d-block" id="filter-ebay2-count"></span>
                                        </th>
                                            <th><div class="d-flex align-items-center">
                                                <select id="filter-ebay3" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                        <span class="text-danger text-center mx-auto d-block" id="filter-ebay3-count"></span>
                                        </th>
                                          <th><div class="d-flex align-items-center">
                                                <select id="filter-bestbuy" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                        <span class="text-danger text-center mx-auto d-block" id="filter-bestbuy-count"></span>
                                        </th>
                                          <th><div class="d-flex align-items-center">
                                                <select id="filter-tiendamia" class="form-control form-control-sm">
                                                    <option value="all">All</option>
                                                    <option value="listed">Listed</option>
                                                    <option value="notlisted">Not Listed</option>
                                                    <option value="nrl">NRL</option>
                                                </select>
                                            </div>
                                        <span class="text-danger text-center mx-auto d-block" id="filter-tiendamia-count"></span>
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
                            <div class="loader-text">Loading data...</div>
                        </div>
                    </div>
                        </div>
                        {{-- inventory --}}
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    <!-- Image Preview -->
    <div id="image-hover-preview" style="display: none; position: fixed; z-index: 1000; pointer-events: none;">
        <img id="preview-image"
            style="max-width: 300px; max-height: 300px; border: 2px solid #ddd; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
    </div>

      <!-- stock Modal -->
    <div class="modal fade modal-draggable" id="stockModal" tabindex="-1" aria-labelledby="stockModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header bg-gradient">
                    <h5 class="modal-title d-flex align-items-center text-dark">
                        <i class="bi bi-bar-chart-line-fill me-2"></i>
                        Missing Listing Analysis for SKU:<span id="stockSkuLabel" class="badge  text-danger m-0 animate__animated animate__fadeIn fw-bold fs-3"></span>                        
                    </h5>
                    <div class="modal-actions">                    
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0">
                    <div class="row g-0">
                        <div class="col-12">  
                            <div id="stockContent" class="p-3" style="color: #000000; width:100%; max-height: 70vh; overflow-y: auto;"></div>                                                        
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
   
  <script>
    let isResizing = false;
    let currentSort = {
        field: null,
        direction: 1
    };
    let tableData = [];
    let filteredData = []; // Added
    let currentPage = 1;   // Added
   let isLoading = false;
   let notlisted=0;

    document.body.style.zoom = "80%";

    $(document).ready(function () {
    
        initTable(1000);

        function initTable() {
            loadData().then(() => {
                filteredData = [...tableData]; // Initialize filteredData
                renderTable(filteredData);
                initResizableColumns();
                initSorting();
                initPagination();
                initSearch();
                initColumnToggle();
            });
        }

        function loadData() {
            showLoader();
            return $.ajax({
                url: '/stock/missing/listing/data',
                type: 'GET',
                dataType: 'json'
            }).then(response => {
                const sheetData = Array.isArray(response.data)
                    ? response.data
                    : Object.values(response.data || {});
                
                datainfo=response.datainfo;                        
                $('#totalNotListed').text(datainfo.shopify.notlisted+datainfo.amazon.notlisted+datainfo.walmart.notlisted+datainfo.reverb.notlisted+datainfo.shein.notlisted+datainfo.doba.notlisted+datainfo.temu.notlisted+datainfo.macy.notlisted+datainfo.ebay1.notlisted+datainfo.ebay2.notlisted+datainfo.ebay3.notlisted);

                $('#shopifynotlisted').text(datainfo.shopify.notlisted);
                if(datainfo.shopify.notlisted==0){
                    $('#hShopifynl').text(`(${datainfo.shopify.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hShopifynl').text(`(${datainfo.shopify.notlisted})`);
                }
                
                
                $('#amazonnotlisted').text(datainfo.amazon.notlisted);
                if(datainfo.amazon.notlisted==0){
                    $('#hAmazonnl').text(`(${datainfo.amazon.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hAmazonnl').text(`(${datainfo.amazon.notlisted})`);
                }

                $('#walmartnotlisted').text(datainfo.walmart.notlisted);
                if(datainfo.walmart.notlisted==0){
                    $('#hWalmartnl').text(`(${datainfo.walmart.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hWalmartnl').text(`(${datainfo.walmart.notlisted})`);
                }

                $('#reverbnotlisted').text(datainfo.reverb.notlisted);
                if(datainfo.reverb.notlisted==0){
                    $('#hReverbnl').text(`(${datainfo.reverb.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hReverbnl').text(`(${datainfo.reverb.notlisted})`);
                }

                $('#sheinnotlisted').text(datainfo.shein.notlisted);
                if(datainfo.shein.notlisted==0){
                    $('#hSheinnl').text(`(${datainfo.shein.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hSheinnl').text(`(${datainfo.shein.notlisted})`);
                }

                $('#dobanotlisted').text(datainfo.doba.notlisted);
                if(datainfo.doba.notlisted==0){
                    $('#hDobanl').text(`(${datainfo.doba.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hDobanl').text(`(${datainfo.doba.notlisted})`);
                }

                $('#temunotlisted').text(datainfo.temu.notlisted);
                if(datainfo.temu.notlisted==0){
                    $('#hTemunl').text(`(${datainfo.temu.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hTemunl').text(`(${datainfo.temu.notlisted})`);
                }

                $('#macynotlisted').text(datainfo.macy.notlisted);
                if(datainfo.macy.notlisted==0){
                    $('#hMacynl').text(`(${datainfo.macy.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hMacynl').text(`(${datainfo.macy.notlisted})`);
                }

                $('#ebay1notlisted').text(datainfo.ebay1.notlisted);
                if(datainfo.ebay1.notlisted==0){
                    $('#hEbay1nl').text(`(${datainfo.ebay1.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hEbay1nl').text(`(${datainfo.ebay1.notlisted})`);
                }


                $('#ebay2notlisted').text(datainfo.ebay2.notlisted);
                if(datainfo.ebay2.notlisted==0){
                    $('#hEbay2nl').text(`(${datainfo.ebay2.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hEbay2nl').text(`(${datainfo.ebay2.notlisted})`);
                }

                $('#ebay3notlisted').text(datainfo.ebay3.notlisted);
                if(datainfo.ebay3.notlisted==0){
                    $('#hEbay3nl').text(`(${datainfo.ebay3.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hEbay3nl').text(`(${datainfo.ebay3.notlisted})`);
                }

                  $('#bestbuylisted').text(datainfo.bestbuy.notlisted);
                if(datainfo.bestbuy.notlisted==0){
                    $('#hbestbuynl').text(`(${datainfo.bestbuy.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#hbestbuynl').text(`(${datainfo.bestbuy.notlisted})`);
                }

                  $('#tiendamialisted').text(datainfo.tiendamia.notlisted);
                if(datainfo.tiendamia.notlisted==0){
                    $('#htiendamianl').text(`(${datainfo.tiendamia.notlisted})`).removeClass('text-danger').css('color', 'green');
                }else{
                    $('#htiendamianl').text(`(${datainfo.tiendamia.notlisted})`);
                }


                // $('#shopifynotmatching').text(datainfo.shopify.mismatching);
                // $('#amazonnotmatching').text(datainfo.amazon.mismatching);
                // $('#walmartnotmatching').text(datainfo.walmart.mismatching);
                // $('#reverbnotmatching').text(datainfo.reverb.mismatching);
                // $('#sheinnotmatching').text(datainfo.shein.mismatching);
                // $('#dobanotmatching').text(datainfo.doba.mismatching);
                // $('#temunotmatching').text(datainfo.temu.mismatching);
                // $('#macynotmatching').text(datainfo.macy.mismatching);
                // $('#ebay1notmatching').text(datainfo.ebay1.mismatching);
                // $('#ebay2notmatching').text(datainfo.ebay2.mismatching);
                // $('#ebay3notmatching').text(datainfo.ebay3.mismatching);

                $('#shopifylisted').text(datainfo.shopify.listed);
                $('#amazonlisted').text(datainfo.amazon.listed);
                $('#walmartlisted').text(datainfo.walmart.listed);
                $('#reverblisted').text(datainfo.reverb.listed);
                $('#sheinlisted').text(datainfo.shein.listed);
                $('#dobalisted').text(datainfo.doba.listed);
                $('#temulisted').text(datainfo.temu.listed);
                $('#macylisted').text(datainfo.macy.listed);
                $('#ebay1listed').text(datainfo.ebay1.listed);
                $('#ebay2listed').text(datainfo.ebay2.listed);
                $('#ebay3listed').text(datainfo.ebay3.listed);
                $('#bestbuylisted').text(datainfo.bestbuy.listed);
                $('#tiendamialisted').text(datainfo.tiendamia.listed);

                // $('#shopifymatching').text(datainfo.shopify.matching);
                // $('#amazonmatching').text(datainfo.amazon.matching);
                // $('#walmartmatching').text(datainfo.walmart.matching);
                // $('#reverbmatching').text(datainfo.reverb.matching);
                // $('#sheinmatching').text(datainfo.shein.matching);
                // $('#dobamatching').text(datainfo.doba.matching);
                // $('#temumatching').text(datainfo.temu.matching);
                // $('#macymatching').text(datainfo.macy.matching);
                // $('#ebay1matching').text(datainfo.ebay1.matching);
                // $('#ebay2matching').text(datainfo.ebay2.matching);
                // $('#ebay3matching').text(datainfo.ebay3.matching);

             tableData = sheetData.map((item, index) => {
    const sku = (item.sku || '').toUpperCase().trim();
    const isparent = item.isparent && item.isparent !== null ? item.isparent : '';
    const parent = item.parent && item.parent !== 'null' ? item.parent : '-';
    const variant_id = item.variant_id;
    const sku_dbid = item.id;
    const image = item.image;

    const INV_shopify = parseFloat(item.inventory_shopify) || 0;
    const INV_amazon = parseFloat(item.inventory_amazon) || 0;
    const INV_walmart = parseFloat(item.inventory_walmart) || 0;
    const INV_reverb = parseFloat(item.inventory_reverb) || 0;
    const INV_shein = parseFloat(item.inventory_shein) || 0;
    const INV_doba = parseFloat(item.inventory_doba) || 0;
    const INV_temu = parseFloat(item.inventory_temu) || 0;
    const INV_macy = parseFloat(item.inventory_macy) || 0;
    const INV_ebay1 = parseFloat(item.inventory_ebay1) || 0;
    const INV_ebay2 = parseFloat(item.inventory_ebay2) || 0;
    const INV_ebay3 = parseFloat(item.inventory_ebay3) || 0;
    const INV_bestbuy = parseFloat(item.inventory_bestbuy) || 0;
    const INV_tiendamia = parseFloat(item.inventory_tiendamia) || 0;

    const notrequired = item.not_required;

//  const allInventories = [
//     INV_shopify, INV_amazon, INV_walmart, INV_reverb, INV_shein,
//     INV_doba, INV_temu, INV_macy, INV_ebay1, INV_ebay2,
//     INV_ebay3, INV_bestbuy, INV_tiendamia
// ];

const allZero = parseFloat(INV_shopify)===0;

// const allNonZero = allInventories.every(inv => parseFloat(inv) > 0);
const allNonZero = parseFloat(INV_shopify)>0

const inventoryStatus = allZero ? 'zero' : allNonZero ? 'nonzero' : 'mixed';

    if (item.inventory_amazon === 'Not Listed') {
        notlisted += 1;
    }
return {
    sku_dbid,
    sl_no: index + 1,
    image,
    variant_id,
    SKU: sku,
    isparent,
    parent,
    TITLE: item.product_title || item.title,
    INV_shopify,
    INV_amazon,
    INV_walmart,
    INV_reverb,
    INV_shein,
    INV_doba,
    INV_temu,
    INV_macy,
    INV_ebay1,
    INV_ebay2,
    INV_ebay3,
    INV_bestbuy,
    INV_tiendamia,
    notrequired,

    // Use the original raw inventory string (item.inventory_...) to determine Not Listed / NRL
    is_notlisted_shopify: item.inventory_shopify === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_shopify: item.inventory_shopify === 'NRL' ? 'nrl' : '',

    is_notlisted_amazon: item.inventory_amazon === 'Not Listed' ? 'notlisted' : 'listed',
    is_notlisted_amazonwzo: allZero && item.inventory_amazon === 'Not Listed' ? 'znl' : 'zl',
    is_notlisted_amazonwo: allNonZero && item.inventory_amazon === 'Not Listed' ? 'wznl' : 'wzl',
    is_nrl_amazon: item.inventory_amazon === 'NRL' ? 'nrl' : '',

    is_notlisted_walmart: item.inventory_walmart === 'Not Listed' ? 'notlisted' : 'listed',
    is_notlisted_walmartwzo: allZero && item.inventory_walmart === 'Not Listed' ? 'znl' : 'zl',
    is_notlisted_walmartwo: allNonZero && item.inventory_walmart === 'Not Listed' ? 'wznl' : 'wzl',
    is_nrl_walmart: item.inventory_walmart === 'NRL' ? 'nrl' : '',

    is_notlisted_reverb: item.inventory_reverb === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_reverb: item.inventory_reverb === 'NRL' ? 'nrl' : '',

    is_notlisted_shein: item.inventory_shein === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_shein: item.inventory_shein === 'NRL' ? 'nrl' : '',

    is_notlisted_doba: item.inventory_doba === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_doba: item.inventory_doba === 'NRL' ? 'nrl' : '',

    is_notlisted_temu: item.inventory_temu === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_temu: item.inventory_temu === 'NRL' ? 'nrl' : '',

    is_notlisted_macy: item.inventory_macy === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_macy: item.inventory_macy === 'NRL' ? 'nrl' : '',

    is_notlisted_ebay1: item.inventory_ebay1 === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_ebay1: item.inventory_ebay1 === 'NRL' ? 'nrl' : '',

    is_notlisted_ebay2: item.inventory_ebay2 === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_ebay2: item.inventory_ebay2 === 'NRL' ? 'nrl' : '',

    is_notlisted_ebay3: item.inventory_ebay3 === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_ebay3: item.inventory_ebay3 === 'NRL' ? 'nrl' : '',

    is_notlisted_bestbuy: item.inventory_bestbuy === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_bestbuy: item.inventory_bestbuy === 'NRL' ? 'nrl' : '',

    is_notlisted_tiendamia: item.inventory_tiendamia === 'Not Listed' ? 'notlisted' : 'listed',
    is_nrl_tiendamia: item.inventory_tiendamia === 'NRL' ? 'nrl' : '',

    matching: INV_shopify === INV_amazon ? 'matching' : 'notmatching',

    inventoryStatus, // ✅ this is what your filter uses
    raw_data: item
};

});
           
                console.log(tableData);
                filteredData=tableData;
                renderTable(filteredData);
                hideLoader();
                return tableData;
            }).catch(error => {
                console.error(" Error loading data:", error);
                hideLoader();
                return [];
            });
        }

        function renderTable(data) {
            // console.log(notlisted);
            
            const $tableBody = $('#inventory-table tbody');
            $tableBody.empty();

            if (!Array.isArray(data) || data.length === 0) {
                $tableBody.append('<tr><td colspan="5" class="text-center">No data found</td></tr>');
                return;
            }

            data.forEach(item => {
                // const isMismatch = item.INV_shopify !== item.INV_amazon!='Not Listed'?parseFloat(item.INV_amazon):item.INV_amazon;
               const isMismatchAmz = item.INV_amazon !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_amazon) ? true:false;
               const isMismatchwal = item.INV_walmart !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_walmart) ? true:false;
               const isMismatchRev = item.INV_reverb !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_reverb) ? true:false;
               const isMismatchShein = item.INV_shein !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_shein) ? true:false;
               const isMismatchDoba = item.INV_doba !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_doba) ? true:false;
               const isMismatchTemu = item.INV_temu !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_temu) ? true:false;
               const isMismatchMacy = item.INV_macy !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_macy) ? true:false;
               const isMismatchEbay1 = item.INV_ebay1 !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_ebay1) ? true:false;
               const isMismatchEbay2 = item.INV_ebay2 !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_ebay2) ? true:false;
               const isMismatchEbay3 = item.INV_ebay3 !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_ebay3) ? true:false;
               const isMismatchBestbuy = item.INV_bestbuy !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_bestbuy) ? true:false;
               const isMismatchTiendamia = item.INV_tiendamia !== 'Not Listed' && parseFloat(item.INV_shopify) === parseFloat(item.INV_tiendamia) ? true:false;




                const isparent=item.isparent==1?true:false;
                const variantid=item.variant_id;
                let style = 'text-align:center;';
                // if (isMismatch) {style += 'color:red;';}
             
                if (isparent === true) {style += 'background:#bde0ff;';}
                const rowClass = `style="${style}"`;
                // const rowClass = isMismatch ? 'style="color:red; text-align:center;"' : 'style="text-align:center;"';
                const checked=item.notrequired==1?'checked':'';
                const image= item.image?`<img src="${item.image}" width="40" height="40" class="product-thumb" style="cursor: pointer;visibility: visible !important;">`:"";
                const parent=item.parent && item.parent !== null ? item.parent : '-';                
                if(item.SKU!=''){
                    
                }
                    const row = `
    <tr ${item.SKU.toLowerCase().includes("parent")?'style="display:none"':''}>
        <td><input type="checkbox" class="checkboxnotrequired" value="${item.SKU}___${item.sku_dbid}" ${checked}/></td>
        <td>${image}</td>
        <td>${parent}</td>
        <td>${item.SKU}
            <button class="float-end btn btn-outline-primary rounded-pill px-3 text-primary showStockModal" id="showStockModal${item.SKU}"
                    style="cursor:pointer !important; background-color: #fff !important"
                    data-sku="${item.SKU}" data-item='${JSON.stringify(item)}'>
                <i class="bi bi-eye me-1"></i>
            </button>
        </td>
        <td style="${item.INV_shopify === 'Not Listed' ? 'color:red;' : ''}">${item.INV_shopify!='Not Listed'?item.INV_shopify:'N/L'}</td>
        <td style="${item.INV_amazon === 'Not Listed' ? 'color:red;' : isMismatchAmz==true?'color:green':'color:red'}">${item.INV_amazon!='Not Listed'?item.INV_amazon:'N/L'}</td>
        <td style="${item.INV_walmart === 'Not Listed' ? 'color:red;' : isMismatchwal==true?'color:green':'color:red'}">${item.INV_walmart!='Not Listed'?item.INV_walmart:'N/L'}</td>
        <td style="${item.INV_reverb === 'Not Listed' ? 'color:red;' : isMismatchRev==true?'color:green':'color:red'}">${item.INV_reverb!='Not Listed'?item.INV_reverb:'N/L'}</td>
        <td style="${item.INV_shein === 'Not Listed' ? 'color:red;' : isMismatchShein==true?'color:green':'color:red'}">${item.INV_shein!='Not Listed'?item.INV_shein:'N/L'}</td>
        <td style="${item.INV_doba === 'Not Listed' ? 'color:red;' : isMismatchDoba==true?'color:green':'color:red'}">${item.INV_doba!='Not Listed'?item.INV_doba:'N/L'}</td>
        <td style="${item.INV_temu === 'Not Listed' ? 'color:red;' : isMismatchTemu==true?'color:green':'color:red'}">${item.INV_temu!='Not Listed'?item.INV_temu:'N/L'}</td>
        <td style="${item.INV_macy === 'Not Listed' ? 'color:red;' : isMismatchMacy==true?'color:green':'color:red'}">${item.INV_macy!='Not Listed'?item.INV_macy:'N/L'}</td>
        <td style="${item.INV_ebay1 === 'Not Listed' ? 'color:red;' : isMismatchEbay1==true?'color:green':'color:red'}">${item.INV_ebay1!='Not Listed'?item.INV_ebay1:'N/L'}</td>
        <td style="${item.INV_ebay2 === 'Not Listed' ? 'color:red;' : isMismatchEbay2==true?'color:green':'color:red'}">${item.INV_ebay2!='Not Listed'?item.INV_ebay2:'N/L'}</td>
        <td style="${item.INV_ebay3 === 'Not Listed' ? 'color:red;' : isMismatchEbay3==true?'color:green':'color:red'}">${item.INV_ebay3!='Not Listed'?item.INV_ebay3:'N/L'}</td>
        <td style="${item.INV_bestbuy === 'Not Listed' ? 'color:red;' : isMismatchBestbuy==true?'color:green':'color:red'}">${item.INV_bestbuy!='Not Listed'?item.INV_bestbuy:'N/L'}</td>
        <td style="${item.INV_tiendamia === 'Not Listed' ? 'color:red;' : isMismatchTiendamia==true?'color:green':'color:red'}">${item.INV_tiendamia!='Not Listed'?item.INV_tiendamia:'N/L'}</td>
    </tr>
    </tr>
    </tr>
`;
                $tableBody.append(row);
            });
            
        }

        

        function initResizableColumns() {
            const $table = $('#inventory-table'); // Correct table ID
            const $headers = $table.find('th');

            $headers.each(function () {
                if (!$(this).find('.resize-handle').length) {
                    $(this).append('<div class="resize-handle"></div>');
                }
            });

            $table.off('mousedown', '.resize-handle').on('mousedown', '.resize-handle', function (e) {
                e.preventDefault();
                e.stopPropagation();
                isResizing = true;
                $(this).addClass('resizing');

                const $th = $(this).parent();
                const columnIndex = $th.index();
                const startX = e.pageX;
                const startWidth = $th.outerWidth();

                $('body').css('user-select', 'none');

                $(document).off('mousemove.resize').on('mousemove.resize', function (e) {
                    if (!isResizing) return;
                    const newWidth = startWidth + (e.pageX - startX);
                    $th.css({ width: newWidth, 'min-width': newWidth, 'max-width': newWidth });
                });

                $(document).off('mouseup.resize').on('mouseup.resize', function () {
                    if (!isResizing) return;
                    $('.resize-handle').removeClass('resizing');
                    $('body').css('user-select', '');
                    isResizing = false;
                    $(document).off('mousemove.resize mouseup.resize');
                });
            });
        }

        function initSorting() {
            const $table = $('#inventory-table');
            $table.find('th div[data-field]').addClass('sortable').off('click').on('click', function (e) {
                if (isResizing) return e.stopPropagation();
                if ($(e.target).is('input, .resize-handle')) return;

                const field = $(this).data('field');
                if (!field) return;

                if (currentSort.field === field) {
                    currentSort.direction *= -1;
                } else {
                    currentSort.field = field;
                    currentSort.direction = 1;
                }

                $('.sort-arrow').text('↓');
                $(this).find('.sort-arrow').text(currentSort.direction === 1 ? '↑' : '↓');

                filteredData.sort((a, b) => {
                    let valA = a[field] ?? '';
                    let valB = b[field] ?? '';

                    const numericFields = ['invshopify', 'invamazon','INV_shopify','INV_amazon'];
                    if (numericFields.includes(field)) {
                        valA = parseFloat(valA) || 0;
                        valB = parseFloat(valB) || 0;
                        return (valA - valB) * currentSort.direction;
                    }

                    // return String(valA).localeCompare(String(valB)) * currentSort.direction;
                    return String(valA).trim().toLowerCase().localeCompare(String(valB).trim().toLowerCase()) * currentSort.direction;
                });

                renderTable(filteredData);
            });
        }

        function initPagination() {
            // Optional: disable if showing all rows
            $('.pagination-controls').hide();
        }

        function initSearch() {
            $('#search-input').off('keyup').on('keyup', function () {
                const term = $(this).val().toLowerCase().trim();
                if (term) {
                    filteredData = tableData.filter(item =>
                        Object.values(item).some(val =>
                            val != null && val.toString().toLowerCase().includes(term)
                        )
                    );
                } else {
                    filteredData = [...tableData];
                }
                renderTable(filteredData);
            });
        }

        function initColumnToggle() {
            const $table = $('#inventory-table'); // Fixed from #ebay-table
            const $headers = $table.find('th[data-field]');
            const $menu = $('#columnToggleMenu');
            const $btn = $('#hideColumnsBtn');

            $menu.empty();
            $headers.each(function () {
                const field = $(this).data('field');
                const title = $(this).text().replace(/ ↑| ↓/g, '').trim();
                const id = `toggle-${field}`;
                $menu.append(`
                    <div class="column-toggle-item">
                        <input type="checkbox" class="column-toggle-checkbox" id="${id}" data-field="${field}" checked>
                        <label for="${id}">${title}</label>
                    </div>
                `);
            });

            $btn.off('click').on('click', e => {
                e.stopPropagation();
                $menu.toggleClass('show');
            });

            $(document).off('click.colToggle').on('click.colToggle', e => {
                if (!$(e.target).closest('.custom-dropdown').length) {
                    $menu.removeClass('show');
                }
            });

            $menu.off('change').on('change', '.column-toggle-checkbox', function () {
                const field = $(this).data('field');
                const visible = $(this).is(':checked');
                const idx = $headers.filter(`[data-field="${field}"]`).index();
                $table.find('tr').each(function () {
                    $(this).find(`td:eq(${idx}), th:eq(${idx})`).toggle(visible);
                });
            });

            $('#showAllColumns').off('click').on('click', () => {
                $menu.find('.column-toggle-checkbox').prop('checked', true).trigger('change');
                $menu.removeClass('show');
            });
        }

        function showLoader() {
            $('#data-loader').fadeIn();
        }

        function hideLoader() {
            $('#data-loader').fadeOut();
        }

        // Optional: add if you need totals (not used in render, but called)
        function calculateTotals() {
            // No-op or implement if needed
        }

        
                $('#row-data-type').on('change', function() {
                    const filterType = $(this).val();
                    applyRowTypeFilter(filterType);
                });
                
function applyRowTypeFilter(filterType) {
    filteredData = [...tableData];
    console.log(filterType);
    if (filterType === 'matching') {
        filteredData = filteredData.filter(item => item.matching === 'matching');
    } else if (filterType === 'notmatching') {
        filteredData = filteredData.filter(item => item.matching === 'notmatching');
    }

    // if (filterType === 'listed') {
    //     filteredData = filteredData.filter(item => item.is_notlisted === 'listed');
    // } else if (filterType === 'notlisted') {
    //     filteredData = filteredData.filter(item => item.is_notlisted === 'notlisted');
    // }

    currentPage = 1;
    renderTable(filteredData);
    calculateTotals();
}


// $('#filter-amazon').on('change', function(e) {
//     e.preventDefault();
//                     const filterType = $(this).val();
//                     applyRowTypeFilterAmazon(filterType);
//                 });
                
// function applyRowTypeFilterAmazon(filterType) {
//    filteredData = [...tableData];
//     console.log(filterType);
   
//     if (filterType === 'listed') {
//         filteredData = filteredData.filter(item => item.is_notlisted_amazon === 'listed');
//     } else if (filterType === 'notlisted') {
//         filteredData = filteredData.filter(item => item.is_notlisted_amazon === 'notlisted');
//     }

//     currentPage = 1;
//     renderTable(filteredData);
//     calculateTotals();
// }

// $('#filter-walmart').on('change', function(e) {
//     e.preventDefault();
//                     const filterType = $(this).val();
//                     applyRowTypeFilterWalmart(filterType);
//                 });
                
// function applyRowTypeFilterWalmart(filterType) {
//    filteredData = [...tableData];
//     console.log(filterType);
   
//     if (filterType === 'listed') {
//         filteredData = filteredData.filter(item => item.is_notlisted_walmart === 'listed');
//     } else if (filterType === 'notlisted') {
//         filteredData = filteredData.filter(item => item.is_notlisted_walmart === 'notlisted');
//     }

//     currentPage = 1;
//     renderTable(filteredData);
//     calculateTotals();
// }


// $('#filter-shopify').on('change', function(e) {
//     e.preventDefault();
//     const filterType = $(this).val();
//     applyRowTypeFilterShopify(filterType);
// });

                
// function applyRowTypeFilterShopify(filterType) {
//      filteredData = [...tableData];
//     console.log(filterType);
   
//     if (filterType === 'listed') {
//         filteredData = filteredData.filter(item => item.is_notlisted_shopify === 'listed');
//     } else if (filterType === 'notlisted') {
//         filteredData = filteredData.filter(item => item.is_notlisted_shopify === 'notlisted');
//     }

//     currentPage = 1;
//     renderTable(filteredData);
//     calculateTotals();
// }

function applyRowTypeFilterA(platform, filterType) {
    let key = '';
    let filteredData = [...tableData];
    let countFiltered = 0;

    // Set the correct key based on filterType
    if (filterType === 'nrl') {
        key = `is_nrl_${platform}`;
    } else if (filterType === 'listed' || filterType === 'notlisted') {
        key = `is_notlisted_${platform}`;
    }

    // Apply filtering
    if (filterType === 'listed' || filterType === 'notlisted' || filterType === 'nrl') {
        filteredData = filteredData.filter(item => item[key] === filterType);
        countFiltered = filteredData.length;
    }

    // Update count display
    const $countEl = $(`#filter-${platform}-count`);
    if (filterType === 'all') {
        $countEl.hide().text('');
    } else {
        $countEl.show().text(countFiltered);
    }

    currentPage = 1;
    console.log(filteredData);    

    renderTable(filteredData);
    calculateTotals();
}




// Generic event binding for all filters
['shopify', 'walmart', 'amazon','reverb','shein','doba','temu','macy','ebay1','ebay2','ebay3','bestbuy','tiendamia'].forEach(platform => {
    $(`#filter-${platform}`).on('change', function(e) {
        e.preventDefault();
        const filterType = $(this).val();
        applyRowTypeFilterA(platform, filterType);
    });
});

function applyRowTypeFilterB(filterInvtype) {
    let filteredData = [];

    if (filterInvtype === 'zero' || filterInvtype === 'nonzero') {
        // Filter rows based on inventoryStatus
        filteredData = tableData.filter(item => item.inventoryStatus === filterInvtype);

        // ✅ Count Amazon Not Listed + Shopify inventory > 0 (only for nonzero filter)
        let amazonNotListedCount = 0;
        if (filterInvtype === 'nonzero') {
            amazonNotListedCount = filteredData.filter(item =>
                item.is_notlisted_amazon === 'notlisted' && parseFloat(item.INV_shopify) > 0
            ).length;
        }

        $('#hAmazonnl').text(amazonNotListedCount);

        // ✅ Count Walmart Not Listed + Shopify inventory > 0 (optional)
        let walmartNotListedCount = 0;
        if (filterInvtype === 'nonzero') {
            walmartNotListedCount = filteredData.filter(item =>
                item.is_notlisted_walmart === 'notlisted' && parseFloat(item.INV_shopify) > 0
            ).length;
        }

        $('#hWalmartnl').text(walmartNotListedCount);
    } else {
        filteredData = [...tableData];
        $('#hAmazonnl').text('');
        $('#hWalmartnl').text('');
    }

    const countFiltered = filteredData.length;
    console.log(`Filtered ${filterInvtype} rows: ${countFiltered}`);

    currentPage = 1;
    renderTable(filteredData);
    calculateTotals();
}

$('#withzero').on('click', function(e) {
    e.preventDefault();
    applyRowTypeFilterB('zero');
});

$('#withoutzero').on('click', function(e) {
    e.preventDefault();
    applyRowTypeFilterB('nonzero');
});


$(document).on('mouseenter', '.product-thumb', function (e) {
    const imgSrc = $(this).attr('src');
    const preview = document.getElementById('image-hover-preview');
    const previewImg = document.getElementById('preview-image');

    if (preview && previewImg && imgSrc) {
        previewImg.src = imgSrc;
        preview.style.display = 'block';
        document.addEventListener('mousemove', moveImagePreview);
    }
});

$(document).on('mouseleave', '.product-thumb', function (e) {
    const preview = document.getElementById('image-hover-preview');
    if (preview) {
        preview.style.display = 'none';
        document.removeEventListener('mousemove', moveImagePreview);
    }
});

function moveImagePreview(e) {
    const preview = document.getElementById('image-hover-preview');
    const rect = preview.getBoundingClientRect();

    // Position preview so its top-left corner aligns with the cursor
    let x = e.pageX;
    let y = e.pageY;

    // Adjust if preview would go off screen
    if (x + rect.width > window.innerWidth) {
        x = window.innerWidth - rect.width-5;
    }
    if (y + rect.height > window.innerHeight) {
        y = window.innerHeight - rect.height-5;
    }

    preview.style.left = x + 'px';
    preview.style.top = y + 'px';
}
    let row='';

    $(document).on('click', '.showStockModal', function () {
    const sku = $(this).data('sku');
    const item = $(this).data('item');
    row =item;
        $('#stockSkuLabel').text("("+sku+")");
    // Render modal content
  $('#stockContent').html(buildStockTable(item));
//   $('#stockContent1').html(buildStockTable1(item));


    const modalEl = document.getElementById('stockModal');
    const modal = new bootstrap.Modal(modalEl);
    const dialogEl = modalEl.querySelector('.modal-dialog');

    // Make modal draggable
    let isDragging = false;
    let currentX = 0, currentY = 0, initialX = 0, initialY = 0, xOffset = 0, yOffset = 0;

    const dragStart = (e) => {
        if (e.target.closest('.modal-header')) {
            isDragging = true;
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }
    };

    const drag = (e) => {
        if (isDragging) {
            e.preventDefault();
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
            xOffset = currentX;
            yOffset = currentY;
            dialogEl.style.transform = `translate(${currentX}px, ${currentY}px)`;
        }
    };

    const dragEnd = () => {
        isDragging = false;
    };

    dialogEl.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', dragEnd);

    modalEl.addEventListener('hidden.bs.modal', () => {
        dialogEl.style.transform = 'none';
        xOffset = 0;
        yOffset = 0;
        dialogEl.removeEventListener('mousedown', dragStart);
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('mouseup', dragEnd);
    });

    modal.show();
});

function buildStockTable(data, showShopify = false) {
    let html = `
        <div class="table-responsive">
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto; position: relative;">
                <table class="table table-sm table-bordered align-middle sortable-table">
                    <thead class="table-light position-sticky" style="top: 0; z-index: 1000;">
                        <tr>
                            <th>Channel</th>
                            <th>Stock Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

    // Always show Shopify
    if(showShopify==true)
    {
        html += `<tr>
        <td>
            <img src="https://inventory.5coremanagement.com/uploads/shopify.png" alt="Shopify" class="channel-logo mb-1" style="width:30px; height:30px; object-fit:contain;">
            <p class="d-inline-block">Shopify</p>
        </td>
        <td>${data.INV_shopify}</td>
    </tr>`;
    }
    

    const channels = [
        { key: 'INV_amazon', name: 'Amazon', img: 'amazon.png' },
        { key: 'INV_walmart', name: 'Walmart', img: 'walmart.png' },
        { key: 'INV_reverb', name: 'Reverb', img: 'reverb.png' },
        { key: 'INV_shein', name: 'Shein', img: 'Shein.jpg' },
        { key: 'INV_doba', name: 'Doba', img: 'doba.png' },
        { key: 'INV_temu', name: 'Temu', img: 'temu.jpeg' },
        { key: 'INV_macy', name: 'Macy', img: 'macy.png' },
        { key: 'INV_ebay1', name: 'Ebay1', img: '1.png' },
        { key: 'INV_ebay2', name: 'Ebay2', img: '2.png' },
        { key: 'INV_ebay3', name: 'Ebay3', img: '3.png' },
        { key: 'INV_bestbuy', name: 'BestBuyUSA', img: 'bestbuy.jpeg' },
        { key: 'INV_tiendamia', name: 'Tiendamia', img: 'ten.jpg' },
    ];

    channels.forEach(channel => {
        const value = data[channel.key];
        const isMismatch = value !== data.INV_shopify;
        const isNotListed = value === 'Not Listed';

      if (!isNotListed) return;

        html += `<tr>
            <td>
                <img src="https://inventory.5coremanagement.com/uploads/${channel.img}" alt="${channel.name}" class="channel-logo mb-1" style="width:50px; height:50px; object-fit:contain;">
                <p class="d-inline-block">${channel.name}</p>
            </td>
            <td style="${isMismatch ? 'color:red;' : ''}">${value=='Not Listed'?'N/L':''}</td>
        </tr>`;
    });

    html += `</tbody></table></div></div>`;
    return html;
}

function buildStockTable1(data, showShopify = true) {
    let html = `
        <div class="table-responsive">
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto; position: relative;">
                <table class="table table-sm table-bordered align-middle sortable-table">
                    <thead class="table-light position-sticky" style="top: 0; z-index: 1000;">
                        <tr>
                            <th>Channel</th>
                            <th>Stock Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

    // Always show Shopify
    if(showShopify==true)
    {
        html += `<tr>
        <td>
            <img src="https://inventory.5coremanagement.com/uploads/shopify.png" alt="Shopify" class="channel-logo mb-1" style="width:30px; height:30px; object-fit:contain;">
            <p class="d-inline-block">Shopify</p>
        </td>
        <td>${data.INV_shopify}</td>
    </tr>`;
    }
    

    const channels = [
        { key: 'INV_amazon', name: 'Amazon', img: 'amazon.png' },
        { key: 'INV_walmart', name: 'Walmart', img: 'walmart.png' },
        { key: 'INV_reverb', name: 'Reverb', img: 'reverb.png' },
        { key: 'INV_shein', name: 'Shein', img: 'Shein.jpg' },
        { key: 'INV_doba', name: 'Doba', img: 'doba.png' },
        { key: 'INV_temu', name: 'Temu', img: 'temu.jpeg' },
        { key: 'INV_macy', name: 'Macy', img: 'macy.png' },
        { key: 'INV_ebay1', name: 'Ebay1', img: '1.png' },
        { key: 'INV_ebay2', name: 'Ebay2', img: '2.png' },
        { key: 'INV_ebay3', name: 'Ebay3', img: '3.png' },
         { key: 'INV_bestbuy', name: 'BestBuyUSA', img: 'bestbuy.jpeg' },
        { key: 'INV_tiendamia', name: 'Tiendamia', img: 'ten.jpg' },
    ];

    channels.forEach(channel => {
        const value = data[channel.key];
        const isMismatch = value !== data.INV_shopify;
        const isNotListed = value === 'Not Listed';

       if (!isMismatch || isNotListed) return;

        html += `<tr>
            <td>
                <img src="https://inventory.5coremanagement.com/uploads/${channel.img}" alt="${channel.name}" class="channel-logo mb-1" style="width:50px; height:50px; object-fit:contain;">
                <p class="d-inline-block">${channel.name}</p>
            </td>
            <td style="${isMismatch ? 'color:red;' : ''}">${value}</td>
        </tr>`;
    });

    html += `</tbody></table></div></div>`;
    return html;
}




$(document).on('click', '#updatenotrequired', function (e) {
    e.preventDefault();

    let getallchecked_nr = [];
    $(".checkboxnotrequired:checked").each(function () {
        getallchecked_nr.push($(this).val());
    });

    $.ajax({
        url: '/stock/mapping/inventory/update_not_required',
        type: 'POST',
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content') // safer than blade token in JS
        },
        data: JSON.stringify({ notrequired: getallchecked_nr })
    })
    .then(response => {
        console.log("Update successful:", response);
          showToast('danger', 'Not Required Updated Successfully');
        // Optionally show success message or refresh UI
    })
    .catch(error => {
        console.error("Error loading data:", error);
        hideLoader();
    });
});


$(document).on('click', '#reFetchliveData', function (e) {
    showLoader();
    e.preventDefault();
     $.ajax({
        url: '/stock/missing/inventory/refetch_live_data',
        type: 'GET',        
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content') // safer than blade token in JS
        },        
    })
    .then(response => {
        console.log("Refetch successful:", response);
        //window.location.reload();
    })
    .catch(error => {
        console.error("Error loading data:", error);
        hideLoader();
    });

});


$(document).on('click', '#reFetchliveDataFor', function (e) {
    e.preventDefault();
    showLoader();

    const source = $("#refetchFilter").val();
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    $.ajax({
        url: '/stock/missing/inventory/refetch_live_data_u',
        type: 'POST',        
         data: {
                source: source,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
        // contentType: 'application/json', // ensures server interprets JSON
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .done(response => {
        console.log('✅ Refetch successful:', response);
        // location.reload(); // reload page on success
    })
    .fail((jqXHR, textStatus, errorThrown) => {
        console.error(`❌ Refetch failed: ${textStatus}`, errorThrown);
        hideLoader(); // hide loader on failure
    });
});

$(document).on('click', '.reFetchliveData', function (e) {
    e.preventDefault();
    showLoader();

    const source = $(this).attr('data-source');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');
    $.ajax({
        url: '/stock/missing/inventory/refetch_live_data_u',
        type: 'POST',        
         data: {
                source: source,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
        // contentType: 'application/json', // ensures server interprets JSON
        headers: {
            'X-CSRF-TOKEN': csrfToken
        }
    })
    .done(response => {
        console.log('✅ Refetch successful:', response);
        location.reload(); // reload page on success
    })
    .fail((jqXHR, textStatus, errorThrown) => {
        console.error(`❌ Refetch failed: ${textStatus}`, errorThrown);
        hideLoader(); // hide loader on failure
    });
});





    $(document).on('click', '#NotListed', function (e) {
        e.preventDefault();
        $('#showNotListed').toggle();
    });

    });
</script>
@endsection
