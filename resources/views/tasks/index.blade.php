@extends('layouts.vertical', ['title' => 'Task Manager', 'sidenav' => 'condensed'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">

    <style>
        /* ========================================
           MOBILE OPTIMIZED STYLES
           ======================================== */
        @media (max-width: 767.98px) {
            /* Statistics Cards - Mobile Grid Layout */
            .stats-row {
                display: grid !important;
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                padding: 15px;
                margin-bottom: 15px !important;
            }
            
            .stats-row > div {
                width: 100%;
            }
            
            .stat-card {
                padding: 18px 15px !important;
                margin-bottom: 0 !important;
                border-radius: 16px !important;
                flex-direction: column !important;
                text-align: center !important;
                height: auto !important;
                min-height: 135px;
                justify-content: center !important;
                box-shadow: 0 4px 12px rgba(0,0,0,0.08) !important;
                border-left: 0 !important;
                border-top: 4px solid !important;
                background: white !important;
                position: relative;
                overflow: hidden;
            }
            
            .stat-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                opacity: 0.05;
                pointer-events: none;
            }
            
            .stat-card-blue::before {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            .stat-card-cyan::before {
                background: linear-gradient(135deg, #0dcaf0 0%, #0891b2 100%);
            }
            
            .stat-card-red::before {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }
            
            .stat-card-green::before {
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            }
            
            .stat-card-yellow::before {
                background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
            }
            
            .stat-card:hover {
                transform: none !important;
            }
            
            .stat-card:active {
                transform: scale(0.98) !important;
            }
            
            .stat-icon {
                width: 55px !important;
                height: 55px !important;
                font-size: 13px !important;
                margin: 0 auto 10px auto !important;
                border-radius: 14px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
            }
            
            .stat-content {
                text-align: center !important;
                width: 100%;
                position: relative;
                z-index: 1;
            }
            
            .stat-label {
                font-size: 9px !important;
                margin-bottom: 4px !important;
                font-weight: 600 !important;
                letter-spacing: 0.3px !important;
            }
            
            .stat-value {
                font-size: 18px !important;
                margin-bottom: 2px !important;
                font-weight: 700 !important;
                line-height: 1.2 !important;
            }
            
            .stat-unit {
                font-size: 8px !important;
                font-weight: 600 !important;
                color: #8094ae !important;
                margin-top: 2px !important;
            }
            
            /* Hide desktop table on mobile */
            #tasks-table {
                display: none !important;
            }
            
            /* Show mobile card view */
            #mobile-tasks-view {
                display: block !important;
            }
            
            /* Mobile Filters - Better Layout */
            .filter-section {
                padding: 15px !important;
            }
            
            .filter-section .row {
                margin: 0 !important;
            }
            
            .filter-section .row > div {
                padding: 0 !important;
                margin-bottom: 12px !important;
            }
            
            .filter-section .form-label {
                font-size: 12px !important;
                font-weight: 600 !important;
                color: #667eea !important;
                margin-bottom: 6px !important;
            }
            
            .filter-section .form-select,
            .filter-section .form-control {
                font-size: 15px !important;
                padding: 12px 15px !important;
                border-radius: 10px !important;
                border: 2px solid #e3e6f0 !important;
                min-height: 48px !important;
            }
            
            .filter-section .form-select:focus,
            .filter-section .form-control:focus {
                border-color: #667eea !important;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1) !important;
            }
            
            /* Mobile Search Box */
            #filter-search {
                font-size: 16px !important;
                padding: 14px 20px !important;
                border-radius: 25px !important;
                border: 2px solid #667eea !important;
                background: #f8f9fa !important;
            }
            
            #filter-search::placeholder {
                color: #adb5bd !important;
            }
            
            /* Quick Filter Chips */
            .mobile-quick-filters {
                display: flex;
                gap: 8px;
                overflow-x: auto;
                padding: 10px 15px;
                margin-bottom: 15px;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            
            .mobile-quick-filters::-webkit-scrollbar {
                display: none;
            }
            
            .quick-filter-chip {
                flex: 0 0 auto;
                padding: 8px 16px;
                border-radius: 20px;
                background: white;
                border: 2px solid #e3e6f0;
                font-size: 13px;
                font-weight: 600;
                color: #495057;
                white-space: nowrap;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            }
            
            .quick-filter-chip.active {
                background: #667eea;
                color: white;
                border-color: #667eea;
                box-shadow: 0 4px 8px rgba(102,126,234,0.3);
            }
            
            /* Mobile Action Buttons - Ultra Compact */
            .mobile-action-buttons {
                display: flex;
                gap: 6px;
                padding: 8px 12px;
                margin-bottom: 8px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
            }
            
            .mobile-action-buttons::-webkit-scrollbar {
                display: none;
            }
            
            .mobile-action-btn {
                flex: 0 0 auto;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                border-radius: 20px;
                border: none;
                text-decoration: none;
                color: white;
                font-weight: 500;
                font-size: 13px;
                white-space: nowrap;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                transition: transform 0.1s;
            }
            
            .mobile-action-btn:active {
                transform: scale(0.95);
            }
            
            .mobile-action-btn i {
                font-size: 16px;
            }
            
            .mobile-action-btn span {
                font-size: 13px;
            }
            
            .mobile-action-btn.btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            
            .mobile-action-btn.btn-success {
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            }
            
            .mobile-action-btn.btn-warning {
                background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
                color: white;
            }
            
            .mobile-action-btn.btn-secondary {
                background: linear-gradient(135deg, #4a5568 0%, #6b7280 100%);
            }
            
            .mobile-action-btn.btn-info {
                background: linear-gradient(135deg, #0dcaf0 0%, #0891b2 100%);
            }
            
            /* Selected count badge on mobile */
            #selected-count-mobile {
                display: none;
                background: #667eea;
                color: white;
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 13px;
                text-align: center;
                margin: 0 15px 10px 15px;
            }
            
            /* Hide page title box on mobile (we have mobile header) */
            .page-title-box {
                display: none !important;
            }
            
            /* Card with action buttons */
            .task-card {
                border-radius: 0 !important;
                box-shadow: none !important;
                margin-bottom: 0 !important;
                border: none !important;
            }
            
            .task-card .card-body {
                padding: 0 !important;
                background: #f8f9fa !important;
            }
            
            /* Mobile page sections */
            .mobile-section-header {
                background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%);
                padding: 8px 15px;
                margin: 0 -15px 10px -15px;
            }
            
            /* Improve filter section mobile */
            .filter-section {
                background: white !important;
                border-radius: 16px !important;
                margin: 0 15px 15px 15px !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08) !important;
            }
            
            /* Content background */
            @media (max-width: 767.98px) {
                .content-page .content {
                    background: #f0f2f5 !important;
                }
            }
            
            /* Card adjustments */
            .card {
                border-radius: 12px !important;
                margin-bottom: 15px !important;
            }
            
            .card-header {
                padding: 12px 15px !important;
            }
            
            .card-body {
                padding: 15px !important;
            }
        }

        /* Assignor / assignee filter: Select2 aligned with form-select-sm */
        .filter-section .task-filter-user-select + .select2-container--bootstrap-5 .select2-selection {
            min-height: calc(1.5em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
        }
        .filter-section .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
        }
        .filter-section .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 100%;
        }
        .select2-container--bootstrap-5.select2-container--open {
            z-index: 1060;
        }

        /* Filter bar: equal column widths on desktop, aligned row */
        .filter-section.filter-section-eq {
            align-items: center;
        }
        @media (min-width: 768px) {
            .filter-section-eq > .col-12 {
                flex: 1 1 0;
                min-width: 0;
                max-width: none;
                width: auto;
            }
        }
        
        /* Mobile Task Cards */
        #mobile-tasks-view {
            display: none; /* Hidden on desktop */
        }
        
        .mobile-task-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid;
            transition: all 0.3s ease;
        }
        
        .mobile-task-card:active {
            transform: scale(0.98);
        }
        
        .mobile-task-card.status-pending {
            border-left-color: #0dcaf0;
        }
        
        .mobile-task-card.status-inprogress {
            border-left-color: #ffc107;
        }
        
        .mobile-task-card.status-done {
            border-left-color: #28a745;
        }
        
        .mobile-task-card.status-overdue {
            border-left-color: #dc3545;
        }
        
        .mobile-task-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }
        
        .mobile-task-title {
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            line-height: 1.4;
        }
        
        .mobile-task-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .mobile-task-badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: 600;
            text-shadow: none;
        }
        
        /* Better badge colors with good contrast */
        .badge.bg-info {
            background-color: #0dcaf0 !important;
            color: #000 !important; /* Dark text for light blue */
        }
        
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #000 !important; /* Dark text for yellow */
        }
        
        .badge.bg-success {
            background-color: #198754 !important; /* Darker green */
            color: #fff !important;
        }
        
        .badge.bg-primary {
            background-color: #0d6efd !important;
            color: #fff !important;
        }
        
        .badge.bg-danger {
            background-color: #dc3545 !important;
            color: #fff !important;
        }
        
        .badge.bg-secondary {
            background-color: #6c757d !important;
            color: #fff !important;
        }
        
        .badge.bg-dark {
            background-color: #212529 !important;
            color: #fff !important;
        }
        
        .mobile-task-info {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 8px;
        }
        
        .mobile-task-info i {
            margin-right: 5px;
            font-size: 14px;
        }
        
        .mobile-task-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }
        
        .mobile-task-actions .btn {
            flex: 1;
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        /* Priority badges with STRONG contrast */
        .mobile-priority-high {
            background: #7c3aed !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 4px rgba(124, 58, 237, 0.35);
        }
        
        .mobile-priority-medium {
            background: #fd7e14 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(253, 126, 20, 0.3);
        }
        
        .mobile-priority-normal {
            background: #0d6efd !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.3);
        }
        
        .mobile-priority-low {
            background: #198754 !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(25, 135, 84, 0.3);
        }
        
        /* Pull to refresh hint */
        .pull-to-refresh-hint {
            text-align: center;
            padding: 10px;
            color: #6c757d;
            font-size: 12px;
        }
        
        /* Loading spinner for mobile */
        .mobile-loading {
            text-align: center;
            padding: 40px 20px;
        }
        
        .mobile-loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Empty state for mobile */
        .mobile-empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .mobile-empty-state i {
            font-size: 64px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
        
        .mobile-empty-state h5 {
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .mobile-empty-state p {
            color: #adb5bd;
            font-size: 14px;
        }
        
        /* Statistics Cards - slim compact row (desktop) */
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06), 0 1px 2px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            border-left: 3px solid !important;
            border-right: none !important;
            border-top: none !important;
            border-bottom: none !important;
            min-height: 0;
            position: relative;
            overflow: hidden;
            width: 100%;
            height: 100%;
        }

        .stat-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.06);
            transform: translateY(-1px);
        }

        .stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-size: 16px;
            color: white;
            flex-shrink: 0;
        }

        .stat-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0;
        }

        /* Keep all stat cards in one line; no scrolling - uniform widths */
        .stats-row.flex-nowrap {
            overflow-x: hidden;
            overflow-y: hidden;
            gap: 0;
            margin-left: -10px;
            margin-right: -10px;
        }
        .stats-row.flex-nowrap > .col {
            min-width: 0;
            flex: 1 1 0;
            padding-left: 10px;
            padding-right: 10px;
            display: flex;
        }
        
        /* Ensure all stat cards have consistent styling and equal height */
        .stats-row.flex-nowrap > .col > .stat-card {
            width: 100%;
            border-left: 3px solid !important;
            border-right: none !important;
            border-top: none !important;
            border-bottom: none !important;
        }

        .stat-label {
            font-size: 8px;
            font-weight: 600;
            color: #6c757d;
            letter-spacing: 0.35px;
            margin-bottom: 1px;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .stat-value {
            font-size: 17px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.15;
            margin-bottom: 0;
        }

        .stat-unit {
            font-size: 8px;
            color: #6c757d;
            margin-top: 1px;
            line-height: 1.2;
            font-weight: 500;
        }

        /* Blue - Total */
        .stat-card-blue {
            border-left-color: #3b7ddd;
        }
        .stat-card-blue .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        /* Cyan - Pending */
        .stat-card-cyan {
            border-left-color: #0dcaf0;
        }
        .stat-card-cyan .stat-icon {
            background: linear-gradient(135deg, #0dcaf0 0%, #0891b2 100%);
        }

        /* Info - Page ETC total */
        .stat-card-info {
            border-left-color: #3bc9e8;
        }
        .stat-card-info .stat-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Hide the year (YYYY) portion of the start-date filter (WebKit/Chromium). */
        #filter-date::-webkit-datetime-edit-year-field {
            display: none;
        }
        /* Hide the "/" separator that precedes the year field. */
        #filter-date::-webkit-datetime-edit-text:last-of-type {
            display: none;
        }

        /* Red - Overdue */
        .stat-card-red {
            border-left-color: #dc3545;
        }
        .stat-card-red .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        /* Green - Done */
        .stat-card-green {
            border-left-color: #28a745;
        }
        .stat-card-green .stat-icon {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        /* Yellow - ETC */
        .stat-card-yellow {
            border-left-color: #ffc107;
        }
        .stat-card-yellow .stat-icon {
            background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%);
        }

        /* Select user - icon before TOTAL */

        /* R&R - Roles & Responsibilities (separate badge card, custom icon) */
        .stat-card-rr {
            border-left-color: #0d9488;
        }
        .stat-card-rr .stat-icon {
            background: transparent;
        }
        .stat-card-rr .stat-icon.stat-icon-img {
            width: 42px;
            height: 42px;
            min-width: 42px;
            min-height: 42px;
            padding: 4px;
            border-radius: 8px;
        }
        .stat-card-rr .stat-icon-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .stat-icon-img {
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Teal - ATC */
        .stat-card-teal {
            border-left-color: #20c997;
        }
        .stat-card-teal .stat-icon {
            background: linear-gradient(135deg, #0ba360 0%, #3cba92 100%);
        }

        /* Performance average score (distinct from stat-card-purple / Done ATC) */
        .stat-card-perf-score {
            border-left-color: #5b21b6;
        }
        .stat-card-perf-score .stat-icon {
            background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        }

        /* Red Missed */
        .stat-card-red-missed {
            border-left-color: #dc3545;
        }
        .stat-card-red-missed .stat-icon {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        /* Orange - Done ETC */
        .stat-card-orange {
            border-left-color: #fd7e14;
        }
        .stat-card-orange .stat-icon {
            background: linear-gradient(135deg, #fa8305 0%, #ff6b6b 100%);
        }

        /* Purple - Done ATC */
        .stat-card-purple {
            border-left-color: #6610f2;
        }
        .stat-card-purple .stat-icon {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 15px;
            }
            
            .stat-value {
                font-size: 24px;
            }
            
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            .stat-card-rr .stat-icon.stat-icon-img {
                width: 48px;
                height: 48px;
                min-width: 48px;
                min-height: 48px;
            }
        }
        
        /* Clean table shell — horizontal scroll lives on .table-wrapper (Tabulator syncs header in .tabulator-tableholder) */
        #tasks-table {
            background: white;
            border-radius: 8px;
        }
        
        .table-wrapper {
            overflow-x: auto;
            overflow-y: visible;
        }

        .tabulator {
            border: 1px solid #e9ecef !important;
            border-radius: 8px !important;
            font-size: 14px;
        }

        .tabulator .tabulator-header {
            background-color: #f8f9fa !important;
            border-bottom: 2px solid #e9ecef !important;
        }

        .tabulator .tabulator-header .tabulator-col {
            background-color: #f8f9fa !important;
            border-right: 1px solid #e9ecef !important;
            padding: 12px 8px !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 0 !important;
        }

        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            font-weight: 600 !important;
            color: #495057 !important;
            font-size: calc(13px * 0.9) !important; /* ~10% smaller than 13px */
            text-transform: uppercase;
        }

        /* ETC / ATC: stacked letters in header (override row uppercase for these) */
        .tabulator .tabulator-header .tabulator-col-title .tasks-th-vertical-letters {
            display: inline-block;
            font-weight: 700 !important;
            font-size: calc(10px * 0.9) !important;
            color: #495057 !important;
            line-height: 1.12;
            text-align: center;
            text-transform: none;
            letter-spacing: 0;
        }

        /* Center header titles without overriding .tabulator-col-content display (breaks column sync) */
        #tasks-table .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            text-align: center !important;
        }
        /* Sort triangle only (.tabulator-arrow is border-drawn); hide sorter box, keep title + column sort */
        #tasks-table .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-sorter {
            display: none !important;
        }
        #tasks-table .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-content .tabulator-col-title {
            padding-right: 0 !important;
        }

        /* Autofit-style narrow cols (see widthGrow:0 + cssClass on column defs) */
        #tasks-table .tabulator-header .tabulator-col.tasks-col-time-compact,
        #tasks-table .tabulator-row .tabulator-cell.tasks-col-time-compact {
            padding: 8px 3px !important;
        }
        #tasks-table .tabulator-header .tabulator-col.tasks-col-link-icon,
        #tasks-table .tabulator-row .tabulator-cell.tasks-col-link-icon {
            padding: 6px 2px !important;
        }
        #tasks-table .tabulator-header .tabulator-col.tasks-col-priority-compact,
        #tasks-table .tabulator-row .tabulator-cell.tasks-col-priority-compact {
            padding: 6px 2px !important;
        }

        .tabulator-row {
            border-bottom: 1px solid #e9ecef !important;
            background: white !important;
        }

        .tabulator-row:hover {
            background-color: #f8f9fa !important;
        }

        .tabulator-row.tabulator-selected {
            background-color: #e7f3ff !important;
        }

        .tabulator-row.tabulator-selected:hover {
            background-color: #d0e8ff !important;
        }
        
        /* Automated task rows - alternating yellow/light-yellow background */
        .tabulator-row.automated-task {
            background-color: #fffbea !important;
        }

        .tabulator-row.automated-task.alt {
            background-color: #fff7cc !important;
        }

        .tabulator-row.automated-task .tabulator-cell {
            background-color: #fffbea !important;
        }

        .tabulator-row.automated-task.alt .tabulator-cell {
            background-color: #fff7cc !important;
        }
        
        .tabulator-row.automated-task:hover {
            background-color: #fef3c7 !important;
        }

        .tabulator-row.automated-task:hover .tabulator-cell {
            background-color: #fef3c7 !important;
        }

        .tabulator-row .tabulator-cell {
            border-right: 1px solid #e9ecef !important;
            padding: 12px 8px !important;
            color: #495057;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background-color: #cff4fc;
            color: #055160;
            border: 1px solid #9eeaf9;
        }

        .status-in_progress {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffe69c;
        }

        .status-archived {
            background-color: #e2e3e5;
            color: #41464b;
            border: 1px solid #d3d6d8;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-need_help {
            background-color: #ffe5d0;
            color: #984c0c;
            border: 1px solid #ffc9a0;
        }

        .status-need_approval {
            background-color: #e0cffc;
            color: #432874;
            border: 1px solid #d8bbff;
        }

        .status-dependent {
            background-color: #f7d6e6;
            color: #ab296a;
            border: 1px solid #f1b0d0;
        }

        .status-approved {
            background-color: #d1f4e0;
            color: #146c43;
            border: 1px solid #9dd9c3;
        }

        .status-hold {
            background-color: #dee2e6;
            color: #212529;
            border: 1px solid #ced4da;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Priority Badges */
        .priority-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-low {
            background-color: #198754;
            color: #ffffff;
            border: 1px solid #146c43;
            box-shadow: 0 2px 4px rgba(25, 135, 84, 0.3);
        }

        .priority-normal {
            background-color: #0d6efd;
            color: #ffffff;
            border: 1px solid #0a58ca;
            box-shadow: 0 2px 4px rgba(13, 110, 253, 0.3);
        }

        .priority-medium {
            background-color: #fd7e14;
            color: #ffffff;
            border: 1px solid #ca6510;
            box-shadow: 0 2px 4px rgba(253, 126, 20, 0.3);
        }

        .priority-high {
            background-color: #7c3aed;
            color: #ffffff;
            border: 1px solid #5b21b6;
            box-shadow: 0 2px 4px rgba(124, 58, 237, 0.35);
        }

        /* Action Icon Buttons (view / edit / delete — 25% smaller than previous 16px/36px) */
        .action-btn-icon {
            padding: 6px 8px;
            font-size: 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 3px;
            display: inline-block;
            text-align: center;
            width: 27px;
            height: 27px;
            line-height: 15px;
        }

        .action-btn-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .action-btn-view {
            background-color: #0dcaf0;
            color: white;
        }

        .action-btn-view:hover {
            background-color: #0bb5d7;
        }

        .action-btn-edit {
            background-color: #ffc107;
            color: #000;
        }

        .action-btn-edit:hover {
            background-color: #e0a800;
        }

        .action-btn-delete {
            background-color: #dc3545;
            color: white;
        }

        .action-btn-delete:hover {
            background-color: #bb2d3b;
        }

        .action-btn-delete-disabled {
            background-color: #e9ecef !important;
            color: #adb5bd !important;
            opacity: 0.5 !important;
            cursor: not-allowed !important;
            pointer-events: none;
        }

        .action-btn-delete-disabled:hover {
            transform: none !important;
            box-shadow: none !important;
        }

        /* Pagination */
        .tabulator-footer {
            background: #f8f9fa !important;
            border-top: 2px solid #e9ecef !important;
            padding: 15px !important;
        }

        .tabulator-page {
            border: 1px solid #dee2e6 !important;
            background: white !important;
            color: #495057 !important;
            border-radius: 4px !important;
            margin: 0 2px !important;
        }

        .tabulator-page:hover {
            background: #e9ecef !important;
        }

        .tabulator-page.active {
            background: #0d6efd !important;
            color: white !important;
            border-color: #0d6efd !important;
        }

        /* Create Button */
        .btn-create-task {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
        }

        .btn-create-task:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        /* Card Styling */
        .task-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .task-card .card-body {
            padding: 12px 14px 14px;
        }

        /* Page Title */
        .page-title {
            font-weight: 700;
            color: #2c3e50;
            font-size: 24px;
        }


        /* ID Column */
        .id-cell {
            font-weight: 600;
            color: #6c757d;
        }

        /* Empty state */
        .tabulator-placeholder {
            padding: 50px !important;
            color: #6c757d !important;
            font-size: 16px !important;
        }

        /* Horizontal Scrollbar Styling */
        .tabulator {
            overflow-x: auto !important;
        }

        .tabulator::-webkit-scrollbar {
            height: 8px;
        }

        .tabulator::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .tabulator::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .tabulator::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Status Dropdown Styling */
        .status-select {
            cursor: pointer;
            border-radius: 20px !important;
            padding: 5px 10px !important;
            font-weight: 600 !important;
            border-width: 2px !important;
        }

        .status-select:focus {
            box-shadow: none !important;
        }

        .status-select option {
            padding: 10px;
        }

        /* Contenteditable Editor Styling */
        #rr-content-editor {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
        }

        #rr-content-editor:focus {
            outline: 2px solid #007bff;
            outline-offset: -2px;
        }

        #rr-content-editor img {
            max-width: 100%;
            height: auto;
            margin: 10px 0;
        }

        #rr-content-editor h1, #rr-content-editor h2, #rr-content-editor h3, #rr-content-editor h4 {
            margin-top: 15px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        #rr-content-editor ul, #rr-content-editor ol {
            padding-left: 30px;
            margin: 10px 0;
        }

        #rr-content-editor p {
            margin: 8px 0;
        }

        /* R&R Tabs Styling - More Prominent */
        #tasksRRTabs {
            background: #fff;
            padding: 0;
            margin: 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex !important;
            visibility: visible !important;
            min-height: auto;
        }

        #tasksRRTabs .nav-item {
            flex: 1;
        }

        #tasksRRTabs .nav-link {
            border: none !important;
            border-bottom: 3px solid transparent !important;
            color: #6c757d !important;
            padding: 6px 14px !important;
            transition: all 0.3s ease;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer;
            background: transparent;
            text-align: center;
            width: 100%;
        }

        #tasksRRTabs .nav-link:hover {
            color: #007bff !important;
            border-bottom-color: #dee2e6 !important;
            background-color: #f8f9fa;
        }

        #tasksRRTabs .nav-link.active {
            color: #007bff !important;
            border-bottom-color: #007bff !important;
            background-color: #f0f7ff !important;
        }

        #tasksRRTabs .nav-link i {
            font-size: 16px !important;
            margin-right: 6px;
        }

        /* R&R Container Styling */
        #rr-container {
            min-height: 400px;
            padding: 12px 4px 16px;
        }

        #rr-loading-spinner {
            margin: 20px auto;
        }

        /* Smooth fade-in for R&R content */
        .rr-container {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Task playback (Assignor / Assignee step-through - above filters) */
        .task-playback-group .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .task-playback-group .btn:not(:disabled):hover {
            background-color: #0d6efd !important;
            color: white !important;
        }
        .task-playback-group .btn:focus {
            outline: none;
            box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
        }
        .task-playback-group .btn.btn-primary {
            background-color: #0d6efd !important;
            color: white !important;
            border-color: #0d6efd;
        }
        /* Play/Pause buttons - larger and colored */
        .task-playback-group #task-play-auto-assignor,
        .task-playback-group #task-play-auto-assignee,
        .task-playback-group #task-play-pause-assignor,
        .task-playback-group #task-play-pause-assignee {
            width: 32px !important;
            height: 32px !important;
            background-color: #28a745 !important;
            color: white !important;
            border-color: #28a745 !important;
        }
        .task-playback-group #task-play-pause-assignor,
        .task-playback-group #task-play-pause-assignee {
            background-color: #ffc107 !important;
            border-color: #ffc107 !important;
        }
        .task-playback-group #task-play-auto-assignor:hover,
        .task-playback-group #task-play-auto-assignee:hover,
        .task-playback-group #task-play-pause-assignor:hover,
        .task-playback-group #task-play-pause-assignee:hover {
            background-color: #218838 !important;
            transform: scale(1.1);
        }
        .task-playback-group #task-play-pause-assignor:hover,
        .task-playback-group #task-play-pause-assignee:hover {
            background-color: #e0a800 !important;
        }
        /* Skip buttons - colored */
        .task-playback-group #task-play-backward-assignor:not(:disabled),
        .task-playback-group #task-play-backward-assignee:not(:disabled),
        .task-playback-group #task-play-forward-assignor:not(:disabled),
        .task-playback-group #task-play-forward-assignee:not(:disabled) {
            background-color: #6c757d !important;
            color: white !important;
            border-color: #6c757d !important;
        }
        .task-playback-group #task-play-backward-assignor:not(:disabled):hover,
        .task-playback-group #task-play-backward-assignee:not(:disabled):hover,
        .task-playback-group #task-play-forward-assignor:not(:disabled):hover,
        .task-playback-group #task-play-forward-assignee:not(:disabled):hover {
            background-color: #5a6268 !important;
        }
    
    /* Stat card clickable effects */
    .stat-card {
        cursor: pointer;
        position: relative;
    }
    
    /* Avatar hover effect */
    .task-avatar-hover {
        position: relative;
        z-index: 1;
    }
    
    .task-avatar-hover:hover {
        transform: scale(2.3);
        z-index: 9999;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.5);
        border: 2px solid white;
    }
    
    .stat-card::after {
        content: '\f201';
        font-family: 'Material Design Icons';
        position: absolute;
        top: 8px;
        right: 8px;
        font-size: 14px;
        opacity: 0.6;
    }
    
    /* History chart modal styling */
    #taskHistoryChartModal {
        z-index: 9999 !important;
    }
    
    #taskHistoryChartModal .modal-dialog {
        z-index: 10000 !important;
    }
    
    #taskHistoryChartModal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    }
    
    #taskHistoryChartModal .btn-outline-primary.active {
        background-color: #0d6efd;
        color: white;
    }

    /* Full-width, top-aligned chart modals */
    .modal-chart-wide {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
    }
    .modal-chart-wide .modal-content {
        border-radius: 0;
    }
    
    .modal-backdrop {
        z-index: 1040 !important;
        background-color: rgba(0, 0, 0, 0.5) !important;
    }
    
    .modal {
        z-index: 1050 !important;
    }
    
    .modal-dialog {
        z-index: 1055 !important;
    }
    
    .modal-content {
        z-index: 1060 !important;
    }

    /* Today Deleted modal — yellow row when deletion was made by the automated system */
    .task-auto-del-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #dc3545;
        cursor: help;
        vertical-align: middle;
        box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .task-auto-del-dot:hover {
        transform: scale(1.2);
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.35);
    }
    .task-auto-del-hit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 28px;
        cursor: help;
        vertical-align: middle;
    }
    #task-auto-del-float-tip {
        position: fixed;
        z-index: 99999;
        padding: 8px 12px;
        background: rgba(33, 37, 41, 0.96);
        color: #fff;
        font-size: 12px;
        line-height: 1.45;
        border-radius: 8px;
        max-width: 320px;
        pointer-events: none;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        display: none;
        white-space: normal;
        text-align: left;
    }

    #todayDeletedModal .today-deleted-row-auto > td {
        background-color: #fff3cd !important; /* Bootstrap "warning" subtle yellow */
        border-left: 3px solid #ffc107;
    }
    #todayDeletedModal .today-deleted-row-auto:hover > td {
        background-color: #ffeeba !important;
    }
    #todayDeletedModal .today-deleted-row-auto > td:first-child {
        border-left: 4px solid #ffc107 !important;
    }

    /* Today Deleted modal — green row when an automated task was completed (Done) and auto-archived */
    #todayDeletedModal .today-deleted-row-done > td {
        background-color: #d1e7dd !important; /* Bootstrap "success" subtle green */
        border-left: 3px solid #198754;
    }
    #todayDeletedModal .today-deleted-row-done:hover > td {
        background-color: #badbcc !important;
    }
    #todayDeletedModal .today-deleted-row-done > td:first-child {
        border-left: 4px solid #198754 !important;
    }

    </style>
