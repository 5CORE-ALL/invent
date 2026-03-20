@extends('layouts.vertical', ['title' => 'Forecast', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
            background-color: rgba(0, 225, 255, 0.1) !important;
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

        /* Wider modal */
        #monthlyDetailsModal .modal-dialog {
            max-width: 90vw;
        }

        /* Grid layout */
        #monthlyDetailsModal .month-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1rem;
        }

        #monthlyDetailsModal .month-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1rem;
        }

        /* Individual month cards */
        #monthlyDetailsModal .month-card {
            min-width: 0;
        }

        /* Larger text and spacing */
        #monthlyDetailsModal .card-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        #monthlyDetailsModal .card-text {
            font-size: 1.3rem;
            font-weight: 700;
            padding-top: 0.25rem;
        }

        /* Optional: increase padding inside each card */
        #monthlyDetailsModal .card-body {
            padding: 1rem;
        }

        #monthlyDetailsModal .modal-header .close span {
            font-size: 2rem;         
            font-weight: bold;      
            line-height: 1;
        }

        @media (max-width: 768px) {
            #monthlyDetailsModal .month-grid {
                grid-template-columns: repeat(2, 1fr); /* 2 cards per row on mobile */
            }
        }

        #amzDetailsModal .modal-dialog {
            max-width: 75vw;
        }

        /* Grid layout instead of scroll */
        #amzDetailsModal .d-flex.flex-nowrap {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            overflow-x: hidden !important;
        }

        /* Card improvements */
        #amzDetailsModal .card-body {
            padding: 1rem;
        }

        #amzDetailsModal .card-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        #amzDetailsModal .card-text {
            font-size: 1.3rem;
            font-weight: 700;
            padding-top: 0.25rem;
        }

        /* Bigger close icon */
        #amzDetailsModal .modal-header .close span {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }

        /* Responsive columns for small devices */
        @media (max-width: 768px) {
            #amzDetailsModal .d-flex.flex-nowrap {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        #cbmDetailsModal .modal-dialog {
            max-width: 50vw;
        }

        /* Grid layout for cards */
        #cbmDetailsModal .d-flex.flex-nowrap {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            overflow-x: hidden !important;
        }

        /* Card styling */
        #cbmDetailsModal .card-body {
            padding: 1rem;
        }

        #cbmDetailsModal .card-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        #cbmDetailsModal .card-text {
            font-size: 1.3rem;
            font-weight: 700;
            padding-top: 0.25rem;
        }

        /* Bigger close icon */
        #cbmDetailsModal .modal-header .close span {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }

        /* Responsive behavior */
        @media (max-width: 768px) {
            #cbmDetailsModal .d-flex.flex-nowrap {
                grid-template-columns: repeat(2, 1fr);
            }
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
    @include('layouts.shared/page-title', ['page_title' => 'Forecast Analysis', 'sub_title' => 'Forecast'])
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Forecast Analysis</h4>

                    <!-- Custom Dropdown Filters Row -->
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <!-- Dil% Filter -->
                        <div class="dropdown manual-dropdown-container">
                            <button class="btn btn-light dropdown-toggle" type="button" id="dilFilterDropdown">
                                <span class="status-circle default"></span>DIL%
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="dilFilterDropdown">
                                <li><a class="dropdown-item column-filter" href="#" data-column="Dil%"
                                        data-color="all">
                                        <span class="status-circle default"></span> All OV DIL</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="Dil%"
                                        data-color="red">
                                        <span class="status-circle red"></span> Red</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="Dil%"
                                        data-color="yellow">
                                        <span class="status-circle yellow"></span> Yellow</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="Dil%"
                                        data-color="green">
                                        <span class="status-circle green"></span> Green</a></li>
                                <li><a class="dropdown-item column-filter" href="#" data-column="Dil%"
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
                                        <h4 class="modal-title" id="createTaskModalLabel">📝 Create New Task to Purchase</h4>
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
                        {{-- <button id="close-all-modals" class="btn btn-danger btn-sm" style="display: none;">
                            <i class="fas fa-times"></i> Close All Modals
                        </button> --}}

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
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="form-group custom-dropdown">
                                <a href="{{route('tobedc.list')}}" class="btn btn-sm" style="background-color: #008cff; color: #ffffff;">
                                    TO BE DC <i class="fas fa-arrow-right"></i>
                                </a>
                                <div class="custom-dropdown-menu" id="columnToggleMenu"></div>
                            </div>
                            <div class="form-group">
                                <button id="showAllColumns" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye me-1"></i>Show All
                                </button>
                            </div>
                            <div class="form-group custom-dropdown">
                                <button id="hideColumnsBtn" class="btn btn-sm btn-secondary">
                                    <i class="fas fa-eye-slash me-1"></i>Hide Columns
                                </button>
                                <div class="custom-dropdown-menu" id="columnToggleMenu"></div>
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
                        <table class="custom-resizable-table" id="amazon-table">
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
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                INV <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="metric-total" id="inv-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ov_l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                OV L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="metric-total" id="ovl30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ov_dil" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                DIL <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="month_view" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Month View <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="msl" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                MSL <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="s_msl" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                S-MSL <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="order_given" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            ORDER given <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="transit"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Transit <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="2order"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            2 ORDER <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="pft" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Approved QTY <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="tpft" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            AMZ PRICE <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="amz_pft" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            AMZ PFT% <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="roi" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            ROI <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="comp" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            COMP <span class="sort-arrow">↓</span>
                                            </div>

                                        </div>
                                    </th>
                                    <th data-field="tacos" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Clink <span class="sort-arrow">↓</span>
                                            </div>

                                        </div>
                                    </th>
                                    <th data-field="ad cost/ pc" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            OLink<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="materic-view" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            METRIC VIEW <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="supplier_tag" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Supplier Tag<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="NR" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            NR<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="Req" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Req<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="Hide" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Hide<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="Notes" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Notes<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="req_form" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            RFQ Form<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="rer_report" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            RFQ Report<span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="date_apprvl" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            Date Arrpvl<span class="sort-arrow">↓</span>
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
                            <div class="loader-text">Loading Forecast data...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


        <!-- Modal -->
        <div class="modal fade" id="mslDetailsModal" tabindex="-1" role="dialog" aria-labelledby="mslModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document"> <!-- Changed to modal-xl -->
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="mslModalLabel">MSL Details</h5>
                    <button type="button" class="close text-white custom-close" data-dismiss="modal" aria-label="Close" style="background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-nowrap overflow-auto" style="gap: 1rem;">
                        <div class="card border-left-primary shadow-sm mb-3" style="min-width: 200px;">
                            <div class="card-body">
                                <h6 class="card-title text-muted">S-MSL</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalsmsl"></p>
                            </div>
                        </div>
                        <div class="card border-left-success shadow-sm mb-3" style="min-width: 200px;">
                            <div class="card-body">
                                <h6 class="card-title text-muted">LP * msl</h6>
                                <p class="card-text font-weight-bold text-dark" id="modallp_msl"></p>
                            </div>
                        </div>
                        <div class="card border-left-info shadow-sm mb-3" style="min-width: 200px;">
                            <div class="card-body">
                                <h6 class="card-title text-muted">SALE AVG</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalsale_avg"></p>
                            </div>
                        </div>
                        <div class="card border-left-info shadow-sm mb-3" style="min-width: 200px;">
                            <div class="card-body">
                                <h6 class="card-title text-muted">SALE ANNUAL</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalsale_anual"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="monthlyDetailsModal" tabindex="-1" role="dialog" aria-labelledby="monthlyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="monthlyModalLabel">MONTH VIEW</h5>
                    <button type="button" class="close text-white custom-close" data-dismiss="modal" aria-label="Close" style="background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="month-grid">
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">DEC</h6>
                                <p class="card-text font-weight-bold text-dark" id="modaldec"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">JAN</h6>
                                <p class="card-text font-weight-bold text-dark" id="modaljan"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">FEB</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalfeb"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">MAR</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalmar"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">APR</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalapr"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">MAY</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalmay"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">JUN</h6>
                                <p class="card-text font-weight-bold text-dark" id="modaljun"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">JUL</h6>
                                <p class="card-text font-weight-bold text-dark" id="modaljul"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">AUG</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalaug"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">SEP</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalsep"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">OCT</h6>
                                <p class="card-text font-weight-bold text-dark" id="modaloct"></p>
                            </div>
                        </div>
                        <div class="card month-card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">NOV</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalnov"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="amzDetailsModal" tabindex="-1" role="dialog" aria-labelledby="amzModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="amzModalLabel">PRICING VIEW</h5>
                    <button type="button" class="close text-white custom-close" data-dismiss="modal" aria-label="Close" style="background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-nowrap">
                        <div class="card border-left-success shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">AMZ PRICE</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalAmzPrice"></p>
                            </div>
                        </div>
                        <div class="card border-left-success shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">AMZ PFT%</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalAmzPft"></p>
                            </div>
                        </div>
                        <div class="card border-left-success shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">AMZ ROI</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalroi"></p>
                            </div>
                        </div>
                        <div class="card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">EBAY Price</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalebay_price"></p>
                            </div>
                        </div>
                        <div class="card border-left-info shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">EBAY PFT%</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalebay_pft"></p>
                            </div>
                        </div>
                        <div class="card border-left-info shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">EBAY ROI</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalebay_roi"></p>
                            </div>
                        </div>
                        <div class="card border-left-warning shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Investment Amount</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalinv_amt"></p>
                            </div>
                        </div>
                        <div class="card border-left-dark shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Actual Sale L30</h6>
                                <p class="card-text font-weight-bold text-dark" id="modala_sale_l30"></p>
                            </div>
                        </div>
                        <div class="card border-left-secondary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Estimated Sale L30</h6>
                                <p class="card-text font-weight-bold text-dark" id="modale_sale_l30"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cbmDetailsModal" tabindex="-1" role="dialog" aria-labelledby="cbmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xxl" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="cbmModalLabel">METRIC VIEW</h5>
                    <button type="button" class="close text-white custom-close" data-dismiss="modal" aria-label="Close" style="background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-flex overflow-auto gap-3 py-2">

                        <div class="card border-left-success shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">GW (LB)</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalgw_lb"></p>
                            </div>
                        </div>
                        <div class="card border-left-success shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">GW (KG)</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalgw_kg"></p>
                            </div>
                        </div>
                        <div class="card border-left-primary shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">CBM MSL</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalcbm_msl"></p>
                            </div>
                        </div>
                        <div class="card border-left-info shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">CP</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalcp"></p>
                            </div>
                        </div>
                        <div class="card border-left-info shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">Freight</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalfreight"></p>
                            </div>
                        </div>
                        <div class="card border-left-warning shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">LP</h6>
                                <p class="card-text font-weight-bold text-dark" id="modallp"></p>
                            </div>
                        </div>
                        <div class="card border-left-dark shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-muted">SH</h6>
                                <p class="card-text font-weight-bold text-dark" id="modalsh"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal -->
