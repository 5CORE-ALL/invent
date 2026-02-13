@extends('layouts.vertical', ['title' => 'Task Manager', 'sidenav' => 'condensed'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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
                font-size: 26px !important;
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
                font-size: 11px !important;
                margin-bottom: 6px !important;
                font-weight: 700 !important;
                letter-spacing: 0.5px !important;
            }
            
            .stat-value {
                font-size: 28px !important;
                margin-bottom: 2px !important;
                font-weight: 800 !important;
                line-height: 1 !important;
            }
            
            .stat-unit {
                font-size: 10px !important;
                font-weight: 600 !important;
                color: #8094ae !important;
                margin-top: 4px !important;
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
            
            /* Mobile Action Buttons Grid */
            .mobile-action-buttons {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                padding: 15px;
                margin-bottom: 15px;
            }
            
            .mobile-action-btn {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 16px 12px;
                border-radius: 12px;
                border: none;
                text-decoration: none;
                color: white;
                font-weight: 500;
                transition: all 0.3s ease;
                min-height: 85px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            }
            
            .mobile-action-btn:active {
                transform: scale(0.95);
            }
            
            .mobile-action-btn i {
                font-size: 28px;
                margin-bottom: 6px;
            }
            
            .mobile-action-btn span {
                font-size: 13px;
                text-align: center;
                line-height: 1.2;
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
            background: #dc3545 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
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
        
        /* Statistics Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            font-size: 28px;
            color: white;
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 600;
            color: #6c757d;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1;
        }

        .stat-unit {
            font-size: 10px;
            color: #6c757d;
            margin-top: 2px;
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

        /* Teal - ATC */
        .stat-card-teal {
            border-left-color: #20c997;
        }
        .stat-card-teal .stat-icon {
            background: linear-gradient(135deg, #0ba360 0%, #3cba92 100%);
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
        }
        
        /* Clean Table Styling */
        #tasks-table {
            background: white;
            border-radius: 8px;
            overflow-x: auto;
            overflow-y: hidden;
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
            font-size: 13px !important;
            text-transform: uppercase;
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
        
        /* Automated task rows - yellow background */
        .tabulator-row.automated-task {
            background-color: #fffbea !important;
        }
        
        .tabulator-row.automated-task:hover {
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
            background-color: #dc3545;
            color: #ffffff;
            border: 1px solid #b02a37;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }

        /* Action Icon Buttons */
        .action-btn-icon {
            padding: 8px 10px;
            font-size: 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 3px;
            display: inline-block;
            text-align: center;
            width: 36px;
            height: 36px;
            line-height: 20px;
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
            padding: 25px;
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
    </style>
@endsection

@section('content')
    <!-- Start Content-->
    <div class="container-fluid">
        
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item active">Task Manager</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Task Manager</h4>
                </div>
            </div>
        </div>     
        <!-- end page title --> 

        <!-- Mobile Stats Header -->
        <div class="d-md-none text-center mb-2" style="padding: 10px 15px 0 15px;">
            <h6 class="mb-0" style="color: #667eea; font-weight: 700; font-size: 14px;">
                <i class="mdi mdi-chart-box"></i> STATISTICS
            </h6>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4 stats-row">
            <!-- Total Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-blue">
                    <div class="stat-icon">
                        <i class="mdi mdi-format-list-bulleted"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">TOTAL</div>
                        <div class="stat-value">{{ $stats['total'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Pending Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-cyan">
                    <div class="stat-icon">
                        <i class="mdi mdi-clock-outline"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">PENDING</div>
                        <div class="stat-value">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Overdue Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-red">
                    <div class="stat-icon">
                        <i class="mdi mdi-alert-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">OVERDUE</div>
                        <div class="stat-value">{{ $stats['overdue'] }}</div>
                    </div>
                </div>
            </div>

            <!-- Done Tasks -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-green">
                    <div class="stat-icon">
                        <i class="mdi mdi-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">DONE</div>
                        <div class="stat-value">{{ $stats['done'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time Statistics Cards -->
        <div class="row mb-4 stats-row">
            <!-- Total ETC -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-yellow">
                    <div class="stat-icon">
                        <i class="mdi mdi-briefcase-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">ETC</div>
                        <div class="stat-value">{{ number_format($stats['etc_total'] / 60, 1) }}</div>
                        <div class="stat-unit">hours</div>
                    </div>
                </div>
            </div>

            <!-- Total ATC -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-teal">
                    <div class="stat-icon">
                        <i class="mdi mdi-timer"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">ATC</div>
                        <div class="stat-value">{{ number_format($stats['atc_total'] / 60, 1) }}</div>
                        <div class="stat-unit">hours</div>
                    </div>
                </div>
            </div>

            <!-- Done ETC -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-orange">
                    <div class="stat-icon">
                        <i class="mdi mdi-clipboard-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">DONE ETC</div>
                        <div class="stat-value">{{ number_format($stats['done_etc'] / 60, 1) }}</div>
                        <div class="stat-unit">hours</div>
                    </div>
                </div>
            </div>

            <!-- Done ATC -->
            <div class="col-xl-3 col-md-6">
                <div class="stat-card stat-card-purple">
                    <div class="stat-icon">
                        <i class="mdi mdi-check-all"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">DONE ATC</div>
                        <div class="stat-value">{{ number_format($stats['done_atc'] / 60, 1) }}</div>
                        <div class="stat-unit">hours</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card task-card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-sm-12">
                                <!-- Desktop Action Buttons -->
                                <div class="d-none d-md-flex justify-content-between align-items-center">
                                    <div>
                                        <a href="{{ route('tasks.create') }}" class="btn btn-danger btn-create-task">
                                            <i class="mdi mdi-plus-circle me-2"></i> Create Task
                                        </a>
                                        
                                        <button type="button" class="btn btn-success ms-2" id="upload-csv-btn">
                                            <i class="mdi mdi-file-upload me-2"></i> Upload CSV
                                        </button>
                                        
                                        <a href="{{ route('tasks.automated') }}" class="btn btn-warning ms-2">
                                            <i class="mdi mdi-robot me-2"></i> Automated Tasks
                                        </a>
                                        
                                        <a href="{{ route('tasks.deleted') }}" class="btn btn-secondary ms-2">
                                            <i class="mdi mdi-delete-forever me-2"></i> Deletion Record
                                        </a>
                                        
                                        <button type="button" class="btn btn-info ms-2" id="bulk-actions-btn">
                                            <i class="mdi mdi-format-list-checks me-2"></i> Bulk Actions
                                        </button>
                                    </div>
                                    
                                    <div>
                                        <span id="selected-count" class="text-muted" style="display: none;">
                                            <strong id="count-number">0</strong> task(s) selected
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Mobile Action Buttons Header -->
                                <div class="d-md-none text-center mb-2" style="padding: 15px 15px 0 15px;">
                                    <h6 class="mb-0" style="color: #667eea; font-weight: 700; font-size: 14px;">
                                        <i class="mdi mdi-lightning-bolt"></i> QUICK ACTIONS
                                    </h6>
                                </div>
                                
                                <!-- Mobile Action Buttons Grid -->
                                <div class="d-md-none mobile-action-buttons">
                                    <a href="{{ route('tasks.create') }}" class="mobile-action-btn btn-primary">
                                        <i class="mdi mdi-plus-circle"></i>
                                        <span>Create Task</span>
                                    </a>
                                    
                                    <button type="button" class="mobile-action-btn btn-success" id="upload-csv-btn-mobile">
                                        <i class="mdi mdi-file-upload"></i>
                                        <span>Upload CSV</span>
                                    </button>
                                    
                                    <a href="{{ route('tasks.automated') }}" class="mobile-action-btn btn-warning">
                                        <i class="mdi mdi-robot"></i>
                                        <span>Automated</span>
                                    </a>
                                    
                                    <a href="{{ route('tasks.deleted') }}" class="mobile-action-btn btn-secondary">
                                        <i class="mdi mdi-delete-forever"></i>
                                        <span>Deleted</span>
                                    </a>
                                    
                                    <button type="button" class="mobile-action-btn btn-info" id="bulk-actions-btn-mobile">
                                        <i class="mdi mdi-format-list-checks"></i>
                                        <span>Bulk Actions</span>
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

                        <!-- Mobile Quick Filters Header -->
                        <div class="d-md-none text-center mb-1" style="padding: 15px 15px 5px 15px;">
                            <h6 class="mb-0" style="color: #667eea; font-weight: 700; font-size: 14px;">
                                <i class="mdi mdi-filter-variant"></i> QUICK FILTERS
                            </h6>
                            <small class="text-muted" style="font-size: 11px;">
                                <i class="mdi mdi-gesture-swipe-horizontal"></i> Swipe to see more
                            </small>
                        </div>
                        
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
                        <div class="row mb-3 p-3 filter-section" style="background: #f8f9fa; border-radius: 8px;">
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Search</label>
                                <input type="text" id="filter-search" class="form-control form-control-sm" placeholder="Search all">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Group</label>
                                <input type="text" id="filter-group" class="form-control form-control-sm" placeholder="Enter Group">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Task</label>
                                <input type="text" id="filter-task" class="form-control form-control-sm" placeholder="Enter Task">
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Assignor</label>
                                <select id="filter-assignor" class="form-select form-select-sm">
                                    <option value="">All Assignors</option>
                                    <option value="__NULL__" style="color: #dc3545; font-weight: bold;">🔴 No Assignor</option>
                                    @foreach($users ?? [] as $user)
                                        <option value="{{ $user->name }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 mb-2">
                                <label class="form-label fw-bold">Assignee</label>
                                <select id="filter-assignee" class="form-select form-select-sm">
                                    <option value="">All Assignees</option>
                                    <option value="__NULL__" style="color: #dc3545; font-weight: bold;">🔴 No Assignee</option>
                                    @foreach($users ?? [] as $user)
                                        <option value="{{ $user->name }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label fw-bold">Status</label>
                                <select id="filter-status" class="form-select form-select-sm">
                                    <option value="">All</option>
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
                                </select>
                            </div>
                            <div class="col-md-1 mb-2">
                                <label class="form-label fw-bold">Priority</label>
                                <select id="filter-priority" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>

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

                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
        </div>
        <!-- end row -->

    </div> <!-- container -->
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
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
                <p class="mb-3"><strong>How much time did you actually spend on this task?</strong></p>
                <div class="mb-3">
                    <label for="atc-input" class="form-label">Actual Time to Complete (ATC) in minutes:</label>
                    <input type="number" class="form-control" id="atc-input" min="1" placeholder="e.g., 45" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirm-done-btn">
                    <i class="mdi mdi-check me-1"></i>Mark as Done
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-status-change-btn">
                    <i class="mdi mdi-check me-1"></i>Confirm Change
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Links Modal -->
<div class="modal fade" id="linksModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                <h5 class="modal-title">
                    <i class="mdi mdi-link-variant me-2"></i>Task Links
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="links-content">
                <!-- Links will be loaded here -->
            </div>
            <div class="modal-footer">
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
                        <small class="d-block text-muted">You can only delete tasks you created</small>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                        <p class="mb-1"><strong>Columns:</strong> Group, Task, Assignor, Assignee, Status, Priority, Image, Links</p>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="upload-csv-submit">
                        <i class="mdi mdi-upload me-1"></i> Upload & Import
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirm-bulk-assignor-btn">
                    <i class="mdi mdi-check me-1"></i>Assign as Assignor
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    
    <script>
        $(document).ready(function() {
            var selectedTasks = [];
            var bulkActionType = '';
            var isAdmin = {{ $isAdmin ? 'true' : 'false' }};
            var currentUserId = {{ Auth::id() }};
            var currentUserEmail = '{{ Auth::user()->email }}';
            
            // ==========================================
            // MOBILE TASK CARDS RENDERER
            // ==========================================
            function renderMobileTasks(tasks) {
                const container = $('#mobile-tasks-container');
                
                if (!tasks || tasks.length === 0) {
                    container.html(`
                        <div class="mobile-empty-state">
                            <i class="mdi mdi-clipboard-text-outline"></i>
                            <h5>No Tasks Found</h5>
                            <p>Create your first task or adjust filters</p>
                        </div>
                    `);
                    return;
                }
                
                let html = '';
                
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
                                ${task.etc_minutes ? `<div><i class="mdi mdi-clock-outline"></i> ${task.etc_minutes} min</div>` : ''}
                                ${task.tid ? `<div><i class="mdi mdi-calendar"></i> ${new Date(task.tid).toLocaleDateString()}</div>` : ''}
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
                                <button class="btn btn-sm btn-outline-secondary" onclick="editTask(${task.id})">
                                    <i class="mdi mdi-pencil"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                container.html(html);
            }
            
            // Helper functions for mobile actions
            window.viewTask = function(id) {
                const task = table.searchData('id', '=', id)[0];
                if (task) {
                    // Trigger existing view modal
                    $(`button[data-task-id="${id}"]`).first().click();
                }
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

            // Initialize Tabulator
            var table = new Tabulator("#tasks-table", {
                selectable: true, // All users can select rows for bulk actions
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
                    
                    // Render mobile view
                    if (window.innerWidth < 768) {
                        renderMobileTasks(response);
                    }
                    
                    return response;
                },
                rowFormatter: function(row) {
                    var data = row.getData();
                    
                    // OVERDUE BASED ON completion_day
                    let isOverdue = false;
                    
                    if (data.status !== 'Archived' && data.start_date && data.due_date) {
                        const startDate = new Date(data.start_date);
                        const dueDate = new Date(data.due_date);
                        const expectedDays = Math.ceil((dueDate - startDate) / (1000 * 60 * 60 * 24));
                        
                        if (data.completion_date && data.completion_date !== '0000-00-00' && data.completion_day) {
                            // Task completed - check if took longer than expected
                            isOverdue = parseInt(data.completion_day) > expectedDays;
                        } else {
                            // Task not completed - check if past due date
                            const now = new Date();
                            isOverdue = now > dueDate;
                        }
                    }
                    
                    // Apply styling based on overdue status
                    if (isOverdue) {
                        row.getElement().style.backgroundColor = "#ffe5e5";
                        row.getElement().style.borderLeft = "4px solid #dc3545";
                        row.getElement().classList.add('overdue-task');
                        row.getElement().classList.remove('automated-task');
                    } else if (data.is_automate_task) {
                        row.getElement().classList.add('automated-task');
                        row.getElement().classList.remove('overdue-task');
                        row.getElement().style.backgroundColor = "#fffbea";
                        row.getElement().style.borderLeft = "4px solid #ffc107";
                    } else {
                        row.getElement().classList.remove('automated-task');
                        row.getElement().classList.remove('overdue-task');
                        row.getElement().style.backgroundColor = "";
                        row.getElement().style.borderLeft = "";
                    }
                },
                layout: "fitData",
                pagination: true,
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                responsiveLayout: false,
                placeholder: "No Tasks Found",
                height: "600px",
                layoutColumnsOnNewData: true,
                horizontalScroll: true,
                autoResize: true,
                columns: (function() {
                    var cols = [];
                    
                    // Add checkbox column for all users (for bulk delete of own tasks)
                    cols.push({
                        formatter: "rowSelection", 
                        titleFormatter: "rowSelection", 
                        hozAlign: "center", 
                        headerSort: false, 
                        width: 60,
                        cellClick: function(e, cell) {
                            cell.getRow().toggleSelect();
                        }
                    });
                    
                    // Column Order: GROUP, TASK, ASSIGNOR, ASSIGNEE, TID, ETC, ATC, STATUS, PRIORITY, IMAGE, LINKS, ACTION
                    
                    // GROUP
                    cols.push({
                        title: "GROUP", 
                        field: "group", 
                        minWidth: 120, 
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<span style="color: #6c757d;">' + value + '</span>' : '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // TASK (2 lines with proper wrapping)
                    cols.push({
                        title: "TASK", 
                        field: "title", 
                        width: 280,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var title = cell.getValue() || '';
                            
                            // Remove [Auto: DD-MMM-YY] suffix from automated task titles
                            title = title.replace(/\s*\[Auto:\s*\d{1,2}-[A-Za-z]{3}-\d{2}\]\s*$/i, '');
                            
                            var isOverdue = false;
                            
                            var startDate = rowData.start_date;
                            if (startDate && !['Done', 'Archived'].includes(rowData.status)) {
                                var tidDate = new Date(startDate);
                                var overdueDate = new Date(tidDate);
                                overdueDate.setDate(overdueDate.getDate() + 10);
                                isOverdue = overdueDate < new Date();
                            }
                            
                            var overdueIcon = isOverdue ? '<i class="mdi mdi-alert-circle text-danger me-1" style="font-size: 14px;"></i>' : '';
                            var htmlTitle = String(title).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                            
                            // Show full text with auto wrapping (no line limit)
                            return '<div style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal; line-height: 1.4;">' + 
                                   overdueIcon + '<strong style="font-size: 13px;">' + htmlTitle + '</strong>' + 
                                   '</div>';
                        }
                    });
                    
                    // ASSIGNOR (first name only)
                    cols.push({
                        title: "ASSIGNOR", 
                        field: "assignor_name", 
                        width: 100, 
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var firstName = value.trim().split(' ')[0];
                                return '<strong>' + firstName + '</strong>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ASSIGNEE (first name only)
                    cols.push({
                        title: "ASSIGNEE", 
                        field: "assignee_name", 
                        width: 100, 
                        formatter: function(cell) {
                            var value = cell.getValue();
                            if (value && value !== '-') {
                                var firstName = value.trim().split(' ')[0];
                                return '<strong>' + firstName + '</strong>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // TID (Task Initiation Date) - Yellow background for automated tasks
                    cols.push({
                        title: "TID", 
                        field: "start_date", 
                        width: 120,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var value = cell.getValue();
                            
                            if (value) {
                                // Parse MySQL datetime correctly (YYYY-MM-DD HH:mm:ss)
                                // Extract date components directly to avoid timezone issues
                                var parts = value.split(/[- :]/); // Split by - or :
                                var year = parseInt(parts[0]);
                                var month = parseInt(parts[1]);
                                var day = parseInt(parts[2]);
                                
                                // Create date with exact values
                                var date = new Date(year, month - 1, day); // month is 0-indexed
                                var dayStr = String(day).padStart(2, '0');
                                var monthStr = date.toLocaleString('default', { month: 'short' });
                                
                                // Calculate days from TID (Day 0 = issue date)
                                var tidDate = new Date(value);
                                tidDate.setHours(0, 0, 0, 0);
                                var now = new Date();
                                now.setHours(0, 0, 0, 0);
                                var daysSinceTID = Math.floor((now - tidDate) / (1000 * 60 * 60 * 24));
                                
                                var textColor = '#0d6efd'; // Default blue
                                
                                // Color logic based on task age (skip for Done/Archived)
                                if (rowData.status !== 'Done' && rowData.status !== 'Archived') {
                                    if (daysSinceTID >= 2) {
                                        // Day 2+ = RED text
                                        textColor = '#dc3545';
                                    } else if (daysSinceTID === 1) {
                                        // Day 1 = ORANGE text
                                        textColor = '#fd7e14';
                                    }
                                    // Day 0 = Blue (default)
                                }
                                
                                return '<span style="color: ' + textColor + '; font-weight: 600;">' + dayStr + '-' + monthStr + '</span>';
                            }
                            return '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ETC (Estimated Time)
                    cols.push({
                        title: "ETC", 
                        field: "eta_time", 
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? value : '<span style="color: #adb5bd;">-</span>';
                        }
                    });
                    
                    // ATC (Actual Time)
                    cols.push({
                        title: "ATC", 
                        field: "etc_done", 
                        width: 90,
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue();
                            return value ? '<strong style="color: #28a745;">' + value + '</strong>' : '<span style="color: #adb5bd;">0</span>';
                        }
                    });
                    
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
                            var assigneeId = rowData.assignee_id;
                            
                            // CALCULATE OVERDUE BASED ON completion_day
                            let isOverdue = false;
                            let displayText = value;
                            
                            if (value !== 'Archived' && rowData.start_date && rowData.due_date) {
                                const startDate = new Date(rowData.start_date);
                                const dueDate = new Date(rowData.due_date);
                                const expectedDays = Math.ceil((dueDate - startDate) / (1000 * 60 * 60 * 24));
                                
                                if (rowData.completion_date && rowData.completion_date !== '0000-00-00' && rowData.completion_day) {
                                    // Task completed - check if took longer
                                    const actualDays = parseInt(rowData.completion_day);
                                    isOverdue = actualDays > expectedDays;
                                    if (isOverdue) {
                                        displayText = `🔴 ${value} (${actualDays}/${expectedDays}d)`;
                                    }
                                } else {
                                    // Not completed - check if past due
                                    const now = new Date();
                                    if (now > dueDate) {
                                        isOverdue = true;
                                        const daysLate = Math.ceil((now - dueDate) / (1000 * 60 * 60 * 24));
                                        displayText = `OVERDUE ${daysLate}d`;
                                    }
                                }
                            }
                            
                            // Check if user can update status
                            var canUpdateStatus = isAdmin || assignorId === currentUserId || assigneeId === currentUserId;
                            
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
                            
                            // OVERRIDE WITH RED IF OVERDUE!
                            var currentStatus = isOverdue 
                                ? {bg: '#dc3545', text: '#fff'} 
                                : (statuses[value] || {bg: '#6c757d', text: '#fff'});
                            
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
                    
                    // PRIORITY (Dark colored backgrounds)
                    cols.push({
                        title: "PRIORITY", 
                        field: "priority", 
                        width: 110, 
                        hozAlign: "center",
                        formatter: function(cell) {
                            var value = cell.getValue() || 'Normal';
                            var styles = {
                                'Low': {bg: '#6c757d', color: '#fff'},
                                'Normal': {bg: '#0d6efd', color: '#fff'},
                                'High': {bg: '#fd7e14', color: '#fff'},
                                'Urgent': {bg: '#dc3545', color: '#fff'},
                                'Take your time': {bg: '#20c997', color: '#fff'}
                            };
                            var style = styles[value] || styles['Normal'];
                            return '<span style="background: ' + style.bg + '; color: ' + style.color + '; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase;">' + value + '</span>';
                        }
                    });
                    
                    // IMG (Screenshot/Image)
                    cols.push({
                        title: "IMG", 
                        field: "image", 
                        width: 60,
                        hozAlign: "center",
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
                    
                    // LINKS
                    cols.push({
                        title: "LINKS", 
                        field: "id", 
                        width: 90,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            // Check all link fields (link1-9)
                            var hasLinks = rowData.link1 || rowData.link2 || rowData.link3 || rowData.link4 || 
                                          rowData.link5 || rowData.link6 || rowData.link7 || rowData.link8 || rowData.link9;
                            
                            if (hasLinks) {
                                return `<button class="btn btn-sm btn-link view-links" data-id="${cell.getValue()}" title="View Links">
                                    <i class="mdi mdi-link-variant text-primary" style="font-size: 18px; cursor: pointer;"></i>
                                </button>`;
                            }
                            return '-';
                        }
                    });
                    
                    // ACTION
                    cols.push({
                        title: "ACTION", 
                        field: "id", 
                        width: 140,
                        hozAlign: "center",
                        headerSort: false,
                        formatter: function(cell) {
                            var rowData = cell.getRow().getData();
                            var id = rowData.id;
                            var assignorId = rowData.assignor_id;
                            var assigneeId = rowData.assignee_id;
                            
                            // Determine permissions
                            var canEdit = isAdmin || assignorId === currentUserId;
                            var canDelete = assignorId === currentUserId; // Only assignor can delete, not even admin
                            var canView = isAdmin || assignorId === currentUserId || assigneeId === currentUserId;
                            
                            var buttons = '';
                            
                            if (canView) {
                                buttons += `
                                    <button class="action-btn-icon action-btn-view view-task" data-id="${id}" title="View">
                                        <i class="mdi mdi-eye"></i>
                                    </button>
                                `;
                            }
                            
                            if (canEdit) {
                                buttons += `
                                    <button class="action-btn-icon action-btn-edit edit-task" data-id="${id}" title="Edit">
                                        <i class="mdi mdi-pencil"></i>
                                    </button>
                                `;
                            }
                            
                            if (canDelete) {
                                buttons += `
                                    <button class="action-btn-icon action-btn-delete delete-task" data-id="${id}" title="Delete">
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
                    done_atc: filteredData.filter(t => t.status === 'Done').reduce((sum, t) => sum + (parseInt(t.etc_done) || 0), 0)
                };
                
                // Overdue calculation
                var now = new Date();
                stats.overdue = filteredData.filter(function(t) {
                    if (t.start_date && !['Done', 'Archived'].includes(t.status)) {
                        var tidDate = new Date(t.start_date);
                        var overdueDate = new Date(tidDate);
                        overdueDate.setDate(overdueDate.getDate() + 10);
                        return overdueDate < now;
                    }
                    return false;
                }).length;
                
                // Update stat cards (find by stat-value divs in each card)
                $('.stat-card').each(function() {
                    var label = $(this).find('.stat-label').text().trim();
                    var valueEl = $(this).find('.stat-value');
                    
                    switch(label) {
                        case 'TOTAL':
                            valueEl.text(stats.total);
                            break;
                        case 'PENDING':
                            valueEl.text(stats.pending);
                            break;
                        case 'OVERDUE':
                            valueEl.text(stats.overdue);
                            break;
                        case 'DONE':
                            valueEl.text(stats.done);
                            break;
                        case 'ETC':
                            valueEl.text(Math.round(stats.etc_total / 60));
                            break;
                        case 'ATC':
                            valueEl.text(Math.round(stats.atc_total / 60));
                            break;
                        case 'DONE ETC':
                            valueEl.text(Math.round(stats.done_etc / 60));
                            break;
                        case 'DONE ATC':
                            valueEl.text(Math.round(stats.done_atc / 60));
                            break;
                    }
                });
            }

            // Combined filter function (proper AND logic)
            function applyFilters() {
                console.log('🔍 Applying filters...');
                
                // Clear existing filters first
                table.clearFilter();
                
                // Build filter array with AND logic
                var filters = [];
                
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
                
                // Assignor filter (including NULL check)
                var assignorValue = $('#filter-assignor').val();
                if (assignorValue) {
                    if (assignorValue === '__NULL__') {
                        // Filter for tasks with NO assignor
                        filters.push(function(data) {
                            return !data.assignor_name || data.assignor_name === '-' || data.assignor_name === '';
                        });
                        console.log('Filter - Assignor: NULL (no assignor)');
                    } else {
                        filters.push({field:"assignor_name", type:"=", value:assignorValue});
                        console.log('Filter - Assignor:', assignorValue);
                    }
                }
                
                // Assignee filter (including NULL check)
                var assigneeValue = $('#filter-assignee').val();
                if (assigneeValue) {
                    if (assigneeValue === '__NULL__') {
                        // Filter for tasks with NO assignee
                        filters.push(function(data) {
                            return !data.assignee_name || data.assignee_name === '-' || data.assignee_name === '';
                        });
                        console.log('Filter - Assignee: NULL (no assignee)');
                    } else {
                        filters.push({field:"assignee_name", type:"=", value:assigneeValue});
                        console.log('Filter - Assignee:', assigneeValue);
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
                
                // Apply all filters if any exist
                if (filters.length > 0) {
                    table.setFilter(filters);
                }
                
                // Update statistics after filtering
                setTimeout(function() {
                    updateStatistics();
                    
                    // Update mobile view
                    if (window.innerWidth < 768) {
                        const filteredData = table.getData('active');
                        renderMobileTasks(filteredData);
                    }
                }, 100);
            }

            // Filter functionality
            $('#filter-search').on('keyup', applyFilters);
            $('#filter-group').on('keyup', applyFilters);
            $('#filter-task').on('keyup', applyFilters);
            $('#filter-assignor').on('change', applyFilters);
            $('#filter-assignee').on('change', applyFilters);
            $('#filter-status').on('change', applyFilters);
            $('#filter-priority').on('change', applyFilters);
            
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
                
                switch(filterType) {
                    case 'all':
                        $('#filter-status').val('');
                        $('#filter-priority').val('');
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
                
                // Manually trigger applyFilters (don't trigger change to avoid recursion)
                applyFilters();
                
                // Haptic feedback if available
                if (navigator.vibrate) {
                    navigator.vibrate(10);
                }
            });

            // Handle Row Selection
            table.on("rowSelectionChanged", function(data, rows) {
                selectedTasks = data.map(task => task.id);
                var count = selectedTasks.length;
                
                if (count > 0) {
                    $('#selected-count').show();
                    $('#count-number').text(count);
                    $('#bulk-actions-btn').removeClass('btn-info').addClass('btn-success');
                } else {
                    $('#selected-count').hide();
                    $('#bulk-actions-btn').removeClass('btn-success').addClass('btn-info');
                }
            });

            // Show CSV Upload Modal
            $('#upload-csv-btn').on('click', function() {
                $('#csvUploadModal').modal('show');
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
            
            // Bulk Assign Assignee
            $('#bulk-assign-assignee-btn').on('click', function(e) {
                e.preventDefault();
                $('#bulkActionsModal').modal('hide');
                $('#bulkAssigneeModal').modal('show');
            });
            
            $('#confirm-bulk-assignee-btn').on('click', function() {
                const selectedEmails = $('.bulk-assignee-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selectedEmails.length === 0) {
                    alert('Please select at least one assignee');
                    return;
                }
                
                const assigneeEmails = selectedEmails.join(', ');
                console.log('Bulk assigning assignees:', assigneeEmails, 'to tasks:', selectedTasks);
                
                bulkUpdate('assign_assignee', { assignee: assigneeEmails });
                $('#bulkAssigneeModal').modal('hide');
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
                        alert('Error: ' + (xhr.responseJSON?.message || 'Something went wrong'));
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
                        $('#taskInfoModal').modal('show');
                    },
                    error: function(xhr) {
                        console.error('Error loading task:', xhr);
                        alert('Failed to load task details');
                    }
                });
            });

            // View Links
            $(document).on('click', '.view-links', function(e) {
                e.preventDefault();
                var taskId = $(this).data('id');
                $.ajax({
                    url: '/tasks/' + taskId,
                    type: 'GET',
                    success: function(response) {
                        var html = '<div class="list-group">';
                        var linkCount = 0;
                        
                        // Show all links (link1-9)
                        for (var i = 1; i <= 9; i++) {
                            var linkField = 'link' + i;
                            if (response[linkField] && response[linkField] !== '') {
                                linkCount++;
                                var isUrl = response[linkField].startsWith('http');
                                
                                if (isUrl) {
                                    html += `
                                        <a href="${response[linkField]}" target="_blank" class="list-group-item list-group-item-action">
                                            <i class="mdi mdi-link text-primary me-2"></i>
                                            <strong>Link ${i}:</strong> ${response[linkField]}
                                        </a>`;
                                } else {
                                    html += `
                                        <div class="list-group-item">
                                            <i class="mdi mdi-text text-secondary me-2"></i>
                                            <strong>Link ${i}:</strong> ${response[linkField]}
                                        </div>`;
                                }
                            }
                        }
                        
                        html += '</div>';
                        
                        if (linkCount === 0) {
                            html = '<p class="text-muted">No links available for this task.</p>';
                        }
                        
                        $('#links-content').html(html);
                        $('#linksModal').modal('show');
                    }
                });
            });

            // View Task
            $(document).on('click', '.view-task', function() {
                var taskId = $(this).data('id');
                $.ajax({
                    url: '/tasks/' + taskId,
                    type: 'GET',
                    success: function(response) {
                        var html = `
                            <div style="padding: 10px;">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="200" style="color: #6c757d; font-weight: 600;">Title:</th>
                                        <td><strong>${response.title}</strong></td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Description:</th>
                                        <td>${response.description || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Group:</th>
                                        <td>${response.group || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Priority:</th>
                                        <td><span class="priority-badge priority-${response.priority}">${response.priority}</span></td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Assignor:</th>
                                        <td>${response.assignor ? response.assignor.name : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Assignee:</th>
                                        <td>${response.assignee ? response.assignee.name : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Split Tasks:</th>
                                        <td>${response.split_tasks ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Flag Raise:</th>
                                        <td>${response.flag_raise ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Status:</th>
                                        <td><span class="status-badge status-${response.status}">${response.status}</span></td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">ETC (Minutes):</th>
                                        <td>${response.etc_minutes ? response.etc_minutes + ' min' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">TID:</th>
                                        <td>${response.tid || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">L1:</th>
                                        <td>${response.l1 || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">L2:</th>
                                        <td>${response.l2 || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Training Link:</th>
                                        <td>${response.training_link ? '<a href="' + response.training_link + '" target="_blank" style="color: #0d6efd;">' + response.training_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Video Link:</th>
                                        <td>${response.video_link ? '<a href="' + response.video_link + '" target="_blank" style="color: #0d6efd;">' + response.video_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Form Link:</th>
                                        <td>${response.form_link ? '<a href="' + response.form_link + '" target="_blank" style="color: #0d6efd;">' + response.form_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Form Report Link:</th>
                                        <td>${response.form_report_link ? '<a href="' + response.form_report_link + '" target="_blank" style="color: #0d6efd;">' + response.form_report_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Checklist Link:</th>
                                        <td>${response.checklist_link ? '<a href="' + response.checklist_link + '" target="_blank" style="color: #0d6efd;">' + response.checklist_link + '</a>' : '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">PL:</th>
                                        <td>${response.pl || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Process:</th>
                                        <td>${response.process || '<span style="color: #adb5bd;">-</span>'}</td>
                                    </tr>
                                    ${response.image ? '<tr><th style="color: #6c757d; font-weight: 600;">Image:</th><td><img src="/uploads/tasks/' + response.image + '" class="img-thumbnail" style="max-width: 300px; border-radius: 8px;"></td></tr>' : ''}
                                    <tr>
                                        <th style="color: #6c757d; font-weight: 600;">Created At:</th>
                                        <td>${response.created_at}</td>
                                    </tr>
                                </table>
                            </div>
                        `;
                        $('#task-details').html(html);
                        $('#viewTaskModal').modal('show');
                    }
                });
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

            // Confirm Done
            $('#confirm-done-btn').on('click', function() {
                var atc = $('#atc-input').val();
                if (!atc || atc <= 0) {
                    alert('Please enter the actual time spent on this task.');
                    return;
                }
                
                updateTaskStatus(currentTaskId, 'Done', atc, null);
                $('#doneModal').modal('hide');
                $('#atc-input').val('');
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
                            var message = reworkReason ? 'Task marked for rework' : 'Status updated successfully!';
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
    </script>
@endsection