@endsection

@section('content')
    <!-- Start Content-->
    <div class="container-fluid">
        
        <!-- start page title -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="page-title-box d-flex align-items-center justify-content-between py-1">
                    <div class="d-flex align-items-center gap-3">
                        <h4 class="page-title mb-0">Task Manager</h4>
                        <img src="{{ asset('assets/images/task-training-video-icon.png') }}"
                            alt="Training Video"
                            id="training-video-icon"
                            data-link="{{ $trainingVideoLink ?? '' }}"
                            data-can-edit="{{ !empty($canEditTrainingVideo) ? '1' : '0' }}"
                            title="Training Video"
                            style="width:38px;height:38px;object-fit:contain;cursor:pointer;border-radius:8px;transition:transform 0.15s ease;"
                            onmouseover="this.style.transform='scale(1.1)'"
                            onmouseout="this.style.transform='scale(1)'">
                    </div>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item active">Task Manager</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>     
        <!-- end page title --> 

        <!-- Statistics Cards (Hidden on mobile) - all in one line -->
        <div class="row mb-2 stats-row d-none d-md-flex align-items-stretch flex-nowrap" style="flex-wrap: nowrap !important;">
            <!-- Total Tasks -->
            <div class="col">
                <div class="stat-card stat-card-blue task-stat-trigger" data-metric="total" data-value="{{ $stats['total'] }}" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-format-list-bulleted"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">TOTAL</div>
                        <div class="stat-value">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Overdue Tasks -->
            <div class="col">
                <div class="stat-card stat-card-red task-stat-trigger" data-metric="overdue" data-value="{{ $stats['overdue'] }}" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-alert-circle"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">OVERDUE</div>
                        <div class="stat-value">{{ $stats['overdue'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Total ETC -->
            <div class="col">
                <div class="stat-card stat-card-yellow task-stat-trigger" data-metric="etc" data-value="{{ (int) round(($stats['etc_last_30'] ?? 0) / 60) }}" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-briefcase-clock"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">ETC 30D</div>
                        <div class="stat-value">{{ (int) round(($stats['etc_last_30'] ?? 0) / 60) }}h</div>
                        <div class="stat-unit" title="Last 30 days ETC including deleted tasks">hours</div>
                    </div>
                </div>
            </div>

            <!-- Total ATC -->
            <div class="col">
                <div class="stat-card stat-card-teal task-stat-trigger" data-metric="atc" data-value="{{ (int) round(($stats['atc_last_30'] ?? 0) / 60) }}" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-timer"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">ATC 30D</div>
                        <div class="stat-value">{{ (int) round(($stats['atc_last_30'] ?? 0) / 60) }}h</div>
                        <div class="stat-unit" title="Last 30 days ATC including deleted tasks">hours</div>
                    </div>
                </div>
            </div>

            <!-- TAT Badge -->
            <div class="col">
                <div class="stat-card stat-card-teal task-stat-trigger" data-metric="tat" data-value="{{ isset($stats['tat_avg_30']) && $stats['tat_avg_30'] !== null ? (int) round((float) $stats['tat_avg_30']) : 0 }}" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-clock-outline"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">TAT</div>
                        <div class="stat-value">{{ isset($stats['tat_avg_30']) && $stats['tat_avg_30'] !== null ? (int) round((float) $stats['tat_avg_30']) : '-' }}</div>
                        <div class="stat-unit" title="Average turnaround (days) for tasks completed in the last 30 days">30-day avg</div>
                    </div>
                </div>
            </div>

            <!-- Performance: average review score (5-point scale) -->
            <div class="col">
                <div class="stat-card stat-card-perf-score task-stat-trigger" data-metric="score" data-value="{{ isset($stats['average_score']) && $stats['average_score'] !== null ? $stats['average_score'] : 0 }}" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-chart-areaspline"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">AVG SCORE</div>
                        <div class="stat-value">{{ isset($stats['average_score']) && $stats['average_score'] !== null ? number_format($stats['average_score'], 2) : '-' }}</div>
                        <div class="stat-unit" title="Your average score from completed performance reviews (out of 5). Not affected by task filters.">Your avg / 5</div>
                    </div>
                </div>
            </div>

            <!-- Missed Badge -->
            <div class="col">
                <div class="stat-card stat-card-red-missed task-stat-trigger" data-metric="missed" data-value="{{ $stats['missed_count_30'] ?? 0 }}" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-alert-circle"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">MISSED</div>
                        <div class="stat-value">{{ $stats['missed_count_30'] ?? 0 }}</div>
                        <div class="stat-unit" title="Missed / not done tasks with start date in the last 30 days">Last 30 days</div>
                    </div>
                </div>
            </div>

            <!-- Pending ETC Badge -->
            <div class="col">
                <div class="stat-card stat-card-cyan task-stat-trigger" data-metric="pending_etc" data-value="0" title="Click to view history">
                    <div class="stat-icon">
                        <i class="mdi mdi-clock-alert-outline"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">PENDING ETC</div>
                        <div class="stat-value">0h</div>
                        <div class="stat-unit" title="Total ETC (estimated time) of pending tasks not yet Done or Archived">hours</div>
                    </div>
                </div>
            </div>

            <!-- Page ETC total (sum of ETC for tasks shown on this page) -->
            <div class="col">
                <div class="stat-card stat-card-info" title="Total ETC of tasks shown on this page">
                    <div class="stat-icon">
                        <i class="mdi mdi-clock-outline"></i>
                    </div>
                    <div class="stat-content text-center">
                        <div class="stat-label">ETC</div>
                        <div class="stat-value" id="etc-page-total">0m</div>
                        <div class="stat-unit">this page</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation - Prominent Location -->
        <div class="row mb-2">
            <div class="col-12">
                <div class="tabs-wrapper" style="background: #fff; padding: 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                    <ul class="nav nav-tabs" id="tasksRRTabs" role="tablist" style="border-bottom: 2px solid #e9ecef; margin: 0;">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks-content" type="button" role="tab" aria-controls="tasks-content" aria-selected="true">
                                <i class="mdi mdi-format-list-checks me-2"></i>Tasks
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="rr-tab" data-bs-toggle="tab" data-bs-target="#rr-content" type="button" role="tab" aria-controls="rr-content" aria-selected="false">
                                <i class="mdi mdi-account-tie me-2"></i>R&R
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card task-card">
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-sm-12">
                                <!-- Desktop Action Buttons -->
                                <div class="d-none d-md-flex justify-content-between align-items-center">
                                    <div>
                                        <button type="button" class="btn btn-success" id="upload-csv-btn">
                                            <i class="mdi mdi-upload me-2"></i> CSV
                                        </button>

                                        <button type="button" class="btn btn-primary ms-2" id="bulk-task-btn">
                                            <i class="mdi mdi-plus-box-multiple me-2"></i> Create Multi Task
                                        </button>
                                        
                                        <button type="button" class="btn btn-info ms-2" id="bulk-actions-btn">
                                            <i class="mdi mdi-format-list-checks me-2"></i> Bulk
                                        </button>

                                        <button type="button" class="btn btn-secondary ms-2" id="export-selected-btn">
                                            <i class="mdi mdi-download me-2"></i> Export Selected
                                        </button>

                                        <button type="button" class="btn btn-warning text-dark ms-2" id="tasks-refresh-table-btn" title="Reload tasks from server (keeps your filters)">
                                            <i class="mdi mdi-refresh"></i>
                                        </button>

                                        @if(!empty($canShowTaskMaintenanceButtons))
                                        <button type="button" class="btn btn-outline-danger ms-2" id="expire-daily-auto-btn"
                                            title="Auto-delete DAILY automated tasks not completed before the California business day ends. Runs at 12:05 AM {{ $taskBusinessTzShort ?? 'PT' }} each night. Weekly/monthly not affected.">
                                            <i class="mdi mdi-magnify me-2"></i> Missed
                                        </button>

                                        @endif


                                        <!-- Playback Controls - Assignor -->
                                        <div class="btn-group task-playback-group task-playback-assignor ms-2" role="group" aria-label="Assignor playback">
                                            <button type="button" id="task-play-backward-assignor" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px;" title="Previous assignor" disabled>
                                                <i class="mdi mdi-skip-previous" style="font-size:16px;"></i>
                                            </button>
                                            <button type="button" id="task-play-pause-assignor" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px; display:none;" title="Show all">
                                                <i class="mdi mdi-pause" style="font-size:16px;"></i>
                                            </button>
                                            <button type="button" id="task-play-auto-assignor" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px;" title="Step through assignors">
                                                <i class="mdi mdi-play" style="font-size:16px;"></i>
                                            </button>
                                            <button type="button" id="task-play-forward-assignor" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px;" title="Next assignor" disabled>
                                                <i class="mdi mdi-skip-next" style="font-size:16px;"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Playback Controls - Assignee -->
                                        <div class="btn-group task-playback-group task-playback-assignee ms-2" role="group" aria-label="Assignee playback">
                                            <button type="button" id="task-play-backward-assignee" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px;" title="Previous assignee" disabled>
                                                <i class="mdi mdi-skip-previous" style="font-size:16px;"></i>
                                            </button>
                                            <button type="button" id="task-play-pause-assignee" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px; display:none;" title="Show all">
                                                <i class="mdi mdi-pause" style="font-size:16px;"></i>
                                            </button>
                                            <button type="button" id="task-play-auto-assignee" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px;" title="Step through assignees">
                                                <i class="mdi mdi-play" style="font-size:16px;"></i>
                                            </button>
                                            <button type="button" id="task-play-forward-assignee" class="btn btn-light btn-sm rounded-circle p-0" style="width:32px;height:32px;" title="Next assignee" disabled>
                                                <i class="mdi mdi-skip-next" style="font-size:16px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <span id="selected-count" class="text-muted" style="display: none;">
                                            <strong id="count-number">0</strong> task(s) selected
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Mobile Action Buttons Grid -->
                                <div class="d-md-none mobile-action-buttons">
                                    <button type="button" class="mobile-action-btn btn-success" id="upload-csv-btn-mobile">
                                        <i class="mdi mdi-file-upload"></i>
                                        <span>CSV</span>
                                    </button>

                                    <button type="button" class="mobile-action-btn btn-primary" id="bulk-task-btn-mobile">
                                        <i class="mdi mdi-plus-box-multiple"></i>
                                        <span>Multiple Task</span>
                                    </button>
                                    
                                    <button type="button" class="mobile-action-btn btn-info" id="bulk-actions-btn-mobile">
                                        <i class="mdi mdi-format-list-checks"></i>
                                        <span>Bulk</span>
                                    </button>

                                    <button type="button" class="mobile-action-btn btn-secondary" id="export-selected-btn-mobile">
                                        <i class="mdi mdi-download"></i>
                                        <span>Export</span>
                                    </button>

                                    <button type="button" class="mobile-action-btn btn-warning text-dark" id="tasks-refresh-table-btn-mobile" title="Reload tasks (keeps filters)">
                                        <i class="mdi mdi-refresh"></i>
                                        <span>Refresh</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if(session('warning'))
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle me-2"></i>{{ session('warning') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <!-- Mobile Quick Filters (Only on mobile) -->
                        <div class="mobile-quick-filters d-md-none">
                            <div class="quick-filter-chip active" data-filter="all">
                                <i class="mdi mdi-view-list"></i> All
                            </div>
                            <div class="quick-filter-chip" data-filter="Todo">
                                <i class="mdi mdi-clock-outline"></i> Todo
                            </div>
                            <div class="quick-filter-chip" data-filter="Working">
                                <i class="mdi mdi-progress-clock"></i> Working
                            </div>
                            <div class="quick-filter-chip" data-filter="Done">
                                <i class="mdi mdi-check-circle"></i> Done
                            </div>
                            <div class="quick-filter-chip" data-filter="no_assignee" style="background: #fff5f5; border-color: #dc3545; color: #dc3545;">
                                <i class="mdi mdi-account-off"></i> No Assignee
                            </div>
                            <div class="quick-filter-chip" data-filter="no_assignor" style="background: #fff5f5; border-color: #dc3545; color: #dc3545;">
                                <i class="mdi mdi-account-cancel"></i> No Assignor
                            </div>
                            <div class="quick-filter-chip" data-filter="Need Help">
                                <i class="mdi mdi-help-circle"></i> Need Help
                            </div>
                            <div class="quick-filter-chip" data-filter="high">
                                <i class="mdi mdi-alert"></i> High
                            </div>
                        </div>

                        <!-- Search/Filter Bar -->
                        <div class="row g-2 mb-2 py-2 px-2 filter-section filter-section-eq align-items-center" style="background: #f8f9fa; border-radius: 8px;">
                            <!-- Desktop: All Filters -->
                            <div class="col-12 mb-2 d-none d-md-block">
                                <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Search" autocomplete="off" onkeydown="if(event.key === 'Enter') { event.preventDefault(); return false; }">
                            </div>
                            <div class="col-12 mb-2 d-none d-md-block">
                                <input type="text" id="filter-group" class="form-control form-control-sm" placeholder="Group" autocomplete="off" onkeydown="if(event.key === 'Enter') { event.preventDefault(); return false; }">
                            </div>
                            <div class="col-12 mb-2 d-none d-md-block">
                                <input type="text" id="filter-task" class="form-control form-control-sm" placeholder="Task" autocomplete="off" onkeydown="if(event.key === 'Enter') { event.preventDefault(); return false; }">
                            </div>
                            <div class="col-12 mb-2 d-none d-md-block">
                                <input type="date" id="filter-date" class="form-control form-control-sm" title="Filter by start date">
                            </div>
                            
                            <!-- Mobile & Desktop: Assignor / Assignee (searchable) -->
                            <div class="col-12 mb-2">
                                <select id="filter-assignor" class="form-select form-select-sm task-filter-user-select" title="Search by name or email">
                                    <option value=""></option>
                                    <option value="__NULL__" data-email="">No assignor</option>
                                    @if(isset($assignorOnTasksUsers) && $assignorOnTasksUsers->isNotEmpty())
                                        <optgroup label="On visible tasks">
                                            @foreach($assignorOnTasksUsers as $u)
                                                <option value="{{ $u->name }}" data-email="{{ $u->email }}">{{ $u->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if(isset($assignorOtherUsers) && $assignorOtherUsers->isNotEmpty())
                                        <optgroup label="All users">
                                            @foreach($assignorOtherUsers as $u)
                                                <option value="{{ $u->name }}" data-email="{{ $u->email }}">{{ $u->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <div class="col-12 mb-2">
                                <select id="filter-assignee" class="form-select form-select-sm task-filter-user-select" title="Search by name or email">
                                    <option value=""></option>
                                    <option value="__NULL__" data-email="">No assignee</option>
                                    @if(isset($assigneeOnTasksUsers) && $assigneeOnTasksUsers->isNotEmpty())
                                        <optgroup label="On visible tasks">
                                            @foreach($assigneeOnTasksUsers as $u)
                                                <option value="{{ $u->name }}" data-email="{{ $u->email }}" data-user-id="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                    @if(isset($assigneeOtherUsers) && $assigneeOtherUsers->isNotEmpty())
                                        <optgroup label="All users">
                                            @foreach($assigneeOtherUsers as $u)
                                                <option value="{{ $u->name }}" data-email="{{ $u->email }}" data-user-id="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </div>
                            <div class="col-12 mb-2">
                                <select id="filter-status" class="form-select form-select-sm">
                                    <option value="">Status</option>
                                    <option value="Todo">Todo</option>
                                    <option value="Working">Working</option>
                                    <option value="Done">Done</option>
                                    <option value="Archived">Archived</option>
                                    <option value="Need Help">Need Help</option>
                                    <option value="Need Approval">Need Approval</option>
                                    <option value="Dependent">Dependent</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Hold">Hold</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="Missed" style="color: #dc3545; font-weight: 600;">Missed</option>
                                </select>
                            </div>
                            
                            <!-- Desktop only: Priority -->
                            <div class="col-12 mb-2 d-none d-md-block">
                                <select id="filter-priority" class="form-select form-select-sm">
                                    <option value="">All Priority</option>
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

                        <!-- Tab Content -->
                        <div class="tab-content" id="tasksRRTabContent">
                            <!-- Tasks Tab -->
                            <div class="tab-pane fade show active" id="tasks-content" role="tabpanel" aria-labelledby="tasks-tab">
                                <div class="table-wrapper">
                                    <div id="tasks-table"></div>
                                </div>

                                <!-- Mobile Tasks View -->
                                <div id="mobile-tasks-view">
                                    <div class="pull-to-refresh-hint d-md-none">
                                        <i class="mdi mdi-chevron-down"></i> Pull down to refresh
                                    </div>
                                    
                                    <div id="mobile-tasks-container">
                                        <!-- Tasks will be loaded here via JavaScript -->
                                        <div class="mobile-loading">
                                            <div class="mobile-loading-spinner"></div>
                                            <p>Loading tasks...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- R&R Tab -->
                            <div class="tab-pane fade" id="rr-content" role="tabpanel" aria-labelledby="rr-tab">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Role & Responsibility</h5>
                                    <button type="button" class="btn btn-primary btn-sm" id="edit-rr-btn" style="display: none;">
                                        <i class="mdi mdi-pencil me-1"></i>Edit R&R
                                    </button>
                                </div>
                                <div id="rr-container">
                                    <!-- R&R content will be loaded here via AJAX -->
                                    <div class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status" id="rr-loading-spinner" style="display: none;">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="text-muted mt-3" id="rr-placeholder">Please select a user from the dropdown above to view their Role & Responsibility.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
        </div>
        <!-- end row -->

    </div> <!-- container -->
    
    <!-- History Chart Modal -->
    <div class="modal fade" id="taskHistoryChartModal" tabindex="-1" aria-labelledby="taskHistoryChartModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-chart-wide">
        <div class="modal-content">
          <div class="modal-header text-white">
            <h5 class="modal-title"><i class="mdi mdi-chart-line me-2"></i><span id="taskHistoryChartTitle">History Trend</span></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" style="min-height: 200px;">
            <div class="mb-3 d-flex justify-content-between align-items-center">
              <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-primary active" data-period="7">7 Days</button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-period="30">30 Days</button>
                <button type="button" class="btn btn-sm btn-outline-primary" data-period="90">90 Days</button>
              </div>
              <div class="text-muted small">
                <i class="mdi mdi-information-outline me-1"></i>Click and drag to zoom, double-click to reset
              </div>
            </div>
            <div style="height: 200px;">
              <canvas id="taskHistoryChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
@endsection

@section('modal')
<!-- View Task Modal -->
<div class="modal fade" id="viewTaskModal" tabindex="-1" aria-labelledby="viewTaskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="viewTaskModalLabel">
                    <i class="mdi mdi-file-document-outline me-2"></i>Task Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="task-details">
                <!-- Task details will be loaded here -->
            </div>
            <div class="modal-footer d-flex flex-wrap gap-2 justify-content-between">
                <button type="button" class="btn btn-outline-danger" id="view-task-rework-btn" style="display: none;" title="Send back to assignee for rework">
                    <i class="mdi mdi-undo-variant me-1"></i>Rework
                </button>
                <button type="button" class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Task Info Modal (Full Text) -->
<div class="modal fade" id="taskInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-text-box me-2"></i>Task Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="text-primary mb-3">Task Title:</h6>
                <p id="task-info-title" class="mb-4" style="font-size: 15px; line-height: 1.6;"></p>
                
                <h6 class="text-primary mb-3">Description:</h6>
                <p id="task-info-description" style="font-size: 14px; line-height: 1.6; white-space: pre-wrap;"></p>

                <div id="task-info-reason-wrap" class="mt-4" style="display: none;">
                    <h6 class="text-primary mb-3">Note / reason:</h6>
                    <p id="task-info-reason" style="font-size: 14px; line-height: 1.6; white-space: pre-wrap;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Done Modal -->
<div class="modal fade" id="doneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-check-circle me-2"></i>Mark Task as Done
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="done-modal-errors" class="alert alert-danger d-none mb-3" role="alert"></div>
                <div class="mb-3">
                    <label for="task-done-report" class="form-label">Report <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="task-done-report" rows="5" placeholder="Describe what was completed, outcomes, or notes for the assignor." required></textarea>
                    <div class="invalid-feedback d-none" id="task-done-report-feedback">Please enter a report.</div>
                </div>
                <div class="mb-3">
                    <label for="task-done-reference-link" class="form-label">Reference link <span class="text-muted fw-normal">(optional)</span></label>
                    <input type="text" class="form-control" id="task-done-reference-link" placeholder="https://…" autocomplete="off">
                    <small class="text-muted">Optional URL (e.g. doc, sheet, or ticket).</small>
                </div>
                <div class="mb-0">
                    <label for="task-done-atc" class="form-label">Actual time to complete (ATC) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="task-done-atc" min="1" max="9999999999" placeholder="Minutes, e.g. 45" autocomplete="off" required>
                    <small class="text-muted">Minutes you actually spent on this task (required).</small>
                    <div class="invalid-feedback d-none" id="task-done-atc-feedback">Please enter ATC in minutes (1–9999999999).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-done-btn">
                    <i class="mdi mdi-check me-1"></i>Submit &amp; mark Done
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Change Modal (for all other statuses) -->
<div class="modal fade" id="statusChangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-swap-horizontal me-2"></i>Change Task Status
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3"><strong>Please provide a reason for this status change:</strong></p>
                <div class="mb-3">
                    <label for="status-change-reason" class="form-label">Reason:</label>
                    <textarea class="form-control" id="status-change-reason" rows="4" placeholder="Why are you changing the status?" required></textarea>
                </div>
                <div class="alert alert-info">
                    <i class="mdi mdi-information me-2"></i>
                    Changing to: <strong id="new-status-label"></strong>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="confirm-status-change-btn">
                    <i class="mdi mdi-check me-1"></i>Confirm Change
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Today Deleted Modal — recover tasks deleted today (incl. auto-expired daily auto-tasks) -->
<!-- Training Video View Modal (80% of screen) -->
<div class="modal fade" id="trainingVideoModal" tabindex="-1" aria-labelledby="trainingVideoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:80vw;width:80vw;">
        <div class="modal-content" style="height:80vh;">
            <div class="modal-header" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white;">
                <h5 class="modal-title" id="trainingVideoModalLabel">
                    <i class="mdi mdi-school me-2"></i>Training Video
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="background:#000;">
                <div id="training-video-frame-wrap" style="width:100%;height:100%;"></div>
                <div id="training-video-empty" class="d-none d-flex flex-column align-items-center justify-content-center text-center text-white h-100 p-4">
                    <i class="mdi mdi-video-off-outline" style="font-size:48px;opacity:0.6;"></i>
                    <p class="mt-3 mb-0">No training video link has been set yet.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Training Video Edit Link Modal (editor only) -->
<div class="modal fade" id="trainingVideoEditModal" tabindex="-1" aria-labelledby="trainingVideoEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white;">
                <h5 class="modal-title" id="trainingVideoEditModalLabel">
                    <i class="mdi mdi-pencil me-2"></i>Edit Training Video Link
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="training-video-link-input" class="form-label">Video URL</label>
                <input type="url" class="form-control" id="training-video-link-input" placeholder="https://www.youtube.com/watch?v=...">
                <div class="form-text">Paste a YouTube, Vimeo, or direct video URL. Leave empty to remove.</div>
                <div class="invalid-feedback" id="training-video-link-feedback">Please enter a valid URL.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="training-video-save-btn">
                    <i class="mdi mdi-content-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="todayDeletedModal" tabindex="-1" aria-labelledby="todayDeletedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title" id="todayDeletedModalLabel">
                    <i class="mdi mdi-undo-variant me-2"></i>Today Deleted Tasks
                    <small class="ms-2 opacity-75" style="font-size: 0.75rem;">Recover anything that was deleted by mistake today</small>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="text-muted small">
                        <i class="mdi mdi-information-outline"></i>
                        Only today's deletions are shown ({{ $taskBusinessTzLabel ?? 'California (PT)' }}). After midnight office time, use Deleted/Archive.
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small text-muted d-inline-flex align-items-center">
                            <span style="display:inline-block; width:14px; height:14px; background:#fff3cd; border-left:3px solid #ffc107; margin-right:4px;"></span>
                            = Auto-deleted (missed)
                        </span>
                        <span class="small text-muted d-inline-flex align-items-center">
                            <span style="display:inline-block; width:14px; height:14px; background:#d1e7dd; border-left:3px solid #198754; margin-right:4px;"></span>
                            = Completed (Done)
                        </span>

                        <!-- Bulk revert: Selected (becomes active when at least 1 row is checked) -->
                        <button type="button" class="btn btn-sm btn-success" id="today-deleted-revert-selected-btn" disabled>
                            <i class="mdi mdi-undo-variant me-1"></i> Revert Selected (<span id="today-deleted-selected-count">0</span>)
                        </button>

                        <!-- Bulk revert: by category -->
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-warning text-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="mdi mdi-undo-variant me-1"></i> Bulk Revert
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" data-bulk-mode="auto">
                                        <i class="mdi mdi-robot text-warning me-1"></i>
                                        Revert <strong>Auto-Expired</strong> (<span class="today-deleted-auto-num">0</span>)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" data-bulk-mode="manual">
                                        <i class="mdi mdi-account me-1"></i>
                                        Revert <strong>Manual</strong> (<span class="today-deleted-manual-num">0</span>)
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="#" data-bulk-mode="all">
                                        <i class="mdi mdi-undo me-1"></i>
                                        Revert <strong>ALL</strong> (<span class="today-deleted-total-num">0</span>)
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <button type="button" class="btn btn-sm btn-outline-primary" id="today-deleted-refresh-btn">
                            <i class="mdi mdi-refresh me-1"></i> Refresh
                        </button>
                    </div>
                </div>

                <div id="today-deleted-loading" class="text-center py-4" style="display:none;">
                    <i class="mdi mdi-loading mdi-spin" style="font-size: 32px; color: #667eea;"></i>
                    <div class="text-muted mt-2">Loading today's deletions...</div>
                </div>

                <div id="today-deleted-empty" class="text-center py-4" style="display:none;">
                    <i class="mdi mdi-emoticon-happy-outline" style="font-size: 48px; color: #28a745;"></i>
                    <div class="text-muted mt-2">Nothing deleted today. Nothing to recover.</div>
                </div>

                <div id="today-deleted-table-wrap" style="display:none;">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 32px;">
                                        <input type="checkbox" id="today-deleted-select-all" class="form-check-input" title="Select all visible">
                                    </th>
                                    <th style="width: 90px;">Deleted At</th>
                                    <th>Title</th>
                                    <th style="width: 100px;">Type</th>
                                    <th style="width: 130px;">Assignor</th>
                                    <th style="width: 130px;">Assignee</th>
                                    <th style="width: 130px;">Deleted By</th>
                                    <th style="width: 100px;">Status</th>
                                    <th style="width: 110px;" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="today-deleted-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto text-muted small">
                    <strong><span id="today-deleted-count">0</span></strong> task(s) deleted today
                    <span id="today-deleted-breakdown" class="ms-1"></span>
                    <span id="today-deleted-truncated" class="ms-2 text-warning" style="display:none;">
                        <i class="mdi mdi-alert-circle-outline"></i>
                        Showing first <span id="today-deleted-returned">0</span> rows. Revert what you need then click Refresh to load more.
                    </span>
                </span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-format-list-checks me-2"></i>Bulk Actions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">
                    <i class="mdi mdi-information-outline me-2"></i>
                    <strong><span id="bulk-selected-count">0</span> task(s) selected</strong>
                </p>

                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-delete-btn">
                        <i class="mdi mdi-delete text-danger me-2"></i>
                        <strong>Delete Selected Tasks</strong>
                        <small class="d-block text-muted">{{ isset($canDeleteAnyTask) && $canDeleteAnyTask ? 'You can delete any selected task' : 'You can only delete tasks you created' }}</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-assign-assignee-btn">
                        <i class="mdi mdi-account-plus text-success me-2"></i>
                        <strong>Assign Assignee</strong>
                        <small class="d-block text-muted">Add assignee to tasks that have none</small>
                    </a>
                    @if($isAdmin)
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-assign-assignor-btn">
                        <i class="mdi mdi-account-star text-primary me-2"></i>
                        <strong>Assign Assignor</strong>
                        <small class="d-block text-muted">Add assignor to tasks that have none</small>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-priority-btn">
                        <i class="mdi mdi-flag text-warning me-2"></i>
                        <strong>Change Priority</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-tid-btn">
                        <i class="mdi mdi-calendar text-info me-2"></i>
                        <strong>Change TID Date</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-group-btn">
                        <i class="mdi mdi-folder-outline text-primary me-2"></i>
                        <strong>Change Group</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-task-title-btn">
                        <i class="mdi mdi-text text-secondary me-2"></i>
                        <strong>Change Task Title</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-assignee-btn">
                        <i class="mdi mdi-account-arrow-right text-success me-2"></i>
                        <strong>Change Assignee</strong>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" id="bulk-etc-btn">
                        <i class="mdi mdi-clock-outline text-primary me-2"></i>
                        <strong>Update ETC</strong>
                    </a>
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Form Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkUpdateModalTitle">Bulk Update</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bulkUpdateModalBody">
                <!-- Dynamic content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="confirm-bulk-update-btn">
                    <i class="mdi mdi-check me-1"></i>Update
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSV Upload Modal -->
<div class="modal fade" id="csvUploadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-file-upload me-2"></i>Upload Tasks via CSV
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="csv-upload-form" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="mdi mdi-information me-2"></i>CSV Format Required:</h6>
                        <p class="mb-1"><strong>Columns:</strong> Group, Task, Assignor, Assignee, Status, Priority, Image, L1, L2, SOP (hover: Training), Video, Form, Report (hover: Form report), CL (hover: Checklist), PL</p>
                        <p class="mb-1"><strong>Status Options:</strong> Todo, Working, Archived, Done, Need Help, Need Approval, Dependent, Approved, Hold, Cancelled</p>
                        <p class="mb-0"><strong>Priority Options:</strong> Low, Normal, High, Urgent</p>
                        <p class="mb-0"><small class="text-muted">Note: Assignor and Assignee should match exact user names in the system</small></p>
                    </div>

                    <div class="mb-3">
                        <label for="csv-file" class="form-label fw-bold">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv-file" name="csv_file" accept=".csv,.txt" required>
                    </div>

                    <div class="mb-3">
                        <a href="{{ route('tasks.downloadTemplate') }}" class="btn btn-sm btn-outline-primary">
                            <i class="mdi mdi-download me-1"></i> Download Sample CSV Template
                        </a>
                    </div>

                    <div id="upload-progress" style="display: none;">
                        <div class="progress mb-2">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                        </div>
                        <p class="text-center text-muted"><i class="mdi mdi-loading mdi-spin me-2"></i>Uploading and processing tasks...</p>
                    </div>

                    <div id="upload-result" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" id="upload-csv-submit">
                        <i class="mdi mdi-upload me-1"></i> Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Multiple Task Create Modal -->
<div class="modal fade" id="bulkTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-plus-box-multiple me-2"></i>Create Multiple Tasks
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('tasks.bulkStore') }}" method="POST" id="bulk-task-form">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="mdi mdi-information me-2"></i>
                        Enter one task title per line. All tasks will use the same assignee, priority, group, date, and link below.
                    </div>
                    <div class="mb-3">
                        <label for="bulk-task-titles" class="form-label fw-bold">Task titles <span class="text-danger">*</span></label>
                        <textarea class="form-control font-monospace" id="bulk-task-titles" name="titles" rows="8" placeholder="Task one&#10;Task two&#10;Task three" required></textarea>
                        <small class="text-muted">One task per line. Empty lines are ignored.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bulk-task-priority" class="form-label">Priority</label>
                            <select class="form-select" id="bulk-task-priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="normal" selected>Normal</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bulk-task-assignee" class="form-label">Assign to</label>
                            <select class="form-select" id="bulk-task-assignee" name="assignee_id">
                                <option value="">— Select assignee —</option>
                                @foreach($users ?? [] as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bulk-task-group" class="form-label">Group</label>
                            <input type="text" class="form-control" id="bulk-task-group" name="group" placeholder="Optional group name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="bulk-task-link1" class="form-label">Link (L1)</label>
                            <input type="url" class="form-control" id="bulk-task-link1" name="link1" placeholder="https://example.com">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="bulk-task-tid" class="form-label">TID / Start date</label>
                            <input type="datetime-local" class="form-control" id="bulk-task-tid" name="tid" value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="bulk-task-etc" class="form-label">ETC (min)</label>
                            <input type="number" class="form-control" id="bulk-task-etc" name="etc_minutes" value="10" min="1" placeholder="10">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-plus-box-multiple me-1"></i> Create tasks
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Assign Assignee Modal -->
<div class="modal fade" id="bulkAssigneeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-account-plus me-2"></i>Assign Assignee to Selected Tasks
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Select user(s) to assign to <span id="bulk-assignee-count">0</span> selected task(s):</strong></p>
                <div class="mb-3">
                    <label class="form-label">Select Assignee(s):</label>
                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 8px;">
                        @foreach($users ?? [] as $user)
                            <div class="form-check mb-2">
                                <input class="form-check-input bulk-assignee-checkbox" 
                                       type="checkbox" 
                                       value="{{ $user->email }}" 
                                       id="bulk_assignee_{{ $user->id }}">
                                <label class="form-check-label" for="bulk_assignee_{{ $user->id }}">
                                    {{ $user->name }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">Select one or more users to assign to all selected tasks</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" id="confirm-bulk-assignee-btn">
                    <i class="mdi mdi-check me-1"></i>Assign to Tasks
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Assign Assignor Modal (Admin Only) -->
<div class="modal fade" id="bulkAssignorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-account-star me-2"></i>Assign Assignor to Selected Tasks
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Select assignor for <span id="bulk-assignor-count">0</span> selected task(s):</strong></p>
                <div class="mb-3">
                    <label class="form-label">Select Assignor:</label>
                    <select class="form-select" id="bulk-assignor-select">
                        <option value="">Select User</option>
                        @foreach($users ?? [] as $user)
                            <option value="{{ $user->email }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">This user will be set as the creator of all selected tasks</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="confirm-bulk-assignor-btn">
                    <i class="mdi mdi-check me-1"></i>Assign as Assignor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- TAT Line Graph Modal -->
<div class="modal fade" id="tatChartModal" tabindex="-1" aria-labelledby="tatChartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-chart-wide">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tatChartModalLabel">
                    <i class="mdi mdi-chart-line me-2"></i>TAT – Last 30 Days (Avg days)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="height: 160px;">
                    <canvas id="tat-line-chart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Missed Line Graph Modal -->
<div class="modal fade" id="missedChartModal" tabindex="-1" aria-labelledby="missedChartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-chart-wide">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="missedChartModalLabel">
                    <i class="mdi mdi-chart-line me-2"></i>Missed Tasks – Last 30 Days (Count)
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div style="height: 160px;">
                    <canvas id="missed-line-chart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit R&R Modal -->
<div class="modal fade" id="editRRModal" tabindex="-1" aria-labelledby="editRRModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editRRModalLabel">
                    <i class="mdi mdi-account-tie me-2"></i>Edit Role & Responsibility
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rr-form">
                <div class="modal-body">
                    <input type="hidden" id="rr-user-id" name="user_id">
                    <div class="mb-3">
                        <label for="rr-content-editor" class="form-label fw-bold">Role & Responsibility Content</label>
                        <small class="text-muted d-block mb-2">You can copy-paste formatted content with images and icons directly into this editor.</small>
                        <div id="rr-content-editor" contenteditable="true" class="form-control" style="min-height: 400px; padding: 15px; overflow-y: auto; border: 1px solid #ced4da; border-radius: 0.375rem; background: white;">
                        </div>
                        <textarea id="rr-content-hidden" name="content" style="display: none;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="mdi mdi-content-save me-1"></i>Save R&R
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        var tatChartData = @json($tatChartData ?? []);
        var tatLineChart = null;
        var missedChartData = @json($missedChartData ?? []);
        var missedLineChart = null;
        /** From performance reviews (not recalculated from task filters) */
        var performanceAverageScore = @json($stats['average_score'] ?? null);

        // Clear user filter and reload page
        function clearUserFilter() {
            $.ajax({
                url: '{{ route("tasks.setSelectedUser") }}',
                method: 'POST',
                data: {
                    user_name: '',
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    window.location.reload();
                }
            });
        }

        $(document).ready(function() {
            var selectedTasks = [];
            var bulkActionType = '';
            var isAdmin = {{ $isAdmin ? 'true' : 'false' }};
            var canDeleteAnyTask = {{ isset($canDeleteAnyTask) && $canDeleteAnyTask ? 'true' : 'false' }};
            var currentUserId = {{ Auth::id() }};
            var currentUserEmail = {!! json_encode(Auth::user()->email) !!};
            var suppressAssignFilterApply = false;
            /** Set from session (e.g. Task Summary dot); OR filter assignor/assignee; cleared when assignee dropdown changes away from this name */
            var taskManagerSessionUserFocus = @json(trim((string) ($selectedUserName ?? '')));
            var taskBusinessToday = @json($taskBusinessToday ?? '');
            var taskBusinessTzShort = @json($taskBusinessTzShort ?? 'PT');
            var taskBusinessTzLabel = @json($taskBusinessTzLabel ?? 'California (PT)');

            function currentUserIsAssigneeOnTask(rowData) {
                if (!rowData) return false;
                if (rowData.assignee_ids && rowData.assignee_ids.length) {
                    for (var i = 0; i < rowData.assignee_ids.length; i++) {
                        if (parseInt(rowData.assignee_ids[i], 10) === currentUserId) {
                            return true;
                        }
                    }
                }
                if (rowData.assignee_id && parseInt(rowData.assignee_id, 10) === currentUserId) {
                    return true;
                }
                if (currentUserEmail && rowData.assignee_email) {
                    var parts = String(rowData.assignee_email).split(',').map(function (e) {
                        return e.trim().toLowerCase();
                    });
                    var me = String(currentUserEmail).trim().toLowerCase();
                    return parts.indexOf(me) !== -1;
                }
                return false;
            }
            var TASK_INDEX_FILTERS_KEY = 'taskManager.indexFilters.v1';

            function persistTaskIndexFilters() {
                try {
                    localStorage.setItem(TASK_INDEX_FILTERS_KEY, JSON.stringify({
                        search: $('#filter-search').val() || '',
                        group: $('#filter-group').val() || '',
                        task: $('#filter-task').val() || '',
                        date: $('#filter-date').val() || '',
                        assignor: $('#filter-assignor').val() || '',
                        assignee: $('#filter-assignee').val() || '',
                        status: $('#filter-status').val() || '',
                        priority: $('#filter-priority').val() || ''
                    }));
                } catch (e) { /* ignore quota / private mode */ }
            }

            function restoreTaskIndexFilters() {
                try {
                    var raw = localStorage.getItem(TASK_INDEX_FILTERS_KEY);
                    if (!raw) return;
                    var s = JSON.parse(raw);
                    if (!s || typeof s !== 'object') return;
                    $('#filter-search').val(s.search || '');
                    $('#filter-group').val(s.group || '');
                    $('#filter-task').val(s.task || '');
                    $('#filter-date').val(s.date || '');
                    $('#filter-status').val(s.status || '');
                    $('#filter-priority').val(s.priority || '');
                    suppressAssignFilterApply = true;
                    $('#filter-assignor').val(s.assignor != null ? s.assignor : '').trigger('change');
                    $('#filter-assignee').val(s.assignee != null ? s.assignee : '').trigger('change');
                    suppressAssignFilterApply = false;
                } catch (e) { /* ignore */ }
            }

            function taskFilterUserMatcher(params, data) {
                if ($.trim(params.term || '') === '') {
                    return data;
                }
                if (data.children && data.children.length > 0) {
                    var filteredChildren = [];
                    $.each(data.children, function (_i, child) {
                        var m = taskFilterUserMatcher(params, child);
                        if (m != null) {
                            filteredChildren.push(m);
                        }
                    });
                    if (filteredChildren.length) {
                        var mod = $.extend({}, data, true);
                        mod.children = filteredChildren;
                        return mod;
                    }
                    return null;
                }
                if (data.element === undefined) {
                    return null;
                }
                var term = String(params.term || '').toLowerCase();
                var text = String(data.text || '').toLowerCase();
                var email = String($(data.element).data('email') || '').toLowerCase();
                if (text.indexOf(term) > -1 || email.indexOf(term) > -1) {
                    return data;
                }
                return null;
            }

            $('#filter-assignor').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: 'Assignor',
                matcher: taskFilterUserMatcher
            });
            $('#filter-assignee').select2({
                theme: 'bootstrap-5',
                width: '100%',
                allowClear: true,
                placeholder: 'Assignee',
                matcher: taskFilterUserMatcher
            });

            function renderTatLineChart() {
                var ctx = document.getElementById('tat-line-chart');
                if (!ctx) return;
                if (tatLineChart) {
                    tatLineChart.destroy();
                    tatLineChart = null;
                }
                var labels = tatChartData.map(function(d) { return d.label; });
                var values = tatChartData.map(function(d) { return d.avg != null ? d.avg : null; });
                var tatNumeric = values.filter(function(v) { return v != null && !isNaN(v); });
                var tatMin = tatNumeric.length ? Math.min.apply(null, tatNumeric) : 0;
                var tatMax = tatNumeric.length ? Math.max.apply(null, tatNumeric) : 1;
                if (tatMin === tatMax) { tatMax = tatMin + 1; }
                tatLineChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Avg TAT (days)',
                            data: values,
                            borderColor: '#20c997',
                            backgroundColor: 'rgba(32, 201, 151, 0.1)',
                            fill: true,
                            tension: 0.2,
                            spanGaps: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        var v = ctx.raw;
                                        if (v == null) {
                                            return 'No data';
                                        }
                                        var n = Math.round(Number(v));
                                        return (isNaN(n) ? v : n) + ' days';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: true, title: { display: true, text: 'Date' } },
                            y: {
                                beginAtZero: false,
                                min: tatMin,
                                max: tatMax,
                                title: { display: true, text: 'TAT (days)' },
                                ticks: {
                                    stepSize: 1,
                                    callback: function(val) {
                                        return Math.round(Number(val));
                                    }
                                }
                            }
                        }
                    }
                });
            }

            function renderMissedLineChart() {
                var ctx = document.getElementById('missed-line-chart');
                if (!ctx) return;
                if (missedLineChart) {
                    missedLineChart.destroy();
                    missedLineChart = null;
                }
                var labels = missedChartData.map(function(d) { return d.label; });
                var values = missedChartData.map(function(d) { return d.count || 0; });
                var missedNumeric = values.filter(function(v) { return v != null && !isNaN(v); });
                var missedMin = missedNumeric.length ? Math.min.apply(null, missedNumeric) : 0;
                var missedMax = missedNumeric.length ? Math.max.apply(null, missedNumeric) : 1;
                if (missedMin === missedMax) { missedMax = missedMin + 1; }
                missedLineChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Missed Tasks (count)',
                            data: values,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            fill: true,
                            tension: 0.2,
                            spanGaps: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: true },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        var v = ctx.raw;
                                        return v + ' task' + (v != 1 ? 's' : '');
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { display: true, title: { display: true, text: 'Date' } },
                            y: {
                                beginAtZero: false,
                                min: missedMin,
                                max: missedMax,
                                title: { display: true, text: 'Count' },
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
            }
            
            /** Parse start_date / TID; label e.g. "3rd Apr", title "DD/MM/YYYY" for tooltip. */
            function addDaysYmd(ymd, days) {
                var p = String(ymd).split('-').map(Number);
                if (p.length < 3 || isNaN(p[0]) || isNaN(p[1]) || isNaN(p[2])) {
                    return ymd;
                }
                var d = new Date(p[0], p[1] - 1, p[2] + days);
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            }

            function isOverdueByBusinessTid(rowData) {
                if (!rowData || rowData.status === 'Archived' || rowData.status === 'Done') {
                    return false;
                }
                var bd = rowData.tid_business_date;
                if (!bd || !taskBusinessToday) {
                    return false;
                }
                return taskBusinessToday > addDaysYmd(bd, 1);
            }

            function formatTidFromBusinessDate(ymd) {
                if (!ymd) {
                    return null;
                }
                return formatTidCellParts(ymd + ' 12:00:00');
            }

            function escAttr(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function autoDeleteHoverText(row) {
                if (!row || !row.auto_delete_at_human) {
                    return '';
                }
                return String(row.auto_delete_at_human).replace(/\s*\(pending\)\s*$/i, '').trim();
            }

            var autoDelFloatTipEl = null;
            function showAutoDelFloatTip(text, clientX, clientY) {
                if (!text) {
                    return;
                }
                if (!autoDelFloatTipEl) {
                    autoDelFloatTipEl = document.createElement('div');
                    autoDelFloatTipEl.id = 'task-auto-del-float-tip';
                    document.body.appendChild(autoDelFloatTipEl);
                }
                autoDelFloatTipEl.textContent = text;
                autoDelFloatTipEl.style.display = 'block';
                autoDelFloatTipEl.style.left = Math.min(clientX + 14, window.innerWidth - 340) + 'px';
                autoDelFloatTipEl.style.top = Math.min(clientY + 14, window.innerHeight - 80) + 'px';
            }
            function hideAutoDelFloatTip() {
                if (autoDelFloatTipEl) {
                    autoDelFloatTipEl.style.display = 'none';
                }
            }

            $(document).on('mouseenter mousemove', '#tasks-table .task-auto-del-hit', function (e) {
                var text = this.getAttribute('data-tip') || '';
                showAutoDelFloatTip(text, e.clientX, e.clientY);
            });
            $(document).on('mouseleave', '#tasks-table .task-auto-del-hit', hideAutoDelFloatTip);

            function formatTidCellParts(value) {
                if (!value) {
                    return null;
                }
                var parts = String(value).trim().split(/[- T]/);
                var year = parseInt(parts[0], 10);
                var month = parseInt(parts[1], 10);
                var day = parseInt(parts[2], 10);
                if (isNaN(year) || isNaN(month) || isNaN(day) || month < 1 || month > 12) {
                    return null;
                }
                var mon = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                var j = day % 10;
                var k = day % 100;
                var ord = (j === 1 && k !== 11) ? day + 'st' : (j === 2 && k !== 12) ? day + 'nd' : (j === 3 && k !== 13) ? day + 'rd' : day + 'th';
                var label = ord + ' ' + mon[month - 1];
                var title = String(day).padStart(2, '0') + '/' + String(month).padStart(2, '0') + '/' + year;
                return { year: year, month: month, day: day, label: label, title: title };
            }

            // ==========================================
            // MOBILE TASK CARDS RENDERER
            // ==========================================
            function renderMobileTasks(tasks) {
                console.log('📱 renderMobileTasks called with', tasks.length, 'tasks');
                const container = $('#mobile-tasks-container');
                
                if (!container.length) {
                    console.error('❌ mobile-tasks-container not found!');
                    return;
                }
                
                if (!tasks || tasks.length === 0) {
                    console.log('No tasks to render');
                    container.html(`
                        <div class="mobile-empty-state">
                            <i class="mdi mdi-clipboard-text-outline"></i>
                            <h5>No Tasks Found</h5>
                            <p>Create your first task or adjust filters</p>
                        </div>
                    `);
                    return;
                }
                
                console.log('✓ Rendering', tasks.length, 'mobile task cards...');
                let html = '';
                
                try {
                    tasks.forEach(task => {
                    // OVERDUE BASED ON completion_day
                    let isOverdue = false;
                    let statusText = task.status;
                    
                    if (task.status !== 'Archived' && task.start_date && task.due_date) {
                        const startDate = new Date(task.start_date);
                        const dueDate = new Date(task.due_date);
                        const expectedDays = Math.ceil((dueDate - startDate) / (1000 * 60 * 60 * 24));
                        
                        if (task.completion_date && task.completion_date !== '0000-00-00' && task.completion_day) {
                            const actualDays = parseInt(task.completion_day);
                            isOverdue = actualDays > expectedDays;
                            if (isOverdue) {
                                statusText = `OVERDUE (${actualDays}/${expectedDays}d)`;
                            }
                        } else {
                            const now = new Date();
                            if (now > dueDate) {
                                isOverdue = true;
                                const daysLate = Math.ceil((now - dueDate) / (1000 * 60 * 60 * 24));
                                statusText = `OVERDUE ${daysLate}d`;
                            }
                        }
                    }
                    
                    const statusClass = isOverdue ? 'status-overdue' : `status-${task.status.toLowerCase().replace(' ', '')}`;
                    const priorityClass = `mobile-priority-${task.priority.toLowerCase()}`;
                    
                    // Status badge color - RED if overdue!
                    let statusBadge = isOverdue ? 'bg-danger text-white' : '';
                    
                    if (!isOverdue) {
                        switch(task.status) {
                        case 'Todo':
                            statusBadge = 'bg-primary text-white';
                            break;
                        case 'Working':
                            statusBadge = 'bg-warning text-dark';
                            break;
                        case 'Done':
                            statusBadge = 'bg-success text-white';
                            break;
                        case 'Need Help':
                            statusBadge = 'bg-danger text-white';
                            break;
                        case 'Need Approval':
                            statusBadge = 'bg-info text-white';
                            break;
                        case 'Approved':
                            statusBadge = 'bg-success text-white';
                            break;
                        case 'Hold':
                            statusBadge = 'bg-secondary text-white';
                            break;
                        case 'Cancelled':
                            statusBadge = 'bg-dark text-white';
                            break;
                        case 'Archived':
                            statusBadge = 'bg-secondary text-white';
                            break;
                        default:
                            statusBadge = 'bg-secondary text-white';
                        }
                    }
                    
                    html += `
                        <div class="mobile-task-card ${statusClass}">
                            <div class="mobile-task-header">
                                <div style="flex: 1;">
                                    <div class="mobile-task-title">${task.title || 'No Title'}</div>
                                    <div class="mobile-task-meta">
                                        <span class="badge ${statusBadge} mobile-task-badge">${statusText}</span>
                                        <span class="badge ${priorityClass} mobile-task-badge">${task.priority}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mobile-task-info">
                                <div><i class="mdi mdi-account-circle"></i> ${task.assignee_name || 'Unassigned'}</div>
                                ${(() => {
                                    var rawEtc = task.eta_time != null && task.eta_time !== '' ? task.eta_time : task.etc_minutes;
                                    var etcN = rawEtc != null && rawEtc !== '' ? Math.round(Number(rawEtc)) : NaN;
                                    var atcN = task.etc_done != null && task.etc_done !== '' ? Math.round(Number(task.etc_done)) : NaN;
                                    var parts = [];
                                    if (!isNaN(etcN)) {
                                        parts.push('<div><i class="mdi mdi-clock-outline"></i> ETC ' + etcN + ' min</div>');
                                    }
                                    if (task.status === 'Done' || (!isNaN(atcN) && atcN > 0)) {
                                        parts.push('<div><i class="mdi mdi-check-circle-outline"></i> ATC ' + (isNaN(atcN) ? 0 : atcN) + ' min</div>');
                                    }
                                    return parts.join('');
                                })()}
                                ${(() => {
                                    var tp = task.tid_business_date
                                        ? formatTidFromBusinessDate(task.tid_business_date)
                                        : formatTidCellParts(task.start_date || task.tid);
                                    return tp ? '<div><i class="mdi mdi-calendar"></i> ' + tp.label + ' <span class="text-muted">(' + taskBusinessTzShort + ')</span></div>' : '';
                                })()}
                                ${task.auto_delete_at_human ? (function() {
                                    var t = autoDeleteHoverText(task);
                                    return '<div><span class="task-auto-del-hit" data-tip="' + escAttr(t) + '"><span class="task-auto-del-dot"></span></span> <span class="text-muted small">Auto-del</span></div>';
                                })() : ''}
                            </div>
                            
                            <div class="mobile-task-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="viewTask(${task.id})">
                                    <i class="mdi mdi-eye"></i> View
                                </button>
                                ${task.status !== 'Done' ? `
                                    <button class="btn btn-sm btn-outline-success" onclick="markAsDone(${task.id})">
                                        <i class="mdi mdi-check"></i> Done
                                    </button>
                                ` : ''}
                                ${(() => {
                                    var st = task.status || '';
                                    var aid = task.assignor_id != null ? parseInt(task.assignor_id, 10) : NaN;
                                    var canRework = isAdmin || canDeleteAnyTask || (!isNaN(aid) && aid === currentUserId);
                                    if (!canRework || st === 'Rework' || st === 'Archived') return '';
                                    return '<button type="button" class="btn btn-sm btn-outline-danger" onclick="openReworkFromMobile(' + task.id + ')"><i class="mdi mdi-undo-variant"></i> Rework</button>';
                                })()}
                                <button class="btn btn-sm btn-outline-secondary" onclick="editTask(${task.id})">
                                    <i class="mdi mdi-pencil"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    });
                    
                    console.log('✓ Generated HTML for', tasks.length, 'cards');
                    console.log('HTML length:', html.length, 'characters');
                    
                    container.html(html);
                    console.log('✅ Mobile tasks rendered successfully!');
                    
                } catch (error) {
                    console.error('❌ Error rendering mobile tasks:', error);
                    console.error('Error stack:', error.stack);
                    container.html(`
                        <div class="alert alert-danger m-3 text-center">
                            <i class="mdi mdi-alert-circle" style="font-size: 48px;"></i>
                            <h5 class="mt-2">Error Loading Task Cards</h5>
                            <p><strong>${error.message}</strong></p>
                            <p class="small text-muted">${error.stack ? error.stack.split('\n')[0] : ''}</p>
                            <hr>
                            <button class="btn btn-danger btn-sm" onclick="console.log(this.error)">
                                <i class="mdi mdi-bug"></i> Show Error Details
                            </button>
                            <button class="btn btn-primary btn-sm" onclick="location.reload()">
                                <i class="mdi mdi-refresh"></i> Reload Page
                            </button>
                            <hr>
                            <small class="text-muted">
                                Open Console (F12) for full error details<br>
                                Or contact support with screenshot
                            </small>
                        </div>
                    `);
                }
            }
            
            // Helper functions for mobile actions
            window.viewTask = function(id) {
                const task = table.searchData('id', '=', id)[0];
                if (task) {
                    // Trigger existing view modal
                    $(`button[data-task-id="${id}"]`).first().click();
                }
            };

            window.openReworkFromMobile = function (id) {
                openReworkModalForTask(id);
            };
            
            window.markAsDone = function(id) {
                currentTaskId = id;
                $('#doneModal').modal('show');
            };
            
            window.editTask = function(id) {
                window.location.href = `/tasks/${id}/edit`;
            };
            
            // Pull to refresh for mobile
            if (window.innerWidth < 768) {
                let startY = 0;
                let pullDistance = 0;
                
                document.addEventListener('touchstart', function(e) {
                    if (window.scrollY === 0) {
                        startY = e.touches[0].clientY;
                    }
                });
                
                document.addEventListener('touchmove', function(e) {
                    if (startY > 0) {
                        pullDistance = e.touches[0].clientY - startY;
                        if (pullDistance > 80 && window.scrollY === 0) {
                            $('.pull-to-refresh-hint').html('<i class="mdi mdi-loading mdi-spin"></i> Release to refresh');
                        }
                    }
                });
                
                document.addEventListener('touchend', function(e) {
                    if (pullDistance > 80 && window.scrollY === 0) {
                        $('.pull-to-refresh-hint').html('<i class="mdi mdi-loading mdi-spin"></i> Refreshing...');
                        table.replaceData();
                        setTimeout(() => {
                            $('.pull-to-refresh-hint').html('<i class="mdi mdi-chevron-down"></i> Pull down to refresh');
                        }, 1000);
                    }
                    startY = 0;
                    pullDistance = 0;
                });
            }

            /** Rows on the current pagination page (Tabulator's getRows("visible") is scroll viewport only, not full page). */
            function getTaskTableRowsOnCurrentPage(tbl) {
                if (!tbl || typeof tbl.getRows !== 'function') {
                    return [];
                }
                var activeRows = tbl.getRows('active');
                var page = tbl.getPage();
                var size = tbl.getPageSize();
                if (page === false || page < 1 || !size || size < 1) {
                    return activeRows;
                }
                var start = (page - 1) * size;
                return activeRows.slice(start, Math.min(start + size, activeRows.length));
            }

            /** Named link column: custom icon for http(s) (full URL in title); "-" if empty or not a URL. */
            function formatNamedLinkSlot(rowData, getRawFn) {
                var v = String(getRawFn(rowData) || '').trim();
                if (!v) return '<span style="color:#adb5bd;">-</span>';
                var escAttr = function(t) {
                    return String(t || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                };
                if (/^https?:\/\//i.test(v)) {
                    return '<a href="' + escAttr(v) + '" target="_blank" rel="noopener noreferrer" title="' + escAttr(v) + '" ' +
                        'style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;line-height:1;" aria-label="Open link">' +
                        '<img src="{{ asset("assets/images/task-link-icon.png") }}" alt="Link" style="width:36px;height:36px;display:inline-block;" /></a>';
                }
                return '<span style="color:#adb5bd;" title="' + escAttr(v) + '">-</span>';
            }

            /** SOP link column: custom SOP icon for http(s) (full URL in title); "-" if empty or not a URL. */
            function formatSopLinkSlot(rowData, getRawFn) {
                var v = String(getRawFn(rowData) || '').trim();
                if (!v) return '<span style="color:#adb5bd;">-</span>';
                var escAttr = function(t) {
                    return String(t || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                };
                if (/^https?:\/\//i.test(v)) {
                    return '<a href="' + escAttr(v) + '" target="_blank" rel="noopener noreferrer" title="' + escAttr(v) + '" ' +
                        'style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;line-height:1;" aria-label="Open SOP link">' +
                        '<img src="{{ asset("assets/images/task-sop-icon.png") }}" alt="SOP" style="width:36px;height:36px;display:inline-block;" /></a>';
                }
                return '<span style="color:#adb5bd;" title="' + escAttr(v) + '">-</span>';
            }

            /** Video link column: custom video icon for http(s) (full URL in title); "-" if empty or not a URL. */
            function formatVideoLinkSlot(rowData, getRawFn) {
                var v = String(getRawFn(rowData) || '').trim();
                if (!v) return '<span style="color:#adb5bd;">-</span>';
                var escAttr = function(t) {
                    return String(t || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                };
                if (/^https?:\/\//i.test(v)) {
                    return '<a href="' + escAttr(v) + '" target="_blank" rel="noopener noreferrer" title="' + escAttr(v) + '" ' +
                        'style="text-decoration:none;display:inline-flex;align-items:center;justify-content:center;line-height:1;" aria-label="Open video link">' +
                        '<img src="{{ asset("assets/images/task-video-icon.png") }}" alt="Video" style="width:36px;height:36px;display:inline-block;" /></a>';
                }
                return '<span style="color:#adb5bd;" title="' + escAttr(v) + '">-</span>';
            }

            // Initialize Tabulator
            var table = new Tabulator("#tasks-table", {
                selectable: true, // All users can select rows for bulk actions
                defaultColumn: {
                    headerHozAlign: "center",
                },
                ajaxURL: "{{ route('tasks.data') }}",
                ajaxParams: {},
                ajaxContentType: "json",
                ajaxResponse: function(url, params, response) {
                    console.log('===== TASK MANAGER DEBUG =====');
                    console.log('Tasks loaded:', response.length);
                    console.log('Current User ID:', currentUserId);
                    console.log('Is Admin:', isAdmin);
                    
                    // Debug: Show unique status values
                    const uniqueStatuses = [...new Set(response.map(t => t.status))];
                    console.log('📊 Unique status values in data:', uniqueStatuses);
                    
                    // Debug: Show first 3 tasks with status
                    console.log('Sample tasks:', response.slice(0, 3).map(t => ({
                        id: t.id,
                        title: t.title?.substring(0, 30),
                        status: t.status
                    })));
                    console.log('==============================');
                    
                    // Render mobile view with error handling
                    if (window.innerWidth < 768) {
                        try {
                            renderMobileTasks(response);
                        } catch (error) {
                            console.error('❌ Mobile render failed:', error);
                            $('#mobile-tasks-container').html(`
                                <div class="alert alert-danger m-3">
                                    <h5><i class="mdi mdi-alert-circle"></i> Error Loading Tasks</h5>
                                    <p><strong>${error.message}</strong></p>
                                    <small>Check console (F12) for details</small>
                                    <hr>
                                    <button class="btn btn-primary btn-sm mt-2" onclick="location.reload()">
                                        <i class="mdi mdi-refresh"></i> Retry
                                    </button>
                                </div>
                            `);
                        }
                    }
                    
                    return response;
                },
                ajaxError: function(xhr, textStatus, errorThrown) {
                    console.error('❌ Failed to load tasks:', textStatus, errorThrown);
                    if (window.innerWidth < 768) {
                        $('#mobile-tasks-container').html(`
                            <div class="alert alert-danger m-3">
                                <h5><i class="mdi mdi-wifi-off"></i> Failed to Load Tasks</h5>
                                <p><strong>Error:</strong> ${textStatus}</p>
                                <p>${errorThrown || 'Network error or server issue'}</p>
                                <button class="btn btn-primary mt-2" onclick="table.replaceData()">
                                    <i class="mdi mdi-refresh"></i> Retry Loading
                                </button>
                            </div>
                        `);
                    }
                    return false;
                },
                rowFormatter: function(row) {
                    var data = row.getData();
                    
                    let isOverdue = isOverdueByBusinessTid(data);
                    
                    // Reset dynamic classes before applying current state
                    row.getElement().classList.remove('automated-task', 'alt', 'overdue-task');
                    row.getElement().style.backgroundColor = "";
                    row.getElement().style.borderLeft = "";

                    // Apply styling:
                    // - Automated tasks: always yellow (even if overdue)
                    // - Non-automated overdue tasks: red highlight
                    if (data.is_automate_task) {
                        row.getElement().classList.add('automated-task');
                        // Alternate shades among automated rows: yellow / light-yellow
                        var automatedRowsBefore = row.getTable().getRows("active").filter(function(r) {
                            var d = r.getData();
                            return !!d && !!d.is_automate_task;
                        });
                        var automatedIndex = automatedRowsBefore.findIndex(function(r) {
                            return r.getData().id === data.id;
                        });
                        if (automatedIndex % 2 === 1) {
                            row.getElement().classList.add('alt');
                        }
                        row.getElement().style.borderLeft = "4px solid #ffc107";
                    } else if (isOverdue) {
                        row.getElement().style.backgroundColor = "#ffe5e5";
                        row.getElement().style.borderLeft = "4px solid #dc3545";
                        row.getElement().classList.add('overdue-task');
                    } else {
                        // Keep default styling for non-automated rows
                    }
                },
                layout: "fitColumns",
                pagination: true,
                paginationSize: 100,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: "rows",
                langs: { default: { pagination: { page_size: "Rows" } } },
                responsiveLayout: false,
                placeholder: "No Tasks Found",
                height: "calc(100vh - 220px)",
                layoutColumnsOnNewData: true,
                autoResize: true,
                initialSort: [
                    {column: "start_date", dir: "asc"},
                    {column: "is_automate_task", dir: "desc"}
                ],
                columns: (function() {
                    var cols = [];

                    // Add checkbox column — header selects all rows on the current pagination page (not scroll viewport, not all pages)
                    cols.push({
                        formatter: "rowSelection", 
                        titleFormatter: function(cell, formatterParams, onRendered) {
                            const checkbox = document.createElement("input");
                            checkbox.type = "checkbox";
                            checkbox.style.margin = "0";
                            checkbox.style.cursor = "pointer";
                            
                            checkbox.addEventListener("click", function(e) {
                                e.stopPropagation();
                                const tbl = cell.getTable();
                                const pageRows = getTaskTableRowsOnCurrentPage(tbl);
                                
                                if (checkbox.checked) {
                                    pageRows.forEach(row => row.select());
                                } else {
                                    pageRows.forEach(row => row.deselect());
                                }
                            });
                            
                            return checkbox;
                        },
                        hozAlign: "center", 
                        headerSort: false, 
                        width: 60,
                        cellClick: function(e, cell) {
                            cell.getRow().toggleSelect();
                        }
                    });
                    
                    // Hidden column for sorting: automated tasks first within same day
                    cols.push({
                        title: "",
                        field: "is_automate_task",
                        width: 1,
                        minWidth: 1,
                        visible: false,
                        sorter: "number"
                    });
                    
                    // Column Order: GROUP, TASK, ASSIGNOR, ASSIGNEE, TID, ETC, ATC, L1, L2, SOP, Video, Form, Report, CL, PL, STATUS, P (Priority), IMAGE, ACTION
                    
                    // GROUP
                    cols.push({
                        title: "GROUP", 
                        field: "group", 
                        widthGrow: 1,
                        minWidth: 80,
                        hozAlign: "left",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<span style="color: #6c757d;">' + value + '</span>' : '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // TASK (2 lines with proper wrapping)
                    cols.push({
                        title: "TASK", 
                        field: "title", 
                        widthGrow: 3,
                        minWidth: 200,
                        hozAlign: "left",
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var title = cell.getValue() || '';
                            
                            // Remove [Auto: DD-MMM-YY] suffix from automated task titles
                            title = title.replace(/\s*\[Auto:\s*\d{1,2}-[A-Za-z]{3}-\d{2}\]\s*$/i, '');
                            
                            var htmlTitle = String(title).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                            
                            // Show full text with auto wrapping (no line limit)
                            return '<div style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal; line-height: 1.4; text-align: left;">' + 
                                   '<strong style="font-size: 13px;">' + htmlTitle + '</strong>' + 
                                   '</div>';
                        }
                    });
                    
                    // ASSIGNOR (avatar + first name)
                    cols.push({
                        title: "ASSIGNOR",
                        field: "assignor_name",
                        width: 120,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var firstName = value.trim().split(' ')[0];
                                var imgSrc = (row.assignor_avatar || "{{ asset('images/users/avatar-2.jpg') }}").replace(/&/g, '&amp;');
                                var nameEsc = String(firstName).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                var designation = row.assignor_designation || '';
                                var designationAttr = designation ? ' title="' + String(designation).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '"' : '';
                                return '<div class="d-flex align-items-center justify-content-center gap-2 flex-nowrap"' + designationAttr + '>' +
                                    '<img src="' + imgSrc + '" alt="" class="rounded-circle task-avatar-hover" style="width:24px;height:24px;object-fit:cover;flex-shrink:0;transition:all 0.2s ease;cursor:pointer;">' +
                                    '<div style="text-align: left;"><strong style="font-size: 11px;">' + nameEsc + '</strong></div>' +
                                    '</div>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });

                    // ASSIGNEE (avatar + name, limited to 12 chars)
                    cols.push({
                        title: "ASSIGNEE",
                        field: "assignee_name",
                        width: 140,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var displayValue = value.length > 12 ? value.substring(0, 12) + '...' : value;
                                var imgSrc = (row.assignee_avatar || "{{ asset('images/users/avatar-2.jpg') }}").replace(/&/g, '&amp;');
                                var nameEsc = String(displayValue).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                var designation = row.assignee_designation || '';
                                var designationAttr = designation ? ' title="' + String(designation).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '"' : '';
                                return '<div class="d-flex align-items-center justify-content-center gap-2 flex-nowrap"' + designationAttr + '>' +
                                    '<img src="' + imgSrc + '" alt="" class="rounded-circle task-avatar-hover" style="width:24px;height:24px;object-fit:cover;flex-shrink:0;transition:all 0.2s ease;cursor:pointer;">' +
                                    '<div style="text-align: left;"><strong style="font-size: 11px; line-height: 1.4;">' + nameEsc + '</strong></div>' +
                                    '</div>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        },
                        tooltip: function(cell) {
                            var row = cell.getRow().getData();
                            return row.assignee_designation || '';
                        }
                    });
                    
                    // TID (Task Initiation Date) — display e.g. "3rd Apr"; title shows DD/MM/YYYY
                    cols.push({
                        title: "TID", 
                        field: "start_date", 
                        width: 88,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var value = cell.getValue();
                            
                            if (value || rowData.tid_business_date) {
                                var fp = rowData.tid_business_date
                                    ? formatTidFromBusinessDate(rowData.tid_business_date)
                                    : formatTidCellParts(value);
                                if (!fp) {
                                    return '<span style="color: #adb5bd;">-</span>';
                                }
                                var label = fp.label;
                                var titleFull = fp.title + ' (' + taskBusinessTzShort + ')';
                                var textColor = isOverdueByBusinessTid(rowData) ? '#dc3545' : '#0d6efd';
                                return '<span style="color: ' + textColor + '; font-weight: 600; font-size: 11px;" title="' + titleFull + '">' + label + '</span>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });

                    // AUTO DEL — red dot; full time on hover (daily auto-tasks)
                    cols.push({
                        title: "AUTO DEL",
                        titleFormatter: function() {
                            return '<span title="Auto-delete (daily auto tasks) — hover row dot for time" style="font-weight:700;font-size:calc(11px * 0.9);color:#495057;">AUTO DEL</span>';
                        },
                        headerTooltip: "Daily automated tasks auto-delete at 12:05 AM {{ $taskBusinessTzShort ?? 'PT' }} the day after TID if not Done. Hover the dot for time.",
                        field: "auto_delete_at_human",
                        width: 52,
                        minWidth: 44,
                        widthGrow: 0,
                        cssClass: "tasks-col-auto-del",
                        headerClass: "tasks-col-auto-del",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var row = cell.getRow().getData();
                            var hoverText = autoDeleteHoverText(row);
                            if (!hoverText) {
                                return '<span style="color: #adb5bd;">-</span>';
                            }
                            return '<span class="task-auto-del-hit" data-tip="' + escAttr(hoverText) + '" role="img" aria-label="' + escAttr(hoverText) + '">' +
                                '<span class="task-auto-del-dot"></span></span>';
                        },
                        tooltip: function (cell) {
                            return autoDeleteHoverText(cell.getRow().getData()) || false;
                        }
                    });
                    
                    // ETC (Estimated Time) — whole minutes
                    cols.push({
                        title: "ETC",
                        field: "eta_time", 
                        width: 52,
                        minWidth: 46,
                        widthGrow: 0,
                        cssClass: "tasks-col-time-compact",
                        headerClass: "tasks-col-time-compact",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value !== null && value !== undefined && value !== '') {
                                var n = Math.round(Number(value));
                                if (!isNaN(n)) {
                                    return '<span style="font-size: 11px;" title="' + n + ' min">' + n + '</span>';
                                }
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ATC (Actual Time) — whole minutes
                    cols.push({
                        title: "ATC",
                        field: "etc_done", 
                        visible: false,
                        width: 52,
                        minWidth: 46,
                        widthGrow: 0,
                        cssClass: "tasks-col-time-compact",
                        headerClass: "tasks-col-time-compact",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value !== null && value !== undefined && value !== '') {
                                var n = Math.round(Number(value));
                                if (!isNaN(n) && n > 0) {
                                    return '<strong style="color: #28a745; font-size: 11px;" title="' + n + ' min">' + n + '</strong>';
                                }
                            }
                            return '<span style="color: #adb5bd;">0</span>';
                        }
                    });

                    // Named link columns (DB link1–link8 + legacy aliases)
                    var linkCol = function(title, field, getRaw, w, opts) {
                        opts = opts || {};
                        var def = {
                            title: title,
                            field: field,
                            width: w || 40,
                            minWidth: opts.minWidth != null ? opts.minWidth : 34,
                            widthGrow: 0,
                            cssClass: "tasks-col-link-icon",
                            headerClass: "tasks-col-link-icon",
                            hozAlign: "center",
                            formatter: function(cell) {
                                return formatNamedLinkSlot(cell.getRow().getData(), getRaw);
                            }
                        };
                        if (opts.titleFormatter) {
                            def.titleFormatter = opts.titleFormatter;
                        }
                        if (opts.headerTooltip) {
                            def.headerTooltip = opts.headerTooltip;
                        }
                        cols.push(def);
                    };
                    linkCol("L1", "link1", function(d) { return d.link1 || d.l1; }, 38);
                    linkCol("L2", "link2", function(d) { return d.link2 || d.l2; }, 38);
                    
                    // SOP - Custom icon
                    cols.push({
                        title: '<img src="{{ asset("assets/images/task-sop-icon.png") }}" alt="SOP" style="width:22px;height:22px;display:inline-block;vertical-align:middle;" />',
                        field: "link3",
                        width: 48,
                        minWidth: 40,
                        widthGrow: 0,
                        cssClass: "tasks-col-link-icon",
                        headerClass: "tasks-col-link-icon",
                        hozAlign: "center",
                        headerTooltip: "Standard Operating Procedure",
                        formatter: function(cell) {
                            return formatSopLinkSlot(cell.getRow().getData(), function(d) { return d.link3 || d.training_link; });
                        }
                    });
                    
                    // Video - Custom icon
                    cols.push({
                        title: '<img src="{{ asset("assets/images/task-video-icon.png") }}" alt="Video" style="width:22px;height:22px;display:inline-block;vertical-align:middle;" />',
                        field: "link4",
                        width: 44,
                        minWidth: 34,
                        widthGrow: 0,
                        cssClass: "tasks-col-link-icon",
                        headerClass: "tasks-col-link-icon",
                        hozAlign: "center",
                        headerTooltip: "Video",
                        formatter: function(cell) {
                            return formatVideoLinkSlot(cell.getRow().getData(), function(d) { return d.link4 || d.video_link; });
                        }
                    });
                    
                    linkCol("Form", "link5", function(d) { return d.link5 || d.form_link; }, 44);
                    linkCol("Report", "link6", function(d) { return d.link6 || d.form_report_link; }, 50, { minWidth: 44, headerTooltip: "Form report" });
                    linkCol("CL", "link7", function(d) { return d.link7 || d.checklist_link; }, 38, {
                        titleFormatter: function() {
                            return '<span title="Checklist" style="font-weight:700;font-size:10.8px;color:#495057;">CL</span>';
                        },
                        headerTooltip: "Checklist"
                    });
                    linkCol("PL", "link8", function(d) { return d.link8 || d.pl; }, 36);
                    
                    // STATUS
                    cols.push({
                        title: "STATUS", 
                        field: "status", 
                        width: 100,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var value = cell.getValue();
                            var taskId = rowData.id;
                            var assignorId = rowData.assignor_id;
                            
                            // Status badge always uses status color (not red for overdue)
                            var displayText = value;
                            
                            // Check if user can update status
                            var canUpdateStatus = isAdmin || assignorId === currentUserId || currentUserIsAssigneeOnTask(rowData);
                            
                            var statuses = {
                                'Todo': {bg: '#0dcaf0', text: '#000'},
                                'Working': {bg: '#ffc107', text: '#000'},
                                'Archived': {bg: '#6c757d', text: '#fff'},
                                'Done': {bg: '#28a745', text: '#fff'},
                                'Need Help': {bg: '#fd7e14', text: '#000'},
                                'Need Approval': {bg: '#6610f2', text: '#fff'},
                                'Dependent': {bg: '#d63384', text: '#fff'},
                                'Approved': {bg: '#20c997', text: '#000'},
                                'Hold': {bg: '#495057', text: '#fff'},
                                'Rework': {bg: '#f5576c', text: '#fff'}
                            };
                            var currentStatus = statuses[value] || {bg: '#6c757d', text: '#fff'};
                            
                            if (!canUpdateStatus) {
                                return '<span style="background: ' + currentStatus.bg + '; color: ' + currentStatus.text + '; padding: 6px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: inline-block;">' + displayText + '</span>';
                            }
                            
                            return `
                                <select class="form-select form-select-sm status-select" 
                                        data-task-id="${taskId}" 
                                        data-current-status="${value}"
                                        style="background: ${currentStatus.bg}; color: ${currentStatus.text}; border: none; font-weight: 700; font-size: 11px; border-radius: 20px; padding: 6px 12px;">
                                    <option value="Todo" ${value === 'Todo' ? 'selected' : ''}>Todo</option>
                                    <option value="Working" ${value === 'Working' ? 'selected' : ''}>Working</option>
                                    <option value="Archived" ${value === 'Archived' ? 'selected' : ''}>Archived</option>
                                    <option value="Done" ${value === 'Done' ? 'selected' : ''}>Done</option>
                                    <option value="Need Help" ${value === 'Need Help' ? 'selected' : ''}>Need Help</option>
                                    <option value="Need Approval" ${value === 'Need Approval' ? 'selected' : ''}>Need Approval</option>
                                    <option value="Dependent" ${value === 'Dependent' ? 'selected' : ''}>Dependent</option>
                                    <option value="Approved" ${value === 'Approved' ? 'selected' : ''}>Approved</option>
                                    <option value="Hold" ${value === 'Hold' ? 'selected' : ''}>Hold</option>
                                    <option value="Rework" ${value === 'Rework' ? 'selected' : ''}>Rework</option>
                                </select>
                            `;
                        }
                    });
                    
                    // P = Priority (colored dots; hover header for full name)
                    cols.push({
                        title: "P",
                        titleFormatter: function() {
                            return '<span title="Priority" style="font-weight:700;font-size:calc(13px * 0.9);color:#495057;">P</span>';
                        },
                        headerTooltip: "Priority",
                        field: "priority", 
                        width: 44,
                        minWidth: 40,
                        widthGrow: 0,
                        cssClass: "tasks-col-priority-compact",
                        headerClass: "tasks-col-priority-compact",
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue() || 'normal';
                            var dotColors = {
                                'low': '#9ca3af',        // Gray
                                'normal': '#fbbf24',     // Yellow
                                'high': '#7c3aed',       // Purple
                                'urgent': '#ef4444'      // Red
                            };
                            var color = dotColors[value.toLowerCase()] || dotColors['normal'];
                            return '<div style="display: flex; align-items: center; justify-content: center;">' +
                                   '<span style="display: inline-block; width: 16px; height: 16px; border-radius: 50%; background: ' + color + '; box-shadow: 0 2px 4px rgba(0,0,0,0.2);" title="' + value.toUpperCase() + '"></span>' +
                                   '</div>';
                        }
                    });
                    
                    // IMG (Screenshot/Image) - Hidden
                    cols.push({
                        title: "IMG", 
                        field: "image", 
                        width: 60,
                        hozAlign: "center",
                        visible: false,
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value) {
                                return `<a href="/uploads/tasks/${value}" target="_blank" title="View Screenshot">
                                    <i class="mdi mdi-camera text-primary" style="font-size: 18px; cursor: pointer;"></i>
                                </a>`;
                            }
                            return '-';
                        }
                    });
                    
                    // ACTION
                    cols.push({
                        title: "ACTION", 
                        field: "id", 
                        width: 176,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var id = rowData.id;
                            var assignorId = rowData.assignor_id;
                            var st = rowData.status || '';
                            
                            // Determine permissions (special: Jasmine, Ritu mam, Joy sir can delete/edit any task)
                            var canEdit = isAdmin || canDeleteAnyTask || assignorId === currentUserId;
                            var canDelete = canDeleteAnyTask || assignorId === currentUserId;
                            var canView = isAdmin || assignorId === currentUserId || currentUserIsAssigneeOnTask(rowData);
                            var canReworkQuick = (isAdmin || canDeleteAnyTask || assignorId === currentUserId) && st !== 'Rework' && st !== 'Archived';
                            
                            var buttons = '';
                            
                            if (canEdit) {
                                buttons += `
                                    <button class="action-btn-icon action-btn-edit edit-task" data-id="${id}" title="Edit">
                                        <i class="mdi mdi-pencil"></i>
                                    </button>
                                `;
                            }
                            
                            // Always show delete button for symmetry, but disable when no permission
                            if (canDelete) {
                                buttons += `
                                    <button class="action-btn-icon action-btn-delete delete-task" data-id="${id}" title="Delete">
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                `;
                            } else {
                                buttons += `
                                    <button class="action-btn-icon action-btn-delete-disabled" 
                                            title="Only task creator can delete"
                                            disabled>
                                        <i class="mdi mdi-delete"></i>
                                    </button>
                                `;
                            }
                            
                            return '<div style="white-space: nowrap;">' + buttons + '</div>';
                        }
                    });
                    
                    return cols;
                })(),
            });

            // Format a minute total as "Xh Ym" (e.g. 5392 -> "89h 52m"). Under an hour -> "Ym".
            function formatMinutesAsHm(totalMinutes) {
                var mins = Math.round(Number(totalMinutes) || 0);
                var hours = Math.floor(mins / 60);
                var rem = mins % 60;
                if (hours > 0) {
                    return hours.toLocaleString() + 'h ' + rem + 'm';
                }
                return rem + 'm';
            }

            // Update statistics based on filtered data
            function updateStatistics() {
                var filteredData = table.getData("active");
                
                var stats = {
                    total: filteredData.length,
                    pending: filteredData.filter(t => t.status === 'Todo').length,
                    done: filteredData.filter(t => t.status === 'Done').length,
                    etc_total: filteredData.reduce((sum, t) => sum + (parseInt(t.eta_time) || 0), 0),
                    atc_total: filteredData.reduce((sum, t) => sum + (parseInt(t.etc_done) || 0), 0),
                    done_etc: filteredData.filter(t => t.status === 'Done').reduce((sum, t) => sum + (parseInt(t.eta_time) || 0), 0),
                    done_atc: filteredData.filter(t => t.status === 'Done').reduce((sum, t) => sum + (parseInt(t.etc_done) || 0), 0),
                    pending_etc: filteredData.filter(t => !['Done', 'Archived'].includes(t.status)).reduce((sum, t) => sum + (parseInt(t.eta_time) || 0), 0)
                };
                
                // Overdue calculation:
                // Task is overdue when TID/start_date + 1 day has already passed and it's not archived.
                var now = new Date();
                stats.overdue = filteredData.filter(function(t) {
                    if (t.start_date && t.status !== 'Archived') {
                        var tidDate = new Date(t.start_date);
                        if (isNaN(tidDate.getTime())) return false;
                        tidDate.setHours(0, 0, 0, 0);

                        // Give all tasks a 1-day grace before overdue.
                        tidDate.setDate(tidDate.getDate() + 1);

                        var current = new Date(now);
                        current.setHours(0, 0, 0, 0);
                        return current > tidDate;
                    }
                    return false;
                }).length;
                
                // TAT calculation: Average days from start_date to completion_date for Done tasks completed in last 30 days
                var thirtyDaysAgo = new Date();
                thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
                thirtyDaysAgo.setHours(0, 0, 0, 0); // Set to start of day
                
                var last30DoneTasks = filteredData.filter(function(t) {
                    if (t.status !== 'Done' || !t.start_date) return false;
                    
                    // Get completion date (prefer completion_date, fallback to updated_at)
                    var completionDate = null;
                    if (t.completion_date && t.completion_date !== '0000-00-00' && t.completion_date !== null) {
                        completionDate = new Date(t.completion_date);
                    } else if (t.updated_at) {
                        completionDate = new Date(t.updated_at);
                    }
                    
                    if (!completionDate || isNaN(completionDate.getTime())) return false;
                    completionDate.setHours(0, 0, 0, 0);
                    
                    return completionDate >= thirtyDaysAgo;
                });

                // ETC 30D / ATC 30D badges are fetched from deleted_tasks via AJAX.
                
                var tatValues = [];
                last30DoneTasks.forEach(function(t) {
                    var start = new Date(t.start_date);
                    if (isNaN(start.getTime())) return;
                    start.setHours(0, 0, 0, 0);
                    
                    var completion = null;
                    if (t.completion_date && t.completion_date !== '0000-00-00' && t.completion_date !== null) {
                        completion = new Date(t.completion_date);
                    } else if (t.updated_at) {
                        completion = new Date(t.updated_at);
                    } else {
                        completion = start; // Fallback to start date
                    }
                    
                    if (isNaN(completion.getTime())) return;
                    completion.setHours(0, 0, 0, 0);
                    
                    var days = Math.abs(completion.getTime() - start.getTime()) / (1000 * 60 * 60 * 24);
                    tatValues.push(Math.round(days));
                });
                
                stats.tat_avg_30 = tatValues.length > 0
                    ? Math.round(tatValues.reduce((a, b) => a + b, 0) / tatValues.length)
                    : null;
                
                // MISSED calculation: Count of tasks with start_date in last 30 days that are not Done/Archived
                // Matches server logic: start_date >= 30 days ago AND status NOT IN ('Done', 'Archived')
                var missedTasks = filteredData.filter(function(t) {
                    if (!t.start_date) return false;
                    
                    var startDate = new Date(t.start_date);
                    if (isNaN(startDate.getTime())) return false;
                    startDate.setHours(0, 0, 0, 0);
                    
                    // Must have start_date in last 30 days
                    if (startDate < thirtyDaysAgo) return false;
                    
                    // Status must NOT be Done or Archived
                    if (['Done', 'Archived'].includes(t.status)) {
                        return false;
                    }
                    
                    // Not Done/Archived and in last 30 days - count as missed
                    return true;
                });
                
                stats.missed_count_30 = missedTasks.length;
                
                // Update stat cards (find by stat-value divs in each card)
                $('.stat-card').each(function() {
                    var label = $(this).find('.stat-label').text().trim();
                    var valueEl = $(this).find('.stat-value');
                    
                    switch(label) {
                        case 'TOTAL':
                            valueEl.text(stats.total);
                            $(this).attr('data-value', stats.total);
                            break;
                        case 'PENDING':
                            valueEl.text(stats.pending);
                            $(this).attr('data-value', stats.pending);
                            break;
                        case 'OVERDUE':
                            valueEl.text(stats.overdue);
                            $(this).attr('data-value', stats.overdue);
                            break;
                        case 'DONE':
                            valueEl.text(stats.done);
                            $(this).attr('data-value', stats.done);
                            break;
                        case 'ETC 30D':
                            break;
                        case 'R&R':
                            valueEl.text(stats.rr != null ? Math.round(stats.rr / 60) : (stats.etc_rr != null ? Math.round(stats.etc_rr / 60) : '-'));
                            break;
                        case 'ATC 30D':
                            break;
                        case 'DONE ETC':
                            valueEl.text(Math.round(stats.done_etc / 60));
                            break;
                        case 'DONE ATC':
                            valueEl.text(Math.round(stats.done_atc / 60));
                            break;
                        case 'TAT':
                            valueEl.text(stats.tat_avg_30 !== null ? String(Math.round(stats.tat_avg_30)) : '-');
                            $(this).attr('data-value', stats.tat_avg_30 !== null ? Math.round(stats.tat_avg_30) : 0);
                            break;
                        case 'AVG SCORE':
                            if (performanceAverageScore !== null && performanceAverageScore !== undefined && performanceAverageScore !== '') {
                                var n = typeof performanceAverageScore === 'number' ? performanceAverageScore : parseFloat(performanceAverageScore);
                                valueEl.text(isNaN(n) ? '-' : n.toFixed(2));
                            } else {
                                valueEl.text('-');
                            }
                            break;
                        case 'MISSED':
                            valueEl.text(stats.missed_count_30);
                            $(this).attr('data-value', stats.missed_count_30);
                            break;
                        case 'ETC':
                            valueEl.text(formatMinutesAsHm(stats.etc_total || 0));
                            break;
                        case 'PENDING ETC':
                            var pendingEtcHours = Math.round((stats.pending_etc || 0) / 60);
                            valueEl.text(pendingEtcHours + 'h');
                            $(this).attr('data-value', pendingEtcHours);
                            break;
                    }
                });
                refreshDeletedBadgeStats();
            }

            function refreshDeletedBadgeStats() {
                $.ajax({
                    url: '{{ route("tasks.deletedBadgeStats") }}',
                    method: 'GET',
                    dataType: 'json',
                    data: {
                        assignor: $('#filter-assignor').val() || '',
                        assignee: $('#filter-assignee').val() || ''
                    },
                    success: function(resp) {
                        var etcHours = Number(resp && resp.etc_hours != null ? resp.etc_hours : 0);
                        var atcHours = Number(resp && resp.atc_hours != null ? resp.atc_hours : 0);
                        if (isNaN(etcHours)) etcHours = 0;
                        if (isNaN(atcHours)) atcHours = 0;

                        $('.stat-card').each(function() {
                            var label = $(this).find('.stat-label').text().trim();
                            var valueEl = $(this).find('.stat-value');
                            if (label === 'ETC 30D') {
                                valueEl.text(Math.round(etcHours) + 'h');
                            } else if (label === 'ATC 30D') {
                                valueEl.text(Math.round(atcHours) + 'h');
                            }
                        });
                    }
                });
            }

            // Decide row order based on active user filters.
            // - When ANY user filter is active (assignor dropdown, assignee dropdown, or session user focus):
            //     manual / normal tasks FIRST, then automated.
            // - Otherwise (global view): automated tasks on top per the existing UX.
            // Day-level ordering (start_date) is always primary.
            function getTaskSortForCurrentFilters() {
                var hasUserFilter = !!(
                    ($('#filter-assignor').val() || '').trim()
                    || ($('#filter-assignee').val() || '').trim()
                    || (typeof taskManagerSessionUserFocus !== 'undefined' && String(taskManagerSessionUserFocus || '').trim())
                );
                return [
                    {column: "start_date", dir: "asc"},
                    {column: "is_automate_task", dir: hasUserFilter ? "asc" : "desc"}
                ];
            }

            function applyTaskOrdering() {
                try {
                    table.setSort(getTaskSortForCurrentFilters());
                } catch (e) {
                    console.warn('applyTaskOrdering failed:', e);
                }
            }

            // Combined filter function (proper AND logic)
            function applyFilters() {
                console.log('🔍 Applying filters...');
                
                // Clear existing filters first
                table.clearFilter();
                
                // Build filter array with AND logic
                var filters = [];
                var focusActive = !!(taskManagerSessionUserFocus && String(taskManagerSessionUserFocus).trim());
                
                // Group filter
                var groupValue = $('#filter-group').val();
                if (groupValue) {
                    filters.push({field:"group", type:"like", value:groupValue});
                    console.log('Filter - Group:', groupValue);
                }
                
                // Task filter
                var taskValue = $('#filter-task').val();
                if (taskValue) {
                    filters.push({field:"title", type:"like", value:taskValue});
                    console.log('Filter - Task:', taskValue);
                }

                // Date filter (start_date contains YYYY-MM-DD)
                var dateValue = $('#filter-date').val();
                if (dateValue) {
                    filters.push({field:"start_date", type:"like", value:dateValue});
                    console.log('Filter - Date:', dateValue);
                }
                

                // Assignor filter (including NULL check); skipped when session user focus is active (uses OR block below)
                var assignorValue = $('#filter-assignor').val();
                if (!focusActive && assignorValue) {
                    if (assignorValue === '__NULL__') {
                        // Custom filter for tasks with NO assignor
                        table.setFilter(function(data) {
                            return !data.assignor_name || data.assignor_name === '-' || data.assignor_name === '';
                        });
                        console.log('✓ Filter applied: No Assignor');
                        persistTaskIndexFilters();
                        setTimeout(syncTaskTableHeaderSelectAllCheckbox, 0);
                        return; // Skip other filters
                    } else {
                        // Use "like" so tasks show when this person is assignor (exact or in list)
                        filters.push({field:"assignor_name", type:"like", value:assignorValue});
                        console.log('Filter - Assignor (like):', assignorValue);
                    }
                }
                
                // Assignee filter (including NULL check); skipped when session user focus is active
                var assigneeValue = $('#filter-assignee').val();
                if (!focusActive && assigneeValue) {
                    if (assigneeValue === '__NULL__') {
                        console.log('🔴 NO ASSIGNEE FILTER TRIGGERED');
                        
                        // First, let's see what we're working with
                        const allData = table.getData();
                        console.log('📊 Total tasks:', allData.length);
                        console.log('📝 Sample assignee_name values:', allData.slice(0, 10).map(t => `ID:${t.id} assignee="${t.assignee_name}"`));
                        
                        // Count how many actually have no assignee
                        const noAssigneeCount = allData.filter(t => {
                            const name = t.assignee_name;
                            return name === '-' || name === '' || name === null || name === undefined;
                        }).length;
                        console.log('📊 Tasks with no assignee (-, null, empty):', noAssigneeCount);
                        
                        // Apply filter - ONLY show tasks where assignee_name is exactly "-"
                        table.clearFilter();
                        filters.push({field:"assignee_name", type:"=", value:"-"});
                        table.setFilter(filters);
                        
                        console.log('✅ Filter applied: assignee_name = "-"');
                        console.log('📊 Filtered tasks showing:', table.getDataCount('active'));
                        
                        // Update mobile view
                        if (window.innerWidth < 768) {
                            const filtered = table.getData('active');
                            renderMobileTasks(filtered);
                        }
                        
                        setTimeout(function () {
                            updateStatistics();
                            syncTaskTableHeaderSelectAllCheckbox();
                        }, 100);
                        persistTaskIndexFilters();
                        return; // Skip other filters
                    } else {
                        // Use "like" so tasks show when this person is assignee (single or in list: "Shobha N" or "Srimanta, Shobha N")
                        filters.push({field: "assignee_name", type: "like", value: assigneeValue});
                        console.log('Filter - Assignee (like):', assigneeValue);
                    }
                }
                
                // Status filter - Try case-insensitive
                var statusValue = $('#filter-status').val();
                if (statusValue) {
                    // Try both exact match and like match
                    filters.push({field:"status", type:"like", value:statusValue});
                    console.log('✓ Filter - Status (like):', statusValue);
                    console.log('Sample task statuses:', table.getData().slice(0, 3).map(t => t.status));
                }
                
                // Priority filter
                var priorityValue = $('#filter-priority').val();
                if (priorityValue) {
                    filters.push({field:"priority", type:"=", value:priorityValue});
                    console.log('Filter - Priority:', priorityValue);
                }
                
                // Search filter (OR logic within search - add last)
                var searchValue = $('#filter-search').val();
                if (searchValue) {
                    filters.push([
                        {field:"title", type:"like", value:searchValue},
                        {field:"group", type:"like", value:searchValue},
                        {field:"assignor_name", type:"like", value:searchValue},
                        {field:"assignee_name", type:"like", value:searchValue}
                    ]);
                }

                // Task Summary / session: show tasks where this user is assignor OR assignee (Tabulator OR group)
                if (focusActive) {
                    var focusName = String(taskManagerSessionUserFocus).trim();
                    filters.push([
                        {field:"assignor_name", type:"like", value:focusName},
                        {field:"assignee_name", type:"like", value:focusName}
                    ]);
                }
                
                // Apply all filters if any exist
                if (filters.length > 0) {
                    table.setFilter(filters);
                }

                // Re-apply ordering: when a user is being filtered we want manual tasks first,
                // then automated. Without this the initialSort would always keep automated on top.
                applyTaskOrdering();

                // Update statistics after filtering
                setTimeout(function() {
                    updateStatistics();
                    
                    // Update mobile view
                    if (window.innerWidth < 768) {
                        const filteredData = table.getData('active');
                        renderMobileTasks(filteredData);
                    }
                    syncTaskTableHeaderSelectAllCheckbox();
                }, 100);
                persistTaskIndexFilters();
            }

            var taskIndexFiltersRestored = false;
            table.on('dataLoaded', function () {
                if (taskIndexFiltersRestored) return;
                taskIndexFiltersRestored = true;
                restoreTaskIndexFilters();
                if (taskManagerSessionUserFocus) {
                    suppressAssignFilterApply = true;
                    $('#filter-assignor').val('').trigger('change');
                    $('#filter-assignee').val(taskManagerSessionUserFocus).trigger('change');
                    suppressAssignFilterApply = false;
                }
                applyFilters();
            });
            $(window).on('beforeunload pagehide', function () {
                persistTaskIndexFilters();
            });

            // Filter functionality - prevent form submission on Enter key
            $('#filter-search, #filter-group, #filter-task').on('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                }
            });
            
            $('#filter-search').on('keyup', function(e) {
                // Don't apply filters if Enter was just pressed
                if (e.keyCode !== 13) {
                    applyFilters();
                }
            });
            $('#filter-group').on('keyup', function(e) {
                if (e.keyCode !== 13) {
                    applyFilters();
                }
            });
            $('#filter-task').on('keyup', function(e) {
                if (e.keyCode !== 13) {
                    applyFilters();
                }
            });
            $('#filter-date').on('change', applyFilters);
            
            $('#filter-assignor, #filter-assignee').on('change', function () {
                if (suppressAssignFilterApply) return;
                if (taskManagerSessionUserFocus) {
                    if (this.id === 'filter-assignee') {
                        var v = $('#filter-assignee').val() || '';
                        if (v !== taskManagerSessionUserFocus) {
                            taskManagerSessionUserFocus = '';
                            $.ajax({
                                url: '{{ route("tasks.setSelectedUser") }}',
                                method: 'POST',
                                data: { _token: '{{ csrf_token() }}', user_name: '' }
                            });
                        }
                    } else if (this.id === 'filter-assignor' && ($('#filter-assignor').val() || '')) {
                        taskManagerSessionUserFocus = '';
                        $.ajax({
                            url: '{{ route("tasks.setSelectedUser") }}',
                            method: 'POST',
                            data: { _token: '{{ csrf_token() }}', user_name: '' }
                        });
                    }
                }
                applyFilters();
            });
            $('#filter-status').on('change', applyFilters);
            $('#filter-priority').on('change', applyFilters);

            // Reload table from server only; keep filter inputs and reapply Tabulator filters
            $('#tasks-refresh-table-btn, #tasks-refresh-table-btn-mobile').on('click', function () {
                var $btn = $(this);
                var $icon = $btn.find('i.mdi-refresh').first();
                $btn.prop('disabled', true);
                if ($icon.length) {
                    $icon.addClass('mdi-spin');
                }
                table.replaceData()
                    .then(function () {
                        applyFilters();
                        setTimeout(updateStatistics, 100);
                    })
                    .catch(function (err) {
                        console.error('Refresh table failed:', err);
                    })
                    .finally(function () {
                        $btn.prop('disabled', false);
                        if ($icon.length) {
                            $icon.removeClass('mdi-spin');
                        }
                    });
            });

            // ============================================================
            // Today Deleted — show today's deletions and let user revert
            // ============================================================
            function escapeHtml(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function renderTodayDeletedRow(row) {
                var title = escapeHtml(row.title || '(no title)');
                var typeBadge = row.is_auto_expired
                    ? '<span class="badge bg-warning text-dark" title="Auto-deleted by nightly daily-auto cleanup">Auto-Expired</span>'
                    : (row.task_type === 'automate_task'
                        ? '<span class="badge bg-info">Auto</span>'
                        : '<span class="badge bg-light text-dark">Manual</span>');
                var assignor = escapeHtml(row.assignor_name || row.assignor || '-');
                var assignee = escapeHtml(row.assignee_name || row.assign_to || '-');
                var deletedBy = row.is_auto_expired
                    ? '<span class="text-warning"><i class="mdi mdi-robot"></i> System</span>'
                    : escapeHtml(row.deleted_by_name || row.deleted_by_email || '-');
                var status = escapeHtml(row.status || '-');
                var time = escapeHtml(row.deleted_at_human || '');

                // Row color rules:
                //   yellow = auto-expired (system@auto cleanup of missed daily auto-tasks)
                //   green  = completed automated tasks auto-archived on Done
                //   white  = manually deleted tasks
                var rowClass = '';
                if (row.is_auto_expired) {
                    rowClass = ' class="today-deleted-row-auto"';
                } else if (String(row.status || '').toLowerCase() === 'done') {
                    rowClass = ' class="today-deleted-row-done"';
                }

                return ''
                    + '<tr data-id="' + row.id + '" data-mode="' + (row.is_auto_expired ? 'auto' : 'manual') + '"' + rowClass + '>'
                    + '  <td><input type="checkbox" class="form-check-input today-deleted-row-chk" data-id="' + row.id + '"></td>'
                    + '  <td><span class="text-muted small">' + time + '</span></td>'
                    + '  <td>' + title + '</td>'
                    + '  <td>' + typeBadge + '</td>'
                    + '  <td><span class="small">' + assignor + '</span></td>'
                    + '  <td><span class="small">' + assignee + '</span></td>'
                    + '  <td><span class="small">' + deletedBy + '</span></td>'
                    + '  <td><span class="badge bg-secondary">' + status + '</span></td>'
                    + '  <td class="text-end">'
                    + '    <button type="button" class="btn btn-sm btn-success today-deleted-revert-btn" data-id="' + row.id + '">'
                    + '      <i class="mdi mdi-undo"></i> Revert'
                    + '    </button>'
                    + '  </td>'
                    + '</tr>';
            }

            function formatBadgeNumber(n) {
                if (typeof n !== 'number' || !isFinite(n)) return '0';
                if (n >= 1000) {
                    return (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1) + 'k';
                }
                return String(n);
            }

            function loadTodayDeleted() {
                var $loading = $('#today-deleted-loading');
                var $empty = $('#today-deleted-empty');
                var $wrap = $('#today-deleted-table-wrap');
                var $tbody = $('#today-deleted-tbody');
                var $count = $('#today-deleted-count');
                var $badge = $('#today-deleted-badge');
                var $breakdown = $('#today-deleted-breakdown');
                var $trunc = $('#today-deleted-truncated');
                var $returned = $('#today-deleted-returned');

                $loading.show();
                $empty.hide();
                $wrap.hide();
                $tbody.empty();
                $breakdown.text('');
                $trunc.hide();

                return $.ajax({
                    url: '{{ route('tasks.todayDeleted.data') }}',
                    method: 'GET',
                    dataType: 'json'
                }).done(function (resp) {
                    var data = (resp && resp.data) ? resp.data : [];
                    var total = (resp && typeof resp.count === 'number') ? resp.count : data.length;
                    var auto = (resp && typeof resp.auto_count === 'number') ? resp.auto_count : 0;
                    var manual = (resp && typeof resp.manual_count === 'number') ? resp.manual_count : Math.max(0, total - auto);

                    // Footer count uses TRUE total (not the limited rows fetched).
                    $count.text(total.toLocaleString());
                    if (auto > 0 || manual > 0) {
                        $breakdown.html('(<span class="text-warning">' + auto.toLocaleString() + ' auto</span>, '
                            + manual.toLocaleString() + ' manual)');
                    }
                    if (resp && resp.truncated) {
                        $returned.text((resp.returned || data.length).toLocaleString());
                        $trunc.show();
                    }

                    // Update bulk-revert dropdown counters
                    $('.today-deleted-auto-num').text(auto.toLocaleString());
                    $('.today-deleted-manual-num').text(manual.toLocaleString());
                    $('.today-deleted-total-num').text(total.toLocaleString());
                    // Reset selection state
                    $('#today-deleted-select-all').prop('checked', false).prop('indeterminate', false);
                    updateTodayDeletedSelection();

                    // Badge uses TRUE total, abbreviated if it's huge.
                    if (total > 0) {
                        $badge.text(formatBadgeNumber(total)).attr('title', total.toLocaleString() + ' deleted today').show();
                    } else {
                        $badge.hide();
                    }

                    if (data.length === 0) {
                        $empty.show();
                    } else {
                        var html = data.map(renderTodayDeletedRow).join('');
                        $tbody.html(html);
                        $wrap.show();
                    }
                }).fail(function () {
                    $tbody.html('<tr><td colspan="9" class="text-center text-danger py-3"><i class="mdi mdi-alert"></i> Failed to load today\'s deletions.</td></tr>');
                    $wrap.show();
                }).always(function () {
                    $loading.hide();
                });
            }

            // Refresh the badge count quietly on page load and every 60s so users notice deletions.
            function refreshTodayDeletedBadge() {
                $.ajax({
                    url: '{{ route('tasks.todayDeleted.data') }}',
                    method: 'GET',
                    data: { limit: 1 }, // tiny payload, count is computed server-side without the row cap
                    dataType: 'json'
                }).done(function (resp) {
                    var n = (resp && typeof resp.count === 'number') ? resp.count : 0;
                    var $badge = $('#today-deleted-badge');
                    if (n > 0) {
                        $badge.text(formatBadgeNumber(n)).attr('title', n.toLocaleString() + ' deleted today').show();
                    } else {
                        $badge.hide();
                    }
                });
            }
            @if(!empty($canShowTaskMaintenanceButtons))
            refreshTodayDeletedBadge();
            setInterval(refreshTodayDeletedBadge, 60000);
            $('#todayDeletedModal').on('show.bs.modal', loadTodayDeleted);
            @endif
            $('#today-deleted-refresh-btn').on('click', loadTodayDeleted);

            // ---- Selection: per-row + select-all ----
            function updateTodayDeletedSelection() {
                var $checked = $('#today-deleted-tbody .today-deleted-row-chk:checked');
                var $all = $('#today-deleted-tbody .today-deleted-row-chk');
                var n = $checked.length;
                $('#today-deleted-selected-count').text(n);
                $('#today-deleted-revert-selected-btn').prop('disabled', n === 0);

                var $selAll = $('#today-deleted-select-all');
                if (n === 0) {
                    $selAll.prop('checked', false).prop('indeterminate', false);
                } else if (n === $all.length) {
                    $selAll.prop('checked', true).prop('indeterminate', false);
                } else {
                    $selAll.prop('checked', false).prop('indeterminate', true);
                }
            }

            $(document).on('change', '#today-deleted-select-all', function () {
                var checked = $(this).is(':checked');
                $('#today-deleted-tbody .today-deleted-row-chk').prop('checked', checked);
                updateTodayDeletedSelection();
            });

            $(document).on('change', '#today-deleted-tbody .today-deleted-row-chk', updateTodayDeletedSelection);

            // ---- Shared bulk-revert AJAX runner ----
            function runBulkRevert(payload, confirmMsg, $triggerBtn) {
                if (!confirm(confirmMsg)) {
                    return;
                }
                var originalHtml = $triggerBtn ? $triggerBtn.html() : null;
                if ($triggerBtn) {
                    $triggerBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Reverting...');
                }
                var data = $.extend({ _token: '{{ csrf_token() }}' }, payload);
                $.ajax({
                    url: '{{ route('tasks.todayDeleted.bulkRevert') }}',
                    method: 'POST',
                    data: data,
                    traditional: true,
                    dataType: 'json'
                }).done(function (resp) {
                    alert((resp && resp.message) ? resp.message : 'Bulk revert finished.');
                    loadTodayDeleted();
                    if (typeof table !== 'undefined' && table && typeof table.replaceData === 'function') {
                        table.replaceData().then(function () {
                            if (typeof applyFilters === 'function') { applyFilters(); }
                            if (typeof updateStatistics === 'function') { setTimeout(updateStatistics, 100); }
                        });
                    }
                }).fail(function (xhr) {
                    var msg = 'Bulk revert failed.';
                    try {
                        var r = xhr.responseJSON;
                        if (r && r.message) { msg = r.message; }
                    } catch (e) {}
                    alert(msg);
                }).always(function () {
                    if ($triggerBtn && originalHtml !== null) {
                        $triggerBtn.prop('disabled', false).html(originalHtml);
                    }
                });
            }

            // ---- Bulk: by category from dropdown ----
            $(document).on('click', '[data-bulk-mode]', function (e) {
                e.preventDefault();
                var mode = $(this).data('bulk-mode');
                var label = mode === 'auto' ? 'auto-expired' : (mode === 'manual' ? 'manual' : 'ALL');
                var num = mode === 'auto'
                    ? $('.today-deleted-auto-num').first().text()
                    : (mode === 'manual'
                        ? $('.today-deleted-manual-num').first().text()
                        : $('.today-deleted-total-num').first().text());
                var msg = 'Revert ' + num + ' ' + label + ' deleted task(s) from today?\n\n'
                    + 'They will be restored to the active Tasks list with status Todo and is_missed cleared.';
                runBulkRevert({ mode: mode }, msg, null);
            });

            // ---- Bulk: revert checked rows ----
            $(document).on('click', '#today-deleted-revert-selected-btn', function () {
                var ids = $('#today-deleted-tbody .today-deleted-row-chk:checked').map(function () {
                    return $(this).data('id');
                }).get();
                if (ids.length === 0) return;
                var $btn = $(this);
                runBulkRevert(
                    { ids: ids },
                    'Revert ' + ids.length + ' selected task(s)?',
                    $btn
                );
            });

            $(document).on('click', '.today-deleted-revert-btn', function () {
                var $btn = $(this);
                var id = $btn.data('id');
                if (!id) return;
                if (!confirm('Revert this task back to active?\n\nThe task will return to your Tasks list with status Todo and is_missed cleared.')) {
                    return;
                }
                $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin"></i>');
                $.ajax({
                    url: '/tasks/today-deleted/' + id + '/revert',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    dataType: 'json'
                }).done(function (resp) {
                    if (resp && resp.success) {
                        $btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
                        // Update count + badge + reload main task table so the revived task shows up.
                        var $count = $('#today-deleted-count');
                        var raw = String($count.text()).replace(/[^0-9]/g, '');
                        var newCount = Math.max(0, (parseInt(raw, 10) || 1) - 1);
                        $count.text(newCount.toLocaleString());
                        var $badge = $('#today-deleted-badge');
                        if (newCount > 0) {
                            $badge.text(formatBadgeNumber(newCount)).attr('title', newCount.toLocaleString() + ' deleted today').show();
                        } else {
                            $badge.hide();
                            $('#today-deleted-table-wrap').hide();
                            $('#today-deleted-empty').show();
                        }
                        if (typeof table !== 'undefined' && table && typeof table.replaceData === 'function') {
                            table.replaceData().then(function () {
                                if (typeof applyFilters === 'function') { applyFilters(); }
                                if (typeof updateStatistics === 'function') { setTimeout(updateStatistics, 100); }
                            });
                        }
                    } else {
                        alert((resp && resp.message) ? resp.message : 'Revert failed.');
                        $btn.prop('disabled', false).html('<i class="mdi mdi-undo"></i> Revert');
                    }
                }).fail(function (xhr) {
                    var msg = 'Revert failed.';
                    try {
                        var r = xhr.responseJSON;
                        if (r && r.message) { msg = r.message; }
                    } catch (e) {}
                    alert(msg);
                    $btn.prop('disabled', false).html('<i class="mdi mdi-undo"></i> Revert');
                });
            });

            // Admin: trigger the "Cleanup Missed Daily" job on demand.
            // Auto-deletes daily automated tasks that were not completed the same day and counts them in Missed.
            $('#expire-daily-auto-btn').on('click', function () {
                var $btn = $(this);
                var originalHtml = $btn.html();

                if (!confirm('Auto-delete DAILY automated tasks that were NOT completed before today?\n\n• Only schedule_type = daily is affected.\n• Weekly and Monthly automated tasks are NOT touched.\n• Deleted tasks are moved to the Deleted/Archive list and counted in the Missed badge.')) {
                    return;
                }

                $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-2"></i> Cleaning...');

                $.ajax({
                    url: '{{ route('tasks.expireDailyAutomated') }}',
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    dataType: 'json'
                }).done(function (resp) {
                    if (resp && resp.success) {
                        alert(resp.message || 'Cleanup complete.');
                        // Refresh table + stats so the Missed badge updates immediately.
                        if (typeof table !== 'undefined' && table && typeof table.replaceData === 'function') {
                            table.replaceData().then(function () {
                                applyFilters();
                                setTimeout(updateStatistics, 100);
                            });
                        } else {
                            location.reload();
                        }
                        // Refresh Today Deleted badge — auto-expired tasks become today-deleted entries.
                        if (typeof refreshTodayDeletedBadge === 'function') {
                            refreshTodayDeletedBadge();
                        }
                    } else {
                        alert((resp && resp.message) ? resp.message : 'Cleanup failed.');
                    }
                }).fail(function (xhr) {
                    var msg = 'Cleanup failed.';
                    try {
                        var r = xhr.responseJSON;
                        if (r && r.message) { msg = r.message; }
                    } catch (e) {}
                    alert(msg);
                }).always(function () {
                    $btn.prop('disabled', false).html(originalHtml);
                });
            });

            // Assignor playback (step through assignors - next to Bulk button)
            var taskPlaybackListAssignor = [];
            var currentTaskPlaybackIndexAssignor = -1;
            var isTaskPlaybackActiveAssignor = false;

            function getTaskPlaybackListAssignor() {
                var list = [];
                $('#filter-assignor').find('option').each(function() {
                    var v = $(this).val();
                    if (v === '' || v === undefined || v === null) return;
                    list.push({ value: v, text: $(this).text().trim() });
                });
                return list;
            }

            function taskStartNavigationAssignor() {
                taskPlaybackListAssignor = getTaskPlaybackListAssignor();
                if (taskPlaybackListAssignor.length === 0) return;
                isTaskPlaybackActiveAssignor = true;
                currentTaskPlaybackIndexAssignor = 0;
                suppressAssignFilterApply = true;
                $('#filter-assignor').val(taskPlaybackListAssignor[0].value).trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                $('#task-play-auto-assignor').hide();
                $('#task-play-pause-assignor').show();
                updateTaskPlaybackButtonStatesAssignor();
            }

            function taskStopNavigationAssignor() {
                isTaskPlaybackActiveAssignor = false;
                currentTaskPlaybackIndexAssignor = -1;
                suppressAssignFilterApply = true;
                $('#filter-assignor').val('').trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                $('#task-play-pause-assignor').hide();
                $('#task-play-auto-assignor').show();
                $('#task-play-backward-assignor, #task-play-forward-assignor').prop('disabled', true).removeClass('btn-primary').addClass('btn-light');
                $('#task-playback-label-assignor').hide().text('');
            }

            function taskNextAssignor() {
                if (!isTaskPlaybackActiveAssignor || currentTaskPlaybackIndexAssignor >= taskPlaybackListAssignor.length - 1) return;
                currentTaskPlaybackIndexAssignor++;
                suppressAssignFilterApply = true;
                $('#filter-assignor').val(taskPlaybackListAssignor[currentTaskPlaybackIndexAssignor].value).trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                updateTaskPlaybackButtonStatesAssignor();
            }

            function taskPreviousAssignor() {
                if (!isTaskPlaybackActiveAssignor || currentTaskPlaybackIndexAssignor <= 0) return;
                currentTaskPlaybackIndexAssignor--;
                suppressAssignFilterApply = true;
                $('#filter-assignor').val(taskPlaybackListAssignor[currentTaskPlaybackIndexAssignor].value).trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                updateTaskPlaybackButtonStatesAssignor();
            }

            function updateTaskPlaybackButtonStatesAssignor() {
                var atStart = currentTaskPlaybackIndexAssignor <= 0;
                var atEnd = currentTaskPlaybackIndexAssignor >= taskPlaybackListAssignor.length - 1;
                $('#task-play-backward-assignor').prop('disabled', !isTaskPlaybackActiveAssignor || atStart);
                $('#task-play-forward-assignor').prop('disabled', !isTaskPlaybackActiveAssignor || atEnd);
                if (isTaskPlaybackActiveAssignor && taskPlaybackListAssignor.length > 0 && currentTaskPlaybackIndexAssignor >= 0) {
                    var item = taskPlaybackListAssignor[currentTaskPlaybackIndexAssignor];
                    $('#task-playback-label-assignor').text(item.text + ' (' + (currentTaskPlaybackIndexAssignor + 1) + '/' + taskPlaybackListAssignor.length + ')').show();
                    $('#task-play-backward-assignor, #task-play-forward-assignor').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#task-playback-label-assignor').hide();
                }
            }

            $('#task-play-auto-assignor').on('click', taskStartNavigationAssignor);
            $('#task-play-pause-assignor').on('click', taskStopNavigationAssignor);
            $('#task-play-forward-assignor').on('click', taskNextAssignor);
            $('#task-play-backward-assignor').on('click', taskPreviousAssignor);

            // Assignee playback (step through assignees - above Assignee filter)
            var taskPlaybackListAssignee = [];
            var currentTaskPlaybackIndexAssignee = -1;
            var isTaskPlaybackActiveAssignee = false;

            function getTaskPlaybackListAssignee() {
                var list = [];
                $('#filter-assignee').find('option').each(function() {
                    var v = $(this).val();
                    if (v === '' || v === undefined || v === null) return;
                    list.push({ value: v, text: $(this).text().trim() });
                });
                return list;
            }

            function taskStartNavigationAssignee() {
                taskPlaybackListAssignee = getTaskPlaybackListAssignee();
                if (taskPlaybackListAssignee.length === 0) return;
                isTaskPlaybackActiveAssignee = true;
                currentTaskPlaybackIndexAssignee = 0;
                suppressAssignFilterApply = true;
                $('#filter-assignee').val(taskPlaybackListAssignee[0].value).trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                $('#task-play-auto-assignee').hide();
                $('#task-play-pause-assignee').show();
                updateTaskPlaybackButtonStatesAssignee();
            }

            function taskStopNavigationAssignee() {
                isTaskPlaybackActiveAssignee = false;
                currentTaskPlaybackIndexAssignee = -1;
                suppressAssignFilterApply = true;
                $('#filter-assignee').val('').trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                $('#task-play-pause-assignee').hide();
                $('#task-play-auto-assignee').show();
                $('#task-play-backward-assignee, #task-play-forward-assignee').prop('disabled', true).removeClass('btn-primary').addClass('btn-light');
                $('#task-playback-label-assignee').hide().text('');
            }

            function taskNextAssignee() {
                if (!isTaskPlaybackActiveAssignee || currentTaskPlaybackIndexAssignee >= taskPlaybackListAssignee.length - 1) return;
                currentTaskPlaybackIndexAssignee++;
                suppressAssignFilterApply = true;
                $('#filter-assignee').val(taskPlaybackListAssignee[currentTaskPlaybackIndexAssignee].value).trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                updateTaskPlaybackButtonStatesAssignee();
            }

            function taskPreviousAssignee() {
                if (!isTaskPlaybackActiveAssignee || currentTaskPlaybackIndexAssignee <= 0) return;
                currentTaskPlaybackIndexAssignee--;
                suppressAssignFilterApply = true;
                $('#filter-assignee').val(taskPlaybackListAssignee[currentTaskPlaybackIndexAssignee].value).trigger('change');
                suppressAssignFilterApply = false;
                applyFilters();
                updateTaskPlaybackButtonStatesAssignee();
            }

            function updateTaskPlaybackButtonStatesAssignee() {
                var atStart = currentTaskPlaybackIndexAssignee <= 0;
                var atEnd = currentTaskPlaybackIndexAssignee >= taskPlaybackListAssignee.length - 1;
                $('#task-play-backward-assignee').prop('disabled', !isTaskPlaybackActiveAssignee || atStart);
                $('#task-play-forward-assignee').prop('disabled', !isTaskPlaybackActiveAssignee || atEnd);
                if (isTaskPlaybackActiveAssignee && taskPlaybackListAssignee.length > 0 && currentTaskPlaybackIndexAssignee >= 0) {
                    var item = taskPlaybackListAssignee[currentTaskPlaybackIndexAssignee];
                    $('#task-playback-label-assignee').text(item.text + ' (' + (currentTaskPlaybackIndexAssignee + 1) + '/' + taskPlaybackListAssignee.length + ')').show();
                    $('#task-play-backward-assignee, #task-play-forward-assignee').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#task-playback-label-assignee').hide();
                }
            }

            $('#task-play-auto-assignee').on('click', taskStartNavigationAssignee);
            $('#task-play-pause-assignee').on('click', taskStopNavigationAssignee);
            $('#task-play-forward-assignee').on('click', taskNextAssignee);
            $('#task-play-backward-assignee').on('click', taskPreviousAssignee);
            
            // Prevent any form submission in filter section
            $('.filter-section').on('submit', function(e) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
            
            // Global prevention for Enter key in filter inputs
            $(document).on('keydown', '#filter-search, #filter-group, #filter-task', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    return false;
                }
            });
            
            // ==========================================
            // MOBILE ACTION BUTTONS
            // ==========================================
            // Connect mobile buttons to desktop functionality
            $('#upload-csv-btn-mobile').on('click', function() {
                $('#upload-csv-btn').click();
            });
            
            $('#bulk-actions-btn-mobile').on('click', function() {
                $('#bulk-actions-btn').click();
            });

            
            // ==========================================
            // MOBILE QUICK FILTER CHIPS
            // ==========================================
            $('.quick-filter-chip').on('click', function() {
                const filterType = $(this).data('filter');
                
                // Update active state
                $('.quick-filter-chip').removeClass('active');
                $(this).addClass('active');
                
                console.log('Quick filter:', filterType);
                
                // Apply filter
                console.log('🔍 Quick filter clicked:', filterType);

                suppressAssignFilterApply = true;
                switch(filterType) {
                    case 'all':
                        $('#filter-status').val('');
                        $('#filter-priority').val('');
                        $('#filter-date').val('');
                        $('#filter-assignor').val('');
                        $('#filter-assignee').val('');
                        console.log('✓ Showing all tasks');
                        break;
                    case 'Todo':
                        $('#filter-status').val('Todo');
                        $('#filter-priority').val('');
                        $('#filter-assignor').val('');
                        $('#filter-assignee').val('');
                        console.log('✓ Filtering: Todo');
                        break;
                    case 'Working':
                        $('#filter-status').val('Working');
                        $('#filter-priority').val('');
                        $('#filter-assignor').val('');
                        $('#filter-assignee').val('');
                        console.log('✓ Filtering: Working');
                        break;
                    case 'Done':
                        $('#filter-status').val('Done');
                        $('#filter-priority').val('');
                        $('#filter-assignor').val('');
                        $('#filter-assignee').val('');
                        console.log('✓ Filtering: Done');
                        break;
                    case 'no_assignee':
                        $('#filter-status').val('');
                        $('#filter-priority').val('');
                        $('#filter-assignor').val('');
                        $('#filter-assignee').val('__NULL__');
                        console.log('✓ Filtering: No Assignee');
                        break;
                    case 'no_assignor':
                        $('#filter-status').val('');
                        $('#filter-priority').val('');
                        $('#filter-assignor').val('__NULL__');
                        $('#filter-assignee').val('');
                        console.log('✓ Filtering: No Assignor');
                        break;
                    case 'Need Help':
                        $('#filter-status').val('Need Help');
                        $('#filter-priority').val('');
                        $('#filter-assignor').val('');
                        $('#filter-assignee').val('');
                        console.log('✓ Filtering: Need Help');
                        break;
                    case 'high':
                        $('#filter-status').val('');
                        $('#filter-priority').val('high');
                        $('#filter-assignor').val('');
                        $('#filter-assignee').val('');
                        console.log('✓ Filtering: High Priority');
                        break;
                }
                $('#filter-assignor, #filter-assignee').trigger('change');
                suppressAssignFilterApply = false;

                applyFilters();
                
                // Haptic feedback if available
                if (navigator.vibrate) {
                    navigator.vibrate(10);
                }
            });

            function escapeCsvCell(value) {
                if (value === null || value === undefined) return '""';
                var text = String(value).replace(/"/g, '""');
                return '"' + text + '"';
            }

            function exportSelectedTasksCsv() {
                if (!selectedTasks || selectedTasks.length === 0) {
                    return;
                }

                var selectedIdSet = new Set(selectedTasks.map(function(id) { return String(id); }));
                var selectedRows = table.getData().filter(function(row) {
                    return selectedIdSet.has(String(row.id));
                });

                if (selectedRows.length === 0) {
                    return;
                }

                selectedRows.sort(function(a, b) {
                    return String(a.start_date || '').localeCompare(String(b.start_date || ''));
                });

                var headers = [
                    'Task ID',
                    'Group',
                    'Task',
                    'Assignor',
                    'Assignee',
                    'Start Date',
                    'Due Date',
                    'Status',
                    'Priority',
                    'ETC Minutes',
                    'ATC Minutes'
                ];

                var lines = [];
                lines.push(headers.map(escapeCsvCell).join(','));

                selectedRows.forEach(function(row) {
                    lines.push([
                        row.id || '',
                        row.group || '',
                        row.title || '',
                        row.assignor_name || row.assignor || '',
                        row.assignee_name || row.assign_to || '',
                        row.start_date || '',
                        row.due_date || '',
                        row.status || '',
                        row.priority || '',
                        row.eta_time || 0,
                        row.etc_done || 0
                    ].map(escapeCsvCell).join(','));
                });

                var csv = '\uFEFF' + lines.join('\r\n');
                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                var url = URL.createObjectURL(blob);
                var stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
                var link = document.createElement('a');
                link.href = url;
                link.download = 'tasks-selected-' + stamp + '.csv';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            }

            function syncTaskTableHeaderSelectAllCheckbox() {
                var headerCb = document.querySelector('#tasks-table .tabulator-headers .tabulator-col:first-child input[type="checkbox"]');
                if (!headerCb || !table) {
                    return;
                }
                var pageRows = getTaskTableRowsOnCurrentPage(table);
                if (pageRows.length === 0) {
                    headerCb.checked = false;
                    headerCb.indeterminate = false;
                    return;
                }
                var selectedOnPage = pageRows.filter(function (r) { return r.isSelected(); }).length;
                headerCb.checked = selectedOnPage === pageRows.length;
                headerCb.indeterminate = selectedOnPage > 0 && selectedOnPage < pageRows.length;
            }

            // Bulk actions: all selected rows that still pass the current filter (can span multiple pages if user selected per page)
            table.on("rowSelectionChanged", function(data, rows) {
                const activeRows = table.getRows('active');
                const selectedRows = table.getSelectedRows();
                const activeRowIds = activeRows.map(r => r.getData().id);
                const selectedRowIds = selectedRows.map(r => r.getData().id);
                selectedTasks = selectedRowIds.filter(id => activeRowIds.includes(id));
                
                var count = selectedTasks.length;
                
                if (count > 0) {
                    $('#selected-count').show();
                    $('#count-number').text(count);
                    $('#bulk-actions-btn').removeClass('btn-info').addClass('btn-success');
                    $('#export-selected-btn').removeClass('btn-secondary').addClass('btn-success');
                    $('#export-selected-btn-mobile').removeClass('btn-secondary').addClass('btn-success');
                } else {
                    $('#selected-count').hide();
                    $('#bulk-actions-btn').removeClass('btn-success').addClass('btn-info');
                    $('#export-selected-btn').removeClass('btn-success').addClass('btn-secondary');
                    $('#export-selected-btn-mobile').removeClass('btn-success').addClass('btn-secondary');
                }
                syncTaskTableHeaderSelectAllCheckbox();
            });

            table.on('pageLoaded', syncTaskTableHeaderSelectAllCheckbox);
            table.on('dataFiltered', syncTaskTableHeaderSelectAllCheckbox);


            // Function to load R&R data
            function loadUserRR(userName) {
                console.log('Loading R&R for user:', userName);
                if (!userName) {
                    $('#rr-container').html(
                        '<div class="text-center py-5">' +
                        '<p class="text-muted">Please select a user from the Assignee filter above to view their Role & Responsibility.</p>' +
                        '</div>'
                    );
                    return;
                }

                // Show loading spinner
                $('#rr-loading-spinner').show();
                $('#rr-placeholder').hide();
                $('#rr-container').html(
                    '<div class="text-center py-5">' +
                    '<div class="spinner-border text-primary" role="status">' +
                    '<span class="visually-hidden">Loading...</span>' +
                    '</div>' +
                    '<p class="text-muted mt-3">Loading Role & Responsibility...</p>' +
                    '</div>'
                );

                $.ajax({
                    url: '{{ route("tasks.getUserRR") }}',
                    method: 'GET',
                    data: {
                        user_name: userName
                    },
                    success: function(response) {
                        console.log('R&R data loaded successfully');
                        $('#rr-loading-spinner').hide();
                        $('#rr-container').html(response.html);
                        // Show Edit button if assignee filter is set
                        var assigneeValue = $('#filter-assignee').val();
                        if (assigneeValue && assigneeValue !== '__NULL__') {
                            $('#edit-rr-btn').show();
                        }
                        // Trigger fade-in animation
                        setTimeout(function() {
                            $('.rr-container').css('opacity', '1');
                        }, 50);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading R&R:', error, xhr);
                        $('#rr-loading-spinner').hide();
                        $('#rr-container').html(
                            '<div class="alert alert-danger">' +
                            '<i class="mdi mdi-alert-circle me-2"></i>' +
                            'Error loading Role & Responsibility data. Please try again.<br>' +
                            '<small>Error: ' + error + '</small>' +
                            '</div>'
                        );
                    }
                });
            }


            // Manual tab switching (fallback if Bootstrap tabs don't work)
            function switchTab(tabName) {
                // Hide all tab panes
                $('.tab-pane').removeClass('show active');
                $('.nav-link').removeClass('active').attr('aria-selected', 'false');
                
                if (tabName === 'tasks') {
                    $('#tasks-tab').addClass('active').attr('aria-selected', 'true');
                    $('#tasks-content').addClass('show active');
                } else if (tabName === 'rr') {
                    $('#rr-tab').addClass('active').attr('aria-selected', 'true');
                    $('#rr-content').addClass('show active');
                    
                    // Load R&R data when switching to R&R tab - use assignee filter
                    var assigneeValue = $('#filter-assignee').val();
                    if (assigneeValue && assigneeValue !== '__NULL__') {
                        loadUserRR(assigneeValue);
                    } else {
                        $('#rr-container').html(
                            '<div class="text-center py-5">' +
                            '<p class="text-muted">Please select a user from the Assignee filter above to view their Role & Responsibility.</p>' +
                            '</div>'
                        );
                        $('#edit-rr-btn').hide();
                    }
                }
            }

            // Click handlers for tabs (manual + Bootstrap)
            $('#tasks-tab').on('click', function(e) {
                e.preventDefault();
                console.log('Tasks tab clicked');
                switchTab('tasks');
                $('#edit-rr-btn').hide(); // Hide edit button on Tasks tab
                // Also trigger Bootstrap tab if available
                if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    var tab = new bootstrap.Tab(this);
                    tab.show();
                }
            });

            $('#rr-tab').on('click', function(e) {
                e.preventDefault();
                console.log('R&R tab clicked');
                switchTab('rr');
                // Show edit button if assignee filter is set
                var assigneeValue = $('#filter-assignee').val();
                if (assigneeValue && assigneeValue !== '__NULL__') {
                    $('#edit-rr-btn').show();
                } else {
                    $('#edit-rr-btn').hide();
                }
                // Also trigger Bootstrap tab if available
                if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
                    var tab = new bootstrap.Tab(this);
                    tab.show();
                }
            });

            // Ensure tabs are visible on page load
            console.log('Tabs initialized. Tasks tab:', $('#tasks-tab').length, 'R&R tab:', $('#rr-tab').length);

            // Bootstrap tab event handlers (if Bootstrap is loaded)
            $('#rr-tab').on('shown.bs.tab', function() {
                var assigneeValue = $('#filter-assignee').val();
                if (assigneeValue && assigneeValue !== '__NULL__') {
                    loadUserRR(assigneeValue);
                } else {
                    $('#rr-container').html(
                        '<div class="text-center py-5">' +
                        '<p class="text-muted">Please select a user from the Assignee filter above to view their Role & Responsibility.</p>' +
                        '</div>'
                    );
                }
            });

            // Simple contenteditable editor - no external dependencies
            // Native browser paste with images is supported automatically

            // Edit R&R Button Click Handler
            $('#edit-rr-btn').on('click', function() {
                var assigneeValue = $('#filter-assignee').val();
                if (!assigneeValue || assigneeValue === '__NULL__') {
                    alert('Please select a user from the Assignee filter first');
                    return;
                }

                // Get user ID from assignee filter option
                var selectedOption = $('#filter-assignee option:selected');
                var userId = selectedOption.data('user-id');

                if (!userId) {
                    alert('Could not find user ID. Please try again.');
                    return;
                }

                // Load existing R&R data
                $.ajax({
                    url: '{{ route("tasks.getUserRRData") }}',
                    method: 'GET',
                    data: {
                        user_id: userId
                    },
                    success: function(response) {
                        $('#rr-user-id').val(response.user.id);
                        
                        // Store response data for later use
                        var rrData = response.userRR || {};
                        
                        // Combine all content into one (for backward compatibility)
                        var combinedContent = '';
                        if (rrData.role) combinedContent += '<h3>Role</h3>' + rrData.role + '<br><br>';
                        if (rrData.responsibilities) combinedContent += '<h3>Responsibilities</h3>' + rrData.responsibilities + '<br><br>';
                        if (rrData.goals) combinedContent += '<h3>Goals</h3>' + rrData.goals;
                        // If we have a combined content field, use that instead
                        if (rrData.content) combinedContent = rrData.content;
                        
                        // Show modal and set content
                        $('#editRRModal').modal('show');
                        
                        // Set content in contenteditable div after modal is shown
                        $('#editRRModal').one('shown.bs.modal', function() {
                            $('#rr-content-editor').html(combinedContent);
                        });
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading R&R data:', error);
                        alert('Error loading R&R data. Please try again.');
                    }
                });
            });

            // R&R Form Submission
            $('#rr-form').on('submit', function(e) {
                e.preventDefault();

                // Get content from contenteditable div
                var content = $('#rr-content-editor').html();
                var userId = $('#rr-user-id').val();

                // Debug logging
                console.log('Form submission - User ID:', userId);
                console.log('Form submission - Content length:', content ? content.length : 0);
                console.log('Form submission - Content preview:', content ? content.substring(0, 100) : 'empty');

                // Validate content
                if (!userId) {
                    alert('User ID is missing. Please try again.');
                    return;
                }

                // Store in hidden textarea for form submission
                $('#rr-content-hidden').val(content);

                var formData = {
                    user_id: userId,
                    content: content || '', // Ensure content is not undefined
                    _token: '{{ csrf_token() }}'
                };

                console.log('Sending form data:', {
                    user_id: formData.user_id,
                    content_length: formData.content ? formData.content.length : 0,
                    has_token: !!formData._token
                });

                $.ajax({
                    url: '{{ route("tasks.storeUserRR") }}',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        console.log('Save response:', response);
                        if (response.success) {
                            $('#editRRModal').modal('hide');
                            // Reload R&R display
                            var assigneeValue = $('#filter-assignee').val();
                            if (assigneeValue && assigneeValue !== '__NULL__') {
                                loadUserRR(assigneeValue);
                            }
                            // Show success message
                            alert('Role & Responsibility saved successfully!');
                        } else {
                            alert('Failed to save: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error saving R&R:', error, xhr);
                        console.error('Response text:', xhr.responseText);
                        var errorMsg = 'Error saving R&R data. ';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg += xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            errorMsg += xhr.responseText;
                        } else {
                            errorMsg += 'Please try again.';
                        }
                        alert(errorMsg);
                    }
                });
            });


            // Show CSV Upload Modal
            $('#upload-csv-btn').on('click', function() {
                $('#csvUploadModal').modal('show');
            });

            $('#bulk-task-btn, #bulk-task-btn-mobile').on('click', function() {
                $('#bulkTaskModal').modal('show');
            });

            // Handle CSV Upload
            $('#csv-upload-form').on('submit', function(e) {
                e.preventDefault();
                
                var fileInput = $('#csv-file')[0];
                if (!fileInput.files.length) {
                    alert('Please select a CSV file');
                    return;
                }
                
                var formData = new FormData();
                formData.append('csv_file', fileInput.files[0]);
                formData.append('_token', '{{ csrf_token() }}');
                
                // Show progress
                $('#upload-progress').show();
                $('#upload-csv-submit').prop('disabled', true);
                $('#upload-result').hide();
                
                $.ajax({
                    url: '/tasks/import-csv',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#upload-progress').hide();
                        $('#upload-csv-submit').prop('disabled', false);
                        
                        var resultHtml = `
                            <div class="alert alert-success">
                                <h6 class="alert-heading"><i class="mdi mdi-check-circle me-2"></i>Import Successful!</h6>
                                <p class="mb-0">✅ ${response.imported} task(s) imported successfully</p>
                                ${response.skipped > 0 ? '<p class="mb-0">⚠️ ' + response.skipped + ' row(s) skipped due to errors</p>' : ''}
                            </div>
                        `;
                        
                        $('#upload-result').html(resultHtml).show();
                        
                            setTimeout(function() {
                                $('#csvUploadModal').modal('hide');
                                $('#csv-upload-form')[0].reset();
                                $('#upload-result').hide();
                                table.replaceData(); // Refresh table data
                            }, 2000);
                    },
                    error: function(xhr) {
                        $('#upload-progress').hide();
                        $('#upload-csv-submit').prop('disabled', false);
                        
                        var errorMsg = xhr.responseJSON?.message || 'Upload failed. Please check your CSV format.';
                        var resultHtml = `
                            <div class="alert alert-danger">
                                <h6 class="alert-heading"><i class="mdi mdi-alert-circle me-2"></i>Import Failed</h6>
                                <p class="mb-0">${errorMsg}</p>
                            </div>
                        `;
                        $('#upload-result').html(resultHtml).show();
                    }
                });
            });

            // Show Bulk Actions Modal
            $('#bulk-actions-btn, #bulk-actions-btn-mobile').on('click', function() {
                if (selectedTasks.length === 0) {
                    // Show error notification
                    var alertHtml = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-alert-circle me-2"></i><strong>Error!</strong> Please select at least one task to perform bulk actions.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    $('.task-card .card-body').prepend(alertHtml);
                    
                    // Auto dismiss after 4 seconds
                    setTimeout(function() {
                        $('.alert-danger').fadeOut();
                    }, 4000);
                    
                    return;
                }
                
                $('#bulk-selected-count').text(selectedTasks.length);
                $('#bulk-assignee-count').text(selectedTasks.length);
                $('#bulk-assignor-count').text(selectedTasks.length);
                $('#bulkActionsModal').modal('show');
            });

            $('#export-selected-btn, #export-selected-btn-mobile').on('click', function() {
                if (selectedTasks.length === 0) {
                    var alertHtml = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="mdi mdi-alert-circle me-2"></i><strong>Error!</strong> Please select at least one task to export.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                    $('.task-card .card-body').prepend(alertHtml);
                    setTimeout(function() {
                        $('.alert-danger').fadeOut();
                    }, 4000);
                    return;
                }

                exportSelectedTasksCsv();
            });
            
            // Bulk Assign Assignee
            $('#bulk-assign-assignee-btn').on('click', function(e) {
                e.preventDefault();
                $('#bulkActionsModal').modal('hide');
                $('#bulkAssigneeModal').modal('show');
            });
            
            $('#confirm-bulk-assignee-btn').on('click', function() {
                console.log('🔍 Bulk Assignee Confirm clicked');
                console.log('Looking for checkboxes with class: .bulk-assignee-checkbox');
                console.log('Total checkboxes found:', $('.bulk-assignee-checkbox').length);
                console.log('Checked checkboxes:', $('.bulk-assignee-checkbox:checked').length);
                
                // Debug: Show all checkbox values
                $('.bulk-assignee-checkbox').each(function(i) {
                    console.log(`Checkbox ${i}: checked=${$(this).is(':checked')}, value="${$(this).val()}"`);
                });
                
                const selectedEmails = [];
                $('.bulk-assignee-checkbox:checked').each(function() {
                    const val = $(this).val();
                    console.log('✓ Checked box has value:', val);
                    if (val) {
                        selectedEmails.push(val);
                    }
                });
                
                console.log('✅ Collected emails array:', selectedEmails);
                
                if (selectedEmails.length === 0) {
                    alert('❌ No assignees selected!\n\n' +
                          'Checkboxes found: ' + $('.bulk-assignee-checkbox').length + '\n' +
                          'Checkboxes checked: ' + $('.bulk-assignee-checkbox:checked').length + '\n\n' +
                          'Please click the checkbox squares (☐) next to user names!');
                    return;
                }
                
                const assigneeEmails = selectedEmails.join(', ');
                console.log('✅ Final email string to send:', assigneeEmails);
                console.log('✅ Bulk assigning to tasks:', selectedTasks);
                
                if (selectedTasks.length === 0) {
                    alert('❌ No tasks selected!');
                    return;
                }
                
                console.log('📤 Calling bulkUpdate with:', {action: 'assign_assignee', assignee: assigneeEmails});
                bulkUpdate('assign_assignee', { assignee: assigneeEmails });
                $('#bulkAssigneeModal').modal('hide');
                
                // Clear selections
                $('.bulk-assignee-checkbox').prop('checked', false);
            });
            
            // Bulk Assign Assignor (Admin only)
            $('#bulk-assign-assignor-btn').on('click', function(e) {
                e.preventDefault();
                $('#bulkActionsModal').modal('hide');
                $('#bulkAssignorModal').modal('show');
            });
            
            $('#confirm-bulk-assignor-btn').on('click', function() {
                const assignorEmail = $('#bulk-assignor-select').val();
                
                if (!assignorEmail) {
                    alert('Please select an assignor');
                    return;
                }
                
                console.log('Bulk assigning assignor:', assignorEmail, 'to tasks:', selectedTasks);
                
                bulkUpdate('assign_assignor', { assignor: assignorEmail });
                $('#bulkAssignorModal').modal('hide');
            });

            // Bulk Delete (no confirmation)
            $('#bulk-delete-btn').on('click', function(e) {
                e.preventDefault();
                bulkUpdate('delete', {});
            });

            // Bulk Change Priority
            $('#bulk-priority-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'priority';
                var html = `
                    <p class="mb-3"><strong>Select new priority for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-priority-select" class="form-label">Priority:</label>
                        <select class="form-select" id="bulk-priority-select">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>
                `;
                showBulkUpdateForm('Change Priority', html);
            });

            // Bulk Change TID
            $('#bulk-tid-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'tid';
                var html = `
                    <p class="mb-3"><strong>Set new TID date for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-tid-input" class="form-label">TID (Task Initiation Date):</label>
                        <input type="datetime-local" class="form-control" id="bulk-tid-input" required>
                    </div>
                `;
                showBulkUpdateForm('Change TID Date', html);
            });

            // Bulk Change Assignee
            $('#bulk-assignee-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'assignee';
                
                // Fetch users via AJAX
                $.ajax({
                    url: '/tasks/create',
                    type: 'GET',
                    success: function(response) {
                        var usersSelect = '<option value="">Please Select</option>';
                        // This is a workaround - we'll need to create an API endpoint
                        // For now, let's create a simple input
                        var html = `
                            <p class="mb-3"><strong>Change assignee for ${selectedTasks.length} task(s):</strong></p>
                            <div class="mb-3">
                                <label for="bulk-assignee-select" class="form-label">Assignee:</label>
                                <select class="form-select" id="bulk-assignee-select">
                                    <option value="">Loading users...</option>
                                </select>
                            </div>
                        `;
                        showBulkUpdateForm('Change Assignee', html);
                        loadUsersForBulk();
                    }
                });
            });

            // Bulk Update ETC
            $('#bulk-etc-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'etc';
                var html = `
                    <p class="mb-3"><strong>Update ETC for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-etc-input" class="form-label">ETC (Minutes):</label>
                        <input type="number" class="form-control" id="bulk-etc-input" min="1" placeholder="e.g., 30" required>
                    </div>
                `;
                showBulkUpdateForm('Update ETC', html);
            });

            // Bulk Update Group
            $('#bulk-group-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'group';
                var html = `
                    <p class="mb-3"><strong>Change group for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-group-input" class="form-label">Group:</label>
                        <input type="text" class="form-control" id="bulk-group-input" placeholder="Enter group name (leave empty to clear)" maxlength="255">
                        <small class="text-muted">Leave empty to clear the group</small>
                    </div>
                `;
                showBulkUpdateForm('Change Group', html);
            });

            // Bulk Update Task Title
            $('#bulk-task-title-btn').on('click', function(e) {
                e.preventDefault();
                bulkActionType = 'task';
                var html = `
                    <p class="mb-3"><strong>Change task title for ${selectedTasks.length} task(s):</strong></p>
                    <div class="mb-3">
                        <label for="bulk-task-title-input" class="form-label">Task Title:</label>
                        <input type="text" class="form-control" id="bulk-task-title-input" placeholder="Enter new task title" maxlength="500" required>
                        <small class="text-muted">This will replace the title for all selected tasks</small>
                    </div>
                `;
                showBulkUpdateForm('Change Task Title', html);
            });

            // Show Bulk Update Form
            function showBulkUpdateForm(title, content) {
                $('#bulkActionsModal').modal('hide');
                $('#bulkUpdateModalTitle').text(title);
                $('#bulkUpdateModalBody').html(content);
                $('#bulkUpdateModal').modal('show');
            }

            // Confirm Bulk Update
            $('#confirm-bulk-update-btn').on('click', function() {
                var data = {};
                
                switch(bulkActionType) {
                    case 'priority':
                        data.priority = $('#bulk-priority-select').val();
                        break;
                    case 'tid':
                        data.tid = $('#bulk-tid-input').val();
                        if (!data.tid) {
                            alert('Please select a date and time');
                            return;
                        }
                        break;
                    case 'assignee':
                        data.assignee_id = $('#bulk-assignee-select').val();
                        if (!data.assignee_id) {
                            alert('Please select an assignee');
                            return;
                        }
                        break;
                    case 'etc':
                        data.etc_minutes = $('#bulk-etc-input').val();
                        if (!data.etc_minutes || data.etc_minutes <= 0) {
                            alert('Please enter a valid ETC value');
                            return;
                        }
                        break;
                    case 'group':
                        data.group = $('#bulk-group-input').val();
                        break;
                    case 'task':
                        data.task_title = $('#bulk-task-title-input').val();
                        if (!data.task_title || data.task_title.trim() === '') {
                            alert('Please enter a task title');
                            return;
                        }
                        break;
                }
                
                bulkUpdate(bulkActionType, data);
            });

            // Bulk Update Function
            function bulkUpdate(action, data) {
                $.ajax({
                    url: '/tasks/bulk-update',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        action: action,
                        task_ids: selectedTasks,
                        ...data
                    },
                    success: function(response) {
                        $('#bulkUpdateModal').modal('hide');
                        $('#bulkActionsModal').modal('hide');
                        table.deselectRow();
                        table.replaceData();
                        
                        var alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('.task-card .card-body').prepend(alertHtml);
                        
                        setTimeout(function() {
                            $('.alert').fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || xhr.statusText || 'Something went wrong';
                        console.error('❌ Bulk update failed:', xhr);
                        
                        // Show instant error notification
                        const errorHtml = `
                            <div class="alert alert-danger alert-dismissible position-fixed fade show" 
                                 style="top: 20px; left: 20px; right: 20px; z-index: 9999;" 
                                 role="alert">
                                <h5 class="alert-heading">
                                    <i class="mdi mdi-alert-circle me-2"></i>Bulk Update Failed
                                </h5>
                                <p><strong>Error:</strong> ${errorMsg}</p>
                                <p class="mb-0"><small>Status: ${xhr.status} - ${xhr.statusText}</small></p>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        `;
                        $('body').prepend(errorHtml);
                        
                        // Auto dismiss after 8 seconds
                        setTimeout(function() {
                            $('.alert-danger').fadeOut();
                        }, 8000);
                    }
                });
            }

            // Load Users for Bulk Assignee Change
            function loadUsersForBulk() {
                $.ajax({
                    url: '/tasks/users-list',
                    type: 'GET',
                    success: function(users) {
                        var options = '<option value="">Please Select</option>';
                        users.forEach(function(user) {
                            options += `<option value="${user.id}">${user.name}</option>`;
                        });
                        $('#bulk-assignee-select').html(options);
                    }
                });
            }

            function syncViewTaskReworkButton(taskId, data) {
                var status = (data && data.status) ? String(data.status) : '';
                var aid = data && data.assignor_id != null ? parseInt(data.assignor_id, 10) : NaN;
                var canRework = isAdmin || canDeleteAnyTask || (!isNaN(aid) && aid === currentUserId);
                var disallowed = status === 'Rework' || status === 'Archived';
                var $btn = $('#view-task-rework-btn');
                $btn.data('task-id', taskId);
                if (canRework && !disallowed) {
                    $btn.show();
                } else {
                    $btn.hide();
                }
            }

            $('#viewTaskModal').on('hidden.bs.modal', function () {
                $('#view-task-rework-btn').hide().removeData('task-id');
            });

            $(document).on('click', '#view-task-rework-btn', function () {
                var tid = $(this).data('task-id');
                if (tid) {
                    openReworkModalForTask(tid);
                }
            });

            $('#statusChangeModal').on('hidden.bs.modal', function () {
                $('#status-change-reason').attr('placeholder', 'Why are you changing the status?');
            });

            // Expand Task Info (show full title and description)
            $(document).on('click', '.expand-task-info', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var taskId = $(this).data('id');
                console.log('Expanding task:', taskId);
                
                $.ajax({
                    url: '/tasks/' + taskId,
                    type: 'GET',
                    success: function(response) {
                        console.log('Task data loaded:', response);
                        $('#task-info-title').text(response.title || 'No title');
                        $('#task-info-description').text(response.description || 'No description available.');
                        var infoReason = response.rework_reason != null ? String(response.rework_reason).trim() : '';
                        if (infoReason) {
                            $('#task-info-reason').text(infoReason);
                            $('#task-info-reason-wrap').show();
                        } else {
                            $('#task-info-reason').text('');
                            $('#task-info-reason-wrap').hide();
                        }
                        $('#taskInfoModal').modal('show');
                    },
                    error: function(xhr) {
                        console.error('Error loading task:', xhr);
                        alert('Failed to load task details');
                    }
                });
            });

            // View Task - use row data from table first (has link1-9), else fetch from API
            $(document).on('click', '.view-task', function() {
                var taskId = $(this).data('id');
                var row = table.getRow(taskId);
                var data = row ? row.getData() : null;
                if (data) {
                    // Build modal from table row: L1/L2=link1/link2, PL=link8, process=link9; link3-7=training,video,form,form_report,checklist
                    var l1Val = data.l1 || data.link1 || '';
                    var l2Val = data.l2 || data.link2 || '';
                    var plVal = data.pl || data.link8 || '';
                    var training = data.training_link || data.link3 || '';
                    var video = data.video_link || data.link4 || '';
                    var form = data.form_link || data.link5 || '';
                    var formReport = data.form_report_link || data.link6 || '';
                    var checklist = data.checklist_link || data.link7 || '';
                    var escapeHtml = function(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
                    var linkCell = function(url, text) {
                        if (!url || !String(url).trim()) return '<span style="color: #adb5bd;">-</span>';
                        var u = String(url).trim();
                        var t = text || u;
                        return '<a href="' + escapeHtml(u) + '" target="_blank" style="color: #0d6efd;">' + escapeHtml(t) + '</a>';
                    };
                    var reasonRow = '';
                    var rr = data.rework_reason != null ? String(data.rework_reason).trim() : '';
                    if (rr) {
                        reasonRow = '<tr><th width="200" style="color: #6c757d; font-weight: 600; vertical-align: top;">Note / reason:</th><td style="white-space: pre-wrap;">' + escapeHtml(rr) + '</td></tr>';
                    }
                    var completionReport = data.report != null ? String(data.report).trim() : '';
                    var completionRef = data.reference_link != null ? String(data.reference_link).trim() : '';
                    var completionReportRow = '';
                    if (completionReport) {
                        completionReportRow = '<tr><th width="200" style="color: #6c757d; font-weight: 600; vertical-align: top;">Completion report:</th><td style="white-space: pre-wrap;">' + escapeHtml(completionReport) + '</td></tr>';
                    }
                    var completionRefRow = '';
                    if (completionRef) {
                        completionRefRow = '<tr><th width="200" style="color: #6c757d; font-weight: 600;">Reference link:</th><td>' + linkCell(completionRef, completionRef) + '</td></tr>';
                    }
                    var html = '<div style="padding: 10px;">' +
                        '<table class="table table-borderless">' +
                        reasonRow +
                        completionReportRow +
                        completionRefRow +
                        '<tr><th width="200" style="color: #6c757d; font-weight: 600;">L1:</th><td>' + (l1Val ? linkCell(l1Val) : '<span style="color: #adb5bd;">-</span>') + '</td></tr>' +
                        '<tr><th style="color: #6c757d; font-weight: 600;">L2:</th><td>' + (l2Val ? linkCell(l2Val) : '<span style="color: #adb5bd;">-</span>') + '</td></tr>' +
                        '<tr><th style="color: #6c757d; font-weight: 600;">Training Link:</th><td>' + linkCell(training) + '</td></tr>' +
                        '<tr><th style="color: #6c757d; font-weight: 600;">Video Link:</th><td>' + linkCell(video) + '</td></tr>' +
                        '<tr><th style="color: #6c757d; font-weight: 600;">Form Link:</th><td>' + linkCell(form) + '</td></tr>' +
                        '<tr><th style="color: #6c757d; font-weight: 600;">Form Report Link:</th><td>' + linkCell(formReport) + '</td></tr>' +
                        '<tr><th style="color: #6c757d; font-weight: 600;">Checklist Link:</th><td>' + linkCell(checklist) + '</td></tr>' +
                        '<tr><th style="color: #6c757d; font-weight: 600;">PL:</th><td>' + (plVal ? linkCell(plVal) : '<span style="color: #adb5bd;">-</span>') + '</td></tr>' +
                        (data.image ? '<tr><th style="color: #6c757d; font-weight: 600;">Image:</th><td><img src="/uploads/tasks/' + escapeHtml(data.image) + '" class="img-thumbnail" style="max-width: 300px; border-radius: 8px;"></td></tr>' : '') +
                        '</table></div>';
                    syncViewTaskReworkButton(taskId, data);
                    $('#task-details').html(html);
                    $('#viewTaskModal').modal('show');
                } else {
                    $.ajax({
                        url: '/tasks/' + taskId,
                        type: 'GET',
                        success: function(response) {
                            var l1Val = response.l1 || response.link1 || '';
                            var l2Val = response.l2 || response.link2 || '';
                            var plVal = response.pl || response.link8 || '';
                            var training = response.training_link || response.link3 || '';
                            var video = response.video_link || response.link4 || '';
                            var form = response.form_link || response.link5 || '';
                            var formReport = response.form_report_link || response.link6 || '';
                            var checklist = response.checklist_link || response.link7 || '';
                            var escapeHtml = function(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); };
                            var linkCell = function(url) {
                                if (!url || !String(url).trim()) return '<span style="color: #adb5bd;">-</span>';
                                var u = String(url).trim();
                                return '<a href="' + escapeHtml(u) + '" target="_blank" style="color: #0d6efd;">' + escapeHtml(u) + '</a>';
                            };
                            var reasonRowAjax = '';
                            var rrAjax = response.rework_reason != null ? String(response.rework_reason).trim() : '';
                            if (rrAjax) {
                                reasonRowAjax = '<tr><th width="200" style="color: #6c757d; font-weight: 600; vertical-align: top;">Note / reason:</th><td style="white-space: pre-wrap;">' + escapeHtml(rrAjax) + '</td></tr>';
                            }
                            var completionReportAjax = response.report != null ? String(response.report).trim() : '';
                            var completionRefAjax = response.reference_link != null ? String(response.reference_link).trim() : '';
                            var completionReportRowAjax = '';
                            if (completionReportAjax) {
                                completionReportRowAjax = '<tr><th width="200" style="color: #6c757d; font-weight: 600; vertical-align: top;">Completion report:</th><td style="white-space: pre-wrap;">' + escapeHtml(completionReportAjax) + '</td></tr>';
                            }
                            var completionRefRowAjax = '';
                            if (completionRefAjax) {
                                completionRefRowAjax = '<tr><th width="200" style="color: #6c757d; font-weight: 600;">Reference link:</th><td>' + linkCell(completionRefAjax) + '</td></tr>';
                            }
                            var html = '<div style="padding: 10px;">' +
                                '<table class="table table-borderless">' +
                                reasonRowAjax +
                                completionReportRowAjax +
                                completionRefRowAjax +
                                '<tr><th width="200" style="color: #6c757d; font-weight: 600;">L1:</th><td>' + (l1Val ? linkCell(l1Val) : '<span style="color: #adb5bd;">-</span>') + '</td></tr>' +
                                '<tr><th style="color: #6c757d; font-weight: 600;">L2:</th><td>' + (l2Val ? linkCell(l2Val) : '<span style="color: #adb5bd;">-</span>') + '</td></tr>' +
                                '<tr><th style="color: #6c757d; font-weight: 600;">Training Link:</th><td>' + linkCell(training) + '</td></tr>' +
                                '<tr><th style="color: #6c757d; font-weight: 600;">Video Link:</th><td>' + linkCell(video) + '</td></tr>' +
                                '<tr><th style="color: #6c757d; font-weight: 600;">Form Link:</th><td>' + linkCell(form) + '</td></tr>' +
                                '<tr><th style="color: #6c757d; font-weight: 600;">Form Report Link:</th><td>' + linkCell(formReport) + '</td></tr>' +
                                '<tr><th style="color: #6c757d; font-weight: 600;">Checklist Link:</th><td>' + linkCell(checklist) + '</td></tr>' +
                                '<tr><th style="color: #6c757d; font-weight: 600;">PL:</th><td>' + (plVal ? linkCell(plVal) : '<span style="color: #adb5bd;">-</span>') + '</td></tr>' +
                                (response.image ? '<tr><th style="color: #6c757d; font-weight: 600;">Image:</th><td><img src="/uploads/tasks/' + escapeHtml(response.image) + '" class="img-thumbnail" style="max-width: 300px; border-radius: 8px;"></td></tr>' : '') +
                                '</table></div>';
                            syncViewTaskReworkButton(taskId, response);
                            $('#task-details').html(html);
                            $('#viewTaskModal').modal('show');
                        }
                    });
                }
            });

            // Edit Task
            $(document).on('click', '.edit-task', function() {
                var taskId = $(this).data('id');
                window.location.href = '/tasks/' + taskId + '/edit';
            });

            // Delete Task
            $(document).on('click', '.delete-task', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var taskId = $(this).data('id');
                
                $.ajax({
                    url: '/tasks/' + taskId,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        table.replaceData();
                            
                            // Show success message
                            var alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>${response.message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            $('.task-card .card-body').prepend(alertHtml);
                            
                            // Auto dismiss after 3 seconds
                            setTimeout(function() {
                                $('.alert').fadeOut(function() { $(this).remove(); });
                            }, 3000);
                        },
                    error: function(xhr, status, error) {
                        console.error('Delete failed:', xhr.responseJSON);
                        var errorMsg = xhr.responseJSON?.message || 'Failed to delete task. You may not have permission.';
                        alert('Error: ' + errorMsg);
                    }
                });
            });

            // Handle Status Change
            var currentTaskId = null;
            var previousStatus = null;
            var newStatusValue = null;

            function openReworkModalForTask(taskId) {
                var row = table.getRow(taskId);
                var rowData = row ? row.getData() : null;
                currentTaskId = taskId;
                newStatusValue = 'Rework';
                previousStatus = rowData && rowData.status ? rowData.status : 'Todo';
                $('#new-status-label').text('Rework');
                $('#status-change-reason').attr('placeholder', 'What should the assignee change or redo?');
                $('#viewTaskModal').modal('hide');
                $('#statusChangeModal').modal('show');
            }

            $(document).on('click', '.open-rework-from-actions', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var tid = $(this).data('id');
                if (tid) {
                    openReworkModalForTask(tid);
                }
            });
            
            var statusLabels = {
                'Todo': 'Todo',
                'Working': 'Working',
                'Archived': 'Archived',
                'Done': 'Done',
                'Need Help': 'Need Help',
                'Need Approval': 'Need Approval',
                'Dependent': 'Dependent',
                'Approved': 'Approved',
                'Hold': 'Hold',
                'Rework': 'Rework'
            };
            
            $(document).on('change', '.status-select', function() {
                var select = $(this);
                newStatusValue = select.val();
                currentTaskId = select.data('task-id');
                previousStatus = select.data('current-status');
                
                if (newStatusValue === 'Done') {
                    // Show Done Modal (ask for ATC)
                    $('#doneModal').modal('show');
                    select.val(previousStatus);
                } else {
                    // Show Status Change Modal (ask for reason)
                    var statusLabel = statusLabels[newStatusValue] || newStatusValue;
                    $('#new-status-label').text(statusLabel);
                    $('#statusChangeModal').modal('show');
                    select.val(previousStatus);
                }
            });

            // Confirm Done — POST /tasks/{id}/complete (report + ATC required, reference link optional)
            $('#confirm-done-btn').on('click', function() {
                $('#done-modal-errors').addClass('d-none').text('');
                $('#task-done-report').removeClass('is-invalid');
                $('#task-done-report-feedback').addClass('d-none');
                $('#task-done-atc').removeClass('is-invalid');
                $('#task-done-atc-feedback').addClass('d-none');

                var report = $('#task-done-report').val().trim();
                if (!report) {
                    $('#task-done-report').addClass('is-invalid');
                    $('#task-done-report-feedback').removeClass('d-none');
                    return;
                }

                var atcRaw = $('#task-done-atc').val();
                var atcNum = parseInt(atcRaw, 10);
                if (!atcRaw || isNaN(atcNum) || atcNum <= 0) {
                    $('#task-done-atc').addClass('is-invalid');
                    $('#task-done-atc-feedback').removeClass('d-none');
                    return;
                }
                if (String(atcRaw).length > 10 || atcNum > 9999999999) {
                    $('#task-done-atc').addClass('is-invalid');
                    $('#task-done-atc-feedback').removeClass('d-none');
                    return;
                }

                var refLink = $('#task-done-reference-link').val().trim();
                var $btn = $(this);
                $btn.prop('disabled', true);

                $.ajax({
                    url: '/tasks/' + currentTaskId + '/complete',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        report: report,
                        reference_link: refLink || '',
                        atc: atcNum
                    },
                    success: function(response) {
                        $('#doneModal').modal('hide');
                        $('#task-done-report').val('');
                        $('#task-done-reference-link').val('');
                        $('#task-done-atc').val('');
                        table.replaceData();

                        var alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>${response.message || 'Task completed successfully!'}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                        $('.task-card .card-body .alert').remove();
                        $('.task-card .card-body').prepend(alertHtml);
                        setTimeout(function() {
                            $('.alert').fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    },
                    error: function(xhr) {
                        if (xhr.status === 422 && xhr.responseJSON) {
                            var e = xhr.responseJSON.errors || {};
                            var parts = [];
                            if (e.report) {
                                parts = parts.concat(e.report);
                            }
                            if (e.reference_link) {
                                parts = parts.concat(e.reference_link);
                            }
                            if (e.atc) {
                                parts = parts.concat(e.atc);
                            }
                            if (xhr.responseJSON.message && parts.length === 0) {
                                parts.push(xhr.responseJSON.message);
                            }
                            if (parts.length) {
                                $('#done-modal-errors').removeClass('d-none').html(parts.map(function(p) {
                                    return $('<div/>').text(p).html();
                                }).join('<br>'));
                                return;
                            }
                        }
                        var msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Could not complete the task. Please try again.';
                        alert(msg);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            $('#doneModal').on('hidden.bs.modal', function () {
                $('#task-done-report').val('');
                $('#task-done-reference-link').val('');
                $('#task-done-atc').val('');
                $('#done-modal-errors').addClass('d-none').text('');
                $('#task-done-report').removeClass('is-invalid');
                $('#task-done-report-feedback').addClass('d-none');
                $('#task-done-atc').removeClass('is-invalid');
                $('#task-done-atc-feedback').addClass('d-none');
            });

            // Confirm Status Change
            $('#confirm-status-change-btn').on('click', function() {
                var reason = $('#status-change-reason').val().trim();
                if (!reason) {
                    alert('Please provide a reason for this status change.');
                    return;
                }
                
                var finalStatus = newStatusValue;
                updateTaskStatus(currentTaskId, finalStatus, null, reason);
                $('#statusChangeModal').modal('hide');
                $('#status-change-reason').val('');
            });

            // Update Task Status Function
            function updateTaskStatus(taskId, status, atc = null, reworkReason = null) {
                var data = {
                    _token: '{{ csrf_token() }}',
                    status: status
                };
                
                if (atc) {
                    data.atc = atc;
                }
                
                if (reworkReason) {
                    data.rework_reason = reworkReason;
                }
                
                $.ajax({
                    url: '/tasks/' + taskId + '/update-status',
                    type: 'POST',
                    data: data,
                        success: function(response) {
                            // Update table data without page reload
                            table.replaceData();
                            
                            // Show success message
                            var message = (status === 'Rework' && reworkReason) ? 'Task marked for rework' : 'Status updated successfully!';
                            var alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="mdi mdi-check-circle me-2"></i>${message}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            $('.task-card .card-body .alert').remove(); // Remove old alerts
                            $('.task-card .card-body').prepend(alertHtml);
                            
                            // Auto dismiss after 3 seconds
                            setTimeout(function() {
                                $('.alert').fadeOut(function() { $(this).remove(); });
                            }, 3000);
                        },
                    error: function(xhr) {
                        alert('Error updating status. Please try again.');
                        table.replaceData();
                    }
                });
            }
        });
        
        // History Chart functionality for stat cards
        let taskHistoryChart = null;
        let currentTaskMetric = null;
        let currentTaskPeriod = 7;

        // Defensive cleanup so a stuck/orphan modal-backdrop doesn't darken the page after close.
        function forceCleanupModalBackdrop() {
            // If no modals are still visible, strip any leftover backdrops + body lock.
            const anyOpen = document.querySelector('.modal.show');
            if (!anyOpen) {
                document.querySelectorAll('.modal-backdrop').forEach(el => el.parentNode && el.parentNode.removeChild(el));
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }
        }
        ['taskHistoryChartModal', 'tatChartModal', 'missedChartModal'].forEach(function(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('hidden.bs.modal', function() {
                if (id === 'taskHistoryChartModal' && taskHistoryChart) {
                    try { taskHistoryChart.destroy(); } catch (e) {}
                    taskHistoryChart = null;
                }
                if (id === 'tatChartModal' && typeof tatLineChart !== 'undefined' && tatLineChart) {
                    try { tatLineChart.destroy(); } catch (e) {}
                    tatLineChart = null;
                }
                if (id === 'missedChartModal' && typeof missedLineChart !== 'undefined' && missedLineChart) {
                    try { missedLineChart.destroy(); } catch (e) {}
                    missedLineChart = null;
                }
                // Run cleanup on next tick so Bootstrap finishes its own teardown first.
                setTimeout(forceCleanupModalBackdrop, 50);
            });
        });

        // Stat card click handlers
        document.querySelectorAll('.task-stat-trigger').forEach(card => {
            card.addEventListener('click', function() {
                currentTaskMetric = this.getAttribute('data-metric');
                const currentValue = parseFloat(this.getAttribute('data-value')) || 0;
                showTaskHistoryChart(currentTaskMetric, currentTaskPeriod, currentValue);
            });
        });

        // Period selector buttons
        document.querySelectorAll('#taskHistoryChartModal [data-period]').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#taskHistoryChartModal [data-period]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTaskPeriod = parseInt(this.getAttribute('data-period'));
                if (currentTaskMetric) {
                    const currentValue = parseFloat(document.querySelector(`.task-stat-trigger[data-metric="${currentTaskMetric}"]`).getAttribute('data-value')) || 0;
                    showTaskHistoryChart(currentTaskMetric, currentTaskPeriod, currentValue);
                }
            });
        });

        function showTaskHistoryChart(metric, period, currentValue) {
            const modalEl = document.getElementById('taskHistoryChartModal');
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            
            // Set title based on metric
            const titles = {
                'total': 'Total Tasks History',
                'overdue': 'Overdue Tasks History',
                'etc': 'ETC 30D History (Hours)',
                'atc': 'ATC 30D History (Hours)',
                'tat': 'TAT History (Days)',
                'score': 'Average Score History',
                'missed': 'Missed Tasks History',
                'pending_etc': 'Pending ETC History (Hours)'
            };
            document.getElementById('taskHistoryChartTitle').textContent = titles[metric] || 'History Trend';
            
            // Generate historical data
            const data = generateTaskHistoricalData(metric, period, currentValue);
            
            // Destroy existing chart if any
            if (taskHistoryChart) {
                taskHistoryChart.destroy();
            }
            
            // Create new chart
            const ctx = document.getElementById('taskHistoryChart').getContext('2d');
            const numericHist = data.values.filter(v => v != null && !isNaN(v));
            let histMin = numericHist.length ? Math.min(...numericHist) : 0;
            let histMax = numericHist.length ? Math.max(...numericHist) : 1;
            if (histMin === histMax) { histMax = histMin + 1; }
            const colors = {
                'total': { border: '#667eea', bg: 'rgba(102, 126, 234, 0.1)' },
                'overdue': { border: '#f5576c', bg: 'rgba(245, 87, 108, 0.1)' },
                'etc': { border: '#f7b733', bg: 'rgba(247, 183, 51, 0.1)' },
                'atc': { border: '#0891b2', bg: 'rgba(8, 145, 178, 0.1)' },
                'tat': { border: '#0dcaf0', bg: 'rgba(13, 202, 240, 0.1)' },
                'score': { border: '#38ef7d', bg: 'rgba(56, 239, 125, 0.1)' },
                'missed': { border: '#dc3545', bg: 'rgba(220, 53, 69, 0.1)' },
                'pending_etc': { border: '#0dcaf0', bg: 'rgba(13, 202, 240, 0.1)' }
            };
            
            taskHistoryChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: titles[metric],
                        data: data.values,
                        borderColor: colors[metric].border,
                        backgroundColor: colors[metric].bg,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: colors[metric].border,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14,
                                    weight: 'bold'
                                },
                                padding: 20
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14,
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (metric === 'score') {
                                        label += context.parsed.y.toFixed(2);
                                    } else if (metric === 'etc' || metric === 'atc') {
                                        label += context.parsed.y + 'h';
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            min: histMin,
                            max: histMax,
                            ticks: {
                                font: {
                                    size: 12
                                },
                                callback: function(value) {
                                    if (metric === 'etc' || metric === 'atc') {
                                        return value + 'h';
                                    } else if (metric === 'score') {
                                        return value.toFixed(1);
                                    }
                                    return value;
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                font: {
                                    size: 11
                                },
                                maxRotation: 45,
                                minRotation: 45
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            modal.show();
        }

        function generateTaskHistoricalData(metric, period, currentValue) {
            const labels = [];
            const values = [];
            const today = new Date();
            
            // Generate dates
            for (let i = period - 1; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            }
            
            // Generate historical trend data
            for (let i = 0; i < period; i++) {
                const variance = (Math.random() - 0.5) * 0.3; // ±15% variance
                const trendFactor = (i / period); // Trend towards current value
                let historicalValue;
                
                if (metric === 'score') {
                    // Score ranges from 0-5
                    historicalValue = Math.max(0, Math.min(5, currentValue * (0.7 + variance + trendFactor * 0.3)));
                    historicalValue = parseFloat(historicalValue.toFixed(2));
                } else {
                    historicalValue = Math.max(0, Math.round(currentValue * (0.7 + variance + trendFactor * 0.3)));
                }
                values.push(historicalValue);
            }
            
            // Ensure last value is current value
            if (metric === 'score') {
                values[values.length - 1] = parseFloat(currentValue.toFixed(2));
            } else {
                values[values.length - 1] = currentValue;
            }
            
            return { labels, values };
        }

        // ===== Training Video header icon: view (click) / edit link (double-click) =====
        $(function () {
            var $icon = $('#training-video-icon');
            if (!$icon.length) return;

            var saveUrl = '{{ route("tasks.trainingVideo.save") }}';
            var csrfToken = '{{ csrf_token() }}';
            var clickTimer = null;

            function getLink() { return ($icon.attr('data-link') || '').trim(); }
            function canEdit() { return $icon.attr('data-can-edit') === '1'; }

            // Build an embeddable player from a URL (YouTube / Vimeo / direct file).
            function buildPlayerHtml(url) {
                var common = 'width:100%;height:100%;border:0;display:block;';
                var yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
                if (yt) {
                    return '<iframe src="https://www.youtube.com/embed/' + yt[1] + '?rel=0&autoplay=1" style="' + common + '" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>';
                }
                var vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
                if (vimeo) {
                    return '<iframe src="https://player.vimeo.com/video/' + vimeo[1] + '?autoplay=1" style="' + common + '" allow="autoplay; fullscreen" allowfullscreen></iframe>';
                }
                if (/\.(mp4|webm|ogg|mov)(\?.*)?$/i.test(url)) {
                    return '<video src="' + url.replace(/"/g, '&quot;') + '" style="' + common + 'object-fit:contain;background:#000;" controls autoplay></video>';
                }
                // Fallback: try to embed the URL directly in an iframe.
                return '<iframe src="' + url.replace(/"/g, '&quot;') + '" style="' + common + '" allow="autoplay; fullscreen" allowfullscreen></iframe>';
            }

            function openViewModal() {
                var link = getLink();
                var $wrap = $('#training-video-frame-wrap');
                var $empty = $('#training-video-empty');
                if (link) {
                    $wrap.html(buildPlayerHtml(link)).removeClass('d-none');
                    $empty.addClass('d-none');
                } else {
                    $wrap.empty().addClass('d-none');
                    $empty.removeClass('d-none');
                }
                $('#trainingVideoModal').modal('show');
            }

            function openEditModal() {
                $('#training-video-link-input').val(getLink()).removeClass('is-invalid');
                $('#trainingVideoEditModal').modal('show');
            }

            $icon.on('click', function () {
                if (clickTimer) return; // a dblclick is in progress
                clickTimer = setTimeout(function () {
                    clickTimer = null;
                    openViewModal();
                }, 250);
            });

            $icon.on('dblclick', function () {
                if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
                if (canEdit()) {
                    openEditModal();
                }
            });

            // Stop the video when the view modal closes.
            $('#trainingVideoModal').on('hidden.bs.modal', function () {
                $('#training-video-frame-wrap').empty();
            });

            $('#training-video-save-btn').on('click', function () {
                var $input = $('#training-video-link-input');
                var val = ($input.val() || '').trim();
                if (val && !/^https?:\/\//i.test(val)) {
                    $input.addClass('is-invalid');
                    return;
                }
                $input.removeClass('is-invalid');
                var $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: saveUrl,
                    method: 'POST',
                    data: { link: val, _token: csrfToken },
                    success: function (res) {
                        $icon.attr('data-link', (res && res.link) ? res.link : '');
                        $('#trainingVideoEditModal').modal('hide');
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Failed to save the link.';
                        alert(msg);
                    },
                    complete: function () {
                        $btn.prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