<div id="editModal" class="modal modal-field">
    <div class="modal-content modal-field-content">
        <h4>Edit Field</h4>

        <select id="editDropdown" class="modal-select modal-select-content">
            <!-- Options will be populated dynamically -->
        </select>

        <div class="modal-actions modal-actions-content">
            <button id="saveEdit" class="btn btn-primary btn-primary-field">Save</button>
            <button class="btn btn-secondary btn-field btn-secondary-field" onclick="$('#editModal').hide()">Cancel</button>
        </div>
    </div>
</div>

<!-- Styles -->
<style>
    .modal-field {
        display: none; /* Hidden by default */
        position: fixed;
        z-index: 1050;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.4); /* Dimmed background */
        justify-content: center;
        align-items: center;
    }

    .modal-field-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 20px 30px;
        border-radius: 10px;
        max-width: 400px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        text-align: center;
    }

    .modal-field-content h4 {
        margin-bottom: 20px;
        font-size: 1.5rem;
        color: #333;
    }

    .modal-select-content {
        width: 100%;
        padding: 10px;
        font-size: 1rem;
        border: 1px solid #ccc;
        border-radius: 5px;
        margin-bottom: 25px;
    }

    .modal-actions-content {
        display: flex;
        justify-content: space-between;
    }

    .btn-field {
        padding: 10px 20px;
        font-size: 1rem;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }

    .btn-primary-field {
        background-color: #007bff;
        color: white;
    }

    .btn-secondary-field {
        background-color: #6c757d;
        color: white;
    }

    .btn-field:hover {
        opacity: 0.9;
    }
</style>

    <div class="modal fade" id="editNotesModal" tabindex="-1" role="dialog" aria-labelledby="editNotesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg shadow-none" role="document">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white border-0 rounded-top-4">
                    <h5 class="modal-title" id="editNotesLabel">
                        <i class="fas fa-edit me-2"></i> Edit Notes
                    </h5>
                    <button type="button" class="close text-white custom-close" data-bs-dismiss="modal" aria-label="Close" style="font-size:25px; background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <textarea id="notesInput" class="form-control form-control-lg shadow-none mb-4" rows="6" placeholder="Type your note here..." style="resize: vertical;" ></textarea>
                    <div class="text-end">
                        <button type="button" class="btn btn-lg btn-primary" id="saveNotesBtn">
                            <i class="fas fa-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<style>
    #editNotesModal .modal-content {
        border-radius: 1rem;
        overflow: hidden;
    }

    #editNotesModal .modal-header {
        padding: 1.25rem 2rem;
        background: linear-gradient(90deg, #0d6efd, #0a58ca);
    }

    #editNotesModal .modal-title {
        font-size: 1.3rem;
        font-weight: 500;
    }

    #editNotesModal .form-control:focus {
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.2);
        border-color: #86b7fe;
    }

    #editNotesModal .btn {
        font-weight: 500;
        border-radius: 0.5rem;
        transition: all 0.2s ease-in-out;
    }

    #editNotesModal .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    #editNotesModal .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
        transform: translateY(-1px);
    }

    #editNotesModal .btn-secondary:hover {
        background-color: #d6d8db;
        border-color: #c6c8ca;
    }

    #editNotesModal textarea {
        font-size: 1rem;
        line-height: 1.5;
    }

    #editNotesModal .form-label {
        font-size: 1.05rem;
        color: #495057;
    }
</style>


    <div class="modal fade" id="editMslModal" tabindex="-1" role="dialog" aria-labelledby="editMslLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="editMslLabel">Edit MSL</h5>
                    <button type="button" class="close text-white custom-close" data-dismiss="modal" aria-label="Close" style="background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="card border-left-info shadow-sm mb-3 p-3">
                        <label for="mslInput" class="text-muted font-weight-bold mb-2">MSL Value</label>
                        <input type="number" id="mslInput" class="form-control" placeholder="Enter MSL value">
                    </div>
                </div>

                <div class="modal-footer justify-content-between">
                    <button id="saveMslBtn" type="button" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="editFieldModal" tabindex="-1" role="dialog" aria-labelledby="editFieldLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="editFieldLabel">Edit Value</h5>
                    <button type="button" class="close text-white custom-close" data-dismiss="modal" aria-label="Close" style="background-color: transparent; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="fieldInput" class="font-weight-bold text-muted">Value</label>
                        <input type="number" id="fieldInput" class="form-control" placeholder="Enter value">
                    </div>
                </div>

                <div class="modal-footer">
                    <button id="saveFieldBtn" type="button" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clink Edit Modal -->
    <!-- Link Edit Modal -->
<div class="modal fade" id="linkEditModal" tabindex="-1" aria-labelledby="linkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0">
                <h5 class="modal-title" id="linkEditModalLabel">
                    <i class="fas fa-link me-2"></i>
                    <span>Edit Link</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label id="linkLabel" class="form-label fw-lg mb-2">Link URL:</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="fas fa-link text-primary"></i>
                        </span>
                        <input type="url" class="form-control form-control-lg border-start-0 ps-2" id="linkEditInput" placeholder="Enter URL here..." autocomplete="off" spellcheck="false">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary" id="saveLinkBtn">
                    <i class="fas fa-check me-1"></i>
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<style>
#linkEditModal .modal-content {
    border-radius: 12px;
    overflow: hidden;
}

#linkEditModal .modal-header {
    padding: 1rem 1.5rem;
}

#linkEditModal .modal-title {
    font-size: 1.1rem;
    font-weight: 500;
}

#linkEditModal .form-control:focus {
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,.15);
    border-color: #86b7fe;
}

#linkEditModal .input-group-text {
    border-color: #dee2e6;
}

#linkEditModal .btn {
    padding: 0.5rem 1.25rem;
    font-weight: 500;
    border-radius: 6px;
}

#linkEditModal .btn-primary {
    background-color: #3bc0c3;
    border-color: #3bc0c3;
    transition: all 0.2s;
}

#linkEditModal .btn-primary:hover {
    background-color: #3bc0c3;
    border-color: #3bc0c3;
    transform: translateY(-1px);
}

#linkEditModal .btn-light {
    background-color: #f8f9fa;
    border-color: #f8f9fa;
}

#linkEditModal .btn-light:hover {
    background-color: #e9ecef;
    border-color: #dee2e6;
}

#linkEditModal .form-text {
    font-size: 0.85rem;
}

#linkEditModal .btn-close-white:focus {
    box-shadow: 0 0 0 0.25rem rgba(255,255,255,.2);
}

#linkEditModal .form-label {
    color: #495057;
}

#linkEditModal .input-group {
    box-shadow: 0 2px 4px rgba(0,0,0,.03);
    border-radius: 6px;
    overflow: hidden;
}
</style>
    </div>




