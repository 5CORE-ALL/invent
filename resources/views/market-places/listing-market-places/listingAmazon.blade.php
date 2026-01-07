@extends('layouts.vertical', ['title' => 'Listing Amazon', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

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

        /* Filter Badge Styles */
        .filter-badge {
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }

        .filter-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .filter-badge.active {
            border: 2px solid #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
            font-weight: bold;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Listing Amazon', 'sub_title' => 'Amazon'])
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <!-- Controls row -->
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <!-- Left side controls - Compact dropdown filters -->
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="d-flex align-items-center gap-1">
                                <label for="row-data-type" class="mb-0" style="font-size: 13px;">Data Type:</label>
                                <select id="row-data-type" class="form-control form-control-sm" style="width: 120px;">
                                    <option value="all">All</option>
                                    <option value="sku" selected>SKU (Child)</option>
                                    <option value="parent">Parent</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <label for="combined-filter" class="mb-0" style="font-size: 13px;">Show:</label>
                                <select id="combined-filter" class="form-control form-control-sm" style="width: 180px;">
                                    <option value="all">All SKUs</option>
                                    <option value="inv">INV (All SKUs with Inventory)</option>
                                    <option value="req-nrl">RL-NRL (RL without Links)</option>
                                    <option value="pending" selected>Pending (Listed=Pending)</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-1">
                                <label for="inv-filter" class="mb-0" style="font-size: 13px;">INV:</label>
                                <select id="inv-filter" class="form-control form-control-sm" style="width: 100px;">
                                    <option value="all">All</option>
                                    <option value="inv-only">INV Only</option>
                                </select>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <!-- Import/Export buttons -->
                            <button type="button" class="btn btn-sm btn-primary" id="import-btn">Import</button>
                            <a href="{{ route('listing_amazon.export') }}" class="btn btn-sm btn-success">Export</a>

                            <!-- Search on right -->
                            <div class="d-flex align-items-center gap-1">
                                <label for="search-input" class="mb-0" style="font-size: 13px;">Search:</label>
                                <input type="text" id="search-input" class="form-control form-control-sm" style="width: 200px;"
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

                                <a href="{{ asset('sample_excel/sample_listing_amazon_file.csv') }}" download class="btn btn-outline-secondary mb-3">📄 Download Sample File</a>

                                <input type="file" id="importFile" name="file" accept=".xlsx,.xls,.csv" class="form-control" />
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="confirmImportBtn">Import</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Badges Section -->
                    <div class="mb-3 p-4" style="background: #f8f9fa; border-radius: 8px; border: 1px solid #dee2e6; min-height: 100px;">
                        <div class="mb-2">
                            <span class="font-weight-bold" style="font-size: 16px;">Filters:</span>
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2" style="flex-wrap: wrap;">
                            <!-- Main Metrics Row -->
                            <span class="badge badge-light filter-badge" data-filter="sku" id="badge-total-sku" style="cursor: pointer; padding: 10px 14px; font-size: 14px;">
                                SKU: <span id="badge-total-sku-count">0</span>
                            </span>
                            <span class="badge badge-light filter-badge" data-filter="batch" id="badge-batch-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px;">
                                Batch: <span id="badge-batch-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="inv" id="badge-inv-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #17a2b8; color: white;">
                                INV: <span id="badge-inv-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="nr" id="badge-nr-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #dc3545; color: white;">
                                NR: <span id="badge-nr-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="rl" id="badge-rl-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #28a745; color: white;">
                                RL: <span id="badge-rl-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="without-link" id="badge-without-link-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #dc3545; color: white;">
                                Without Link: <span id="badge-without-link-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="listed" id="badge-listed-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #28a745; color: white;">
                                Listed: <span id="badge-listed-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="pending" id="badge-pending-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #dc3545; color: white;">
                                Pending: <span id="badge-pending-total-count">0</span>
                            </span>
                            
                            <!-- Status Filters Row -->
                            <span class="badge filter-badge" data-filter="active-status" id="badge-active-status-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #28a745; color: white;">
                                Active: <span id="badge-active-status-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="inactive-status" id="badge-inactive-status-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #dc3545; color: white;">
                                Inactive: <span id="badge-inactive-status-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="missing-status" id="badge-missing-status-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #ffc107; color: #000;">
                                Missing: <span id="badge-missing-status-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="dc-status" id="badge-dc-status-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #6c757d; color: white;">
                                DC: <span id="badge-dc-status-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="upcoming-status" id="badge-upcoming-status-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #17a2b8; color: white;">
                                Upcoming: <span id="badge-upcoming-status-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="2bdc-status" id="badge-2bdc-status-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #fd7e14; color: white;">
                                2BDC: <span id="badge-2bdc-status-total-count">0</span>
                            </span>
                            <span class="badge filter-badge" data-filter="nr-status" id="badge-nr-status-total" style="cursor: pointer; padding: 10px 14px; font-size: 14px; background: #dc3545; color: white;">
                                NR Status: <span id="badge-nr-status-total-count">0</span>
                            </span>
                            
                            <!-- Clear Filters Button - In same line -->
                            <button type="button" class="btn btn-sm btn-secondary" id="clear-filters-btn" style="padding: 10px 16px; margin-left: auto;">
                                <i class="fas fa-times"></i> Clear Filters
                            </button>
                        </div>
                        
                        <!-- Separated Combined Filter Button -->
                        <div class="mt-3 pt-3" style="border-top: 2px solid #dee2e6;">
                            <span class="badge filter-badge" data-filter="missing-inv-combined" id="badge-missing-inv-combined-total" style="cursor: pointer; padding: 16px 22px; font-size: 18px; font-weight: bold; background: #ff9800; color: #000; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                Missing & INV>0: <span id="badge-missing-inv-combined-total-count">0</span>
                            </span>
                        </div>
                    </div>

                    <!-- Daily Metrics Chart Section -->
                    <div class="mb-3 p-4" style="background: #ffffff; border-radius: 8px; border: 1px solid #dee2e6;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 style="font-size: 16px; font-weight: bold; margin-bottom: 0;">Missing & INV>0 Trend (Last 30 Days)</h5>
                            <button id="toggle-chart-btn" class="btn btn-sm btn-secondary">
                                <i class="fas fa-chevron-down" id="chart-toggle-icon"></i>
                            </button>
                        </div>
                        <div id="chart-controls-container" style="display: none;">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <select id="chart-days-select" class="form-control form-control-sm" style="width: 150px;">
                                    <option value="7">Last 7 Days</option>
                                    <option value="30" selected>Last 30 Days</option>
                                    <option value="60">Last 60 Days</option>
                                    <option value="90">Last 90 Days</option>
                                </select>
                                <button id="refresh-chart-btn" class="btn btn-sm btn-primary">Refresh</button>
                            </div>
                        </div>
                        <div id="chart-container" style="position: relative; height: 350px; display: none;">
                            <canvas id="dailyMetricsChart"></canvas>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="custom-resizable-table" id="listing-table">
                            <thead>
                                <tr>
                                    <th data-field="image_path" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Images
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                        </div>
                                    </th>
                                    <th data-field="status" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Status
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                        </div>
                                    </th>
                                    <th data-field="parent" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center sortable-header">
                                                Parent <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
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
                                        </div>
                                    </th>
                                    <th data-field="NR" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                NR/RL
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                        </div>
                                    </th>
                                    <th data-field="links" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center" style="gap: 8px;">
                                                <button id="refresh-links-btn" class="btn btn-sm btn-primary" style="padding: 2px 8px; font-size: 10px; line-height: 1.2;" title="Refresh All Links">
                                                    <i class="fa fa-refresh"></i> Refresh
                                                </button>
                                                <span>Links</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                        </div>
                                    </th>
                                    <th data-field="listed" style="vertical-align: middle; white-space: nowrap; display: none;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Listed/Pending
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                        </div>
                                    </th>
                                    <th data-field="listing_status" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                Amazon Status
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
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
                    <div class="pagination-controls mt-2 d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2">
                            <span id="visible-rows" class="badge badge-light" style="color: #dc3545;">Showing 0-0 of 0</span>
                            <label for="rows-per-page" class="mb-0">Rows per page:</label>
                            <select id="rows-per-page" class="form-control form-control-sm" style="width: 80px;">
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="200">200</option>
                                <option value="500">500</option>
                            </select>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button id="first-page" class="btn btn-sm btn-outline-secondary">First</button>
                            <button id="prev-page" class="btn btn-sm btn-outline-secondary">Previous</button>
                            <span id="page-info" class="mx-2">Page 1 of 1</span>
                            <button id="next-page" class="btn btn-sm btn-outline-secondary">Next</button>
                            <button id="last-page" class="btn btn-sm btn-outline-secondary">Last</button>
                        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.body.style.zoom = "80%";
        $(document).ready(function() {
            // Cache system
            const amazonListingDataCache = {
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
                amazonListingDataCache.clear();
            });

            // Current state
            let currentPage = 1;
            let rowsPerPage = 50;
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
            let currentCombinedFilter = 'pending'; // Track combined filter

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
                    // Apply default filters: SKU only with INACTIVE/MISSING status
                    applyAllFilters();
                    
                    renderTable();
                    initResizableColumns();
                    initSorting();
                    initPagination();
                    initSearch();
                    calculateTotals();
                    initEnhancedDropdowns();
                });
            }   

            // Load data from server
            function loadData() {
                showLoader();
                return $.ajax({
                    url: '/listing_amazon/view-data',
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
                            nr_req: item.nr_req || (parseFloat(item.INV) > 0 ? 'REQ' : 'NR'),
                            listed: item.listed || (parseFloat(item.INV) > 0 ? 'Pending' : 'Listed'),
                            is_parent: item.sku && item.sku.toUpperCase().includes('PARENT')
                        }));

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

                // Flatten grouped data into a single array for pagination
                // We need to keep parent groups together
                const flattenedRows = [];
                sortedParents.forEach(parent => {
                    const items = groupedData[parent];
                    // Sort items within the group so that the PARENT row appears last
                    const sortedItems = items.sort((a, b) => {
                        if (a.sku.includes('PARENT')) return 1; // Move PARENT to the end
                        if (b.sku.includes('PARENT')) return -1; // Move PARENT to the end
                        return 0; // Keep other rows in their original order
                    });
                    sortedItems.forEach(item => {
                        flattenedRows.push(item);
                    });
                });

                // Calculate pagination
                const totalRows = flattenedRows.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                
                // Ensure currentPage is valid
                if (currentPage > totalPages && totalPages > 0) {
                    currentPage = totalPages;
                }
                if (currentPage < 1) {
                    currentPage = 1;
                }

                // Get the slice of data for current page
                const startIndex = (currentPage - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                const paginatedRows = flattenedRows.slice(startIndex, endIndex);

                // Render paginated rows
                let rowIndex = startIndex + 1;
                paginatedRows.forEach(item => {
                    const $row = createTableRow(item, rowIndex++);
                    $tbody.append($row);
                });

                if ($tbody.children().length === 0) {
                    $tbody.append('<tr><td colspan="5" class="text-center">No matching records found</td></tr>');
                }

                updatePaginationInfo(totalRows, totalPages);
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
                    url: "{{ route('listing_amazon.import') }}",
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

                // Images column (first)
                const $imageCell = $('<td>').css('text-align', 'center');
                if (item.image_path) {
                    $imageCell.html(`<img src="${item.image_path}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">`);
                } else {
                    $imageCell.html('-');
                }
                $row.append($imageCell);

                // Status column (from ProductMaster) - Editable dropdown
                const $statusCell = $('<td>').css('text-align', 'center');
                if (!item.sku.includes('PARENT')) {
                    const statusValue = item.status || '';
                    const statusOptions = ['', 'Active', 'DC', '2BDC', 'Sourcing', 'In Transit', 'To Order', 'MFRG'];
                    
                    const $statusDropdown = $('<select>')
                        .addClass('form-select form-select-sm status-select')
                        .attr('data-sku', item.sku)
                        .css({
                            'border': '1px solid #ddd',
                            'text-align': 'center',
                            'cursor': 'pointer',
                            'padding': '2px 4px',
                            'font-size': '14px',
                            'width': '120px',
                            'height': '28px'
                        });
                    
                    statusOptions.forEach(option => {
                        const $option = $('<option>')
                            .val(option)
                            .text(option || '-');
                        if (option === statusValue) {
                            $option.prop('selected', true);
                        }
                        $statusDropdown.append($option);
                    });
                    
                    $statusCell.append($statusDropdown);
                } else {
                    $statusCell.text('-');
                }
                $row.append($statusCell);

                $row.append($('<td>').text(item.parent)); // Parent
                $row.append($('<td>').text(item.sku)); // SKU
                $row.append($('<td>').css('text-align', 'center').text(item.INV)); // INV

                // NR/RL column (matching amazon-tabulator-view format)
                if (!item.sku.includes('PARENT')) {
                    const nrValue = item.NR || 'REQ';
                    let bgColor = '#28a745'; // Green for REQ/RL
                    let textColor = 'black';
                    if (nrValue === 'NR') {
                        bgColor = '#dc3545'; // Red for NR
                        textColor = 'black';
                    }

                    const $nrDropdown = $('<select>')
                        .addClass('form-select form-select-sm nr-select')
                        .attr('data-sku', item.sku)
                        .css({
                            'border': '1px solid #ddd',
                            'text-align': 'center',
                            'cursor': 'pointer',
                            'padding': '2px 4px',
                            'font-size': '16px',
                            'width': '50px',
                            'height': '28px',
                            'background-color': bgColor,
                            'color': textColor
                        })
                        .append(`<option value="REQ" ${nrValue === 'REQ' ? 'selected' : ''}>🟢</option>`)
                        .append(`<option value="NR" ${nrValue === 'NR' ? 'selected' : ''}>🔴</option>`);

                    $row.append($('<td>').css('text-align', 'center').append($nrDropdown));
                } else {
                    $row.append($('<td>').text('')); // Empty cell for parent rows
                }

                // Links column (matching amazon-tabulator-view format)
                const $linkCell = $('<td>').css({
                    'text-align': 'center',
                    'vertical-align': 'middle'
                });
                
                const buyerLink = item.buyer_link || '';
                const sellerLink = item.seller_link || '';
                
                let html = '<div style="display: flex; flex-direction: column; gap: 4px; align-items: center;">';
                
                if (sellerLink) {
                    html += `<a href="${sellerLink}" target="_blank" class="text-info" style="font-size: 12px; text-decoration: none;">
                        <i class="fa fa-link"></i> S Link
                    </a>`;
                }
                
                if (buyerLink) {
                    html += `<a href="${buyerLink}" target="_blank" class="text-success" style="font-size: 12px; text-decoration: none;">
                        <i class="fa fa-link"></i> B Link
                    </a>`;
                }
                
                if (!sellerLink && !buyerLink) {
                    html += '<span class="text-muted" style="font-size: 12px;">-</span>';
                }
                
                // Add edit icon for non-parent rows
                if (!item.sku.includes('PARENT')) {
                    html += `<i class="fas fa-pen text-primary link-edit-icon" style="cursor: pointer; font-size: 12px; margin-top: 4px;" title="Edit Links" data-sku="${item.sku}"></i>`;
                }
                
                html += '</div>';
                $linkCell.html(html);
                
                $row.append($linkCell);

                // Listed/Pending dropdown only for non-parent rows
                if (!item.sku.includes('PARENT')) {
                    const $listedDropdown = $('<select>')
                        .addClass('listed-dropdown form-control form-control-sm')
                        .append('<option value="Listed" class="listed-option">Listed</option>')
                        .append('<option value="Pending" class="pending-option">Pending</option>')
                        .append('<option value="NRL" class="nrl-option">NRL</option>');

                    // If nr_req is 'NR', automatically set listed to 'NRL'
                    const listedValue = (item.nr_req === 'NR') ? 'NRL' : (item.listed || 'Pending');
                    $listedDropdown.val(listedValue);

                    if (listedValue === 'Listed') {
                        $listedDropdown.css('background-color', '#28a745').css('color', 'white');
                    } else if (listedValue === 'Pending') {
                        $listedDropdown.css('background-color', '#dc3545').css('color', 'white');
                    } else if (listedValue === 'NRL') {
                        $listedDropdown.css('background-color', '#6c757d').css('color', 'white');
                    }

                    $row.append($('<td>').css('display', 'none').append($listedDropdown));
                } else {
                    $row.append($('<td>').css('display', 'none').text('')); // Empty cell for parent rows
                }

                // Listing Status column (Amazon Status)
                const $amazonStatusCell = $('<td>').css('text-align', 'center');
                // If NR is set to 'NR', show NR instead of MISSING
                if (item.NR === 'NR') {
                    $amazonStatusCell.html('<span style="background:#dc3545; color:white; padding:8px 18px; border-radius:8px; font-weight:600; font-size:15px;">NR</span>');
                } else if (item.listing_status) {
                    let statusBadge = '';
                    if (item.listing_status === 'ACTIVE') {
                        statusBadge = '<span style="background:#28a745; color:white; padding:8px 18px; border-radius:8px; font-weight:600; font-size:15px;">ACTIVE</span>';
                    } else if (item.listing_status === 'INACTIVE') {
                        statusBadge = '<span style="background:#dc3545; color:white; padding:8px 18px; border-radius:8px; font-weight:600; font-size:15px;">INACTIVE</span>';
                    } else {
                        statusBadge = '<span style="background:#6c757d; color:white; padding:8px 18px; border-radius:8px; font-weight:600; font-size:15px;">' + item.listing_status + '</span>';
                    }
                    $amazonStatusCell.html(statusBadge);
                } else {
                    $amazonStatusCell.html('<span style="background:#ffc107; color:#000; padding:8px 18px; border-radius:8px; font-weight:600; font-size:15px;">MISSING</span>');
                }
                $row.append($amazonStatusCell);

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
                // Set initial value for rows-per-page selector
                $('#rows-per-page').val(rowsPerPage);
                
                // Rows per page selector
                $('#rows-per-page').on('change', function() {
                    rowsPerPage = parseInt($(this).val());
                    currentPage = 1; // Reset to first page when changing rows per page
                    renderTable();
                });

                // Pagination buttons
                $('#first-page').on('click', function() {
                    currentPage = 1;
                    renderTable();
                });

                $('#prev-page').on('click', function() {
                    if (currentPage > 1) {
                        currentPage--;
                        renderTable();
                    }
                });

                $('#next-page').on('click', function() {
                    const totalRows = filteredData.length;
                    const totalPages = Math.ceil(totalRows / rowsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderTable();
                    }
                });

                $('#last-page').on('click', function() {
                    const totalRows = filteredData.length;
                    const totalPages = Math.ceil(totalRows / rowsPerPage);
                    currentPage = totalPages > 0 ? totalPages : 1;
                    renderTable();
                });
            }

            function updatePaginationInfo(totalRows = 0, totalPages = 1) {
                // Show pagination controls
                $('.pagination-controls').show();

                // Calculate display range
                const startRow = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
                const endRow = Math.min(currentPage * rowsPerPage, totalRows);

                // Update visible rows info
                $('#visible-rows').text(`Showing ${startRow}-${endRow} of ${totalRows}`);

                // Update page info
                $('#page-info').text(`Page ${currentPage} of ${totalPages}`);

                // Enable/disable pagination buttons
                $('#first-page, #prev-page').prop('disabled', currentPage === 1);
                $('#next-page, #last-page').prop('disabled', currentPage === totalPages || totalPages === 0);
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
                        nrTotal: 0, // Count for NR (from NR field)
                        rlTotalNR: 0, // Count for RL (from NR field)
                        withoutLinkTotal: 0,
                        listedTotal: 0,
                        pendingTotal: 0,
                        activeStatusTotal: 0,
                        inactiveStatusTotal: 0,
                        missingStatusTotal: 0,
                        missingInvCombinedTotal: 0, // Count for Missing & INV>0 combined filter
                        nrStatusTotal: 0, // Count for NR from listing status
                        rowCount: 0,
                        // Status counts (from ProductMaster)
                        statusAll: 0,
                        statusActive: 0,
                        statusInactive: 0,
                        statusDC: 0,
                        statusUpcoming: 0,
                        status2BDC: 0,
                        statusMissing: 0
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
                            // nr_req = 'NR' means NRL (red)
                            if (item.nr_req === 'NR') {
                                metrics.nrlTotal++;
                            } else {
                                // Default to RL if nr_req is 'REQ' or any other value
                                metrics.rlTotal++;
                            }
                            
                            // For NR/RL column: Count based on NR field (matching amazon-tabulator-view)
                            // NR = 'NR' means NR (red)
                            // NR = 'REQ' means RL (green)
                            if (item.NR === 'NR') {
                                metrics.nrTotal++;
                            } else {
                                // Default to RL if NR is 'REQ' or any other value
                                metrics.rlTotalNR++;
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
                            // If NR is set to 'NR', count it as NR status
                            if (item.NR === 'NR') {
                                metrics.nrStatusTotal++;
                            } else if (item.listing_status === 'ACTIVE') {
                                metrics.activeStatusTotal++;
                            } else if (item.listing_status === 'INACTIVE') {
                                metrics.inactiveStatusTotal++;
                            } else if (!item.listing_status) {
                                metrics.missingStatusTotal++;
                            }
                            
                            // Count Missing & INV>0 combined filter
                            // Items with INV > 0 AND missing status (!listing_status && NR !== 'NR')
                            if (parseFloat(item.INV) > 0 && !item.listing_status && item.NR !== 'NR') {
                                metrics.missingInvCombinedTotal++;
                            }
                            
                            // Count Status column values (from ProductMaster)
                            metrics.statusAll++;
                            if (item.status) {
                                const statusLower = item.status.toLowerCase();
                                if (statusLower === 'active') {
                                    metrics.statusActive++;
                                } else if (statusLower === 'inactive') {
                                    metrics.statusInactive++;
                                } else if (statusLower === 'dc') {
                                    metrics.statusDC++;
                                } else if (statusLower === 'upcoming') {
                                    metrics.statusUpcoming++;
                                } else if (statusLower === '2bdc') {
                                    metrics.status2BDC++;
                                }
                            } else {
                                metrics.statusMissing++;
                            }
                        } else {
                            // Count parent rows for Batch total
                            metrics.batchTotal++;
                        }
                    });

                    // Update badge counts at top
                    $('#badge-total-sku-count').text(metrics.totalSku);
                    $('#badge-batch-total-count').text(metrics.batchTotal);
                    $('#badge-inv-total-count').text(metrics.invTotal.toLocaleString());
                    $('#badge-nr-total-count').text(metrics.nrTotal); // Red badge for NR
                    $('#badge-rl-total-count').text(metrics.rlTotalNR); // Green badge for RL
                    $('#badge-without-link-total-count').text(metrics.withoutLinkTotal);
                    $('#badge-listed-total-count').text(metrics.listedTotal); // Green
                    $('#badge-pending-total-count').text(metrics.pendingTotal); // Red
                    $('#badge-active-status-total-count').text(metrics.activeStatusTotal); // Green
                    $('#badge-inactive-status-total-count').text(metrics.inactiveStatusTotal); // Red
                    $('#badge-missing-status-total-count').text(metrics.missingStatusTotal); // Yellow
                    $('#badge-missing-inv-combined-total-count').text(metrics.missingInvCombinedTotal); // Orange - Combined filter
                    $('#badge-dc-status-total-count').text(metrics.statusDC); // DC status
                    $('#badge-upcoming-status-total-count').text(metrics.statusUpcoming); // Upcoming status
                    $('#badge-2bdc-status-total-count').text(metrics.status2BDC); // 2BDC status
                    $('#badge-nr-status-total-count').text(metrics.nrStatusTotal); // NR from listing status
                } catch (error) {
                    console.error('Error in calculateTotals:', error);
                    resetMetricsToZero();
                }
            }

            function resetMetricsToZero() {
                $('#badge-total-sku-count').text('0');
                $('#badge-batch-total-count').text('0');
                $('#badge-inv-total-count').text('0');
                $('#badge-nr-total-count').text('0');
                $('#badge-rl-total-count').text('0');
                $('#badge-without-link-total-count').text('0');
                $('#badge-listed-total-count').text('0');
                $('#badge-pending-total-count').text('0');
                $('#badge-active-status-total-count').text('0');
                $('#badge-inactive-status-total-count').text('0');
                $('#badge-missing-status-total-count').text('0');
                $('#badge-missing-inv-combined-total-count').text('0');
                $('#badge-dc-status-total-count').text('0');
                $('#badge-upcoming-status-total-count').text('0');
                $('#badge-2bdc-status-total-count').text('0');
                $('#badge-nr-status-total-count').text('0');
            }
            
            // Track active filters
            let activeFilters = new Set();
            
            // Unified function to apply all filters (both dropdown and badge)
            function applyAllFiltersUnified() {
                // Start with all data
                filteredData = [...tableData];
                
                // Apply badge filters first
                activeFilters.forEach(filter => {
                    switch(filter) {
                        case 'sku':
                            filteredData = filteredData.filter(item => !item.sku.includes('PARENT'));
                            break;
                        case 'batch':
                            filteredData = filteredData.filter(item => item.sku.includes('PARENT'));
                            break;
                        case 'inv':
                            filteredData = filteredData.filter(item => parseFloat(item.INV) > 0);
                            break;
                        case 'nr':
                            filteredData = filteredData.filter(item => item.NR === 'NR');
                            break;
                        case 'rl':
                            filteredData = filteredData.filter(item => item.NR === 'REQ' || !item.NR || item.NR !== 'NR');
                            break;
                        case 'without-link':
                            filteredData = filteredData.filter(item => !item.buyer_link && !item.seller_link);
                            break;
                        case 'listed':
                            filteredData = filteredData.filter(item => item.listed === 'Listed');
                            break;
                        case 'pending':
                            filteredData = filteredData.filter(item => item.listed === 'Pending');
                            break;
                        case 'active-status':
                            filteredData = filteredData.filter(item => item.listing_status === 'ACTIVE');
                            break;
                        case 'inactive-status':
                            filteredData = filteredData.filter(item => item.listing_status === 'INACTIVE');
                            break;
                        case 'missing-status':
                            filteredData = filteredData.filter(item => !item.listing_status && item.NR !== 'NR');
                            break;
                        case 'missing-inv-combined':
                            // Combined filter: Missing status AND INV > 0 (excluding parent SKUs)
                            filteredData = filteredData.filter(item => 
                                !item.sku.includes('PARENT') &&
                                parseFloat(item.INV) > 0 && 
                                !item.listing_status && 
                                item.NR !== 'NR'
                            );
                            break;
                        case 'dc-status':
                            filteredData = filteredData.filter(item => {
                                if (!item.status) return false;
                                return item.status.toLowerCase() === 'dc';
                            });
                            break;
                        case 'upcoming-status':
                            filteredData = filteredData.filter(item => {
                                if (!item.status) return false;
                                return item.status.toLowerCase() === 'upcoming';
                            });
                            break;
                        case '2bdc-status':
                            filteredData = filteredData.filter(item => {
                                if (!item.status) return false;
                                return item.status.toLowerCase() === '2bdc';
                            });
                            break;
                        case 'nr-status':
                            filteredData = filteredData.filter(item => item.NR === 'NR');
                            break;
                    }
                });
                
                // Skip dropdown filters if missing-inv-combined filter is active (to match count)
                const hasCombinedFilter = activeFilters.has('missing-inv-combined');
                
                if (!hasCombinedFilter) {
                    // Apply dropdown filters on top of badge filters
                    // Combined filter
                    if (currentCombinedFilter === 'inv') {
                        filteredData = filteredData.filter(item => parseFloat(item.INV) > 0);
                    } else if (currentCombinedFilter === 'req-nrl') {
                        filteredData = filteredData.filter(item => 
                            item.nr_req === 'REQ' && !item.buyer_link && !item.seller_link
                        );
                    } else if (currentCombinedFilter === 'pending') {
                        filteredData = filteredData.filter(item => item.listed === 'Pending');
                    }
                    
                    // Data Type filter
                    if (currentDataTypeFilter === 'parent') {
                        filteredData = filteredData.filter(item => item.is_parent);
                    } else if (currentDataTypeFilter === 'sku') {
                        filteredData = filteredData.filter(item => 
                            !item.is_parent && 
                            (item.listing_status === 'INACTIVE' || !item.listing_status)
                        );
                    }
                    
                    // INV filter
                    if (currentInvFilter === 'inv-only') {
                        filteredData = filteredData.filter(item => parseFloat(item.INV) > 0);
                    }
                } else {
                    // When combined filter is active, only apply Data Type filter to exclude parents (already done in badge filter)
                    // Skip other dropdown filters to match the count exactly
                }
                
                currentPage = 1;
                renderTable();
                calculateTotals();
            }
            
            // Function to apply badge filter
            function applyBadgeFilter(filterType) {
                // Map filter types to badge IDs
                const badgeIdMap = {
                    'sku': 'badge-total-sku',
                    'batch': 'badge-batch-total',
                    'inv': 'badge-inv-total',
                    'nr': 'badge-nr-total',
                    'rl': 'badge-rl-total',
                    'without-link': 'badge-without-link-total',
                    'listed': 'badge-listed-total',
                    'pending': 'badge-pending-total',
                    'active-status': 'badge-active-status-total',
                    'inactive-status': 'badge-inactive-status-total',
                    'missing-status': 'badge-missing-status-total',
                    'missing-inv-combined': 'badge-missing-inv-combined-total',
                    'dc-status': 'badge-dc-status-total',
                    'upcoming-status': 'badge-upcoming-status-total',
                    '2bdc-status': 'badge-2bdc-status-total',
                    'nr-status': 'badge-nr-status-total'
                };
                
                const badgeId = badgeIdMap[filterType];
                if (!badgeId) return;
                
                // Toggle filter
                if (activeFilters.has(filterType)) {
                    activeFilters.delete(filterType);
                    $(`#${badgeId}`).removeClass('active');
                } else {
                    activeFilters.add(filterType);
                    $(`#${badgeId}`).addClass('active');
                }
                
                // Apply all filters (both badge and dropdown)
                applyAllFiltersUnified();
            }
            
            // Function to clear all filters
            function clearAllFilters() {
                activeFilters.clear();
                $('.filter-badge').removeClass('active');
                // Reset dropdown filters to 'all'
                $('#row-data-type').val('sku');
                $('#combined-filter').val('pending');
                $('#inv-filter').val('all');
                $('#search-input').val('');
                // Reset filter state variables
                currentDataTypeFilter = 'sku';
                currentInvFilter = 'all';
                currentCombinedFilter = 'pending';
                // Apply all filters (which will show all data since all filters are reset)
                applyAllFiltersUnified();
            }
            
            // Add click handlers for filter badges
            $(document).on('click', '.filter-badge', function() {
                const filterType = $(this).data('filter');
                applyBadgeFilter(filterType);
            });
            
            // Clear filters button
            $('#clear-filters-btn').on('click', function() {
                clearAllFilters();
            });

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
                    currentDataTypeFilter = $(this).val();
                    applyAllFiltersUnified();
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

            function applyAllFilters() {
                // Use the unified filter function
                applyAllFiltersUnified();
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
                currentCombinedFilter = $(this).val();
                applyAllFiltersUnified();
            });

            // Handle INV filter change
            $('#inv-filter').on('change', function() {
                currentInvFilter = $(this).val();
                applyAllFiltersUnified();
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
                    url: '/listing_amazon/save-status',
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

                saveStatusToDB(sku, {
                    listed
                });
            });

            // Handle NR/RL dropdown change (matching amazon-tabulator-view)
            $(document).on('change', '.nr-select', function() {
                const $row = $(this).closest('tr');
                const sku = $(this).data('sku');
                const nrValue = $(this).val();
                
                // Update color based on selection
                if (nrValue === 'REQ') {
                    $(this).css('background-color', '#28a745').css('color', 'black');
                } else if (nrValue === 'NR') {
                    $(this).css('background-color', '#dc3545').css('color', 'black');
                    
                    // When NR is selected, also change Listed dropdown to NRL
                    const $listedDropdown = $row.find('.listed-dropdown');
                    if ($listedDropdown.length) {
                        $listedDropdown.val('NRL');
                        $listedDropdown.css('background-color', '#6c757d').css('color', 'white');
                    }
                }

                // Sync with nr_req field - map NR to nr_req format
                const nr_req = (nrValue === 'NR') ? 'NR' : 'REQ';
                
                // Update the local tableData
                const item = tableData.find(row => row.sku === sku);
                if (item) {
                    item.NR = nrValue;
                    item.nr_req = nr_req;
                }

                // Prepare data to save
                const saveData = {
                    nr_req: nr_req
                };
                
                // If NR is selected, also update listed to NRL
                if (nrValue === 'NR') {
                    saveData.listed = 'NRL';
                }

                // Save to database
                saveStatusToDB(sku, saveData);
            });

            // Handle Status dropdown change
            $(document).on('change', '.status-select', function() {
                const $select = $(this);
                const sku = $select.data('sku');
                const statusValue = $select.val();
                
                // Store original value for error revert
                const item = tableData.find(row => row.sku === sku);
                const originalStatus = item ? (item.status || '') : '';
                
                // Update the local tableData
                if (item) {
                    item.status = statusValue || null;
                }
                
                // Save to ProductMaster Values field
                $.ajax({
                    url: '/listing_amazon/save-status',
                    type: 'POST',
                    data: {
                        sku: sku,
                        status: statusValue || '',
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        showNotification('success', 'Status saved!');
                        // Recalculate totals and re-render if needed
                        calculateTotals();
                    },
                    error: function(xhr) {
                        showNotification('danger', 'Save failed!');
                        // Revert the dropdown value on error
                        $select.val(originalStatus);
                        // Revert tableData
                        if (item) {
                            item.status = originalStatus || null;
                        }
                    }
                });
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

            // Refresh Links Button Handler
            $('#refresh-links-btn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                // Disable button and show loading state
                $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Refreshing...');
                
                // Show notification
                if (typeof showToast === 'function') {
                    showToast('info', 'Refreshing links for all SKUs...');
                } else {
                    alert('Refreshing links for all SKUs...');
                }
                
                $.ajax({
                    url: '/amazon/refresh-links',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        update_all: true
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            const message = response.message || `Successfully updated ${response.updated || 0} links`;
                            
                            if (typeof showToast === 'function') {
                                showToast('success', message);
                            } else {
                                alert(message);
                            }
                            
                            // Reload table data
                            if (typeof loadTableData === 'function') {
                                loadTableData();
                            } else {
                                // Fallback: reload page
                                setTimeout(function() {
                                    location.reload();
                                }, 1000);
                            }
                        } else {
                            const errorMsg = response.message || 'Failed to refresh links';
                            if (typeof showToast === 'function') {
                                showToast('error', errorMsg);
                            } else {
                                alert(errorMsg);
                            }
                        }
                    },
                    error: function(xhr) {
                        const error = xhr.responseJSON?.message || 'Failed to refresh links';
                        if (typeof showToast === 'function') {
                            showToast('error', error);
                        } else {
                            alert(error);
                        }
                    },
                    complete: function() {
                        // Re-enable button
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });

            // Daily Metrics Chart
            let dailyMetricsChartInstance = null;

            function initDailyMetricsChart(days = 30) {
                fetch(`/listing_amazon/daily-metrics?days=${days}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success' && result.data && result.data.length > 0) {
                            const dates = result.data.map(item => {
                                const date = new Date(item.date);
                                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            });
                            const counts = result.data.map(item => item.count);

                            // Destroy existing chart if it exists
                            if (dailyMetricsChartInstance) {
                                dailyMetricsChartInstance.destroy();
                            }

                            // Create line chart
                            const ctx = document.getElementById('dailyMetricsChart');
                            if (!ctx) {
                                console.warn('Chart canvas not found');
                                return;
                            }

                            dailyMetricsChartInstance = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: dates,
                                    datasets: [{
                                        label: 'Missing & INV>0 Count',
                                        data: counts,
                                        borderColor: 'rgba(255, 152, 0, 1)',
                                        backgroundColor: 'rgba(255, 152, 0, 0.1)',
                                        borderWidth: 3,
                                        pointRadius: 4,
                                        pointBackgroundColor: 'rgba(255, 152, 0, 1)',
                                        pointBorderColor: '#fff',
                                        pointBorderWidth: 2,
                                        tension: 0.4,
                                        fill: true
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'top'
                                        },
                                        tooltip: {
                                            mode: 'index',
                                            intersect: false
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                precision: 0,
                                                stepSize: 1
                                            },
                                            title: {
                                                display: true,
                                                text: 'Count'
                                            }
                                        },
                                        x: {
                                            title: {
                                                display: true,
                                                text: 'Date'
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
                        } else {
                            console.warn('No chart data available');
                            // Show message if no data
                            const ctx = document.getElementById('dailyMetricsChart');
                            if (ctx) {
                                const ctx2d = ctx.getContext('2d');
                                ctx2d.clearRect(0, 0, ctx.width, ctx.height);
                                ctx2d.font = '16px Arial';
                                ctx2d.fillStyle = '#666';
                                ctx2d.textAlign = 'center';
                                ctx2d.fillText('No data available', ctx.width / 2, ctx.height / 2);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error loading chart data:', error);
                    });
            }

            // Toggle chart visibility
            function toggleChart() {
                const chartContainer = $('#chart-container');
                const chartControls = $('#chart-controls-container');
                const toggleIcon = $('#chart-toggle-icon');
                
                if (chartContainer.is(':visible')) {
                    // Minimize
                    chartContainer.slideUp(300);
                    chartControls.slideUp(300);
                    toggleIcon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
                } else {
                    // Maximize
                    chartControls.slideDown(300);
                    chartContainer.slideDown(300, function() {
                        // Initialize chart if not already initialized
                        if (!dailyMetricsChartInstance) {
                            const days = parseInt($('#chart-days-select').val());
                            initDailyMetricsChart(days);
                        }
                    });
                    toggleIcon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
                }
            }

            // Initialize chart on page load
            $(document).ready(function() {
                // Chart is minimized by default, so don't initialize it automatically
                // Only initialize when user expands it

                // Handle toggle button click
                $('#toggle-chart-btn').on('click', function() {
                    toggleChart();
                });

                // Handle chart days select change
                $('#chart-days-select').on('change', function() {
                    const days = parseInt($(this).val());
                    if (dailyMetricsChartInstance) {
                        initDailyMetricsChart(days);
                    }
                });

                // Handle refresh button click
                $('#refresh-chart-btn').on('click', function() {
                    const days = parseInt($('#chart-days-select').val());
                    if (dailyMetricsChartInstance) {
                        initDailyMetricsChart(days);
                    } else {
                        // If chart is visible but not initialized, initialize it
                        if ($('#chart-container').is(':visible')) {
                            initDailyMetricsChart(days);
                        }
                    }
                });
            });
        });
    </script>
@endsection
