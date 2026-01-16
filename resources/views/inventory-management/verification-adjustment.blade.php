@extends('layouts.vertical', ['title' => 'Verification Adjustment', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
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
        
        /* Ensure table cells maintain proper alignment */
        .custom-resizable-table th,
        .custom-resizable-table td {
            box-sizing: border-box;
        }

        .custom-resizable-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            user-select: none;
            position: sticky;
            top: 0;
            z-index: 10;
            text-align: center;
            vertical-align: middle;
        }

        /* Allow text selection and interaction in search inputs */
        .custom-resizable-table th input,
        .custom-resizable-table th select,
        .custom-resizable-table th button {
            user-select: auto;
            pointer-events: auto;
        }

        /* Center header content but exclude search containers */
        .custom-resizable-table th > div:not(.dropdown-search-container):not(.header-text) {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        /* Ensure search containers are clickable and properly positioned */
        .custom-resizable-table th .dropdown-search-container {
            position: relative;
            z-index: 100 !important;
            pointer-events: auto !important;
            margin-top: 8px;
            width: 100%;
            display: block;
        }

        .custom-resizable-table th .dropdown-search-container input {
            pointer-events: auto !important;
            z-index: 101 !important;
            position: relative;
            cursor: text !important;
            user-select: auto !important;
            -webkit-user-select: auto !important;
            -moz-user-select: auto !important;
            -ms-user-select: auto !important;
        }

        .custom-resizable-table th .dropdown-search-results {
            pointer-events: auto !important;
            z-index: 102 !important;
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

        /* ========== VERIFIED STOCK INPUT ========== */
        .verified-stock-input {
            min-width: 100px;
            width: 100%;
            max-width: 120px;
        }

        /* ========== TABLE CONTROLS ========== */
        .table-controls {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 10px 0;
            border-top: 1px solid #ddd;
        }
        
        /* ========== PAGINATION CONTROLS ========== */
        .pagination-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 0;
            margin-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .pagination-controls .form-group {
            margin: 0;
            margin-right: 15px;
        }
        
        .pagination-controls button {
            min-width: 80px;
        }
        
        .pagination-controls button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ========== VERIFIED STATUS BUTTONS ========== */
        .verified-status-btn {
            min-width: 120px;
            font-size: 12px;
            padding: 4px 8px;
        }

        .verified-filter-btn {
            margin-right: 5px;
        }

        .verified-filter-btn.active {
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.5);
        }
        
        /* Ensure verified filter button is green */
        #filter-verified-green,
        #filter-verified-green.btn-success {
            background-color: #28a745 !important;
            border-color: #28a745 !important;
            color: #fff !important;
        }
        
        #filter-verified-green:hover,
        #filter-verified-green.btn-success:hover {
            background-color: #218838 !important;
            border-color: #1e7e34 !important;
        }
        
        #filter-verified-green.active,
        #filter-verified-green.btn-success.active {
            background-color: #218838 !important;
            border-color: #1e7e34 !important;
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.5) !important;
        }
        
        /* Ensure unverified filter button is red */
        #filter-verified-yellow,
        #filter-verified-yellow.btn-danger {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
        }
        
        #filter-verified-yellow:hover,
        #filter-verified-yellow.btn-danger:hover {
            background-color: #c82333 !important;
            border-color: #bd2130 !important;
        }
        
        #filter-verified-yellow.active,
        #filter-verified-yellow.btn-danger.active {
            background-color: #c82333 !important;
            border-color: #bd2130 !important;
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.5) !important;
        }

        /* ========== HIDE LAST APPROVED COLUMN ========== */
        /* Hide Last Approved column header and data cells while maintaining column alignment */
        .custom-resizable-table th.last-approved-header,
        .custom-resizable-table td.last-approved-at {
            width: 0 !important;
            min-width: 0 !important;
            max-width: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            border-left: none !important;
            border-right: none !important;
            overflow: hidden !important;
            visibility: hidden !important;
            font-size: 0 !important;
            line-height: 0 !important;
        }
        
        /* Ensure table layout is fixed for proper column alignment */
        .custom-resizable-table {
            table-layout: auto;
        }


        /* ========== SORTING ========== */
        .sortable {
            cursor: pointer;
        }

        .sortable:hover {
            background-color: #f1f1f1;
        }

        .sort-arrow {
            display: none; /* Hide sort arrow icon but keep functionality */
            margin-left: 5px;
        }

        /* ========== PARENT ROWS ========== */
        .parent-row {
            /* background-color: rgba(69, 233, 255, 0.1) !important; */
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

        /* ========== SKU HISTORY EYE ICON ========== */
        .view-sku-history-icon {
            transition: all 0.2s ease;
        }

        .view-sku-history-icon:hover {
            transform: scale(1.2);
            color: #0056b3 !important;
        }

        .view-parent-history-icon {
            transition: all 0.2s ease;
        }

        .view-parent-history-icon:hover {
            transform: scale(1.2);
            color: #17a2b8 !important;
        }

        /* ========== HORIZONTAL HEADERS (2X SIZE) ========== */
        .horizontal-header {
            text-align: center;
            white-space: normal;
            vertical-align: middle;
            font-size: 2em !important; /* 2x the normal size (24px -> 48px) */
            font-weight: bold;
            line-height: 1.2;
            padding: 15px 10px !important;
            width: auto;
            min-width: 100px;
        }

        .horizontal-header .header-text {
            display: block;
            width: 100%;
            text-align: center;
            white-space: nowrap;
        }

        .horizontal-header .dropdown-search-container {
            margin-top: 10px;
            width: 100%;
            position: relative;
            z-index: 100;
            pointer-events: auto;
        }

        .horizontal-header .search-input {
            margin-top: 8px;
            font-size: 0.5em !important; /* Half size for search inputs (24px -> 12px) */
            width: 100%;
            max-width: 150px;
            margin: 8px auto 0;
            pointer-events: auto;
            cursor: text;
            user-select: auto;
            position: relative;
            z-index: 101;
        }

        .horizontal-header .dropdown-search-results {
            pointer-events: auto;
            z-index: 102;
        }

        .horizontal-header .sort-arrow {
            font-size: 0.7em !important; /* Slightly smaller for sort arrow */
            vertical-align: middle;
        }

        .horizontal-header .sortable-header {
            display: inline-block;
        }

        /* ========== ACTIVITY LOG FILTER BAR ========== */
        .activity-log-filter-bar {
            border-radius: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .activity-log-filter-bar .form-label {
            font-size: 13px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .activity-log-filter-bar .form-control {
            border: 1px solid rgba(255,255,255,0.3);
            background-color: rgba(255,255,255,0.95);
            transition: all 0.3s ease;
        }

        .activity-log-filter-bar .form-control:focus {
            background-color: #fff;
            border-color: rgba(255,255,255,0.8);
            box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.5);
        }

        .activity-log-filter-bar .btn-light {
            background-color: rgba(255,255,255,0.9);
            border: 1px solid rgba(255,255,255,0.5);
            color: #667eea;
            font-weight: 600;
        }

        .activity-log-filter-bar .btn-light:hover {
            background-color: #fff;
            color: #764ba2;
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

        /* set up table  */
        #ebay-table th,
        #ebay-table td {
            padding: 4px 6px !important;
            font-size: 12px;
            white-space: nowrap;
        }

         #ebay-table thead tr#summaryRow th {
        position: sticky;
        top: 0;
        background: #f8f9fa;
        z-index: 11;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    /* Make the main header row stick below summary row */
    #ebay-table thead tr:nth-child(2) th {
        position: sticky;
        top: 36px; /* height of summary row */
        background: #ffffff;
        z-index: 10;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    /* Optional: ensure headers are tall enough and vertical */
    #ebay-table thead th {
        height: auto;
        min-height: 120px;
        max-width: 50px;
        padding: 8px 4px !important;
        text-align: center;
        vertical-align: bottom;
    }
    
    #ebay-table thead th > div {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: flex-start;
        height: 100%;
    }
    
    #ebay-table thead th > div > div.d-flex:not(.dropdown-search-container) {
        writing-mode: vertical-rl;
        text-orientation: mixed;
        transform: rotate(180deg);
        white-space: nowrap;
        margin: 4px 0;
    }
    
    #ebay-table thead th > div > div.dropdown-search-container {
        writing-mode: horizontal-tb;
        transform: none;
        margin-top: 8px;
        width: 100%;
        pointer-events: auto;
        z-index: 100;
        position: relative;
    }
    
    #ebay-table thead th > div > div.dropdown-search-container input {
        transform: none;
        width: 100%;
        height: 24px;
        pointer-events: auto;
        cursor: text;
        user-select: auto;
        position: relative;
        z-index: 101;
    }

    #ebay-table thead th > div > div.dropdown-search-results {
        pointer-events: auto;
        z-index: 102;
    }

    #ebay-table {
        color: #000 !important; /* Force dark black font */
    }
    
    /* Reason dropdown styling */
    .reason-select {
        min-width: 120px;
        font-size: 12px;
        padding: 4px 8px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        background-color: #fff;
        cursor: pointer;
    }
    
    .reason-select:focus {
        border-color: #80bdff;
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
    
    .reason-select option {
        padding: 4px 8px;
        background-color: #fff;
    }
    

    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => 'Inventory Management', 'sub_title' => 'Verification & Adjustment'])
    <!-- <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <button id="updateAllSkusBtn" class="btn btn-primary">
                        <i class="ri-refresh-line me-1"></i> Update All SKUs
                    </button>
                    <div id="updateStatus" class="mt-2"></div>
                </div>
            </div>
        </div>
    </div> -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Verification & Adjustment</h4>

                    <div class="d-flex justify-content-between align-items-center mb-3 w-100">
                        <!-- Left Side: Play Controls + Activity Log -->
                        <div class="d-flex align-items-center">
                            <div class="btn-group time-navigation-group mr-2" role="group" aria-label="Parent navigation">
                                <button id="play-backward" class="btn btn-light rounded-circle" title="Previous parent">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button id="play-pause" class="btn btn-light rounded-circle" title="Pause" style="display: none;">
                                    <i class="fas fa-pause"></i>
                                </button>
                                <button id="play-auto" class="btn btn-light rounded-circle" title="Play">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button id="play-forward" class="btn btn-light rounded-circle" title="Next parent">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                            </div>

                            <button id="activity-log-btn" class="btn btn-dark ml-2 me-2" data-toggle="modal" data-target="#activityLogModal">
                                <i class="fas fa-history"></i> Activity Log
                            </button>

                            <button id="exportExcel" class="btn btn-success ml-2">
                                <i class="fas fa-file-excel"></i> Export
                            </button>

                            <!-- <button id="viewHiddenRows" class="btn btn-primary ml-2 ms-2" data-toggle="modal">
                                <i class="fa-regular fa-eye-slash"></i> Hide Rows
                            </button> -->
                        </div>

                    </div>


                     <!-- Activity Log Modal -->
                    <div class="modal fade" id="activityLogModal" tabindex="-1" role="dialog" aria-labelledby="activityLogModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="activityLogModalLabel">Activity Log</h5>
                                <!-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> -->
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" style="padding: 0;">
                                <!-- Filter Bar with Colored Background -->
                                <div class="activity-log-filter-bar" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; margin-bottom: 0;">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label for="activityLogFilterParent" class="form-label text-white" style="font-weight: 600; margin-bottom: 5px;">
                                                <i class="fas fa-filter"></i> Filter by Parent
                                            </label>
                                            <input type="text" id="activityLogFilterParent" class="form-control form-control-sm" placeholder="Search Parent...">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="activityLogFilterSku" class="form-label text-white" style="font-weight: 600; margin-bottom: 5px;">
                                                <i class="fas fa-filter"></i> Filter by SKU
                                            </label>
                                            <input type="text" id="activityLogFilterSku" class="form-control form-control-sm" placeholder="Search SKU...">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="activityLogFilterPerson" class="form-label text-white" style="font-weight: 600; margin-bottom: 5px;">
                                                <i class="fas fa-user"></i> Filter by Person
                                            </label>
                                            <input type="text" id="activityLogFilterPerson" class="form-control form-control-sm" placeholder="Search Person...">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="activityLogFilterReason" class="form-label text-white" style="font-weight: 600; margin-bottom: 5px;">
                                                <i class="fas fa-tag"></i> Filter by Reason
                                            </label>
                                            <input type="text" id="activityLogFilterReason" class="form-control form-control-sm" placeholder="Search Reason...">
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12 text-end">
                                            <button id="activityLogClearFilters" class="btn btn-light btn-sm">
                                                <i class="fas fa-times"></i> Clear All Filters
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div style="padding: 15px;">
                                    <table class="table table-bordered" id="activityLogTable">
                                <thead>
                                    <tr>
                                    <th>Parent</th>
                                    <th>SKU</th>
                                    <th>Verified</th>
                                    <th>Adjusted</th>
                                    <th>
                                        <span id="activityLossGainTotal" class="badge bg-primary fs-4"> 0 </span><br>
                                        Loss/Gain 
                                    </th>
                                    <th>Reason</th>
                                    <th>Approved By</th>
                                    <th>Approved</th>
                                    <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Will be populated via JS -->
                                </tbody>
                                    </table>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- History modal  -->
                    <div class="modal fade" id="skuHistoryModal" tabindex="-1" aria-labelledby="skuHistoryModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                            <div class="modal-header d-flex justify-content-between align-items-center">
                                <h5 class="modal-title" id="skuHistoryModalLabel">Adjustment History</h5>
                                <div class="d-flex align-items-center">
                                    <a href="#" id="shopifyInventoryLink" target="_blank" class="btn btn-success btn-sm me-2" style="display: none;">
                                        <i class="fas fa-external-link-alt"></i> View in Shopify
                                    </a>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                            </div>
                            <div class="modal-body" id="sku-history-content">
                                Loading...
                            </div>
                            </div>
                        </div>
                    </div>


                    <div class="modal fade" id="hiddenRowsModal" tabindex="-1" aria-labelledby="hideRowsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                            <div class="modal-header d-flex justify-content-between align-items-center">
                                <h5 class="modal-title" id="hideRowsModalLabel">Hidden Rows</h5>

                                <div class="d-flex align-items-center ms-auto">
                                    <input type="text" id="hiddenRowsSearch" 
                                        class="form-control me-2" 
                                        placeholder="Search..." 
                                        style="max-width: 250px;">
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                    {{-- <input type="text" id="hiddenRowsSearch" class="form-control ms-auto me-2" placeholder="Search..." style="max-width: 250px;"> --}}
                                    {{-- <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button> --}}
                            </div>
                            <div class="modal-body">
                                <table class="table" id="hiddenRowsTable">
                                <thead>
                                    <tr>
                                    <th><input type="checkbox" id="selectAllHidden"></th>
                                    <!-- <th>Parent</th> -->
                                    <th>SKU</th>
                                    <th>Verified</th>
                                    <th>Adjusted</th>
                                    <th>Loss/Gain</th>
                                    <th>Reason</th>
                                    <th>Approved By</th>
                                    <th>Approved</th>
                                    <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                                </table>
                                <button id="clearSelectedHiddenRows" class="btn btn-success">Unverified Selected Rows</button>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- <button id="openActivityLogBtn" class="btn btn-outline-dark rounded-circle ml-2" 
                            data-toggle="modal" data-target="#activityLogModal" title="Activity Log">
                        <i class="fas fa-list-alt"></i>
                    </button> -->

                    <!-- All Controls in One Row -->
                    <div class="d-flex flex-wrap align-items-center mb-3 gap-2" style="gap: 10px;">
                        <!-- Data Type -->
                        <div class="d-flex align-items-center">
                            <label for="row-data-type" class="mr-2 mb-0">Data Type:</label>
                            <select id="row-data-type" class="form-control form-control-sm" style="width: auto; min-width: 120px;">
                                <option value="all">All</option>
                                <option value="sku">SKU (Child)</option>
                                <option value="parent">Parent</option>
                            </select>
                        </div>
                        
                        <!-- Parent Search -->
                        <div class="d-flex align-items-center">
                            <label for="parentSearch" class="mr-2 mb-0">Parent:</label>
                            <div class="dropdown-search-container" style="position: relative;">
                                <input type="text" class="form-control form-control-sm parent-search" 
                                    placeholder="Search parent..." id="parentSearch" style="width: 150px;">
                                <div class="dropdown-search-results" id="parentSearchResults"></div>
                            </div>
                        </div>
                        
                        <!-- SKU Search -->
                        <div class="d-flex align-items-center">
                            <label for="skuSearch" class="mr-2 mb-0">SKU:</label>
                            <div class="dropdown-search-container" style="position: relative;">
                                <input type="text" class="form-control form-control-sm sku-search" 
                                    placeholder="Search SKU..." id="skuSearch" style="width: 150px;">
                                <div class="dropdown-search-results" id="skuSearchResults"></div>
                            </div>
                        </div>
                        
                        <!-- Search All Columns -->
                        <div class="d-flex align-items-center">
                            <label for="search-input" class="mr-2 mb-0">Search:</label>
                            <input type="text" id="search-input" class="form-control form-control-sm" placeholder="Search all columns" style="width: 180px;">
                        </div>
                        
                        <!-- Bulk Action Dropdown -->
                        <div class="d-flex align-items-center">
                            <label for="bulk-action-select" class="mr-2 mb-0">Action:</label>
                            <select id="bulk-action-select" class="form-control form-control-sm" style="width: auto; min-width: 180px;">
                                <option value="">Select action...</option>
                                <option value="mark-verified">Mark as Verified</option>
                                <option value="mark-unverified">Mark as Unverified</option>
                            </select>
                            <button id="apply-bulk-action" class="btn btn-primary btn-sm ml-2" style="margin-left: 5px;">Apply</button>
                        </div>
                        
                        <!-- All Filters -->
                        <div class="d-flex align-items-center" style="margin-left: 10px;">
                            <button id="filter-verified-green" class="btn btn-success btn-sm verified-filter-btn mr-2" data-status="green">
                                <i class="fas fa-check-circle"></i> Verified 
                                <span class="badge badge-light ml-1" id="green-count">0</span>
                            </button>
                            <button id="filter-verified-yellow" class="btn btn-danger btn-sm verified-filter-btn mr-2" data-status="yellow">
                                <i class="fas fa-circle"></i> Unverified 
                                <span class="badge badge-light ml-1" id="yellow-count">0</span>
                            </button>
                            <button id="filter-show-all" class="btn btn-secondary btn-sm filter-show-all-btn active" data-status="all">
                                <i class="fas fa-list"></i> Show All
                            </button>
                        </div>
                        
                        <!-- View Hidden Rows Button -->
                        <div class="ml-auto">
                            <button id="viewHiddenRows" class="btn btn-primary btn-sm" data-toggle="modal">
                                <i class="fa-regular fa-eye-slash"></i> Verified Rows
                            </button>
                        </div>
                    </div>

                        <!-- <div id="zeroInvLabel" style="background-color: #d0e7ff; color: #004080; font-size: 14px; font-weight: 600; padding: 6px 12px; border-radius: 6px; display: inline-block; margin-bottom: 10px;"> -->
                            <!-- Will be filled by JS -->
                        <!-- </div> -->

                            <!-- <div class="form-inline">
                                <div class="form-group mr-2">
                                    <label for="row-data-type" class="mr-2">Data Type:</label>
                                    <select id="row-data-type" class="form-control form-control-sm">
                                        <option value="all">All</option>
                                        <option value="sku">SKU (Child)</option>
                                        <option value="parent">Parent</option>
                                    </select>
                                </div>
                            </div> -->
                        <!-- <div>
                            <div class="form-group mr-2 custom-dropdown">
                                <button id="hideColumnsBtn" class="btn btn-sm btn-outline-secondary">
                                    Hide Columns
                                </button>
                                <div class="custom-dropdown-menu" id="columnToggleMenu">
                                </div>
                            </div>
                            <div class="form-group">
                                <button id="showAllColumns" class="btn btn-sm btn-outline-secondary">
                                    Show All
                                </button>
                            </div>
                        </div> -->

                        <!-- Search on right -->
                        <!-- <div class="form-inline">
                            <div class="form-group">
                                <label for="search-input" class="mr-2">Search:</label>
                                <input type="text" id="search-input" class="form-control form-control-sm"
                                    placeholder="Search all columns">
                            </div>
                        </div> -->
                    </div>

                   

                    <div class="table-container">
                        <table class="custom-resizable-table" id="ebay-table">
                            <thead>
                                <tr id="summaryRow">
                                    <th></th> <!-- Checkbox column -->
                                    <th colspan="4"></th> <!-- Skip IMAGES, Parent, SKU, R&A (4 columns) -->
                                    <th>
                                        <div class="metric-total" id="inv-total" style="font-weight: bold; color: #007bff;">0</div>
                                    </th>
                                    <th>
                                        <div class="metric-total" id="ovl30-total" style="font-weight: bold; color: #007bff;">0</div>
                                    </th>
                                    <th>
                                        <div class="metric-total" id="ovdil-total" style="font-weight: bold; color: #007bff;">0%</div>
                                    </th>
                                    <th>
                                        <div class="metric-total" id="onhand-total" style="font-weight: bold; color: #007bff;">0</div>
                                    </th> 
                                    <th>
                                        <div class="metric-total" id="committed-total" style="font-weight: bold; color: #007bff;">0</div>
                                    </th> 
                                    <th>
                                        <div class="metric-total" id="avltosell-total" style="font-weight: bold; color: #007bff;">0</div>
                                    </th> 
                                    <th colspan="10"></th> <!-- Skipping columns: VERIFIED, TO ADJUST, REASON, Accept, ADJ HISTORY (hidden), ADJ QTY, LOSS/GAIN, APPR-AT, VERIFIED, USER -->
                                    <th></th> <!-- REMARK -->
                                    <th class="last-approved-header"></th> <!-- LAST APPR-AT (hidden) -->
                                </tr>
                                <tr>
                                    <th data-field="bulk-select" style="width: 50px; text-align: center;">
                                        <input type="checkbox" id="select-all-checkbox" title="Select All">
                                    </th>
                                    <th data-field="sl_no">IMAGES <span class="sort-arrow">↓</span></th>
                                    <th data-field="Parent" class="horizontal-header">
                                        <div class="header-text">
                                            <span class="sortable-header">PARENT <span class="sort-arrow">↓</span></span>
                                        </div>
                                    </th>
                                    <th data-field="SKU" class="horizontal-header">
                                        <div class="header-text sortable">
                                            SKU <span class="sort-arrow">↓</span>
                                        </div>
                                    </th>
                                    <th data-field="r&a" class="hide-column"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                R&A <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="INV" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                INV <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div class="metric-total" id="inv-total">0</div> -->
                                        </div>
                                    </th>
                                    <th data-field="L30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div class="metric-total" id="ovl30-total">0</div> -->
                                        </div>
                                    </th>
                                    <th data-field="DIL" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                DIL <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div class="metric-total" id="ovdil-total">0%</div> -->
                                        </div>
                                    </th>
                                    <th data-field="AVAILABLE_TO_SELL" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            AVL TO SELL<span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div class="metric-total" id="el30-total">0</div> -->
                                        </div>
                                    </th>
                                    <th data-field="COMMITTED" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                COMMITTED <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div class="metric-total" id="eDil-total">0%</div> -->
                                        </div>
                                    </th>
                                    <th data-field="ON_HAND" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                            ON HAND <span class="sort-arrow">↓</span>
                                            </div>
                                            <!-- <div class="metric-total" id="views-total">0</div> -->
                                        </div>
                                    </th>
                                    <th data-field="price"
                                        style="vertical-align: middle; white-space: nowrap; padding-right: 4px; width: 130px;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                VERIFIED <span class="sort-arrow"></span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="pft" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                TO ADJUST <span class="sort-arrow"></span>      
                                            </div>
                                            <!-- <div class="metric-total" id="pft-total">0%</div> -->
                                        </div>
                                    </th>
                                    <th data-field="roi" class="horizontal-header">
                                        <div class="header-text">
                                            REASON <span class="sort-arrow"></span>
                                        </div>
                                    </th>
                                    <th data-field="remark" class="horizontal-header">
                                        <div class="header-text remarks-input">
                                            REMARK<span class="sort-arrow"></span>
                                        </div>
                                    </th>
                                    <th data-field="tacos" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                Accept <span class="sort-arrow"></span>
                                            </div>
                                            <!-- <div class="metric-total" id="tacos-total">0%</div> -->
                                        </div>
                                    </th>
                                    <th data-field="tacos" style="vertical-align: middle; white-space: nowrap; display: none;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                ADJ HISTORY<span class="sort-arrow"></span>
                                            </div>
                                        </div>
                                    </th>
                                    <!-- <th data-field="tacos" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                APPR-IH <span class="sort-arrow">↓</span>
                                            </div>
                                        </div>
                                    </th> -->
                                    <th data-field="cvr" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                ADJ QTY <span class="sort-arrow"></span>
                                            </div>
                                            <!-- <div class="metric-total" id="cvr-total">0%</div> -->
                                        </div>
                                    </th>
                                    <th data-field="ad cost/ pc" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <!-- <small id="lossGainTotalText" class="badge bg-success mb-1" style="font-size: 13px;">
                                                $ 0
                                            </small> -->
                                            <div class="d-flex align-items-center" id="lossGainHeader">
                                                LOSS/GAIN<span class="sort-arrow "></span>
                                            </div>
                                            <!-- <small id="lossGainTotalText" class="text-success"></small> -->
                                        </div>
                                    </th>
                                    <th data-field="ad cost/ pc" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                APPR-AT<span class="sort-arrow"></span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="ad cost/ pc" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                VERIFIED<span class="sort-arrow"></span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="user" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                USER<span class="sort-arrow"></span>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="ad cost/ pc" class="last-approved-header" style="vertical-align: middle; white-space: nowrap; width: 0; padding: 0; border: none; visibility: hidden;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center">
                                                LAST APPR-AT<span class="sort-arrow"></span>
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
                            <div class="loader-text">Loading data...</div>
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
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <!--for popup modal script-->
    <script>
        flatpickr("#duration", {
            enableTime: true,
            mode: "range",
            dateFormat: "M d, Y h:i K"
        });
    </script>

    <!--for popup modal script-->
    <script>
        document.body.style.zoom = "80%";
        $(document).ready(function() {
            $('#updateAllSkusBtn').click(function() {
                // Disable button and show loading state
                $(this).prop('disabled', true);
                $(this).html('<i class="ri-loader-4-line me-1"></i> Updating...');
                $('#updateStatus').html(
                '<div class="alert alert-info">Updating SKUs, please wait...</div>');

                // Get CSRF token from meta tag
                const csrfToken = $('meta[name="csrf-token"]').attr('content');

                // Make AJAX request
                $.ajax({
                    url: '/update-all-ebay-skus',
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
                        $('#updateAllSkusBtn').html(
                            '<i class="ri-refresh-line me-1"></i> Update All SKUs');
                    }
                });
            });

            // Cache system
            const ebayDataCache = {
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
                ebayDataCache.clear();
            });

            // Current state
            let currentPage = 1;
            let rowsPerPage = 25;
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
            let hiddenRows = [];
            let allData = []; 

            // Define status indicator fields for different modal types
            // const statusIndicatorFields = {
            //     'price view': ['PFT %', 'TPFT', 'ROI%', 'Spft%', 'a+spft', 'a+ROI'],
            //     'advertisement view': [
            //         'KwCtrL60', 'KwCtrL30', 'KwCtrL7',
            //         'KwAcosL60', 'KwAcosL30', 'KwAcosL7',
            //         'KwCvrL30', 'KwCvrL7',
            //         'Ub 7', 'Ub yes',
            //         'PmtCtrL30', 'PmtCtrL7',
            //         'PmtAcosL30', 'PmtAcosL7',
            //         'PmtCvrL30', 'PmtCvrL7',
            //         'Pmt%',
            //         'TacosL30'
            //     ],
            //     'conversion view': ['SCVR', 'KwCvrL60', 'KwCvrL30', 'KwCvrL7', 'PmtCvrL30', 'PmtCvrL7'],
            //     'visibility view': ['KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PmtCtrL30', 'PmtCtrL7']
            // };

            // Filter state
            const state = {
                filters: {
                    'Dil%': 'all',
                    'E Dil%': 'all',
                    'OV CLICKS L30': 'all',
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
                let totalRows = 0;

                parentRows.forEach(row => {
                    // Only count rows that have R&A data (not undefined/null/empty)
                    const ra = row['R&A'];

                    totalRows++; // Include every row regardless of blank/checked

                    if (ra === true || ra === 'true' || ra === 1 || ra === '1') {
                        checkedCount++;
                    }
                });

                // Determine which button is currently visible
                const $activeButton = $('#play-pause').is(':visible') ? $('#play-pause') : $('#play-auto');

                // Remove all state classes first
                $activeButton.removeClass('btn-success btn-warning btn-danger btn-light');

                if (checkedCount === 0) {
                    $activeButton.addClass('btn-danger'); // All unchecked or blank — red
                } else if (checkedCount === totalRows) {
                    $activeButton.addClass('btn-success'); // All checked — green
                } else {
                    $activeButton.addClass('btn-warning'); // Some checked — yellow
                }
                // console.log(`Checked: ${checkedCount}, Total: ${totalRows}`);
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

            

            // $(document).ready(function () {
            //     loadData().then((data) => {
            //         renderTable(data); 
            //     });
            // });

            function formatApprovedAt(rawDate) {
                if (!rawDate) return '-';

                const dateObj = new Date(rawDate);
                if (isNaN(dateObj)) return '-';

                const day = dateObj.getDate().toString().padStart(2, '0'); 
                const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                const month = monthNames[dateObj.getMonth()];
                const year = dateObj.getFullYear();

                let hours = dateObj.getHours();
                const minutes = dateObj.getMinutes().toString().padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;

                const datePart = `${day} ${month} ${year}`;
                const timePart = `${hours}:${minutes} ${ampm}`;

                return `<div style="line-height:1.3">${datePart}<br><small>${timePart}</small></div>`;
            }


            // Load data from server
            function loadData() {
                showLoader();
                
                return $.ajax({
                    url: '/verification-adjustment-data-view',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.data) {
                            console.log('FULL API RESPONSE:', response);
                            
                            tableData = response.data.map((item, index) => {
                                const INV = parseFloat(item.INV) || 0;
                                const L30 = parseFloat(item.L30) || 0;
                                let DIL = 0;

                                if (INV > 0 && L30 === 0) {
                                    DIL = 0;
                                } else if (INV !== 0) {
                                    DIL = (L30 / INV).toFixed(2);
                                }
                                
                                return {
                                    // sl_no: index + 1, // Serial number
                                    IMAGE_URL: item.IMAGE_URL || '',
                                    Parent: item.Parent || item.parent || item.parent_asin || item.Parent_ASIN || '(No Parent)',
                                    // SKU: item['SKU'] || '', // Normalize SKU field
                                    SKU: (item.sku || item.SKU || '').toString().trim(), // Normalize SKU field (support both keys)
                                    TITLE: item['TITLE'] || '', // Title field from the sheet

                                    INV: INV, // Inventory
                                    L30: L30, // Last 30 Days 
                                    DIL: DIL, 
                                    ON_HAND: item.ON_HAND || 0,
                                    COMMITTED: item.COMMITTED || 0,
                                    AVAILABLE_TO_SELL: item.AVAILABLE_TO_SELL || 0,
                                    VERIFIED_STOCK: item.verified_stock || '', // User input
                                    TO_ADJUST: item.to_adjust || '', // Auto-calculated
                                    REASON: item.reason || '', // Dropdown
                                    REMARK: item.REMARKS || item.remarks || '', // Remark field
                                    APPROVED: item.APPROVED === true, // Checkbox state
                                    APPROVED_BY:  item.approved_by || '',
                                    IS_VERIFIED: (item.is_verified === true || item.is_verified === 1 || item.is_verified === '1' || item.IS_VERIFIED === 1 || item.IS_VERIFIED === true) ? 1 : 0, // Verified status from DB
                                    VERIFIED_BY_FIRST_NAME: item.VERIFIED_BY_FIRST_NAME || item.verified_by_first_name || null, // First name of user who verified
                                    // LOSS_GAIN: item.LOSS_GAIN && !isNaN(item.LOSS_GAIN) ? parseFloat(item.LOSS_GAIN) : '',
                                    LOSS_GAIN: (item.APPROVED === true && !item.approved_at) ? item.LOSS_GAIN : '',

                                    APPROVED_AT: item.approved_at ? formatOhioTime(item.approved_at) : '',
                                    LAST_APPROVED_AT: formatApprovedAt(item.APPROVED_AT),

                                    is_parent: (() => {
                                        const skuVal = (item.sku || item.SKU || '').toString().trim().toUpperCase();
                                        const parentVal = (item.Parent || item.parent || item.parent_asin || item.Parent_ASIN || '').toString().trim().toUpperCase();
                                        // Only treat as parent if SKU explicitly starts with 'PARENT'
                                        // Don't treat standalone SKUs (where SKU === Parent) as parents
                                        return skuVal.startsWith('PARENT');
                                    })(),

                                    // OVERRIDE: Set IS_HIDE to 0 for all items to ensure all SKUs are displayed
                                    IS_HIDE: 0,
                                    is_hide: 0,

                                    raw_data: item || {} // Full original row, in case needed later
                                };
                            });

                            // Show all products from product_master, including those without Shopify data
                            // Default: Show all items
                            filteredData = [...tableData];

                            renderTable(filteredData);
                           
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


            function renderTable() {
                const $tbody = $('#ebay-table tbody');
                $tbody.empty();

                if (isLoading) {
                    $tbody.append('<tr><td colspan="24" class="text-center">Loading data...</td></tr>');
                    return;
                }

                // OVERRIDE: Display ALL SKUs from product_master - NO FILTERING
                // All filtering logic based on IS_HIDE or is_hide has been removed
                // All SKUs from product_master will be displayed regardless of any other conditions


                if (filteredData.length === 0) {
                    $tbody.append('<tr><td colspan="23" class="text-center">No matching records found</td></tr>');
                    return;
                }

                // Apply pagination
                const startIndex = (currentPage - 1) * rowsPerPage;
                const endIndex = startIndex + rowsPerPage;
                const paginatedData = filteredData.slice(startIndex, endIndex);
                
                // Group rows by Parent so that child SKUs render first and parent row is appended last
                const parentTotalsMap = {};
                const groups = {}; // parentName -> { children: [], parentRow: null }
                const parentOrder = [];

                paginatedData.forEach(item => {
                    const parentName = item.Parent || '(No Parent)';
                    const isParentRow = !!item.is_parent || (item.SKU && String(item.SKU).toUpperCase().startsWith('PARENT'));

                    if (!groups[parentName]) {
                        groups[parentName] = { children: [], parentRow: null };
                        parentOrder.push(parentName);
                    }

                    if (isParentRow) {
                        groups[parentName].parentRow = item;
                    } else {
                        groups[parentName].children.push(item);

                        // accumulate totals from children for parent totals
                        if (!parentTotalsMap[parentName]) {
                            parentTotalsMap[parentName] = {
                                INV: 0,
                                L30: 0,
                                ON_HAND: 0,
                                COMMITTED: 0,
                                AVAILABLE_TO_SELL: 0,
                                count: 0
                            };
                        }

                        parentTotalsMap[parentName].INV += parseFloat(item.AVAILABLE_TO_SELL) || 0;
                        parentTotalsMap[parentName].L30 += parseFloat(item.L30) || 0;
                        parentTotalsMap[parentName].ON_HAND += parseFloat(item.ON_HAND) || 0;
                        parentTotalsMap[parentName].COMMITTED += parseFloat(item.COMMITTED) || 0;
                        parentTotalsMap[parentName].AVAILABLE_TO_SELL += parseFloat(item.AVAILABLE_TO_SELL) || 0;
                        parentTotalsMap[parentName].count += 1;
                    }
                });

                // Build ordered array: for each parent, push children then parentRow (if exists)
                const orderedRows = [];
                parentOrder.forEach(parentName => {
                    const grp = groups[parentName];
                    if (!grp) return;
                    // push children in their original order
                    grp.children.forEach(ch => orderedRows.push(ch));
                    if (grp.parentRow) orderedRows.push(grp.parentRow);
                });

                // Now render rows from orderedRows
                orderedRows.forEach((item, rowIndex) => {
                    // OVERRIDE: Display ALL SKUs - removed filtering logic that hides rows
                    // Previously: Hide row if SKU contains parent as string - THIS HAS BEEN REMOVED
                    // All SKUs from product_master will be displayed
                    
                    // Define sku variable for use in rendering (no filtering applied)
                    const sku = item.SKU || '';

                    // Hide rows that have "parent" in SKU string (case-insensitive)
                    const skuUpper = String(sku).toUpperCase();
                    const hasParentInSku = skuUpper.includes('PARENT');

                    const $row = $('<tr>');
                    
                    // Hide row by default if SKU contains "parent"
                    if (hasParentInSku) {
                        $row.css('display', 'none');
                        $row.addClass('parent-sku-hidden');
                    }

                    const isParentRow = !!item.is_parent || (item.SKU && String(item.SKU).toUpperCase().startsWith('PARENT'));
                    if (isParentRow) {
                        $row.css({
                            // backgroundColor: 'rgba(13, 110, 253, 0.2)',
                            backgroundColor: 'rgba(69, 233, 255, 0.1)',
                            fontWeight: '500'
                        });

                        // Inject totals into parent row
                        const totals = parentTotalsMap[item.Parent];
                        if (totals) {
                            item.INV = totals.INV;
                            item.L30 = totals.L30;
                            item.ON_HAND = totals.ON_HAND;
                            item.COMMITTED = totals.COMMITTED;
                            item.AVAILABLE_TO_SELL = totals.AVAILABLE_TO_SELL;
                            item.DIL = (totals.INV > 0 && totals.L30 === 0) ? 0 : (totals.INV > 0 ? (totals.L30 / totals.INV).toFixed(2) : 0);
                        }
                    }

                    if (item.is_parent) {
                        $row.addClass('parent-row');
                    }

                    // Count SKUs with INV === 0
                    const zeroInvCount = filteredData.filter(item => parseFloat(item.AVAILABLE_TO_SELL) === 0).length;
                    $('#zeroInvLabel').text(`0 Inv SKUs: ${zeroInvCount}`);

                    const getDilColor = (value) => {
                        const percent = parseFloat(value) * 100;
                        if (percent < 16.66) return 'red';
                        if (percent >= 16.66 && percent < 25) return 'yellow';
                        if (percent >= 25 && percent < 50) return 'green';
                        return 'pink';
                    };

                    // Add checkbox column as first column
                    const $checkboxCell = $('<td>').css('text-align', 'center').html(
                        `<input type="checkbox" class="row-checkbox" data-sku="${item.SKU || ''}" data-index="${rowIndex}">`
                    );
                    $row.append($checkboxCell);

                    const imgTd = $('<td>').html(
                        item.IMAGE_URL ? `<img src="${item.IMAGE_URL}" style="width:40px;height:auto;">` : ''
                    );
                    $row.append(imgTd);

                    // $row.append($('<td>').text(item.sl_no));
                    $row.append($('<td>').text(item.Parent));

                    const $skuCell = $('<td>').addClass('skuColumn').css('position', 'static');
                    
                    // Copy icon button for copying SKU to clipboard
                    const copyButton = `<i class="fas fa-copy text-secondary copy-sku-icon" 
                        data-sku="${item.SKU || ''}" 
                        title="Copy SKU to clipboard" 
                        style="cursor: pointer; margin-left: 5px; font-size: 12px; opacity: 0.7;" 
                        onmouseover="this.style.opacity='1'" 
                        onmouseout="this.style.opacity='0.7'"></i>`;
                    
                    // Eye icon button for viewing history
                    let eyeButton = '';
                    if (isParentRow) {
                        // Parent row - show history for all SKUs in this parent
                        eyeButton = `<i class="fas fa-eye text-info view-parent-history-icon" 
                            data-parent="${item.Parent || ''}" 
                            title="View All SKUs History for Parent: ${item.Parent || ''}" 
                            style="cursor: pointer; margin-left: 8px; font-size: 14px;"></i>`;
                    } else {
                        // Regular SKU - show history for this SKU only
                        eyeButton = `<i class="fas fa-eye text-primary view-sku-history-icon" 
                            data-sku="${item.SKU || ''}" 
                            title="View SKU History" 
                            style="cursor: pointer; margin-left: 8px; font-size: 14px;"></i>`;
                    }
                    
                    if (isParentRow) {
                        $skuCell.html(`<strong>${sku}</strong> ${copyButton} ${eyeButton}<input type="hidden" class="sku-hidden" value="${item.SKU || ''}" />`);
                    } else {
                        const buyerLink = item.raw_data?.['B Link'] || '';
                        const sellerLink = item.raw_data?.['AMZ LINK SL'] || '';
                        if (buyerLink || sellerLink) {
                            $skuCell.html(`
                                <div class="sku-tooltip-container d-inline-block">
                                    <span class="sku-text">${sku}</span>
                                    <div class="sku-tooltip">
                                        ${buyerLink ? `<div class="sku-link"><a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a></div>` : ''}
                                        ${sellerLink ? `<div class="sku-link"><a href="${sellerLink}" target="_blank" rel="noopener noreferrer">Seller link</a></div>` : ''}
                                    </div>
                                </div>
                                ${copyButton}
                                ${eyeButton}
                                <input type="hidden" class="sku-hidden" value="${item.SKU || ''}" />
                            `);
                        } else {
                            $skuCell.html(`${sku} ${copyButton} ${eyeButton}<input type="hidden" class="sku-hidden" value="${item.SKU || ''}" />`);
                        }
                    }
                    $row.append($skuCell);

                    if (isNavigationActive) {
                        const $raCell = $('<td>').addClass('ra-cell');
                        if (item.hasOwnProperty('R&A')) {
                            const $container = $('<div>').addClass('ra-edit-container d-flex align-items-center');
                            const isChecked = item['R&A'] === true || item['R&A'] === '1' || item['R&A'] === 1;
                            const $checkbox = $('<input>', {
                                type: 'checkbox',
                                checked: isChecked,
                                class: 'ra-checkbox',
                                'data-sku': item['SKU'],
                                disabled: isChecked
                            }).data('original-value', item['R&A']).data('sku', item.SKU);
                            const $editIcon = $('<i>').addClass('fas fa-pen edit-icon ml-2 text-primary')
                                .css('cursor', 'pointer')
                                .attr('title', 'Edit R&A');
                            $container.append($checkbox, $editIcon);
                            $raCell.append($container);
                        } else {
                            $raCell.html('&nbsp;');
                        }
                        $row.append($raCell);
                    }

                    $row.append($('<td>').text(item.INV || 0));
                    $row.append($('<td>').text(item.L30));

                    const dilValue = parseFloat(item.DIL) || 0;
                    let dilContent;
                    if (dilValue <= 0) {
                        dilContent = `<span>-</span>`;
                    } else {
                        const dilPercent = Math.round(dilValue * 100);
                        const dilClass = getDilColor(dilValue);
                        dilContent = `<span class="dil-percent-value ${dilClass}">${dilPercent}%</span>`;
                    }
                    $row.append($('<td>').html(dilContent));

                    $row.append($('<td>').text(item.AVAILABLE_TO_SELL));
                    $row.append($('<td>').text(item.COMMITTED));
                    $row.append($('<td>').addClass('on-hand').text(item.ON_HAND));

                    $row.append($('<td>').html(`
                        <input type="number" class="form-control verified-stock-input" 
                            data-sku="${item.SKU}" data-index="${rowIndex}" value="${item.VERIFIED_STOCK ?? ''}" />
                    `));

                    const toAdjust = item.VERIFIED_STOCK !== '' ? parseInt(item.VERIFIED_STOCK) - parseInt(item.ON_HAND || 0) : '';
                    item.TO_ADJUST = toAdjust;
                    $row.append($('<td>').addClass('to-adjust').text(toAdjust));

                    // Parse comma-separated reasons - take first value for single select
                    const reasonStr = item.REASON || '';
                    const selectedReason = reasonStr ? reasonStr.split(',').map(r => r.trim())[0] : '';

                    $row.append($('<td>').html(`
                        <select class="form-control reason-select" data-sku="${item.SKU}" data-index="${rowIndex}">
                            <option value="">Select reason...</option>
                            <option value="Count" ${selectedReason === 'Count' ? 'selected' : ''}>Count</option>
                            <option value="Received" ${selectedReason === 'Received' ? 'selected' : ''}>Received</option>
                            <option value="Return Restock" ${selectedReason === 'Return Restock' ? 'selected' : ''}>Return Restock</option>
                            <option value="Damaged" ${selectedReason === 'Damaged' ? 'selected' : ''}>Damaged</option>
                            <option value="Theft or Loss" ${selectedReason === 'Theft or Loss' ? 'selected' : ''}>Theft or Loss</option>
                            <option value="Promotion" ${selectedReason === 'Promotion' ? 'selected' : ''}>Promotion</option>
                            <option value="Suspense" ${selectedReason === 'Suspense' ? 'selected' : ''}>Suspense</option>
                            <option value="Unknown" ${selectedReason === 'Unknown' ? 'selected' : ''}>Unknown</option>
                            <option value="Adjustment" ${selectedReason === 'Adjustment' ? 'selected' : ''}>Adjustment</option>
                            <option value="Combo" ${selectedReason === 'Combo' ? 'selected' : ''}>Combo</option>
                            <option value="Maybe FBA" ${selectedReason === 'Maybe FBA' ? 'selected' : ''}>Maybe FBA</option>
                            <option value="Need 2 Find" ${selectedReason === 'Need 2 Find' ? 'selected' : ''}>Need 2 Find</option>
                        </select>
                    `));

                    // REMARK column - contains both hide checkbox and remarks input
                    // Field should be blank (not showing latest remark) per requirement
                    $row.append(`<td>
                        <div class="d-flex align-items-center gap-2">
                            <input type="checkbox" class="form-check-input hide-row-checkbox" data-sku="${item.SKU}" ${item.IS_HIDE ? 'checked' : ''} style="margin-right: 5px;">
                            <input type="text" class="form-control remarks-input" data-sku="${item.SKU}" value="" style="flex: 1;" placeholder="Enter remark...">
                        </div>
                    </td>`);

                    $row.append($('<td>').html(`
                        <div class="d-flex flex-column align-items-center">
                            <input type="checkbox" class="form-check-input approve-checkbox" 
                                data-index="${rowIndex}" ${item.APPROVED ? 'checked' : ''}/>
                            <small class="approved-by text-success">${item.APPROVED_BY || ''}</small>
                        </div>
                    `));

                    const $historyIcon = $(`
                        <td class="text-center" style="display: none;">
                            <i class="fas fa-external-link-alt text-primary view-history-icon" 
                            data-sku="${item.SKU}" 
                            title="View History" 
                            style="cursor: pointer;"></i>
                        </td>
                    `);
                    $row.append($historyIcon);

                    $row.append($('<td>').addClass('adjusted-qty').text(toAdjust));

                    const lossGain = item.LOSS_GAIN;
                    $row.append($('<td>').addClass('loss-gain').text(lossGain === '' ? '' : Math.trunc(lossGain)));

                    let approvedAtHTML = '-';
                    if (item.APPROVED_AT && item.APPROVED_AT.includes(', ')) {
                        const [datePart, timePart] = item.APPROVED_AT.split(', ');
                        approvedAtHTML = `<div style="line-height:1.3">${datePart}<br><small>${timePart}</small></div>`;
                    }
                    $row.append($('<td>').addClass('approved-at').html(approvedAtHTML));

                    // Verified column - Green dot if verified, Red dot if unverified - read from DB
                    const isVerified = item.IS_VERIFIED === 1 || item.IS_VERIFIED === true || item.is_verified === true || item.is_verified === 1 || item.is_verified === '1';
                    const dotColor = isVerified ? '#28a745' : '#dc3545'; // Green for verified, Red for unverified
                    const verifiedHTML = `<button type="button" class="btn btn-sm verified-status-btn" 
                        data-sku="${item.SKU || ''}" data-index="${rowIndex}" data-verified="${isVerified ? '1' : '0'}"
                        style="background: none; border: none; padding: 0; cursor: pointer;">
                        <i class="fas fa-circle" style="color: ${dotColor}; font-size: 12px;"></i>
                    </button>`;
                    const $verifiedCell = $('<td>').addClass('verified-column').css('text-align', 'center').html(verifiedHTML);
                    $row.append($verifiedCell);

                    // User column - Display first name of user who verified
                    const verifiedByFirstName = item.VERIFIED_BY_FIRST_NAME || item.verified_by_first_name || '';
                    const $userCell = $('<td>').addClass('user-column').css({
                        'text-align': 'center',
                        'vertical-align': 'middle'
                    }).html(verifiedByFirstName || '—');
                    $row.append($userCell);

                    // $row.append($('<td>').addClass('last-approved-at').text(item.LAST_APPROVED_AT || '-'));
                    $row.append($('<td>').addClass('last-approved-at').css({
                        'width': '0',
                        'padding': '0',
                        'border': 'none',
                        'visibility': 'hidden',
                        'overflow': 'hidden'
                    }).html(item.LAST_APPROVED_AT || '-'));
 
                    // $row.append($('<td>').text(item.LAST_APPROVED_AT || '-'));


                    // let lastApprovedAtHTML = '-';
                    // if (item.LAST_APPROVED_AT && item.LAST_APPROVED_AT.includes(', ')) {
                    //     const [datePart, timePart] = item.LAST_APPROVED_AT.split(', ');
                    //     lastApprovedAtHTML = `<div style="line-height:1.3">${datePart}<br><small>${timePart}</small></div>`;
                    // }
                    // $row.append($('<td>').addClass('last-approved-at').html(lastApprovedAtHTML));

                    $tbody.append($row);
                });

                let totalLossGain = filteredData.reduce((sum, item) => {
                    const value = parseFloat(item.LOSS_GAIN);
                    return !isNaN(value) ? sum + value : sum;
                }, 0);

                $('#lossGainTotalText').text(`$ ${Math.trunc(totalLossGain)}`);
                updatePaginationInfo();
                initTooltips();
                
                // Update Verified status counts
                updateVerifiedCounts();
            }
            
            // Update Verified status counts
            function updateVerifiedCounts() {
                const greenCount = tableData.filter(item => {
                    const verified = item.IS_VERIFIED === 1 || item.IS_VERIFIED === true || item.is_verified === true || item.is_verified === 1;
                    return verified;
                }).length;
                const yellowCount = tableData.filter(item => {
                    const verified = item.IS_VERIFIED === 1 || item.IS_VERIFIED === true || item.is_verified === true || item.is_verified === 1;
                    return !verified;
                }).length;
                $('#green-count').text(greenCount);
                $('#yellow-count').text(yellowCount);
            }

            // Select All checkbox functionality
            $(document).on('change', '#select-all-checkbox', function() {
                const isChecked = $(this).is(':checked');
                $('.row-checkbox').prop('checked', isChecked);
            });

            // Individual row checkbox - update select all state
            $(document).on('change', '.row-checkbox', function() {
                const totalCheckboxes = $('.row-checkbox').length;
                const checkedCheckboxes = $('.row-checkbox:checked').length;
                $('#select-all-checkbox').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
            });

            // Get selected rows (for bulk operations)
            function getSelectedRows() {
                const selectedSkus = [];
                $('.row-checkbox:checked').each(function() {
                    selectedSkus.push($(this).data('sku'));
                });
                return selectedSkus;
            }

            // Bulk action handler
            $(document).on('click', '#apply-bulk-action', function() {
                const action = $('#bulk-action-select').val();
                if (!action) {
                    showNotification('warning', 'Please select an action');
                    return;
                }

                const selectedSkus = getSelectedRows();
                if (selectedSkus.length === 0) {
                    showNotification('warning', 'Please select at least one product');
                    return;
                }

                const isVerified = action === 'mark-verified';

                // Show loading notification
                showNotification('info', `Processing ${selectedSkus.length} product(s)...`);

                // Process each SKU
                let processed = 0;
                let failed = 0;
                const total = selectedSkus.length;

                function processNext(index) {
                    if (index >= selectedSkus.length) {
                        // All done
                        if (failed === 0) {
                            showNotification('success', `Successfully ${isVerified ? 'verified' : 'unverified'} ${processed} product(s)`);
                        } else {
                            showNotification('warning', `Processed ${processed} product(s), ${failed} failed`);
                        }
                        // Reload data to reflect changes
                        loadData();
                        // Reset dropdown
                        $('#bulk-action-select').val('');
                        // Uncheck all checkboxes
                        $('.row-checkbox').prop('checked', false);
                        $('#select-all-checkbox').prop('checked', false);
                        return;
                    }

                    const sku = selectedSkus[index];
                    
                    $.ajax({
                        url: '/update-verified-status',
                        method: 'POST',
                        data: {
                            sku: sku,
                            is_verified: isVerified,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(res) {
                            if (res.success) {
                                processed++;
                            } else {
                                failed++;
                            }
                            processNext(index + 1);
                        },
                        error: function(xhr) {
                            failed++;
                            processNext(index + 1);
                        }
                    });
                }

                // Start processing
                processNext(0);
            });

            // Verified status button click handler
            // Copy SKU to clipboard functionality (for main table)
            $(document).on('click', '.copy-sku-icon', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const sku = $(this).data('sku') || '';
                if (!sku) {
                    return;
                }
                
                copyToClipboard(sku, e.target);
            });
            
            // Copy SKU to clipboard functionality (for activity log table)
            $(document).on('click', '.copy-sku-icon-activity', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const sku = $(this).data('sku') || '';
                if (!sku) {
                    return;
                }
                
                copyToClipboard(sku, e.target);
            });
            
            // Shared copy to clipboard function
            function copyToClipboard(text, iconElement) {
                // Use modern Clipboard API if available
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function() {
                        // Show success feedback
                        const $icon = $(iconElement);
                        const originalClass = $icon.attr('class');
                        $icon.removeClass('fa-copy').addClass('fa-check text-success');
                        $icon.attr('title', 'Copied!');
                        
                        setTimeout(function() {
                            $icon.attr('class', originalClass);
                            $icon.attr('title', 'Copy SKU to clipboard');
                        }, 2000);
                    }).catch(function(err) {
                        console.error('Failed to copy: ', err);
                        // Fallback to old method
                        fallbackCopyToClipboard(text, iconElement);
                    });
                } else {
                    // Fallback for older browsers
                    fallbackCopyToClipboard(text, iconElement);
                }
            }
            
            // Fallback copy function for older browsers
            function fallbackCopyToClipboard(text, iconElement) {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        const $icon = $(iconElement);
                        const originalClass = $icon.attr('class');
                        $icon.removeClass('fa-copy').addClass('fa-check text-success');
                        $icon.attr('title', 'Copied!');
                        
                        setTimeout(function() {
                            $icon.attr('class', originalClass);
                            $icon.attr('title', 'Copy SKU to clipboard');
                        }, 2000);
                    }
                } catch (err) {
                    console.error('Fallback copy failed: ', err);
                    alert('Failed to copy SKU. Please copy manually: ' + text);
                }
                
                document.body.removeChild(textArea);
            }

            $(document).on('click', '.verified-status-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const $btn = $(this);
                const sku = $btn.data('sku');
                // Read current verified state from attribute directly (more reliable than .data())
                // Get the current value from the data attribute to avoid jQuery caching issues
                const currentVerifiedAttr = $btn.attr('data-verified');
                const currentVerified = currentVerifiedAttr === '1' || currentVerifiedAttr === 1 || currentVerifiedAttr === true;
                const newVerified = !currentVerified; // Toggle the value (true becomes false, false becomes true)
                
                // Disable button during save
                $btn.prop('disabled', true);
                
                // Save to server - send the new (toggled) value
                $.ajax({
                    url: '/update-verified-status',
                    method: 'POST',
                    data: {
                        sku: sku,
                        is_verified: newVerified ? 1 : 0, // Send as 1 or 0 - this is the toggled value
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            // Find item in tableData by SKU and update both versions
                            const itemIndex = tableData.findIndex(item => item.SKU === sku);
                            if (itemIndex !== -1) {
                                tableData[itemIndex].IS_VERIFIED = newVerified ? 1 : 0;
                                tableData[itemIndex].is_verified = newVerified;
                                // Update verified by first name from response
                                if (response.verified_by_first_name) {
                                    tableData[itemIndex].VERIFIED_BY_FIRST_NAME = response.verified_by_first_name;
                                } else if (!newVerified) {
                                    // Clear the name when unverifying
                                    tableData[itemIndex].VERIFIED_BY_FIRST_NAME = null;
                                }
                            }
                            
                            // Update button appearance
                            if (newVerified) {
                                // Show green dot when verified
                                $btn.removeClass('btn-warning btn-success').css({
                                    'background': 'none',
                                    'border': 'none',
                                    'padding': '0'
                                });
                                $btn.html('<i class="fas fa-circle" style="color: #28a745; font-size: 12px;"></i>');
                                $btn.data('verified', '1');
                            } else {
                                // Show red dot when unverified
                                $btn.removeClass('btn-warning btn-success').css({
                                    'background': 'none',
                                    'border': 'none',
                                    'padding': '0'
                                });
                                $btn.html('<i class="fas fa-circle" style="color: #dc3545; font-size: 12px;"></i>');
                                $btn.data('verified', '0');
                            }
                            
                            // Update counts
                            updateVerifiedCounts();
                            
                            // Apply all filters (unified)
                            applyAllFilters();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating verified status:', error);
                        console.error('Response:', xhr.responseText);
                        alert('Failed to update verified status. Please try again.');
                    },
                    complete: function() {
                        // Re-enable button
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Unified filter function that applies all filters together (AND logic)
            function applyAllFilters() {
                currentPage = 1; // Reset to first page when filters change
                let tempData = [...tableData];
                
                // 1. Data Type filter
                const dataType = $('#row-data-type').val();
                if (dataType === 'parent') {
                    tempData = tempData.filter(item => item.is_parent);
                } else if (dataType === 'sku') {
                    tempData = tempData.filter(item => !item.is_parent);
                }
                
                // 2. Parent Search filter
                const selectedParent = $('#parentSearch').val()?.trim();
                if (selectedParent) {
                    tempData = tempData.filter(item => {
                        const parent = String(item.Parent || '').toUpperCase();
                        return parent.includes(selectedParent.toUpperCase());
                    });
                }
                
                // 3. SKU Search filter
                const selectedSku = $('#skuSearch').val()?.trim();
                if (selectedSku) {
                    tempData = tempData.filter(item => {
                        const sku = String(item.SKU || '').toUpperCase();
                        return sku.includes(selectedSku.toUpperCase());
                    });
                }
                
                // 4. Verified filter
                const verifiedFilter = $('.verified-filter-btn.active').data('status');
                if (verifiedFilter && verifiedFilter !== 'all') {
                    if (verifiedFilter === 'green') {
                        tempData = tempData.filter(item => {
                            const verified = item.IS_VERIFIED === 1 || item.IS_VERIFIED === true || item.is_verified === true || item.is_verified === 1;
                            return verified;
                        });
                    } else if (verifiedFilter === 'yellow') {
                        tempData = tempData.filter(item => {
                            const verified = item.IS_VERIFIED === 1 || item.IS_VERIFIED === true || item.is_verified === true || item.is_verified === 1;
                            return !verified;
                        });
                    }
                }
                
                // 5. Search all columns filter
                const searchTerm = $('#search-input').val()?.trim();
                if (searchTerm) {
                    const normalizedSearch = searchTerm.replace(/\s+/g, '').toLowerCase();
                    tempData = tempData.filter(item => {
                        return Object.values(item).some(val => {
                            if (typeof val === 'boolean' || val === null) return false;
                            const normalizedVal = val.toString().replace(/\s+/g, '').toLowerCase();
                            return normalizedVal.includes(normalizedSearch);
                        });
                    });
                }
                
                // Note: All filters above are applied with AND logic - items must match ALL active filters
                
                filteredData = tempData;
                renderTable();
            }

            // Verified filter button handlers - Allow multiple filters to be active
            $(document).on('click', '.verified-filter-btn', function() {
                // Toggle active state for verified filters (only one verified filter can be active)
                $('.verified-filter-btn').removeClass('active');
                $('.filter-show-all-btn').removeClass('active');
                $(this).addClass('active');
                applyAllFilters();
            });

            // Doubtful filter button handler - Can work alongside verified filter
            $(document).on('click', '.doubtful-filter-btn', function() {
                // Toggle active state for doubtful filter
                if ($(this).hasClass('active')) {
                    $(this).removeClass('active');
                } else {
                    $(this).addClass('active');
                }
                $('.filter-show-all-btn').removeClass('active');
                applyAllFilters();
            });

            // Show All filter button handler - Clears all filters
            $(document).on('click', '.filter-show-all-btn', function() {
                $('.verified-filter-btn').removeClass('active');
                $('.doubtful-filter-btn').removeClass('active');
                $('.filter-show-all-btn').removeClass('active');
                $(this).addClass('active');
                applyAllFilters();
            });

            // Legacy function - now calls unified filter
            function applyVerifiedFilter(status) {
                applyAllFilters();
            }


            // Legacy function - now calls unified filter
            function applyDoubtfulFilter(status) {
                applyAllFilters();
            }

            //for update Adjusted qty
            $('#ebay-table').on('input', '.verified-stock-input', function () {
                const $input = $(this);
                const $row = $input.closest('tr');
                const index = parseInt($input.data('index'));
                const inputVal = $input.val().trim();

                const verifiedStock = parseInt(inputVal);
                const onHand = parseInt($row.find('.on-hand').text().trim()) || 0;

                let toAdjust = '';

                // Only calculate if input is a valid number
                if (!isNaN(verifiedStock)) {
                    toAdjust = verifiedStock - onHand;
                }

                // Update Adjusted Qty cell
                $row.find('.to-adjust').text(toAdjust);

                // Update in-memory tableData
                if (!isNaN(index) && tableData[index]) {
                    tableData[index].VERIFIED_STOCK = isNaN(verifiedStock) ? '' : verifiedStock;
                    tableData[index].TO_ADJUST = toAdjust;
                }
            });

            
            //call after checked the appr-WH checkbox
            $('#ebay-table').on('change', '.approve-checkbox', function () {
                const $checkbox = $(this);
                const $row = $checkbox.closest('tr');
                const sku = $row.find('.sku-hidden').val();
                const verifiedStock = parseInt($row.find('.verified-stock-input').val().trim()) || 0;
                const onHand = parseInt($row.find('.on-hand').text().trim()) || 0;
                const toAdjust = verifiedStock - onHand;
                const reason = $row.find('.reason-select').val() || '';
                const isApproved = $checkbox.is(':checked') ? 1 : 0;
                const index = parseInt($checkbox.data('index'));
                const remarks = $row.find('.remarks-input').val() || ''; 
                console.log("Remarks for SKU", sku, ":", remarks);


                $row.find('.to-adjust').text(toAdjust);

                if (isApproved && $row.find('.verified-stock-input').val().trim() === '') {
                    alert('Please enter Verified before approving.');
                    $checkbox.prop('checked', false);
                    return;
                }

                if (isApproved && !reason) {
                    alert('Please select a reason before approving.');
                    $checkbox.prop('checked', false);
                    return;
                }

                $.ajax({
                    url: '/update-verified-stock',
                    method: 'POST',
                    data: {
                        sku: sku,
                        on_hand: onHand,
                        verified_stock: verifiedStock,
                        to_adjust: toAdjust,
                        reason: reason,
                        remarks: remarks,
                        is_approved: isApproved,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (res) {
                        if (res.success) {
                            const approvedAt = res.data.approved_at;
                            const approvedBy = res.data.approved_by;
                            const lossGain = res.data.loss_gain ?? 0;

                            // Format approved_at visually only (do NOT change timezone)
                            let approvedAtFormatted = '-';
                            if (approvedAt) {
                                const dateObj = new Date(approvedAt);
                                const day = dateObj.getUTCDate().toString().padStart(2, '0');
                                const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", 
                                                    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                                const month = monthNames[dateObj.getUTCMonth()];
                                const year = dateObj.getUTCFullYear();

                                let hours = dateObj.getUTCHours();
                                const minutes = dateObj.getUTCMinutes().toString().padStart(2, '0');
                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                hours = hours % 12;
                                hours = hours ? hours : 12; // 0 becomes 12

                                approvedAtFormatted = `${day} ${month} ${year}<br><small>${hours}:${minutes} ${ampm}</small>`;

                            }

                            if (!isNaN(index)) {
                                filteredData[index].APPROVED = !!isApproved;
                                filteredData[index].APPROVED_BY = isApproved ? approvedBy : '-';
                                filteredData[index].APPROVED_AT = isApproved ? approvedAt : '-';
                                filteredData[index].TO_ADJUST = toAdjust;
                                filteredData[index].ADJUSTED_QTY = isApproved ? toAdjust : 0;
                                filteredData[index].LOSS_GAIN = isApproved ? lossGain : 0;
                            }

                            $row.find('.approved-by').text(isApproved ? approvedBy : '-');

                            $row.find('.approved-at').html(isApproved ? approvedAtFormatted : '-');

                            $row.find('.adjusted-qty').text(isApproved ? toAdjust : '-');
                            $row.find('.loss-gain').text(isApproved ? Math.trunc(lossGain) : '0');

                            let totalLossGain = filteredData.reduce((sum, item) => {
                                const val = parseFloat(item.LOSS_GAIN);
                                return !isNaN(val) ? sum + val : sum;
                            }, 0);

                            $('#lossGainTotalText')
                                // .addClass('bg-primary')
                                .text(`$ ${Math.trunc(totalLossGain)}`);

                            // Show success message
                            let message = isApproved ? 'Approved and Shopify inventory updated successfully!' : 'Approval removed';
                            let alertType = 'success';
                            
                            showNotification(alertType, message);
                        } else {
                            $checkbox.prop('checked', !isApproved);
                            showNotification('danger', res.message || 'Something went wrong.');
                        }
                    },
                    error: function () {
                        $checkbox.prop('checked', !isApproved);
                        showNotification('danger', 'Failed to update data. Please try again.');
                    }
                });
            });

            // Save remark when user leaves the field (blur) or presses Enter
            $(document).on('blur', '.remarks-input', function() {
                const $input = $(this);
                const sku = $input.data('sku');
                const remark = $input.val().trim();
                
                if (!sku) {
                    return;
                }
                
                // Save remark
                $.ajax({
                    url: '/save-remark',
                    method: 'POST',
                    data: {
                        sku: sku,
                        remark: remark,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(res) {
                        if (res.success) {
                            // Clear the remark field after saving
                            $input.val('');
                            showNotification('success', 'Remark saved successfully');
                        } else {
                            showNotification('danger', res.message || 'Failed to save remark');
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Failed to save remark. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        showNotification('danger', errorMsg);
                    }
                });
            });
            
            // Save remark when user presses Enter
            $(document).on('keypress', '.remarks-input', function(e) {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    $(this).blur(); // Trigger blur event which will save the remark
                }
            });

            // call after click history icon 
            // Function to load and show SKU history
            function showSkuHistory(sku) {
                $.ajax({
                    url: '/inventory-history', // Adjust if your route is different
                    type: 'GET',
                    data: { sku },
                    success: function (res) {
                    
                        let html = `<strong>History for SKU: ${sku}</strong><hr>`;
                        if (!res.data || !res.data.length) {
                            html += `<p>No history found.</p>`;
                        } else {
                            html += `
                                <table class="table table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>On Hand</th>
                                            <th>Verified</th>
                                            <th>To Adjust</th>
                                            <th>Reason</th>
                                            <th>Approved By</th>
                                            <th>Approved</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                            res.data.forEach(entry => {
                                html += `
                                    <tr>
                                        <td>${entry.sku}</td>
                                        <td>${entry.on_hand}</td>
                                        <td>${entry.verified_stock}</td>
                                        <td>${entry.to_adjust}</td>
                                        <td>${entry.reason}</td>
                                        <td>${entry.approved_by}</td>
                                        <td>${entry.approved_at}</td>
                                    </tr>`;
                            });
                            html += `</tbody></table>`;
                        }

                        $('#sku-history-content').html(html);
                        
                        // Update modal title
                        $('#skuHistoryModalLabel').text(`Adjustment History - SKU: ${sku}`);
                        
                        // Update Shopify link
                        const shopifyDomain = '{{ env("SHOPIFY_STORE_URL") }}';
                        const shopifyInventoryUrl = `https://${shopifyDomain}/admin/products/inventory?query=${encodeURIComponent(sku)}`;
                        $('#shopifyInventoryLink').attr('href', shopifyInventoryUrl).show();
                        
                        $('#skuHistoryModal').modal('show');
                    },
                    error: function () {
                        $('#sku-history-content').html('<p class="text-danger">Failed to load history.</p>');
                        $('#skuHistoryModalLabel').text(`Adjustment History - SKU: ${sku}`);
                        $('#shopifyInventoryLink').hide();
                        $('#skuHistoryModal').modal('show');
                    }
                });
            }

            // Handle click on ADJ HISTORY column icon
            $(document).on('click', '.view-history-icon', function () {
                const sku = $(this).data('sku');
                showSkuHistory(sku);
            });

            // Handle click on eye icon next to SKU
            $(document).on('click', '.view-sku-history-icon', function () {
                const sku = $(this).data('sku');
                showSkuHistory(sku);
            });

            // Handle click on eye icon next to Parent row - show all SKUs history
            $(document).on('click', '.view-parent-history-icon', function () {
                const parent = $(this).data('parent');
                showParentHistory(parent);
            });

            // Function to show history for all SKUs in a parent
            function showParentHistory(parent) {
                // Get all SKUs that belong to this parent from tableData
                const parentSkus = tableData
                    .filter(item => item.Parent === parent && !item.is_parent)
                    .map(item => item.SKU)
                    .filter(sku => sku && sku.trim() !== '');

                if (parentSkus.length === 0) {
                    $('#sku-history-content').html(`<p>No SKUs found for parent: ${parent}</p>`);
                    $('#skuHistoryModalLabel').text(`Adjustment History - Parent: ${parent}`);
                    $('#shopifyInventoryLink').hide();
                    $('#skuHistoryModal').modal('show');
                    return;
                }

                // Show loading message
                $('#sku-history-content').html('<p>Loading history for all SKUs...</p>');
                $('#skuHistoryModalLabel').text(`Adjustment History - Parent: ${parent} (${parentSkus.length} SKUs)`);
                $('#skuHistoryModal').modal('show');

                // Fetch history for all SKUs using Promise.all
                const historyPromises = parentSkus.map(sku => {
                    return $.ajax({
                        url: '/inventory-history',
                        type: 'GET',
                        data: { sku },
                        dataType: 'json'
                    }).then(function(response) {
                        return { sku: sku, response: response };
                    }).catch(function(error) {
                        return { sku: sku, response: null, error: error };
                    });
                });

                // Wait for all requests to complete
                Promise.all(historyPromises).then(function(results) {
                    let html = `<strong>History for Parent: ${parent}</strong><br>`;
                    html += `<small class="text-muted">Total SKUs: ${parentSkus.length}</small><hr>`;

                    let allHistory = [];
                    let hasHistory = false;

                    // Process each response
                    results.forEach(function(result) {
                        const sku = result.sku;
                        const response = result.response;
                        if (response && response.data && response.data.length > 0) {
                            hasHistory = true;
                            response.data.forEach(function(entry) {
                                allHistory.push({
                                    sku: sku,
                                    on_hand: entry.on_hand,
                                    verified_stock: entry.verified_stock,
                                    to_adjust: entry.to_adjust,
                                    reason: entry.reason,
                                    approved_by: entry.approved_by,
                                    approved_at: entry.approved_at
                                });
                            });
                        }
                    });

                    if (!hasHistory || allHistory.length === 0) {
                        html += `<p>No history found for any SKUs in this parent.</p>`;
                    } else {
                        // Sort by approved_at descending (most recent first)
                        allHistory.sort(function(a, b) {
                            const dateA = new Date(a.approved_at || 0);
                            const dateB = new Date(b.approved_at || 0);
                            return dateB - dateA;
                        });

                        html += `
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm table-striped">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>SKU</th>
                                            <th>On Hand</th>
                                            <th>Verified</th>
                                            <th>To Adjust</th>
                                            <th>Reason</th>
                                            <th>Approved By</th>
                                            <th>Approved</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;
                        allHistory.forEach(function(entry) {
                            html += `
                                <tr>
                                    <td><strong>${entry.sku || '-'}</strong></td>
                                    <td>${entry.on_hand || '-'}</td>
                                    <td>${entry.verified_stock || '-'}</td>
                                    <td>${entry.to_adjust || '-'}</td>
                                    <td>${entry.reason || '-'}</td>
                                    <td>${entry.approved_by || '-'}</td>
                                    <td>${entry.approved_at || '-'}</td>
                                </tr>`;
                        });
                        html += `</tbody></table>
                            </div>`;
                        html += `<p class="mt-2"><small class="text-muted">Total history entries: ${allHistory.length}</small></p>`;
                    }

                    $('#sku-history-content').html(html);
                    $('#shopifyInventoryLink').hide(); // Hide Shopify link for parent view
                }).catch(function(error) {
                    $('#sku-history-content').html('<p class="text-danger">Failed to load history for some SKUs.</p>');
                    console.error('Error loading parent history:', error);
                });
            }

            //  call after click hide checkbox
            $(document).on('change', '.hide-row-checkbox', function () {
                const sku = $(this).data('sku');
                const $row = $(this).closest('tr');

                $.post('/row-hide-toggle', {
                    sku: sku,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }, function (res) {
                    if (res.success) {
                    $row.remove();
                    }
                });
            });

            // Step 5: Button to open hidden modal
            $('#viewHiddenRows').on('click', function () {
                $.get('/get-hidden-rows', function (res) {
                    const $tbody = $('#hiddenRowsTable tbody');
                    $tbody.empty();

                    if (res.data.length === 0) {
                    $tbody.append('<tr><td colspan="8">No hidden rows available.</td></tr>');
                    } else {
                    res.data.forEach(row => {
                        $tbody.append(`
                        <tr>
                            <td><input type="checkbox" class="hidden-row-select" value="${row.sku}"></td>
                            <td>${row.sku}</td>
                            <td>${row.verified_stock}</td>
                            <td>${row.to_adjust}</td>
                            <td>${row.loss_gain}</td>
                            <td>${row.reason || ''}</td>
                            <td>${row.approved_by}</td>
                            <td>${row.approved_at}</td>
                            <td>${row.remarks || '-'}</td>
                        </tr>
                        `);
                    });
                    }

                    $('#hiddenRowsModal').modal('show');
                });
            });

            // Step 6: Select all checkbox logic
            $(document).on('change', '#selectAllHidden', function () {
                $('.hidden-row-select').prop('checked', this.checked);
            });

            // Step 7: Unhide selected rows
            $('#clearSelectedHiddenRows').on('click', function () {
                const skus = $('.hidden-row-select:checked').map(function () {
                    return $(this).val();
                }).get();

                if (skus.length === 0) {
                    alert('Please select at least one row to unhide.');
                    return;
                }

                $.post('/unhide-multiple-rows', {
                    skus: skus,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }, function (res) {
                    if (res.success) {
                        $('#hiddenRowsModal').modal('hide');
                        //Refresh only filteredData and rerender (not full loadData)
                        filteredData = filteredData.concat(res.unhiddenRows);
                        renderTable();
                    }
                });
            }); 

            // Search filter for hidden rows
            $(document).on('keyup', '#hiddenRowsSearch', function () {
                let value = $(this).val().toLowerCase();
                $("#hiddenRowsTable tbody tr").filter(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Export to Excel (SheetJS)
            $("#exportExcel").on("click", function () {
                if (!filteredData || filteredData.length === 0) {
                    alert("No data to export!");
                    return;
                }

                // OVERRIDE: Export ALL SKUs - NO FILTERING
                // All filtering logic based on is_hide has been removed
                // All SKUs from product_master will be exported

                // Convert filteredData to flat JSON
                const rows = filteredData.map(item => ({
                    Parent: item.Parent,
                    SKU: item.SKU,
                    INV: item.INV,
                    L30: item.L30,
                    DIL: item.DIL,
                    ON_HAND: item.ON_HAND,
                    COMMITTED: item.COMMITTED,
                    AVAILABLE_TO_SELL: item.AVAILABLE_TO_SELL,
                    // VERIFIED_STOCK: item.VERIFIED_STOCK,
                    // TO_ADJUST: item.TO_ADJUST,
                    // REASON: item.REASON,
                    // APPROVED: item.APPROVED ? "Yes" : "No",
                    // APPROVED_BY: item.APPROVED_BY,
                    // APPROVED_AT: item.APPROVED_AT,
                    // LOSS_GAIN: item.LOSS_GAIN,
                    // REMARK: item.REMARK || '',
                    // LAST_APPROVED_AT: item.LAST_APPROVED_AT
                }));

                const ws = XLSX.utils.json_to_sheet(rows);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "VerificationData");

                XLSX.writeFile(wb, "verification_data.xlsx");
            });

            

            function getParentBySku(sku) {
                sku = sku.trim().toUpperCase();
                const row = tableData.find(item => item.SKU.trim().toUpperCase() === sku);
                return row ? row.Parent : '(No Parent)';
            }

            function getLPBySku(sku) {
                const row = tableData.find(item => item.SKU.trim().toUpperCase() === sku.trim().toUpperCase());
                return row ? parseFloat(row.raw_data?.LP || 0) : 0;
            }

             // load data on Activity modal
            // Store activity log data globally for filtering
            let activityLogData = [];

            $('#activity-log-btn').on('click', function () {
                $.ajax({
                    url: '/verified-stock-activity-log',
                    method: 'GET',
                    success: function (res) {
                        activityLogData = res.data || [];
                        renderActivityLogTable(activityLogData);
                        $('#activityLogModal').modal('show');
                    },
                    error: function () {
                        alert('Failed to load activity log.');
                    }
                });
            });

            // Function to render activity log table
            function renderActivityLogTable(data) {
                const tableBody = $('#activityLogTable tbody');
                tableBody.empty();

                let totalLossGain = 0;

                if (!data || data.length === 0) {
                    tableBody.append('<tr><td colspan="9" class="text-center">No activity found.</td></tr>');
                } else {
                    data.forEach(item => {
                        const parentTitle = getParentBySku(item.sku);
                        const toAdjust = parseFloat(item.to_adjust) || 0;
                        const lp = getLPBySku(item.sku);

                        let lossGainValue;

                        if (item.loss_gain !== null && item.loss_gain !== undefined) {
                            lossGainValue = parseFloat(item.loss_gain);
                        } else {
                            lossGainValue = lp ? toAdjust * lp : 0;
                        }

                        totalLossGain += lossGainValue;

                        const formattedLossGain = lossGainValue !== 0 ? `${lossGainValue.toFixed(2)}` : '-';

                        const copyButton = `<i class="fas fa-copy text-secondary copy-sku-icon-activity" 
                            data-sku="${item.sku || ''}" 
                            title="Copy SKU to clipboard" 
                            style="cursor: pointer; margin-left: 5px; font-size: 12px; opacity: 0.7;" 
                            onmouseover="this.style.opacity='1'" 
                            onmouseout="this.style.opacity='0.7'"></i>`;
                        
                        tableBody.append(`
                            <tr data-parent="${(parentTitle || '').toLowerCase()}" 
                                data-sku="${(item.sku || '').toLowerCase()}" 
                                data-person="${(item.approved_by || '').toLowerCase()}" 
                                data-reason="${(item.reason || '').toLowerCase()}">
                                <td>${parentTitle}</td>
                                <td>${item.sku} ${copyButton}</td>
                                <td>${item.verified_stock ?? '-'}</td>
                                <td>${item.to_adjust ?? '-'}</td>
                                <td>${formattedLossGain}</td>
                                <td>${item.reason ?? '-'}</td>
                                <td>${item.approved_by ?? '-'}</td>
                                <td>${item.approved_at ?? '-'}</td>
                                <td>${item.remarks ?? '-'}</td>
                            </tr>
                        `);
                    });
                }

                // Set total Loss/Gain in header badge
                $('#activityLossGainTotal').text(`${Math.trunc(totalLossGain)}`);
            }

            // Function to filter activity log table
            function filterActivityLogTable() {
                const parentFilter = $('#activityLogFilterParent').val().toLowerCase().trim();
                const skuFilter = $('#activityLogFilterSku').val().toLowerCase().trim();
                const personFilter = $('#activityLogFilterPerson').val().toLowerCase().trim();
                const reasonFilter = $('#activityLogFilterReason').val().toLowerCase().trim();

                let visibleLossGainTotal = 0;
                let visibleCount = 0;

                // Remove any existing "no results" message
                $('#activityLogTable tbody tr').filter(function() {
                    return $(this).find('td').length === 1 && $(this).text().includes('No matching records');
                }).remove();

                $('#activityLogTable tbody tr').each(function() {
                    const $row = $(this);
                    // Skip "no results" message rows
                    if ($row.find('td').length === 1 && $row.text().includes('No activity found')) {
                        return;
                    }
                    
                    const parent = $row.data('parent') || '';
                    const sku = $row.data('sku') || '';
                    const person = $row.data('person') || '';
                    const reason = $row.data('reason') || '';

                    const matchesParent = !parentFilter || parent.indexOf(parentFilter) > -1;
                    const matchesSku = !skuFilter || sku.indexOf(skuFilter) > -1;
                    const matchesPerson = !personFilter || person.indexOf(personFilter) > -1;
                    const matchesReason = !reasonFilter || reason.indexOf(reasonFilter) > -1;

                    const isVisible = matchesParent && matchesSku && matchesPerson && matchesReason;
                    $row.toggle(isVisible);

                    if (isVisible) {
                        visibleCount++;
                        const lossGainText = $row.find('td:eq(4)').text().trim();
                        const lossGainValue = parseFloat(lossGainText);
                        if (!isNaN(lossGainValue)) {
                            visibleLossGainTotal += lossGainValue;
                        }
                    }
                });

                // Update total for visible rows
                $('#activityLossGainTotal').text(`${Math.trunc(visibleLossGainTotal)}`);

                // Show message if no results
                if (visibleCount === 0 && (parentFilter || skuFilter || personFilter || reasonFilter)) {
                    $('#activityLogTable tbody').append('<tr><td colspan="9" class="text-center text-muted">No matching records found.</td></tr>');
                }
            }

            // Filter event handlers
            $('#activityLogFilterParent, #activityLogFilterSku, #activityLogFilterPerson, #activityLogFilterReason').on('keyup', function() {
                filterActivityLogTable();
            });

            // Clear all filters
            $('#activityLogClearFilters').on('click', function() {
                $('#activityLogFilterParent').val('');
                $('#activityLogFilterSku').val('');
                $('#activityLogFilterPerson').val('');
                $('#activityLogFilterReason').val('');
                filterActivityLogTable();
            });



            // Close modal
            $('.close').on('click', function () {
                $('#activityLogModal').modal('hide');
            });


            //call after checked the appr-IH checkbox
            $('#ebay-table').on('change', '.ih-approve-checkbox', function () {
                const $checkbox = $(this);
                const $row = $(this).closest('tr');
                const sku = $row.find('.sku-hidden').val();
                const approvedByIH = $checkbox.is(':checked') ? 1 : 0;
                const index = parseInt($checkbox.data('index'));

                $.ajax({
                    url: '/update-approved-by-ih',  
                    method: 'POST',
                    data: {
                        sku: sku,
                        approved_by_ih: approvedByIH,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (res) {
                        if (res.success) {
                            if (!isNaN(index)) {
                                tableData[index].APPROVED_BY_IH = approvedByIH;
                            }

                            // $checkbox.prop('disabled', true); // Optional: disable after check
                            showNotification('success', 'IH approval saved.');
                        } else {
                            $checkbox.prop('checked', false);
                            showNotification('danger', res.message || 'Failed to save IH approval.');
                        }
                    },
                    error: function () {
                        $checkbox.prop('checked', false);
                        showNotification('danger', 'Server error. Could not save IH approval.');
                    }
                });
            });


            //call after checked the  R&A field checkbox
            $('#ebay-table').on('change', '.ra-checkbox', function () {
                const $checkbox = $(this);
                const sku = $checkbox.data('sku');
                const isChecked = $checkbox.is(':checked') ? 1 : 0;
                
                $.ajax({
                    url: '/update-ra-status',
                    method: 'POST',
                    data: {
                        sku: sku,
                        is_ra_checked: isChecked,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (res) {
                    if (res.success) {
                        showNotification('success', `R&A updated for SKU: ${sku}`);
                    } else {
                        $checkbox.prop('checked', !$checkbox.is(':checked')); // revert change
                        showNotification('danger', `Failed to update R&A for SKU: ${sku}`);
                    }
                    },
                    error: function (xhr) {
                        $checkbox.prop('checked', !$checkbox.is(':checked'));
                        console.error(xhr.responseText); // to inspect what went wrong
                        showNotification('danger', `Error updating R&A for SKU: ${sku}`);
                    }
                });
            });

           





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

                    const itemId = itemData['Sl'] || 'unknown';
                    const modalId = `modal-${itemId}-${type.replace(/\s+/g, '-').toLowerCase()}`;

                    // Check cache first - use the cached data if available
                    const cachedData = ebayDataCache.get(itemId);
                    const dataToUse = cachedData || itemData;

                    // Store the data in cache if it wasn't already
                    if (!cachedData) {
                        ebayDataCache.set(itemId, itemData);
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

            // Helper function to create a field card
            function createFieldCard(field, data, type, itemId) {
                const hyperlinkFields = ['link 1', 'link 2', 'link 3'];

                const editableFields = ['S price', 'LMP 1', 'link 1', 'lmp 2', 'link 2', 'lmp 3', 'link 3',
                    'HIDE', 'LISTED', 'LIVE / ACTIVE', 'VISIBILITY ISSUE', 'INV SYNCED',
                    'RIGHT CATEGORY', 'INCOMPLETE LISTING', 'BUYBOX ISSUE', 'SEO  (KW RICH) ISSUE',
                    'TITLE ISSUEAD ISSUE', 'AD ISSUE', 'BP ISSUE', 'DESCR ISSUE', 'SPECS ISSUE',
                    'IMG ISSUE', 'CATEGORY ISSUE', 'MAIN IMAGE ISSUE', 'PRICE ISSUE',
                    'REVIEW ISSUE', 'WRONG KW IN LISTING', 'CVR ISSUE', 'REV ISSUE',
                    'IMAGE ISSUE', 'VID ISSUE', 'USP HIGHLIGHT ISSUE', 'SPECS ISSUES',
                    'MISMATCH ISSUE', 'NOTES', 'ACTION', 'TITLE ISSUE'
                ];

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

                // const getIndicatorColor = (fieldTitle, fieldValue) => {
                //     const value = (fieldValue * 100).toFixed(2) || 0;

                //     if (type === 'price view') {
                //         if (['PFT %', 'Spft%'].includes(fieldTitle)) {
                //             if (value < 10) return 'red';
                //             if (value >= 10 && value < 15) return 'yellow';
                //             if (value >= 15 && value < 20) return 'blue';
                //             if (value >= 20 && value < 40) return 'green';
                //             if (value >= 40) return 'pink';
                //         }

                //         if (fieldTitle === 'ROI%') {
                //             if (value < 50) return 'red';
                //             if (value >= 50 && value < 75) return 'yellow';
                //             if (value >= 75 && value < 125) return 'green';
                //             if (value >= 125) return 'pink';
                //         }

                //         if (['a+spft', 'a+ROI'].includes(fieldTitle)) {
                //             return 'gray'; // Missing in sheet
                //         }

                //         return 'gray';
                //     }

                //     if (type === 'visibility view') {
                //         if (['KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PmtCtrL30', 'PmtCtrL7'].includes(fieldTitle)) {
                //             return 'gray'; // Marked as missing
                //         }

                //         return 'gray';
                //     }

                //     if (type === 'advertisement view') {
                //         if (['KwAcosL60', 'KwAcosL30', 'KwAcosL7', 'TacosL30'].includes(fieldTitle)) {
                //             if (value == 0 || value == 100) return 'red';
                //             if (value > 0 && value <= 7) return 'pink';
                //             if (value > 7 && value <= 14) return 'green';
                //             if (value > 14 && value <= 21) return 'yellow';
                //             if (value > 21) return 'red';
                //         }

                //         if (['KwCvrL30', 'KwCvrL7'].includes(fieldTitle)) {
                //             if (value < 7) return 'red';
                //             if (value > 7 && value <= 13) return 'green';
                //             if (value > 13) return 'pink';
                //         }

                //         if (['Ub 7', 'Ub yes'].includes(fieldTitle)) {
                //             if (value < 50) return 'red';
                //             if (value >= 50 && value <= 90) return 'green';
                //             if (value > 90) return 'pink';
                //         }

                //         if (['PmtAcosL30', 'PmtAcosL7'].includes(fieldTitle)) {
                //             if (value == 0) return 'red';
                //             if (value > 0 && value <= 10) return 'pink';
                //             if (value > 10 && value <= 20) return 'green';
                //             if (value > 20) return 'red';
                //         }

                //         if (fieldTitle === 'PmtCvrL30') {
                //             if (value < 7) return 'red';
                //             if (value > 7 && value < 13) return 'green';
                //             if (value >= 13) return 'pink';
                //         }

                //         if (fieldTitle === 'PmtCvrL7') {
                //             if (value < 7) return 'red';
                //             if (value > 7 && value < 14) return 'green';
                //             if (value >= 14) return 'pink';
                //         }

                //         if (['KwCtrL60', 'KwCtrL30', 'KwCtrL7', 'PmtCtrL30', 'PmtCtrL7', 'Pmt%'].includes(
                //                 fieldTitle)) {
                //             return 'gray'; // Missing in sheet
                //         }

                //         return 'gray';
                //     }

                //     if (type === 'conversion view') {
                //         if (['Scvr', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30', 'DspCvr60', 'DspCvr30',
                //                 'HdCvr60',
                //                 'HdCvr30', 'TCvr60', 'TCvr30'
                //             ].includes(fieldTitle)) {
                //             if (value <= 7) return 'red';
                //             if (value > 7 && value <= 13) return 'green';
                //             if (value > 13) return 'pink';
                //         }
                //         return 'gray';
                //     }

                //     return 'gray';
                // };

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

                // if (percentageFields.includes(field.title) && typeof content ===
                //     'number') {
                //     content = `${(content * 100).toFixed(2)}%`;
                // }

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
                ebayDataCache.updateField(itemId, title, cacheUpdateValue);

                // 2. Update the filteredData array to reflect the change
                const index = filteredData.findIndex(item => item['Sl'] == itemId);
                if (index !== -1) {
                    filteredData[index][title] = cacheUpdateValue;

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

                // 4. Send the update to the server with dynamic URL
                $.ajax({
                    method: 'POST',
                    url: window.location.origin + (window.location.pathname.includes('/public') ?
                        '/public' : '') + '/api/update-ebay-column',
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
                        ebayDataCache.updateField(itemId, title, originalValue);

                        // Revert filteredData
                        if (index !== -1) {
                            filteredData[index][title] = originalValue;
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
                const $table = $('#ebay-table');
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
            // function initSorting() {
            //     $('th[data-field]').addClass('sortable').on('click', function(e) {
            //         if (isResizing) {
            //             e.stopPropagation();
            //             return;
            //         }

            //         // Prevent sorting when clicking on search inputs
            //         if ($(e.target).is('input') || $(e.target).closest('.position-relative').length) {
            //             return;
            //         }

            //         const th = $(this).closest('th');
            //         const thField = th.data('field');
            //         const dataField = thField === 'parent' ? 'Parent' : thField;

            //         // Toggle direction if clicking same column, otherwise reset to ascending
            //         if (currentSort.field === dataField) {
            //             currentSort.direction *= -1;
            //         } else {
            //             currentSort.field = dataField;
            //             currentSort.direction = 1;
            //         }

            //         // Update UI arrows
            //         $('.sort-arrow').html('↓');
            //         $(this).find('.sort-arrow').html(currentSort.direction === 1 ? '↑' : '↓');

            //         // Sort with fresh data
            //         const freshData = [...tableData];
            //         freshData.sort((a, b) => {
            //             const valA = a[dataField] || '';
            //             const valB = b[dataField] || '';

            //             // Numeric comparison for numeric fields
            //             if (dataField === 'sl_no' || dataField === 'INV' || dataField === 'L30') {
            //                 return (parseFloat(valA) - parseFloat(valB)) * currentSort.direction;
            //             }

            //             // String comparison for other fields
            //             return String(valA).localeCompare(String(valB)) * currentSort.direction;
            //         });

            //         filteredData = freshData;
            //         currentPage = 1;
            //         renderTable();
            //     });
            // }

            function initSorting() {
                $('th[data-field]').addClass('sortable').off('click').on('click', function (e) {
                    if (isResizing) {
                        e.stopPropagation();
                        return;
                    }

                    // Prevent sorting when clicking inside inputs/selects
                    if ($(e.target).is('input, select') || $(e.target).closest('.position-relative').length) {
                        return;
                    }

                    const $th = $(this);
                    const dataField = $th.data('field');

                    if (!dataField) return;

                    const sortField = dataField === 'parent' ? 'Parent' : dataField;

                    // Toggle sort direction
                    if (currentSort.field === sortField) {
                        currentSort.direction *= -1;
                    } else {
                        currentSort.field = sortField;
                        currentSort.direction = 1;
                    }

                    // Update UI arrows
                    $('th .sort-arrow').html('↓'); // reset all
                    $th.find('.sort-arrow').html(currentSort.direction === 1 ? '↑' : '↓');

                    // Sort tableData (not just filteredData)
                    const sorted = [...filteredData].sort((a, b) => {
                        let valA = a[sortField] ?? '';
                        let valB = b[sortField] ?? '';

                        // Explicitly handle numeric fields (INV, L30, DIL, etc.)
                        const numericFields = ['INV', 'L30', 'DIL', 'ON_HAND', 'COMMITTED', 'AVAILABLE_TO_SELL'];
                        if (numericFields.includes(sortField)) {
                            valA = parseFloat(valA) || 0;
                            valB = parseFloat(valB) || 0;
                            return (valA - valB) * currentSort.direction;
                        }

                        // Handle numbers (for other numeric fields)
                        const numA = parseFloat(valA);
                        const numB = parseFloat(valB);
                        if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
                            return (numA - numB) * currentSort.direction;
                        }

                        // Fallback: string compare
                        return String(valA).localeCompare(String(valB)) * currentSort.direction;
                    });

                    filteredData = sorted;
                    currentPage = 1;
                    renderTable();
                });
            }


            // Initialize pagination
            function initPagination() {
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
                    const totalPages = Math.ceil(filteredData.length / rowsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        renderTable();
                    }
                });

                $('#last-page').on('click', function() {
                    const totalPages = Math.ceil(filteredData.length / rowsPerPage);
                    currentPage = totalPages || 1;
                    renderTable();
                });
            }

            function updatePaginationInfo() {
                // Show pagination with proper info
                $('.pagination-controls').show();
                
                const totalRows = filteredData.length;
                const totalPages = Math.ceil(totalRows / rowsPerPage);
                const startRow = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
                const endRow = Math.min(currentPage * rowsPerPage, totalRows);
                
                $('#visible-rows').text(`Showing ${startRow}-${endRow} of ${totalRows}`);
                $('#page-info').text(`Page ${currentPage} of ${totalPages || 1}`);
                
                // Enable/disable pagination buttons
                $('#first-page, #prev-page').prop('disabled', currentPage === 1 || totalPages === 0);
                $('#next-page, #last-page').prop('disabled', currentPage >= totalPages || totalPages === 0);
            }

            // Initialize search functionality
            function initSearch() {
                $('#search-input').on('keyup', function() {
                    currentPage = 1; // Reset to first page when search changes
                    applyAllFilters(); // Apply all filters including search
                });
            }

            // Initialize column toggle functionality
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

            // Apply column filters
            function applyColumnFilters() {
                console.log('Applying column filters...');
                filteredData = [...tableData]; // start fresh

                // Apply Data Type filter
                const rowTypeFilter = $('#row-data-type').val();
                if (rowTypeFilter === 'parent') {
                    filteredData = filteredData.filter(item => item.is_parent === true);
                } else if (rowTypeFilter === 'sku') {
                    filteredData = filteredData.filter(item => item.is_parent === false);
                }

                // Apply any other active filters if you have them
                if (state.filters && Object.keys(state.filters).length > 0) {
                    Object.entries(state.filters).forEach(([column, filterValue]) => {
                        if (filterValue === 'all') return;

                        filteredData = filteredData.filter(item => {
                            if (column === 'entryType') {
                                if (filterValue === 'parent') return item.is_parent === true;
                                if (filterValue === 'child') return item.is_parent === false;
                                return true;
                            }

                            const color = getColorForColumn(column, item);
                            return color === filterValue;
                        });
                    });
                }

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

                // For OV CLICKS L30, use the raw value (not percentage)
                if (column === 'OV CLICKS L30') {
                    const value = parseInt(rowData[column]) || 0;
                    return value >= 30 ? 'green' : 'red';
                }

                const value = parseFloat(rowData[column]) * 100;

                // Special cases for numeric columns that must be valid numbers
                const numericColumns = ['PFT %', 'Roi', 'Tacos30', 'SCVR']; // Add other numeric columns as needed
                if (numericColumns.includes(column) && isNaN(value)) {
                    return '';
                }


                const colorRules = {
                    'Dil%': {
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
                        pftSum: 0,
                        roiSum: 0,
                        tacosTotal: 0,
                        scvrSum: 0,
                        rowCount: 0,
                        onHand:0,
                        committed:0,
                        avlToSell:0
                    };

                    filteredData.forEach(item => {
                        const sku = String(item.SKU || '').trim().toUpperCase();
                        if (sku.startsWith('PARENT ')) return;

                        metrics.invTotal += parseFloat(item.INV) || 0;
                        metrics.ovL30Total += parseFloat(item.L30) || 0;
                        metrics.onHand += parseFloat(item.ON_HAND) || 0;
                        metrics.committed += parseFloat(item.COMMITTED) || 0;
                        metrics.avlToSell += parseFloat(item.AVAILABLE_TO_SELL) || 0;
                        metrics.el30Total += parseFloat(item['E L30']) || 0;
                        metrics.viewsTotal += parseFloat(item['OV CLICKS L30']) || 0;

                        // Make sure these property names match your data structure
                        metrics.pftSum += parseFloat(item['PFT %']) || 0;
                        metrics.roiSum += parseFloat(item.Roi) || 0; // Changed from ROI% to Roi
                        metrics.tacosTotal += parseFloat(item.Tacos30) ||
                            0; // Changed from TacosL30 to Tacos30
                        metrics.scvrSum += parseFloat(item.SCVR) || 0;
                        metrics.rowCount++;
                    });

                    // Calculate percentages
                    metrics.ovDilTotal = metrics.invTotal > 0 ?
                        (metrics.ovL30Total / metrics.invTotal) * 100 : 0;
                    metrics.eDilTotal = metrics.el30Total > 0 ?
                        (metrics.el30Total / metrics.ovL30Total) * 100 : 0;

                    const divisor = metrics.rowCount || 1;

                    // Update metric displays with correct calculations
                    $('#inv-total').text(metrics.invTotal.toLocaleString());
                    $('#ovl30-total').text(metrics.ovL30Total.toLocaleString());
                    $('#ovdil-total').text(Math.round(metrics.ovDilTotal) + '%');
                    $('#el30-total').text(metrics.el30Total.toLocaleString());
                    $('#eDil-total').text(Math.round(metrics.eDilTotal) + '%');
                    $('#views-total').text(metrics.viewsTotal.toLocaleString());
                    $('#onhand-total').text(metrics.onHand.toLocaleString());
                    $('#committed-total').text(metrics.committed.toLocaleString());
                    $('#avltosell-total').text(metrics.avlToSell.toLocaleString());

                    // Calculate and display averages
                    $('#pft-total').text(Math.round((metrics.pftSum / divisor) * 100) + '%');
                    $('#roi-total').text(Math.round((metrics.roiSum / divisor) * 100) + '%');
                    $('#tacos-total').text(Math.round((metrics.tacosTotal / divisor) * 100) + '%');
                    $('#cvr-total').text(Math.round((metrics.scvrSum / divisor) * 100) + '%');

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
                $('#eDil-total').text('0%');
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
                initEnhancedDropdown($skuSearch, $skuResults, 'SKU');

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

                    const normalizedSearch = searchTerm.replace(/\s+/g, '').toLowerCase();

                    // Filter based on search term if provided
                    // const filteredValues = searchTerm.length >= minSearchLength ?
                    //     uniqueValues.filter(value =>
                    //         // value.toLowerCase().includes(searchTerm.toLowerCase())
                    //         const normalizedValue = value.replace(/\s+/g, '').toLowerCase();
                    //         return normalizedValue.includes(normalizedSearch);
                    //     ) :
                    //     uniqueValues;
                    const filteredValues =
                        searchTerm.length >= minSearchLength
                            ? uniqueValues.filter(value => {
                                const normalizedValue = value.replace(/\s+/g, '').toLowerCase();
                                return normalizedValue.includes(normalizedSearch);
                            })
                            : uniqueValues;

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

                // Function to filter the table by column value - now uses unified filter
                function filterByColumn(column, value) {
                    // Use unified filter function that applies all filters together
                    applyAllFilters();
                    currentPage = 1;
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

                        if (searchTerm === '') {
                            applyAllFilters(); // Use unified filter
                            $results.hide();
                            return;
                        }

                        timeout = setTimeout(() => {
                            updateDropdownResults($results, field, searchTerm);
                            // Apply unified filter after updating dropdown
                            applyAllFilters();
                        }, 300);
                    });

                    // Handle item selection
                    $results.on('click', '.dropdown-search-item:not(.no-results)', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const value = $(this).data('value');
                        $input.val(value);
                        applyAllFilters(); // Use unified filter
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

                // $('#row-data-type').on('change', function() {
                //     const filterType = $(this).val();
                //     applyRowTypeFilter(filterType);
                // });
                $(document).on('change', '#row-data-type', function () {
                    const filterType = $(this).val();
                    applyRowTypeFilter(filterType);
                });
                
                // Add direct event handlers for search inputs to trigger unified filter
                // Parent search
                $('#parentSearch').on('input', function() {
                    const value = $(this).val().trim();
                    if (value === '') {
                        applyAllFilters();
                    }
                });
                
                // SKU search
                $('#skuSearch').on('input', function() {
                    const value = $(this).val().trim();
                    if (value === '') {
                        applyAllFilters();
                    }
                });
            }

            // function initEnhancedDropdown($input, $results, field) {
            //     let timeout;
            //     const minSearchLength = 1;

            //     // Show dropdown when input is clicked
            //     $input.on('click', function(e) {
            //         e.stopPropagation();
            //         updateDropdownResults($results, field, $(this).val().trim().toLowerCase());
            //     });

            //     // Handle input events
            //     $input.on('input', function() {
            //         clearTimeout(timeout);
            //         const searchTerm = $(this).val().trim().toLowerCase();

            //         // If search is cleared, trigger filtering immediately
            //         if (searchTerm === '') {
            //             filterByColumn(field, '');
            //             $results.hide();
            //             return;
            //         }

            //         timeout = setTimeout(() => {
            //             updateDropdownResults($results, field, searchTerm);
            //         }, 300);
            //     });

            //     // Handle item selection
            //     $results.on('click', '.dropdown-search-item', function(e) {
            //         e.preventDefault();
            //         e.stopPropagation();

            //         const value = $(this).data('value');
            //         $input.val(value);
            //         filterByColumn(field, value);

            //         // Close the dropdown after selection
            //         $results.hide();

            //         // If you want to clear the filter when clicking the same value again
            //         if ($input.data('last-value') === value) {
            //             $input.val('');
            //             filterByColumn(field, '');
            //         }
            //         $input.data('last-value', value);
            //     });

            //     // Handle keyboard navigation
            //     $input.on('keydown', function(e) {
            //         if (e.key === 'ArrowDown') {
            //             e.preventDefault();
            //             const $firstItem = $results.find('.dropdown-search-item').first();
            //             if ($firstItem.length) {
            //                 $firstItem.focus();
            //             }
            //         }
            //     });

            //     $results.on('keydown', '.dropdown-search-item', function(e) {
            //         if (e.key === 'ArrowDown') {
            //             e.preventDefault();
            //             $(this).next('.dropdown-search-item').focus();
            //         } else if (e.key === 'ArrowUp') {
            //             e.preventDefault();
            //             const $prev = $(this).prev('.dropdown-search-item');
            //             if ($prev.length) {
            //                 $prev.focus();
            //             } else {
            //                 $input.focus();
            //             }
            //         } else if (e.key === 'Enter') {
            //             e.preventDefault();
            //             $(this).click();
            //             $results.hide();
            //         } else if (e.key === 'Escape') {
            //             $results.hide();
            //             $input.focus();
            //         }
            //     });
            // }

            // function updateDropdownResults($results, field, searchTerm) {
            //     if (!tableData.length) return;

            //     $results.empty();

            //     if (searchTerm.length < minSearchLength) {
            //         // Show all unique values when search is empty
            //         const uniqueValues = [...new Set(tableData.map(item => String(item[field] || '')))];
            //         uniqueValues.sort().forEach(value => {
            //             if (value) {
            //                 $results.append(
            //                     `<div class="dropdown-search-item" data-value="${value}">${value}</div>`
            //                 );
            //             }
            //         });
            //     } else {
            //         // Filter results based on search term
            //         const matches = tableData.filter(item =>
            //             String(item[field] || '').toLowerCase().includes(searchTerm)
            //         );

            //         if (matches.length) {
            //             const uniqueMatches = [...new Set(matches.map(item => String(item[field] || '')))];
            //             uniqueMatches.sort().forEach(value => {
            //                 if (value) {
            //                     $results.append(
            //                         `<div class="dropdown-search-item" data-value="${value}">${value}</div>`
            //                     );
            //                 }
            //             });
            //         } else {
            //             $results.append('<div class="dropdown-search-item no-results">No matches found</div>');
            //         }
            //     }

            //     $results.show();
            // }

            function applyRowTypeFilter(filterType) {
                // Use unified filter function
                applyAllFilters();
                currentPage = 1;
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
                const isDanger = type === 'danger' || type === 'error';
                const fontSize = isDanger ? 'font-size: 20px; font-weight: bold;' : 'font-size: 14px;';
                const padding = isDanger ? 'padding: 20px 25px;' : '';
                
                const notification = $(`
                    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                        <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="${fontSize} ${padding}">
                            ${message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    </div>
                `);

                $('body').append(notification);

                setTimeout(() => {
                    notification.find('.alert').alert('close');
                }, isDanger ? 5000 : 3000); // Error messages stay longer
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