@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!--for popup modal script-->
    <script>
    let currentRowIndex, currentFieldType;

    $(document).on('click', '.edit-icon', function() {
        const $parent = $(this).closest('.editable-field');
        currentFieldType = $parent.data('type');
        currentRowIndex = $parent.data('row-index');
        const currentValue = $parent.data('value');

        const options = (currentFieldType === 'Req')
            ? ['Yes', 'No', 'Later']
            : ['@Need', '@Taken', '@Senior'];

        const $dropdown = $('#editDropdown');
        $dropdown.empty();
        options.forEach(opt => {
            $dropdown.append(`<option value="${opt}" ${opt === currentValue ? 'selected' : ''}>${opt}</option>`);
        });

        $('#editModal').show();
    });

    $('#saveEdit').on('click', function() {
        const newValue = $('#editDropdown').val();

        // Update UI immediately
        const $field = $(`.editable-field[data-row-index="${currentRowIndex}"][data-type="${currentFieldType}"]`);
        $field.html(`${newValue} <i class="fas fa-edit edit-icon" style="cursor:pointer; margin-left:5px;"></i>`)
            .data('value', newValue);

        $('#editModal').hide();

        // Send POST to Laravel backend
        $.ajax({
            url: '/updateForcastSheet', // adjust route as needed
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: {
                rowIndex: currentRowIndex, // +1 if Google Sheets is 1-based
                rowData: {
                    [currentFieldType]: newValue
                }
            },
            success: function(response) {
                console.log(response);
                if (response.success) {
                    console.log('Google Sheet updated');
                } else {
                    alert('Failed to update');
                }
            },
            error: function() {
                alert('Error updating Google Sheet.');
            }
        });
    });

    //
    $('#saveFieldBtn').on('click', function () {
        const newValue = $('#fieldInput').val();
        const index = $(this).data('index');

        if (typeof index === 'undefined' || currentFieldName === '') {
            alert('Missing field or index.');
            return;
        }

        const rowData = {};
        rowData[currentFieldName] = newValue;

        $.ajax({
            url: '/updateForcastSheet',
            method: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: JSON.stringify({
                rowIndex: index,
                rowData: rowData
            }),
            success: function (response) {
                if (response.success) {
                    $('#editFieldModal').modal('hide');
                    // alert(`${currentFieldName} updated successfully!`);

                    // Update the <span> in that td
                    const selector = `.edit-order-btn[data-index="${index}"][data-field="${currentFieldName}"]`;
                    const $icon = $(selector);
                    $icon.siblings('span').text(newValue);
                    $icon.attr('data-value', newValue);
                }
            },
            error: function (xhr) {
                console.error(xhr.responseJSON);
                alert(xhr.responseJSON?.message || 'Update failed.');
            }
        });
    });

    $(document).on('click', '.cbm-icon', function () {
        const gwLb = $(this).data('gw_lb') ?? '';
        const gwKg = $(this).data('gw_kg') ?? '';
        const cbmMsl = $(this).data('cbm_msl') ?? '';
        const cp = $(this).data('cp') ?? '';
        const freight = $(this).data('freight') ?? '';
        const lp = $(this).data('lp') ?? '';
        const sh = $(this).data('sh') ?? '';

        $('#modalgw_lb').text(gwLb);
        $('#modalgw_kg').text(gwKg);
        $('#modalcbm_msl').text(cbmMsl);
        $('#modalcp').text(cp);
        $('#modalfreight').text(freight);
        $('#modallp').text(lp);
        $('#modalsh').text(sh);
        $('#cbmDetailsModal')
            .addClass('show')
            .css('display', 'block')
            .attr('aria-modal', 'true')
            .removeAttr('aria-hidden');

        $('body').addClass('modal-open');
        if ($('.modal-backdrop').length === 0) {
            $('body').append('<div class="modal-backdrop fade show"></div>');
        }
       // $('#cbmDetailsModal').modal('show');
    });

    $(document).on('click', '.month-view', function () {
        const months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];

        months.forEach(month => {
            const value = $(this).data(month);
            $(`#modal${month}`).text(value !== undefined ? value : '0'); // Show 0 if missing
        });
        $('#monthlyDetailsModal')
            .addClass('show')
            .css('display', 'block')
            .attr('aria-modal', 'true')
            .removeAttr('aria-hidden');

        $('body').addClass('modal-open');
        if ($('.modal-backdrop').length === 0) {
            $('body').append('<div class="modal-backdrop fade show"></div>');
        }
      //  $('#monthlyDetailsModal').modal('show');
    });
    $(document).on('click', '.amz-icon', function () {

        $('#modalAmzPrice').text($(this).data('AmzPrice') ?? '');
        $('#modalroi').text($(this).data('roi') ?? '');
        $('#modalAmzPft').text($(this).data('pft') ?? '');
        $('#modalebay_price').text($(this).data('ebay_price') ?? '');
        $('#modalebay_pft').text($(this).data('ebay_pft') ?? '');
        $('#modalebay_roi').text($(this).data('ebay_roi') ?? '');
        $('#modalinv_amt').text($(this).data('inv_amt') ?? '');
        $('#modala_sale_l30').text($(this).data('a_sale_l30') ?? '');
        $('#modale_sale_l30').text($(this).data('e_sale_l30') ?? '');

        $('#amzDetailsModal')
            .addClass('show')
            .css('display', 'block')
            .attr('aria-modal', 'true')
            .removeAttr('aria-hidden');

        $('body').addClass('modal-open');
        if ($('.modal-backdrop').length === 0) {
            $('body').append('<div class="modal-backdrop fade show"></div>');
        }
    });


  $(document).on('click', '.tag-icon', function () {
    const smsl = $(this).data('smsl') ?? '';
        const lp_msl = $(this).data('lp_msl') ?? '';
        const sale_avg = $(this).data('sale_avg') ?? '';
        const sale_anual = $(this).data('sale_anual') ?? '';

        $('#modalsmsl').text(smsl);
        $('#modallp_msl').text(lp_msl);
        $('#modalsale_avg').text(sale_avg);
        $('#modalsale_anual').text(sale_anual);

        // Show modal manually
        $('#mslDetailsModal')
            .addClass('show')
            .css('display', 'block')
            .attr('aria-modal', 'true')
            .removeAttr('aria-hidden');

        $('body').addClass('modal-open');

        // Add backdrop if not exists
        if ($('.modal-backdrop').length === 0) {
            $('body').append('<div class="modal-backdrop fade show"></div>');
        }
    });
    $(document).on('click', '.custom-close', function () {
        $('#mslDetailsModal')
            .removeClass('show')
            .css('display', 'none')
            .removeAttr('aria-modal')
            .attr('aria-hidden', 'true');
        $('#amzDetailsModal')
            .removeClass('show')
            .css('display', 'none')
            .removeAttr('aria-modal')
            .attr('aria-hidden', 'true');
        $('#cbmDetailsModal')
            .removeClass('show')
            .css('display', 'none')
            .removeAttr('aria-modal')
            .attr('aria-hidden', 'true');
        $('#monthlyDetailsModal')
            .removeClass('show')
            .css('display', 'none')
            .removeAttr('aria-modal')
            .attr('aria-hidden', 'true');
            
        $('#editMslModal')
            .removeClass('show')
            .css('display', 'none')
            .removeAttr('aria-modal')
            .attr('aria-hidden', 'true');
        $('#editFieldModal')
            .removeClass('show')
            .css('display', 'none')
            .removeAttr('aria-modal')
            .attr('aria-hidden', 'true');

        $('body').removeClass('modal-open');
        $('.modal-backdrop').remove();
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
        document.body.style.zoom = "85%";
        $(document).ready(function() {
            $('#updateAllSkusBtn').click(function() {
                // Disable button and show loading state
                $(this).prop('disabled', true);
                $(this).html('<i class="ri-loader-4-line me-1"></i> Updating...');
                $('#updateStatus').html('<div class="alert alert-info">Updating SKUs, please wait...</div>');

                // Get CSRF token from meta tag
                const csrfToken = $('meta[name="csrf-token"]').attr('content');

                // Make AJAX request
                $.ajax({
                    url: '/update-all-amazon-skus',
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    },
                    data: {
                        _token: csrfToken
                    },
                    success: function(response) {
                        $('#updateStatus').html(`
                            <div class="alert alert-success">
                                ${response.message}<br>
                                Total updated: ${response.total_updated} SKUs
                            </div>
                        `);
                    },
                    error: function(xhr) {
                        let errorMsg = 'An error occurred';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        $('#updateStatus').html(`
                            <div class="alert alert-danger">
                                ${errorMsg}
                            </div>
                        `);
                    },
                    complete: function() {
                        $('#updateAllSkusBtn').prop('disabled', false);
                        $('#updateAllSkusBtn').html('<i class="ri-refresh-line me-1"></i> Update All SKUs');
                    }
                });
            });

            // Cache system
            const amazonDataCache = {
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
                amazonDataCache.clear();
            });

            // Current state
            let currentPage = 1;
            let isLoading = false;
            let hasMore = true;
            let rowsPerPage = Infinity;
            let currentSort = {
                field: null,
                direction: 1
            };
            let tableData = [];
            let filteredData = [];
            let isResizing = false;
            let isEditMode = false;
            let currentEditingElement = null;
            let isNavigationActive = false; // Add this line

            // Parent Navigation System
            let currentParentIndex = -1; // -1 means showing all products
            let uniqueParents = [];
            let isPlaying = false;

            // Define status indicator fields for different modal types
            const statusIndicatorFields = {
                'price view': ['PFT %', 'TPFT', 'Roi', 'Spft%'],
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
                    'Dil%': 'all',
                    'A Dil%': 'all',
                    'PFT %': 'all',
                    'Roi': 'all',
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
                // Get all unique parent names (case-insensitive & trimmed)
                uniqueParents = [
                    ...new Map(
                        tableData
                            .filter(item => item.Parent)
                            .map(item => {
                                const key = item.Parent.trim().toUpperCase(); // For comparison
                                const value = item.Parent.trim();             // Original cleaned
                                return [key, value];
                            })
                    ).values()
                ];

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
                currentPage = 1;   // Reset to first page for new parent
                showCurrentParent();
            }

            function previousParent() {
                if (!isNavigationActive) return;
                if (currentParentIndex <= 0) return;

                currentParentIndex--;
                currentPage = 1;   // Reset to first page for new parent
                showCurrentParent();
            }

            function showCurrentParent() {
                if (!isNavigationActive || currentParentIndex === -1) return;

                const parentName = uniqueParents[currentParentIndex].trim();

                // Filter all items matching the parent
                let allItems = tableData.filter(item => item.Parent && item.Parent.trim() === parentName);

                // Separate parent row and child rows
                const parentRowIndex = allItems.findIndex(item => item.SKU && item.SKU.trim() === `PARENT ${parentName}`);
                let parentRow = null;

                if (parentRowIndex !== -1) {
                    parentRow = allItems.splice(parentRowIndex, 1)[0];  // Remove parent row from children
                }

                // Put parent row at the beginning if exists
                filteredData = parentRow ? [parentRow, ...allItems] : allItems;

                // Pagination slice
                const startIndex = (currentPage - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                const pageData = filteredData.slice(startIndex, endIndex);

                // Assign pageData back to your rendering data source
                displayedData = pageData;

                // Now call rendering and UI update functions
                renderTable();
                calculateTotals();
                updateButtonStates();
                checkParentRAStatus();
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
                    url: '/forecast-analysis-data-view',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.data) {
                            console.log(response.data);
                            const dataArray = Object.values(response.data);
                            tableData = dataArray.map((item, index) => {
                                const l30 = parseFloat(item.L30) || 0;
                                const inv = parseFloat(item.INV);
                                const dil = inv && !isNaN(inv) ? Math.round((l30 / inv) * 100) : 0;
                                return {
                                    sl_no: index + 1,
                                    'SL No.': item['SL No.'] || index + 1,
                                    Parent: item.Parent || item.parent || item.parent_asin ||
                                        item.Parent_ASIN || '(No Parent)',
                                    '(Child) sku': item['SKU'] || '',
                                    INV: item.INV || 0,
                                    L30: item.L30 || 0,
                                    'index': index,
                                    'Dil%': dil || 0,
                                    'Total month': item['Total month'] || '',
                                    'Total': item['Total'] || '',
                                    'MSL': item['MSL'] || '',
                                    '2 ORDERED': item['2 ORDERED'] || '',
                                    'Transit': item.transit || '',
                                    'Approved QTY': item['Approved QTY'] || '',
                                    'AMZ PRICE': item['AMZ PRICE'] || '',
                                    'Comp Anal': item['Comp Anal'] || '',
                                    'COMP': item['COMP'] || '',
                                    'Clink': item['Clink'] || '',
                                    'CBM': item['CBM'] || '',
                                    'Olink': item['Olink'] || '',
                                    'Supplier Tag': item['Supplier Tag'] || '',
                                    'nr': item['nr'] || '',
                                    'req': item['req'] || '',
                                    'hide': item['hide'] || '',
                                    'notes': item['notes'] || '',

                                    'S-MSL': item['s-msl'] || '',
                                    'LP * msl': item['LP * msl'] || '',
                                    'SALE AVG': item['SALE AVG'] || '',
                                    'SALE ANNUAL': item['SALE ANNUAL'] || '',
                                    'pft%': item['pft%'] || '',
                                    'ROI': item['ROI'] || '',
                                    'EBAY PRICE': item['EBAY PRICE'] || '',
                                    'EBAY pft%': item['EBAY pft%'] || '',
                                    'EBAY ROI': item['EBAY ROI'] || '',
                                    'Inv Amt': item['Inv Amt'] || '',
                                    'A SaleL30 amt': item['A SaleL30 amt'] || '',
                                    'E SaleL30 amt': item['E SaleL30 amt'] || '',
                                    'Dec': item['Dec'] || '',
                                    'jan': item['jan'] || '',
                                    'feb': item['feb'] || '',
                                    'Mar': item['Mar'] || '',
                                    'Apr': item['Apr'] || '',
                                    'May': item['May'] || '',
                                    'Jun': item['Jun'] || '',
                                    'Jul': item['Jul'] || '',
                                    'Aug': item['Aug'] || '',
                                    'Sep': item['Sep'] || '',
                                    'Oct': item['Oct'] || '',
                                    'Nov': item['Nov'] || '',

                                    'GW (LB)': item['GW (LB)'] || '',
                                    'GW (KG)': item['GW (KG)'] || '',
                                    'CBM MSL': item['CBM MSL'] || '',
                                    'CP': item['CP'] || '',
                                    'Freight': item['Freight'] || '',
                                    'LP': item['LP'] || '',
                                    'SH': item['SH'] || '',
                                    is_parent: item['(Child) sku'] ? item['(Child) sku'].toUpperCase().includes("PARENT") : false,
                                    order_given: item.order_given ?? '',
                                    rfq_form_link: item.rfq_form_link || '',
                                    rfq_report: item.rfq_report || '',
                                    date_apprvl: item.date_apprvl || '',
                                    raw_data: item || {} // Ensure raw_data always exists
                                };
                            });

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

            // Render table with current data
            function renderTable() {
                const $tbody = $('#amazon-table tbody');
                $tbody.empty();

                if (isLoading) {
                    $tbody.append('<tr><td colspan="15" class="text-center">Loading data...</td></tr>');
                    return;
                }

                if (filteredData.length === 0) {
                    $tbody.append('<tr><td colspan="15" class="text-center">No matching records found</td></tr>');
                    return;
                }

                filteredData.forEach(item => {
                    const sku = item['(Child) sku']?.trim() || '';
                    const parent = item['Parent']?.trim() || '';

                    const $row = $('<tr>').attr('data-sku', sku).attr('data-parent', parent);
                    const isParentSku = item['(Child) sku'] && item['(Child) sku'].toUpperCase().includes('PARENT');
                    if (isParentSku) {
                        $row.addClass('parent-row');
                    }
                    // Helper functions for color coding
                    const getDilColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 16.66) return 'red';
                        if (percent >= 16.66 && percent < 25) return 'yellow';
                        if (percent >= 25 && percent < 50) return 'green';
                        return 'pink'; // 50 and above
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
                        const percent = parseFloat(value) * 100;
                        if (percent >= 0 && percent < 50) return 'red';
                        if (percent >= 50 && percent < 75) return 'yellow';
                        if (percent >= 75 && percent <= 100) return 'green';
                        return 'pink';
                    };

                    const getTacosColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent <= 5) return 'pink';
                        if (percent > 5 && percent <= 10) return 'green';
                        if (percent > 10 && percent <= 15) return 'blue';
                        if (percent > 15 && percent <= 20) return 'yellow';
                        return 'red';
                    };

                    const getCvrColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent <= 7) return 'red';
                        if (percent > 7 && percent <= 13) return 'green';
                        return 'pink';
                    };

                    $row.append($('<td>').text(item['SL No.']));
                    $row.append($('<td>').text(item.Parent));

                    // SKU with hover content for links
                    const $skuCell = $('<td>').addClass('skuColumn').css('position', 'static');
                    const skuValue = item['(Child) sku'] || '';

                    if (item.is_parent) {
                        $skuCell.html(`<strong>${skuValue}</strong>`);
                    } else {
                        const buyerLink = item.raw_data['AMZ LINK BL'] || '';
                        const sellerLink = item.raw_data['AMZ LINK SL'] || '';

                        if (buyerLink || sellerLink) {
                            $skuCell.html(`
                                <div class="sku-tooltip-container">
                                    <span class="sku-text">${skuValue}</span>
                                    <div class="sku-tooltip">
                                        ${buyerLink ? `<div class="sku-link"><a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a></div>` : ''}
                                        ${sellerLink ? `<div class="sku-link"><a href="${sellerLink}" target="_blank" rel="noopener noreferrer">Seller link</a></div>` : ''}
                                    </div>
                                </div>
                            `);
                        } else {
                            $skuCell.text(skuValue);
                        }
                    }

                    $row.append($skuCell);

                    $row.append($('<td class="text-center">').text(item.INV));
                    $row.append($('<td class="text-center">').text(item.L30));

                    // OV DIL with color coding and WMPNM tooltip
                    // Calculate Dil% as L30 / INV
                    let dilValue = 0;
                    if (parseFloat(item.INV) && parseFloat(item.L30)) {
                        dilValue = (parseFloat(item.L30) / parseFloat(item.INV)) * 100;
                    }
                    
                    // Format dilValue to show with 2 decimal places
                    const formattedDilValue = (dilValue / 100).toFixed(2);
                    
                    $row.append($('<td class="text-center">').html(
                        `<span class="dil-percent-value ${getDilColor(dilValue / 100)}">${formattedDilValue}%</span>`
                    ));
                    const DecValue = item['Dec'] ? Math.round(parseFloat(item['Dec'])) : '';
                    const janValue = item['jan'] ? Math.round(parseFloat(item['jan'])) : '';
                    const febValue = item['feb'] ? Math.round(parseFloat(item['feb'])) : '';
                    const MarValue = item['Mar'] ? Math.round(parseFloat(item['Mar'])) : '';
                    const AprValue = item['Apr'] ? Math.round(parseFloat(item['Apr'])) : '';
                    const MayValue = item['May'] ? Math.round(parseFloat(item['May'])) : '';
                    const JunValue = item['Jun'] ? Math.round(parseFloat(item['Jun'])) : '';
                    const JulValue = item['Jul'] ? Math.round(parseFloat(item['Jul'])) : '';
                    const AugValue = item['Aug'] ? Math.round(parseFloat(item['Aug'])) : '';
                    const SepValue = item['Sep'] ? Math.round(parseFloat(item['Sep'])) : '';
                    const OctValue = item['Oct'] ? Math.round(parseFloat(item['Oct'])) : '';
                    const NovValue = item['Nov'] ? Math.round(parseFloat(item['Nov'])) : '';

                    const monthIcon = `
                        <i class="fas fa-tag tag-icon month-view"
                            data-dec="${DecValue}"
                            data-jan="${janValue}"
                            data-feb="${febValue}"
                            data-mar="${MarValue}"
                            data-apr="${AprValue}"
                            data-may="${MayValue}"
                            data-jun="${JunValue}"
                            data-jul="${JulValue}"
                            data-aug="${AugValue}"
                            data-sep="${SepValue}"
                            data-oct="${OctValue}"
                            data-nov="${NovValue}"
                            style="cursor:pointer; margin-left:5px;"
                            title="View Monthly Details">
                        </i>`;

                    // Append to row
                    $row.append($('<td>').html(`${monthIcon}`));

                    const smslValue = item['S-MSL'] ? Math.round(parseFloat(item['S-MSL'])) : '';
                    const lpMslValue = item['LP * msl'] ? Math.round(parseFloat(item['LP * msl'])) : '';
                    const saleAvgValue = item['SALE AVG'] ? Math.round(parseFloat(item['SALE AVG'])) : '';
                    const saleAnualValue = item['SALE ANNUAL'] ? Math.round(parseFloat(item['SALE ANNUAL'])) : '';

                    // Create the icon element as a string
                    const icon = `
                        <i class="fas fa-tag tag-icon"
                            data-smsl="${smslValue}"
                            data-lp_msl="${lpMslValue}"
                            data-sale_avg="${saleAvgValue}"
                            data-sale_anual="${saleAnualValue}"
                            style="cursor:pointer; margin-left:5px;"
                            title="View Details">
                        </i>`;

                    const totalRaw = item['Total'];
                    const totalMonthRaw = item['Total month'];

                    const total = !isNaN(totalRaw) ? parseFloat(totalRaw) : 0;
                    const totalMonth = !isNaN(totalMonthRaw) ? parseFloat(totalMonthRaw) : 0;

                    if (totalMonth > 0) {
                        item['MSL'] = (total / totalMonth) * 4;
                    } else {
                        item['MSL'] = null;
                    }

                    const mslValue = item['MSL'] !== null ? Math.round(item['MSL']) : '0';

                    $row.append(
                        $('<td class="text-center fw-bold">').append(
                            $('<span>').text(mslValue || '')
                        )
                    );
                    // Append the icon to the MSL cell
                    if (!isParentSku) {
                        $row.append(
                            $('<td>')
                                .addClass('editable-qty text-center fw-bold')
                                .attr('contenteditable', 'true')
                                .attr('data-sku', item['(Child) sku']) 
                                .attr('data-parent', item.Parent) 
                                .attr('data-field', 'S-MSL')
                                .attr('data-original', item['S-MSL'] ?? '')
                                .text(item['S-MSL'] ?? '')
                        );
                    } else {
                        $row.append(
                            $('<td>')
                                .text(item['S-MSL'] ?? '')
                                .css({ 'font-weight': 'bold', color: '#0d6efd' })
                        );
                    }

                    //ORDER given
                    if (!isParentSku) {
                        $row.append(
                            $('<td>')
                                .addClass('editable-qty text-center fw-bold')
                                .attr('contenteditable', 'true')
                                .attr('data-sku', item['(Child) sku']) 
                                .attr('data-parent', item.Parent) 
                                .attr('data-field', 'ORDER given')
                                .attr('data-original', item.order_given ?? '')
                                .text(item.order_given ?? '0')
                        );
                    } else {
                        $row.append(
                            $('<td class="text-center fw-bold">')
                                .text(item.order_given ?? '')
                                .css({ 'font-weight': 'bold', color: '#0d6efd' })
                        );
                    }

                    // Transit
                    if (!isParentSku) {
                        $row.append(
                            $('<td>')
                                .addClass('editable-qty text-center fw-bold')
                                .attr('contenteditable', 'true')
                                .attr('data-sku', item['(Child) sku']) 
                                .attr('data-parent', item.Parent) 
                                .attr('data-field', 'Transit')
                                .attr('data-original', item['Transit'] ?? '')
                                .text(item['Transit'] ?? '')
                        );
                    } else {
                        $row.append(
                            $('<td class="text-center fw-bold">')
                                .text(item['Transit'] ?? '')
                                .css({ 'font-weight': 'bold', color: '#0d6efd' })
                        );
                    }
                    
                    // 2 ORDERED
                    const msl = parseFloat(item['MSL']) || 0;
                    const inv = parseFloat(item['INV']) || 0;
                    const transit = parseFloat(item['Transit']) || 0;
                    const orderGiven = parseFloat(item.order_given) || 0;

                    // Calculate value as MSL - INV - TRANSIT - ORDER GIVEN
                    const value = Math.round(msl - inv - transit - orderGiven);
                    const isNegative = value < 0;

                    // Create the value span with conditional styles
                    const $valueSpan = $('<span>')
                        .text(value)
                        .css({
                            'background-color': isNegative ? 'red' : 'yellow',
                            'color': isNegative ? 'white' : 'black',
                            'padding': '2px 6px',
                            'border-radius': '4px',
                            'display': 'inline-block',
                            'min-width': '30px',
                            'text-align': 'center',
                            'font-weight': 'bold'
                        });

                    // Append to the row
                    $row.append($('<td class="text-center fw-bold">').append($valueSpan));

                    // Approved QTY
                    if (!isParentSku) {
                        $row.append(
                            $('<td>')
                                .addClass('editable-qty text-center fw-bold')
                                .attr('contenteditable', 'true')
                                .attr('data-placeholder', 'Enter Approved QTY')
                                .attr('data-sku', item['(Child) sku']) 
                                .attr('data-parent', item.Parent) 
                                .attr('data-field', 'Approved QTY')
                                .attr('data-original', item['Approved QTY'] ?? '')
                                .text(item['Approved QTY'] ?? '')
                        );
                    } else {
                        $row.append(
                            $('<td class="text-center fw-bold">')
                                .text(item['Approved QTY'] ?? '')
                                .css({ 'font-weight': 'bold', color: '#0d6efd' })
                        );
                    }


                    const amzPrice = item['AMZ PRICE'] ? parseFloat(item['AMZ PRICE']).toFixed(2) : '';
                    const pftPerc = item['pft%'] ? Math.round(parseFloat(item['pft%'])) : '';
                    const roi = item['ROI'] ? Math.round(parseFloat(item['ROI'])) : '';
                    const ebayPrice = item['EBAY PRICE'] ? parseFloat(item['EBAY PRICE']).toFixed(2) : '';
                    const ebayPftPerc = item['EBAY pft%'] ? Math.round(parseFloat(item['EBAY pft%'])) : '';
                    const ebayRoi = item['EBAY ROI'] ? Math.round(parseFloat(item['EBAY ROI'])) : '';
                    const invAmt = item['Inv Amt'] ? parseFloat(item['Inv Amt']).toFixed(2) : '';
                    const aSaleL30 = item['A SaleL30 amt'] ? parseFloat(item['A SaleL30 amt']).toFixed(2) : '';
                    const eSaleL30 = item['E SaleL30 amt'] ? parseFloat(item['E SaleL30 amt']).toFixed(2) : '';

                    const amzIcon = `
                        <i class="fas fa-info-circle info-icon amz-icon"
                            data-AmzPrice="${amzPrice}"
                            data-pft="${pftPerc}"
                            data-roi="${roi}"
                            data-ebay_price="${ebayPrice}"
                            data-ebay_pft="${ebayPftPerc}"
                            data-ebay_roi="${ebayRoi}"
                            data-inv_amt="${invAmt}"
                            data-a_sale_l30="${aSaleL30}"
                            data-e_sale_l30="${eSaleL30}"
                            style="cursor:pointer; margin-left:5px;"
                            title="View Financial Details">
                        </i>`;

                    // amzPrice and pft% 
                    if(amzPrice != ''){
                        $row.append($('<td class="text-center fw-bold">').html(`$${amzPrice}`));
                    }else{
                        $row.append($('<td class="text-center fw-bold">').text(amzPrice));
                    }

                    $row.append($('<td>').html(
                        typeof item['pft%'] === 'number' && !isNaN(item['pft%']) ?
                        `<span class="dil-percent-value ${getPftColor(item['pft%'])}">${Math.round(item['pft%'] * 100)}%</span>` :
                        ''
                    ));

                    // ROI with color coding
                    $row.append($('<td>').html(
                        typeof item['ROI'] === 'number' && !isNaN(item['ROI']) ?
                        `<span class="dil-percent-value ${getRoiColor(item['ROI'])}">${Math.round(item['ROI'] * 100)}%</span>` :
                        ''
                    ));
                    
                    const comp = item.raw_data?.scout_data?.min_price
                        ? parseFloat(item.raw_data.scout_data.min_price).toFixed(2)
                        : '0.00';


                    const compHtml = `
                        <span>$${comp}</span>
                        <span class="text-info tooltip-icon scouth-products-view-trigger" 
                            data-bs-toggle="tooltip" 
                            data-bs-placement="left" 
                            title="Scouth Products View"
                            data-item='${JSON.stringify(item.raw_data)}'>P</span>
                    `;

                    $row.append($('<td>').html(compHtml));

                    let editingLinkCell = null;
                    let editingRow = null;
                    let editingField = '';

                    // ---- CLINK CELL ----
                    const clink = item['Clink']?.trim() ?? '';
                    const $clinkCell = $('<td>').html(`
                    ${clink ? `<a href="${clink}" target="_blank" title="Clink"><i class="fas fa-link text-primary me-1"></i></a>` : ''}
                    <a href="#" class="edit-clink"><i class="fas fa-edit text-warning" title="Edit Clink"></i></a>
                    `);

                    $clinkCell.on('click', '.edit-clink', function (e) {
                        e.preventDefault();
                        editingLinkCell = $clinkCell;
                        editingRow = item;
                        editingField = 'Clink';

                        $('#linkEditModalLabel').text('Edit Clink');
                        $('#linkLabel').text('Clink:');
                        $('#linkEditInput').val(clink);
                        $('#linkEditModal').modal('show');
                    });

                    // ---- OLINK CELL ----
                    const olink = item['Olink']?.trim() ?? '';
                    const $olinkCell = $('<td>').html(`
                    ${olink ? `<a href="${olink}" target="_blank" title="Olink"><i class="fas fa-external-link-alt text-success me-1"></i></a>` : ''}
                    <a href="#" class="edit-olink"><i class="fas fa-edit text-warning" title="Edit Olink"></i></a>
                    `);

                    $olinkCell.on('click', '.edit-olink', function (e) {
                        e.preventDefault();
                        editingLinkCell = $olinkCell;
                        editingRow = item;
                        editingField = 'Olink';

                        $('#linkEditModalLabel').text('Edit Olink');
                        $('#linkLabel').text('Olink:');
                        $('#linkEditInput').val(olink);
                        $('#linkEditModal').modal('show');
                    });

                    // ---- SAVE BUTTON ----
                    $('#saveLinkBtn').on('click', function () {

                        const newValue = $('#linkEditInput').val().trim();
                        editingRow[editingField] = newValue;

                        if (editingField === 'Clink') {
                            const clinkHtml = (newValue ? `<a href="${newValue}" target="_blank" title="Clink"><i class="fas fa-link text-primary me-1"></i></a>` : '')
                            + `<a href="#" class="edit-clink"><i class="fas fa-edit text-warning" title="Edit Clink"></i></a>`;
                            editingLinkCell.html(clinkHtml);
                        } else if (editingField === 'Olink') {
                            const olinkHtml = (newValue ? `<a href="${newValue}" target="_blank" title="Olink"><i class="fas fa-external-link-alt text-success me-1"></i></a>` : '')
                            + `<a href="#" class="edit-olink"><i class="fas fa-edit text-warning" title="Edit Olink"></i></a>`;
                            editingLinkCell.html(olinkHtml);
                        }

                        $('#linkEditModal').modal('hide');

                        $.ajax({
                            url: '/update-forecast-data',
                            method: 'POST',
                            data: {
                                _token: $('meta[name="csrf-token"]').attr('content'),
                                sku: editingRow['(Child) sku'],
                                parent: editingRow['Parent'],
                                column: editingField, // "Clink" or "Olink"
                                value: newValue
                            },
                            success: function (res) {
                                console.log(res.message);
                            },
                            error: function (err) {
                                console.error('Error:', err);
                            }
                        });

                    });

                    // ---- Append separately to row ----
                    $row.append($clinkCell);
                    $row.append($olinkCell);
                    
                    const cbm = !isNaN(parseFloat(item['CBM'])) ? parseFloat(item['CBM']).toFixed(3) : '0.000';
                    const gwLb = !isNaN(parseFloat(item['GW (LB)'])) ? parseFloat(item['GW (LB)']).toFixed(4) : '';
                    const gwKg = !isNaN(parseFloat(item['GW (KG)'])) ? parseFloat(item['GW (KG)']).toFixed(4) : '';
                    const cbmMsl = !isNaN(parseFloat(item['CBM MSL'])) ? parseFloat(item['CBM MSL']).toFixed(4) : '';
                    const cp = !isNaN(parseFloat(item['CP'])) ? parseFloat(item['CP']).toFixed(4) : '';
                    const freight = !isNaN(parseFloat(item['Freight'])) ? parseFloat(item['Freight']).toFixed(4) : '';
                    const lp = !isNaN(parseFloat(item['LP'])) ? parseFloat(item['LP']).toFixed(4) : '';
                    const sh = !isNaN(parseFloat(item['SH'])) ? parseFloat(item['SH']).toFixed(4) : '';

                    const cbmHtml = `<button class="btn btn-sm btn-outline-primary cbm-icon"
                            data-gw_lb="${gwLb}"
                            data-gw_kg="${gwKg}"
                            data-cbm_msl="${cbmMsl}"
                            data-cp="${cp}"
                            data-freight="${freight}"
                            data-lp="${lp}"
                            data-sh="${sh}"
                            style="margin-left: 5px;"
                            title="View Logistics Info">
                            <i class="fas fa-eye me-1"></i> View
                        </button>
                        `;

                    $row.append($('<td>').html(cbmHtml));

                    $row.append($('<td>').text(item['Supplier Tag']));

                    $row.append($('<td>').html(`
                        <select class="form-select form-select-sm editable-select" 
                                data-type="NR" 
                                data-sku="${item['(Child) sku']}" 
                                data-parent="${item['Parent']}" 
                                style="width: auto; min-width: 85px; padding: 4px 24px 4px 8px; 
                                    font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                    background-color: #fff;">
                            <option value="">Select</option>
                            <option value="Yes" ${item['nr'] === 'Yes' ? 'selected' : ''}>Yes</option>
                            <option value="No" ${item['nr'] === 'No' ? 'selected' : ''}>No</option>
                            <option value="Later" ${item['nr'] === 'Later' ? 'selected' : ''}>Later</option>
                        </select>
                    `));

                    $row.append($('<td>').html(`
                        <select class="form-select form-select-sm editable-select" 
                                data-type="REQ" 
                                data-sku="${item['(Child) sku']}" 
                                data-parent="${item['Parent']}" 
                                style="width: auto; min-width: 85px; padding: 4px 24px 4px 8px; 
                                    font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                    background-color: #fff;">
                            <option value="">Select</option>
                            <option value="Yes" ${item['req'] === 'Yes' ? 'selected' : ''}>Yes</option>
                            <option value="No" ${item['req'] === 'No' ? 'selected' : ''}>No</option>
                            <option value="Later" ${item['req'] === 'Later' ? 'selected' : ''}>Later</option>
                        </select>
                    `));

                    $row.append($('<td>').html(`
                        <select class="form-select form-select-sm editable-select" 
                                data-type="Hide" 
                                data-sku="${item['(Child) sku']}" 
                                data-parent="${item['Parent']}" 
                                style="width: auto; min-width: 85px; padding: 4px 24px 4px 8px; 
                                    font-size: 0.875rem; border-radius: 4px; border: 1px solid #dee2e6;
                                    background-color: #fff;">
                            <option value="">Select</option>
                            <option value="@Need" ${item['hide'] === '@Need' ? 'selected' : ''}>@Need</option>
                            <option value="@Taken" ${item['hide'] === '@Taken' ? 'selected' : ''}>@Taken</option>
                            <option value="@Senior" ${item['hide'] === '@Senior' ? 'selected' : ''}>@Senior</option>
                        </select>
                    `));


                    $row.append(
                        $('<td>').append(
                            item['notes']
                                ? $('<i>')
                                    .addClass('fas fa-eye text-info ms-2 view-note-btn')
                                    .css('cursor', 'pointer')
                                    .attr('title', 'View Note')
                                    .attr('data-note', item['notes'])
                                : ''
                        ).append(
                            $('<i>')
                                .addClass('fas fa-edit text-primary ms-2 edit-notes-btn')
                                .css('cursor', 'pointer')
                                .attr('title', 'Edit Note')
                                .attr('data-note', item['notes'] ?? '')
                                .attr('data-sku', item['(Child) sku'] ?? '')
                                .attr('data-parent', item['Parent'] ?? '')
                        )
                    );


                    // RFQ Form link
                    const rfqFormLink = item.rfq_form_link ?.trim() ?? '';
                    const $rfqFormCell = $('<td>').html(`
                        ${rfqFormLink ? `
                            <a href="${rfqFormLink}" target="_blank" title="Open RFQ Form">
                                <i class="fas fa-file-contract text-success me-1"></i>
                            </a>
                            <a href="#" class="copy-link" data-link="${rfqFormLink}" title="Copy RFQ Form Link">
                                <i class="fas fa-copy text-primary me-1"></i>
                            </a>
                        ` : ''}
                        <a href="#" class="edit-rfq-form" title="Edit RFQ Form Link">
                            <i class="fas fa-edit text-warning"></i>
                        </a>
                    `);

                    // Edit button handler
                    $rfqFormCell.on('click', '.edit-rfq-form', function (e) {
                        e.preventDefault();
                        editingLinkCell = $rfqFormCell;
                        editingRow = item;
                        editingField = 'rfq_form_link';

                        $('#linkEditModalLabel').text('Edit RFQ Form Link');
                        $('#linkLabel').text('RFQ Form Link:');
                        $('#linkEditInput').val(rfqFormLink);
                        $('#linkEditModal').modal('show');
                    });

                    $row.append($rfqFormCell);

                    // RFQ Report link
                    const rfqReport = item.rfq_report?.trim() ?? '';
                    const $rfqReportCell = $('<td>').html(`
                        ${rfqReport ? `
                            <a href="${rfqReport}" target="_blank" title="Open RFQ Report">
                                <i class="fas fa-file-contract text-success me-1"></i>
                            </a>
                            <a href="#" class="copy-link" data-link="${rfqReport}" title="Copy RFQ Report Link">
                                <i class="fas fa-copy text-primary me-1"></i>
                            </a>
                        ` : ''}
                        <a href="#" class="edit-rfq-report" title="Edit RFQ Report Link">
                            <i class="fas fa-edit text-warning"></i>
                        </a>
                    `);

                    // Edit button handler
                    $rfqReportCell.on('click', '.edit-rfq-report', function (e) {
                        e.preventDefault();
                        editingLinkCell = $rfqReportCell;
                        editingRow = item;
                        editingField = 'rfq_report';

                        $('#linkEditModalLabel').text('Edit RFQ Report Link');
                        $('#linkLabel').text('RFQ Report Link:');
                        $('#linkEditInput').val(rfqReport);
                        $('#linkEditModal').modal('show');
                    });

                    $row.append($rfqReportCell);
                    
                    $row.append($('<td>').html(`
                        <input type="date" 
                            class="form-control form-control-sm editable-date"
                            data-sku="${item['(Child) sku']}"
                            data-parent="${item.Parent}"
                            data-field="Date of Appr"
                            value="${item.date_apprvl || ''}"
                            data-original="${item['Date of Appr'] || ''}"
                            style="min-width: 120px;"
                        >
                    `));

                    $tbody.append($row);
                });

                updatePaginationInfo();
                $('#visible-rows').text(`Showing all ${filteredData.length} rows`);
                // Initialize tooltips
                initTooltips();
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

                    const itemId = itemData['SL No.'] || 'unknown';
                    const modalId = `modal-${itemId}-${type.replace(/\s+/g, '-').toLowerCase()}`;

                    // Check cache first - use the cached data if available
                    const cachedData = amazonDataCache.get(itemId);
                    const dataToUse = cachedData || itemData;

                    // Store the data in cache if it wasn't already
                    if (!cachedData) {
                        amazonDataCache.set(itemId, itemData);
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
                                    title: 'AMZ',
                                    content: selectedItem['AMZ']
                                },
                                {
                                    title: 'PFT %',
                                    content: selectedItem['PFT %']
                                },
                                {
                                    title: 'TPFT',
                                    content: selectedItem['TPFT']
                                },
                                {
                                    title: 'Roi',
                                    content: selectedItem['Roi']
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
                    'Tacos60', 'Tacos30', 'PFT %', 'TPFT', 'Roi'
                ];

                const getIndicatorColor = (fieldTitle, fieldValue) => {
                    const value = (fieldValue * 100).toFixed(2) || 0;

                    if (type === 'price view') {
                        if (['PFT %', 'TPFT', 'Spft%'].includes(fieldTitle)) {
                            if (value < 10) return 'red';
                            if (value >= 10 && value < 15) return 'yellow';
                            if (value >= 15 && value < 20) return 'blue';
                            if (value >= 20 && value < 40) return 'green';
                            if (value >= 40) return 'pink';
                        }
                        if (fieldTitle === 'Roi') {
                            if (value <= 50) return 'red';
                            if (value > 50 && value <= 75) return 'yellow';
                            if (value > 75 && value <= 100) return 'green';
                            if (value > 100) return 'pink';
                        }
                        return 'gray';
                    }

                    if (type === 'advertisement view') {
                        if (['KwAcos60', 'KwAcos30', 'PtAcos60', 'PtAcos30', 'DspAcos60', 'DspAcos30',
                                'TAcos60', 'TAcos30'
                            ]
                            .includes(fieldTitle)) {
                            if (value === 0) return 'red';
                            if (value > 0.01 && value <= 7) return 'pink';
                            if (value > 7 && value <= 14) return 'green';
                            if (value > 14 && value <= 21) return 'blue';
                            if (value > 21 && value <= 28) return 'yellow';
                            if (value > 28) return 'red';
                        }
                        if (['KwCvr60', 'KwCvr30', 'PtCvr60', 'DspCvr60', 'PtCvr30', 'DspCvr30', 'HdAcos60',
                                'HdAcos30',
                                'HdCvr60', 'HdCvr30', 'TCvr60', 'TCvr30'
                            ].includes(fieldTitle)) {
                            if (value <= 7) return 'red';
                            if (value > 7 && value <= 13) return 'green';
                            if (value > 13) return fieldTitle.includes('PtCvr') || fieldTitle.includes(
                                    'DspCvr') ||
                                fieldTitle.includes('HdCvr') || fieldTitle.includes('TCvr') ? 'pink' :
                                'gray';
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
                const $table = $('#amazon-table');
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
                const $table = $('#amazon-table');
                const $headers = $table.find('th[data-field]');
                const $menu = $('#columnToggleMenu');
                const $dropdownBtn = $('#hideColumnsBtn');

                const STORAGE_KEY = 'amazon_hidden_columns';
                const hiddenFields = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');

                $menu.empty();

                $headers.each(function () {
                    const $th = $(this);
                    const field = $th.data('field');
                    const title = $th.text().trim().replace(' ↓', '');
                    const isChecked = !hiddenFields.includes(field);

                    const $item = $(`
                        <div class="column-toggle-item">
                            <input type="checkbox" class="column-toggle-checkbox"
                                id="toggle-${field}" data-field="${field}" ${isChecked ? 'checked' : ''}>
                            <label for="toggle-${field}">${title}</label>
                        </div>
                    `);

                    $menu.append($item);
                });

                $dropdownBtn.on('click', function (e) {
                    e.stopPropagation();
                    $menu.toggleClass('show');
                });

                $(document).on('click', function (e) {
                    if (!$(e.target).closest('.custom-dropdown').length) {
                        $menu.removeClass('show');
                    }
                });

                $menu.on('change', '.column-toggle-checkbox', function () {
                    const field = $(this).data('field');
                    const isVisible = $(this).is(':checked');

                    const colIndex = $headers.filter(`[data-field="${field}"]`).index();
                    $table.find('tr').each(function () {
                        $(this).find('td, th').eq(colIndex).toggle(isVisible);
                    });

                    // Update hidden columns in localStorage
                    const updatedHiddenFields = [];
                    $menu.find('.column-toggle-checkbox').each(function () {
                        if (!$(this).is(':checked')) {
                            updatedHiddenFields.push($(this).data('field'));
                        }
                    });
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(updatedHiddenFields));
                });

                // Show all columns
                $('#showAllColumns').on('click', function () {
                    $menu.find('.column-toggle-checkbox').prop('checked', true).trigger('change');
                    localStorage.setItem(STORAGE_KEY, JSON.stringify([])); // Clear hidden fields
                    $menu.removeClass('show');
                });

                // Initial hide based on saved settings
                hiddenFields.forEach(field => {
                    const colIndex = $headers.filter(`[data-field="${field}"]`).index();
                    $table.find('tr').each(function () {
                        $(this).find('td, th').eq(colIndex).hide();
                    });
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

            // Apply column filters
            function applyColumnFilters() {
                // Reset filteredData to all data first
                filteredData = [...tableData];

                // Apply row type filter first
                const rowTypeFilter = $('#row-data-type').val();
                if (rowTypeFilter === 'parent') {
                    filteredData = filteredData.filter(item => item.is_parent);
                } else if (rowTypeFilter === 'sku') {
                    filteredData = filteredData.filter(item => !item.is_parent);
                }

                // Then apply other filters as before
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
                const value = parseFloat(rowData[column]) * 100;

                // Special cases for numeric columns that must be valid numbers
                const numericColumns = ['PFT %', 'Roi', 'Tacos30', 'SCVR']; // Add other numeric columns as needed
                if (numericColumns.includes(column) && isNaN(value)) {
                    return '';
                }


                const colorRules = {
                    'Dil%': {
                        ranges: [16.66, 25, 50], // Key change here
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'A Dil%': {
                        ranges: [16.66, 25, 50],
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'PFT %': {
                        ranges: [10, 15, 20, 40],
                        colors: ['red', 'yellow', 'blue', 'green', 'pink']
                    },
                    'Roi': {
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
                        rowCount: 0
                    };

                    filteredData.forEach(item => {
                        metrics.invTotal += parseFloat(item['INV']) || 0;
                        metrics.ovL30Total += parseFloat(item['L30'] ) || 0;
                        metrics.el30Total += parseFloat(item['A L30']) || 0;
                        metrics.eDilTotal += parseFloat(item['A Dil%']) || 0;
                        metrics.viewsTotal += parseFloat(item.Sess30) || 0;
                        metrics.tacosTotal += parseFloat(item.Tacos30) || 0;
                        metrics.pftSum += parseFloat(item['PFT %']) || 0;
                        metrics.roiSum += parseFloat(item.Roi) || 0;
                        metrics.scvrSum += parseFloat(item.SCVR) || 0;
                        metrics.rowCount++;
                    });

                    metrics.ovDilTotal = metrics.invTotal > 0 ?
                        (metrics.ovL30Total / metrics.invTotal) * 100 : 0;
                    const divisor = metrics.rowCount || 1;

                    // Update metric displays
                    $('#inv-total').text(metrics.invTotal.toLocaleString());
                    $('#ovl30-total').text(metrics.ovL30Total.toLocaleString());
                     $('#ovdil-total').text(Math.round(metrics.ovDilTotal) + '%');
                    $('#al30-total').text(metrics.el30Total.toLocaleString());
                    $('#lDil-total').text(Math.round(metrics.eDilTotal / divisor * 100) + '%');
                    $('#views-total').text(metrics.viewsTotal.toLocaleString());
                    $('#pft-total').text(Math.round(metrics.pftSum / divisor * 100) + '%');
                    $('#roi-total').text(Math.round(metrics.roiSum / divisor * 100) + '%');
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
    <script>
        let currentNotesCell = null;

        $(document).on('click', '.edit-notes-btn', function () {
            const note = $(this).data('note') || '';
            const $tr = $(this).closest('tr');

            const sku = $tr.attr('data-sku') || '';
            const parent = $tr.attr('data-parent') || '';

            if (!sku || !parent) {
                alert('Missing SKU or Parent for this row.');
                return;
            }

            $('#notesInput').val(note);
            $('#editNotesModal')
                .data('sku', sku)
                .data('parent', parent);

            // ✅ Open modal using Bootstrap's API
            $('#editNotesModal').modal('show');
        });

        $('#saveNotesBtn').on('click', function () {
            const newNote = $('#notesInput').val().trim();
            const sku = $('#editNotesModal').data('sku');
            const parent = $('#editNotesModal').data('parent');

            if (!sku || !parent) {
                alert('SKU or Parent missing.');
                return;
            }

            $.ajax({
                url: '/update-forecast-data',
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                data: {
                    sku: sku,
                    parent: parent,
                    column: 'Notes',
                    value: newNote
                },
                success: function (res) {
                    console.log(res.message);
                    $('#editNotesModal').modal('hide');

                    // Find the related cell using data-sku and data-parent
                    const cell = $(`.edit-notes-btn[data-sku="${sku}"][data-parent="${parent}"]`).closest('td');

                    if (cell.length === 0) {
                        console.warn('Cell not found for SKU:', sku, 'and Parent:', parent);
                        return;
                    }

                    // Clear the existing content
                    cell.empty();

                    // View button (eye icon)
                    const viewBtn = $('<i>')
                        .addClass('fas fa-eye text-info ms-2 view-note-btn')
                        .css('cursor', 'pointer')
                        .attr('title', 'View Note')
                        .attr('data-note', newNote);

                    // Edit button (pencil icon)
                    const editBtn = $('<i>')
                        .addClass('fas fa-edit text-primary ms-2 edit-notes-btn')
                        .css('cursor', 'pointer')
                        .attr('title', 'Edit Note')
                        .attr('data-note', newNote)
                        .attr('data-sku', sku)
                        .attr('data-parent', parent);

                    // Append everything back to cell
                    cell.append(viewBtn, editBtn);
                },
                error: function (err) {
                    console.error(err);
                    alert('Failed to update note.');
                }
            });
        });

        $(document).on('click', '.view-note-btn', function () {
            const note = $(this).data('note') || 'No note';
            alert(`Note:\n${note}`);
        });


        // Handle blur for contenteditable fields
        $(document).off('blur', '.editable-qty').on('blur', '.editable-qty', function () {
            const $cell = $(this);
            const newValueRaw = $cell.text().trim();
            const originalValue = ($cell.data('original') ?? '').toString().trim();
            const field = $cell.data('field');
            const sku = $cell.data('sku');
            const parent = $cell.data('parent');

            // Convert raw value to number safely
            const newValue = ['Approved QTY', 'S-MSL', 'ORDER given'].includes(field)
                ? Number(newValueRaw)
                : newValueRaw;

            const original = ['Approved QTY', 'S-MSL', 'ORDER given'].includes(field)
                ? Number(originalValue)
                : originalValue;

            // Avoid unnecessary updates
            if (newValue === original) return;

            // Numeric validation
            if (['Approved QTY', 'S-MSL', 'ORDER given'].includes(field) && isNaN(newValue)) {
                alert('Please enter a valid number.');
                $cell.text(originalValue); // revert
                return;
            }

            // Optional validation for date fields (YYYY-MM-DD)
            if (['Date of Appr'].includes(field)) {
                const isValidDate = /^\d{4}-\d{2}-\d{2}$/.test(newValue);
                if (!isValidDate) {
                    alert('Please enter a valid date in YYYY-MM-DD format.');
                    $cell.text(originalValue);
                    return;
                }
            }

            updateForecastField({ sku, parent, column: field, value: newValue }, function () {
                $cell.data('original', newValue);
            }, function () {
                $cell.text(originalValue);
            });
        });

        // Handle change for dropdowns
        $(document).off('change', '.editable-select').on('change', '.editable-select', function () {
            const $select = $(this);
            const newValue = $select.val();
            const field = $select.data('type');
            const $row = $select.closest('tr');
            const sku = $row.find('[data-sku]').data('sku');
            const parent = $row.find('[data-parent]').data('parent');

            updateForecastField({ sku, parent, column: field, value: newValue });
        });

        $(document).off('change', '.editable-date').on('change', '.editable-date', function () {
            const $input = $(this);
            const newValue = $input.val().trim();
            const sku = $input.data('sku');
            const parent = $input.data('parent');
            const field = $input.data('field');
            const originalValue = $input.data('original');

            if (newValue === originalValue) return;
            console.log(newValue, originalValue);

            updateForecastField(
                { sku, parent, column: field, value: newValue },
                () => $input.data('original', newValue),
                () => $input.val(originalValue)
            );
        });


        // Reusable AJAX call
        function updateForecastField(data, onSuccess = () => {}, onFail = () => {}) {
            $.post('/update-forecast-data', {
                ...data,
                _token: $('meta[name="csrf-token"]').attr('content')
            }).done(res => {
                if (res.success) {
                    console.log('Saved:', res.message);
                    onSuccess();
                } else {
                    console.warn('Not saved:', res.message);
                    onFail();
                }
            }).fail(err => {
                console.error('AJAX failed:', err);
                alert('Error saving data.');
                onFail();
            });
        }

        //copy link to clipboard
        $(document).on('click', '.copy-link', function () {
            const link = $(this).data('link');

            if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link)
                .catch(() => {
                alert('Failed to copy!');
                });
            } else {
            // Fallback for unsupported browsers
            const tempInput = $('<input>');
                $('body').append(tempInput);
                tempInput.val(link).select();
                document.execCommand('copy');
                tempInput.remove();
            }
        });
    </script>
@endsection
