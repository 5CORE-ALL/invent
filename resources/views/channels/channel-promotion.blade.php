@extends('layouts.vertical', ['title' => 'Promotion Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">

    <style>
        /* Table container */
        .table-responsive {
            position: relative;
            max-height: 85vh;
            overflow-y: auto;
            overflow-x: auto;
            margin-bottom: 2rem;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        /* Table structure */
        .table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed !important;
        }

        /* Force columns to respect width */
        .table th,
        .table td {
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ======================== COLUMN WIDTH ENFORCEMENT ======================== */
        /* Explicit width definitions for every column */
        .table th:nth-child(1),
        .table td:nth-child(1) {
            width: 60px !important;
            min-width: 60px !important;
            max-width: 60px !important;
        }

        .table th:nth-child(2),
        .table td:nth-child(2) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(3),
        .table td:nth-child(3) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(4),
        .table td:nth-child(4) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(5),
        .table td:nth-child(5) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(6),
        .table td:nth-child(6) {
            width: 140px !important;
            min-width: 140px !important;
            max-width: 140px !important;
        }

        .table th:nth-child(7),
        .table td:nth-child(7) {
            width: 140px !important;
            min-width: 140px !important;
            max-width: 140px !important;
        }

        .table th:nth-child(8),
        .table td:nth-child(8) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(9),
        .table td:nth-child(9) {
            width: 145px !important;
            min-width: 145px !important;
            max-width: 145px !important;
        }

        .table th:nth-child(10),
        .table td:nth-child(10) {
            width: 160px !important;
            min-width: 160px !important;
            max-width: 160px !important;
        }

        .table th:nth-child(11),
        .table td:nth-child(11) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(12),
        .table td:nth-child(12) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(13),
        .table td:nth-child(13) {
            width: 120px !important;
            min-width: 120px !important;
            max-width: 120px !important;
        }

        .table th:nth-child(14),
        .table td:nth-child(14) {
            width: 160px !important;
            min-width: 160px !important;
            max-width: 160px !important;
        }

        .table th:nth-child(15),
        .table td:nth-child(15) {
            width: 300px !important;
            min-width: 300px !important;
            max-width: 300px !important;
        }

        .table th:nth-child(16),
        .table td:nth-child(16) {
            width: 300px !important;
            min-width: 300px !important;
            max-width: 300px !important;
        }

        .table th:nth-child(17),
        .table td:nth-child(17) {
            width: 150px !important;
            min-width: 150px !important;
            max-width: 150px !important;
        }

        .table th:nth-child(18),
        .table td:nth-child(18) {
            width: 150px !important;
            min-width: 150px !important;
            max-width: 150px !important;
        }

        .table th:nth-child(19),
        .table td:nth-child(19) {
            width: 150px !important;
            min-width: 150px !important;
            max-width: 150px !important;
        }

        .table th:nth-child(20),
        .table td:nth-child(20) {
            width: 150px !important;
            min-width: 150px !important;
            max-width: 150px !important;
        }

        .table th:nth-child(21),
        .table td:nth-child(21) {
            width: 100px !important;
            min-width: 100px !important;
            max-width: 100px !important;
        }

        /* ======================== HEADER STYLING ======================== */
        /* All header cells */
        .table-responsive thead th {
            position: sticky;
            background: linear-gradient(135deg, #1a56b7 0%, #0c397a 100%);
            color: white;
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 3px rgba(0, 0, 0, 0.1);
            padding: 12px 8px;
            font-size: 13px;
            font-weight: 700;
            writing-mode: horizontal-tb !important;
            white-space: normal;
            z-index: 20;
            line-height: 1.3;
        }

        /* First row */
        .table-responsive thead tr:first-child th {
            top: 0;
            z-index: 21;
        }

        /* Second row */
        .table-responsive thead tr:nth-child(2) th {
            top: 43px;
            z-index: 20;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
        }

        /* Essential sticky header styling */
        .table-responsive thead {
            position: sticky;
            top: 0;
            z-index: 50;
        }

        /* First row in header - column group headers */
        .table-responsive thead tr:first-child th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #1a56b7 0%, #0c397a 100%);
            z-index: 21;
            padding: 12px 4px;
            font-size: 14px;
            font-weight: 800;
            color: white;
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 3px rgba(0, 0, 0, 0.1);
        }

        /* Second row headers */
        .table-responsive thead tr:nth-child(2) th {
            position: sticky;
            top: 43px;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            color: white;
            z-index: 20;
            padding: 15px 8px;
            font-weight: 700;
            font-size: 13px;
            text-align: center;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 2px 3px rgba(0, 0, 0, 0.1);
        }

        /* For headers spanning both rows */
        .table-responsive thead tr:first-child th[rowspan="2"] {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #1a56b7 0%, #0c397a 100%);
            z-index: 22;
            height: calc(43px + 47px);
        }

        /* Header hover state */
        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%);
        }

        /* ======================== TABLE BODY STYLING ======================== */
        /* Table cell styling */
        .table-responsive tbody td {
            padding: 8px 6px;
            font-size: 13px;
            text-align: center;
            border-right: 1px solid #f0f0f0;
        }

        /* Table row styling */
        .table-responsive tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        .table-responsive tbody tr:hover {
            background-color: #e8f0fe;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .table-responsive tbody tr:hover td {
            color: #000;
        }

        /* ======================== OTHER STYLES ======================== */
        /* Remove inline styles from HTML and use this CSS instead */
        .table th[colspan="2"] {
            text-align: center;
        }

        /* Filter inputs in header */
        .table-responsive thead input {
            background-color: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 4px;
            color: #333;
            padding: 6px 10px;
            margin-top: 8px;
            font-size: 12px;
            width: 100%;
            transition: all 0.2s;
        }

        .table-responsive thead input:focus {
            background-color: white;
            box-shadow: 0 0 0 2px rgba(26, 86, 183, 0.3);
            outline: none;
        }

        .table-responsive thead input::placeholder {
            color: #8e9ab4;
            font-style: italic;
        }

        /* Edit button styling */
        .edit-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #1a56b7;
            color: #1a56b7;
        }

        .edit-btn:hover {
            background: #1a56b7;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(26, 86, 183, 0.2);
        }

        /* Promotion Status Colors */
        .promotion-status-no {
            background-color: yellow;
            color: black;
            font-weight: bold;
            padding: 5px;
            border-radius: 4px;
        }

        .promotion-status-active {
            background-color: green;
            color: white;
            font-weight: bold;
            padding: 5px;
            border-radius: 4px;
        }

        .promotion-status-inactive {
            background-color: red;
            color: white;
            font-weight: bold;
            padding: 5px;
            border-radius: 4px;
        }

        .promotion-status-progress {
            background-color: violet;
            color: white;
            font-weight: bold;
            padding: 5px;
            border-radius: 4px;
        }

        .promotion-status-ended {
            background-color: darkred;
            color: white;
            font-weight: bold;
            padding: 5px;
            border-radius: 4px;
        }

        /* Status badge styling for the table */
        .status-badge {
            display: flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 8px;
            background-color: #f8f9fa;
            width: fit-content;
            white-space: nowrap;
            font-size: 13px;
            font-weight: 500;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .status-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.08);
            background-color: #f0f4f8;
        }

        .status-badge .option-indicator {
            width: 20px;
            height: 20px;
            border-radius: 5px;
            margin-right: 8px;
            flex-shrink: 0;
        }

        /* Specific status badge styling based on status */
        .status-badge[data-status="No Promotion"] {
            background-color: rgba(255, 214, 0, 0.1);
            border-left: 3px solid #FFD600;
        }

        .status-badge[data-status="Active"] {
            background-color: rgba(34, 197, 94, 0.1);
            border-left: 3px solid #22C55E;
        }

        .status-badge[data-status="Inactive"] {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 3px solid #EF4444;
        }

        .status-badge[data-status="On Progress"] {
            background-color: rgba(139, 92, 246, 0.1);
            border-left: 3px solid #8B5CF6;
        }

        .status-badge[data-status="Ended"] {
            background-color: rgba(185, 28, 28, 0.1);
            border-left: 3px solid #B91C1C;
        }

        /* Responsive styles */
        @media (max-width: 1200px) {
            .table-responsive thead th {
                font-size: 12px;
                padding: 8px 4px;
            }

            .table-responsive tbody td {
                font-size: 12px;
                padding: 6px 4px;
            }
        }

        @media (max-width: 900px) {
            .table-responsive {
                max-height: 45vh;
            }

            .table-responsive th,
            .table-responsive td {
                max-width: 120px;
                min-width: 80px;
                padding: 8px 4px;
                font-size: 12px;
            }
        }

        @media (max-width: 768px) {
            .table-responsive {
                max-height: 40vh;
            }
        }

        /* Additional styles kept from original */
        .form-control {
            border: 2px solid #E2E8F0;
            border-radius: 6px;
            padding: 0.75rem;
        }

        #status {
            display: block !important;
            position: static !important;
            width: 100% !important;
            height: auto !important;
            margin: auto !important;
        }

        .dt-buttons .btn {
            margin-left: 10px;
        }

        /* Modal styling */
        .modal-header-gradient {
            background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%);
            border-bottom: 4px solid #4D55E6;
            padding: 1.5rem;
        }

        .modal-footer-gradient {
            background: linear-gradient(135deg, #F8FAFF 0%, #E6F0FF 100%);
            border-top: 4px solid #E2E8F0;
            padding: 1.5rem;
        }

        .modal-dialog {
            max-width: 900px;
            margin: 1.75rem auto;
        }

        .modal-content {
            max-height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-body {
            flex: 1 1 auto;
            overflow-y: auto;
            max-height: 60vh;
        }

        /* Loader styles */
        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-text {
            margin-top: 10px;
            font-weight: bold;
        }

        /* Selection controls and actions */
        .selection-controls {
            position: relative;
            z-index: 100;
            display: inline-flex;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .card-body:hover .selection-controls {
            opacity: 1;
        }

        .select-toggle-btn {
            background: linear-gradient(135deg, #4ADE80 0%, #22C55E 100%);
            color: white;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(34, 197, 94, 0.3);
            transition: all 0.2s;
        }

        .select-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(34, 197, 94, 0.4);
        }

        .select-toggle-text {
            margin-right: 10px;
            font-weight: 600;
            color: #333;
        }

        .checkbox-column {
            width: 40px;
            text-align: center;
            display: none;
        }

        .select-all-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .row-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .selection-actions {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            padding: 10px 20px;
            border-radius: 50px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .selection-actions .btn {
            margin: 0 5px;
            border-radius: 20px;
            font-weight: 600;
            padding: 5px 15px;
        }

        .selection-count {
            color: white;
            font-weight: bold;
            margin-right: 15px;
            display: inline-block;
        }

        /* Field operations styling */
        .field-operation {
            padding: 10px;
            border-radius: 6px;
            background-color: #f8f9fa;
            transition: all 0.2s;
        }

        .field-operation:hover {
            background-color: #e9ecef;
        }

        #addFieldBtn {
            border-radius: 20px;
            padding: 6px 15px;
        }

        #applyChangesBtn {
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%);
            border: none;
        }

        .remove-field {
            width: 36px;
            height: 36px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }

        .custom-toast {
            z-index: 2000;
        }

        /* Force line breaks in headers */
        .table-responsive thead th br {
            display: inline;
        }

        /* Dropdown Styling - Matching Screenshot */
        #promotion_status {
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 0.65rem 1rem;
            font-size: 14px;
            background-color: #FFFFFF;
            color: #4A5568;
            transition: all 0.2s;
            font-weight: 500;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%234A5568' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            appearance: none;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            width: 100%;
            height: 42px;
        }

        #promotion_status:focus {
            border-color: #4F46E5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        #promotion_status:hover {
            border-color: #4F46E5;
            cursor: pointer;
        }

        /* Custom select styles */
        select.custom-select {
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: pointer;
        }

        /* Dropdown menu styling */
        .select-wrapper {
            position: relative;
        }

        /* Modern dropdown styling for opened dropdown */
        .select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            margin-top: 8px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            overflow: hidden;
            display: none;
        }

        /* Modern Floating Dropdown - New Styles */
        .custom-dropdown-container {
            position: relative;
            width: 100%;
            margin-bottom: 16px;
        }

        .custom-select {
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 14px;
            background-color: #FFFFFF;
            color: #4A5568;
            transition: all 0.2s;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            margin-top: 8px;
            z-index: 1050;
            overflow: hidden;
            display: none;
            padding: 8px 0;
            max-height: 260px;
            overflow-y: auto;
        }

        .dropdown-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: background 0.15s;
            position: relative;
            margin: 2px 5px;
            border-radius: 8px;
        }

        .dropdown-item.selected {
            background-color: #EEF2FF;
            font-weight: 500;
        }

        .dropdown-item.selected:after {
            content: '✓';
            position: absolute;
            right: 16px;
            color: #4F46E5;
            font-weight: bold;
        }

        .option-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 5px;
            margin-right: 12px;
        }

        /* Enhance the hover effect */
        .dropdown-item:hover {
            background-color: #F5F7FA;
            transform: translateY(-1px);
        }

        /* Animation effects for dropdown */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu.show {
            animation: fadeIn 0.2s ease-out forwards;
        }

        .dropdown-item {
            transition: all 0.2s ease;
        }

        .dropdown-item:active {
            transform: scale(0.98);
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared/page-title', [
        'page_title' => 'Promotion Master',
        'sub_title' => 'Channel',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="dataTables_length">
                                {{-- <div class="selection-controls"
                                    style="position: relative; opacity: 1; display: inline-flex; margin-right: 15px;">
                                    <span class="select-toggle-text">Multi Add</span>
                                    <button type="button" class="select-toggle-btn" id="toggleSelection">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div> --}}
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control"
                                    placeholder="Search channels...">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">Clear</button>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 text-end mb-3">
                        @if (in_array('create', $permissions['product_lists'] ?? []))
                            {{-- <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                                data-bs-target="#addProductModal">
                                <i class="fas fa-plus me-1"></i> ADD PRODUCT
                            </button> --}}
                        @endif

                        <button type="button" class="btn btn-success ms-2" id="downloadExcel">
                            <i class="fas fa-file-excel me-1"></i> Download Excel
                        </button>
                    </div>

                    <!-- Add Product Modal -->
                    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel"
                        aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content"
                                style="border: none; border-radius: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                <!-- Modal Header -->
                                <div class="modal-header"
                                    style="background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%); border-bottom: 4px solid #4D55E6; padding: 1.5rem; border-radius: 0;">
                                    <h5 class="modal-title" id="addProductModalLabel"
                                        style="color: white; font-weight: 800; font-size: 1.8rem; letter-spacing: 0.5px;">
                                        <i class="fas fa-pen me-2"></i>UPDATE CHANNEL PROMOTION
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>

                                <!-- Modal Body -->
                                <div class="modal-body"
                                    style="background-color: #F8FAFF; padding: 2rem; max-height: 70vh; overflow-y: auto;">
                                    <div id="form-errors" class="mb-3"></div>
                                    <form id="updateChannelForm">
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label for="channel" class="form-label fw-bold"
                                                    style="color: #4A5568;">Channel*</label>
                                                <input type="text" class="form-control" id="channel" name="channel"
                                                    placeholder="Enter Channel" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="worksheet_link" class="form-label fw-bold"
                                                    style="color: #4A5568;">Worksheet Link</label>
                                                <input type="url" class="form-control" id="worksheet_link"
                                                    name="worksheet_link" placeholder="Paste Worksheet Link">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="priority" class="form-label fw-bold"
                                                    style="color: #4A5568;">Priority</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="priority"
                                                        name="priority" value="1">
                                                    <label class="form-check-label" for="priority">Mark as Priority</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label for="issues" class="form-label fw-bold"
                                                    style="color: #4A5568;">Issues</label>
                                                <input type="text" class="form-control" id="issues" name="issues"
                                                    placeholder="Enter Issues">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="t_sales_l60" class="form-label fw-bold"
                                                    style="color: #4A5568;">T Sales L60</label>
                                                <input type="number" class="form-control" id="t_sales_l60"
                                                    name="t_sales_l60" placeholder="Enter T Sales L60">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="t_sales_l30" class="form-label fw-bold"
                                                    style="color: #4A5568;">T Sales L30</label>
                                                <input type="number" class="form-control" id="t_sales_l30"
                                                    name="t_sales_l30" placeholder="Enter T Sales L30">
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label for="growth" class="form-label fw-bold"
                                                    style="color: #4A5568;">Growth</label>
                                                <input type="number" class="form-control" id="growth" name="growth"
                                                    placeholder="Enter Growth">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="promotion_status" class="form-label fw-bold"
                                                    style="color: #4A5568;">Promotion Status</label>
                                                <div class="custom-dropdown-container">
                                                    <input type="hidden" name="promotion_status"
                                                        id="promotion_status_hidden">
                                                    <div class="custom-select" id="promotion_status_display">
                                                        <span class="selected-option">Select Promotion Status</span>
                                                        <i class="dropdown-icon fas fa-chevron-down"></i>
                                                    </div>
                                                    <div class="dropdown-menu" id="promotion_options">
                                                        <div class="dropdown-item" data-value="No Promotion">
                                                            <span class="option-indicator"
                                                                style="background-color: #FFD600;"><i class="fas fa-ban"
                                                                    style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                            <span class="option-text">No Promotion</span>
                                                        </div>
                                                        <div class="dropdown-item" data-value="Active">
                                                            <span class="option-indicator"
                                                                style="background-color: #22C55E;"><i
                                                                    class="fas fa-check-circle"
                                                                    style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                            <span class="option-text">Active</span>
                                                        </div>
                                                        <div class="dropdown-item" data-value="Inactive">
                                                            <span class="option-indicator"
                                                                style="background-color: #EF4444;"><i
                                                                    class="fas fa-times-circle"
                                                                    style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                            <span class="option-text">Inactive</span>
                                                        </div>
                                                        <div class="dropdown-item" data-value="On Progress">
                                                            <span class="option-indicator"
                                                                style="background-color: #8B5CF6;"><i class="fas fa-clock"
                                                                    style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                            <span class="option-text">On Progress</span>
                                                        </div>
                                                        <div class="dropdown-item" data-value="Ended">
                                                            <span class="option-indicator"
                                                                style="background-color: #B91C1C;"><i
                                                                    class="fas fa-stop-circle"
                                                                    style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                            <span class="option-text">Ended</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="promotion_active_count" class="form-label fw-bold"
                                                    style="color: #4A5568;">No. of Promotion active</label>
                                                <input type="number" class="form-control" id="promotion_active_count"
                                                    name="promotion_active_count"
                                                    placeholder="Enter No. of Promotion active">
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label for="promotion_type" class="form-label fw-bold"
                                                    style="color: #4A5568;">Promotion Type</label>
                                                <input type="text" class="form-control" id="promotion_type"
                                                    name="promotion_type" placeholder="Enter Promotion Type">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="start_date" class="form-label fw-bold"
                                                    style="color: #4A5568;">Start Date</label>
                                                <input type="date" class="form-control" id="start_date"
                                                    name="start_date">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="end_date" class="form-label fw-bold"
                                                    style="color: #4A5568;">End Date</label>
                                                <input type="date" class="form-control" id="end_date"
                                                    name="end_date">
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label for="sku_participated_count" class="form-label fw-bold"
                                                    style="color: #4A5568;">Number of SKU Participated</label>
                                                <input type="number" class="form-control" id="sku_participated_count"
                                                    name="sku_participated_count"
                                                    placeholder="Enter Number of SKU Participated">
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label for="l30_sold_qty_participated_before"
                                                    class="form-label fw-bold">L30 Sold Qt of Participated SKUs
                                                    (Before)</label>
                                                <input type="number" class="form-control"
                                                    id="l30_sold_qty_participated_before"
                                                    name="l30_sold_qty_participated_before">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="l30_t_sales_participated_before"
                                                    class="form-label fw-bold">L30 T Sales of Participated SKUs
                                                    (Before)</label>
                                                <input type="number" class="form-control"
                                                    id="l30_t_sales_participated_before"
                                                    name="l30_t_sales_participated_before">
                                            </div>
                                        </div>
                                        <div class="row mb-4">
                                            <div class="col-md-4">
                                                <label for="l30_sold_qty_participated_after"
                                                    class="form-label fw-bold">L30 Sold Qt of Participated SKUs (After 7
                                                    days)</label>
                                                <input type="number" class="form-control"
                                                    id="l30_sold_qty_participated_after"
                                                    name="l30_sold_qty_participated_after">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="l30_t_sales_participated_after" class="form-label fw-bold">L30
                                                    T Sales of Participated SKUs (After 7 days)</label>
                                                <input type="number" class="form-control"
                                                    id="l30_t_sales_participated_after"
                                                    name="l30_t_sales_participated_after">
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Modal Footer -->
                                <div class="modal-footer"
                                    style="background: linear-gradient(135deg, #F8FAFF 0%, #E6F0FF 100%); border-top: 4px solid #E2E8F0; padding: 1.5rem; border-radius: 0;">
                                    <button type="button" class="btn btn-lg" data-bs-dismiss="modal"
                                        style="background: linear-gradient(135deg, #FF6B6B 0%, #FF0000 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </button>
                                    <button type="button" class="btn btn-lg" id="saveProductBtn"
                                        style="background: linear-gradient(135deg, #4ADE80 0%, #22C55E 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                                        <i class="fas fa-save me-2"></i>Update Channel Promotion
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Modal -->
                    <div id="progressModal" class="modal fade" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">Processing Data</h5>
                                </div>
                                <div class="modal-body">
                                    <div id="progress-container" class="mb-3"></div>
                                    <div id="error-container"></div>
                                    <div id="success-alert" class="alert alert-success" style="display:none">
                                        All sheets updated successfully!
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button id="cancelUploadBtn" class="btn btn-secondary">Cancel</button>
                                    <button id="doneBtn" class="btn btn-primary" style="display:none">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Process Selected Modal -->
                    <div class="modal fade" id="processSelectedModal" tabindex="-1"
                        aria-labelledby="processSelectedModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header"
                                    style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                                    <h5 class="modal-title" id="processSelectedModalLabel">Process Selected Items</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                        aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Selected <span id="selectedItemCount" class="fw-bold">0</span> items. Choose fields
                                        to update:</p>

                                    <div id="fieldOperations">
                                        <div class="field-operation mb-3">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-3">
                                                    <select class="form-select field-selector">
                                                        <option value="">Select Field</option>
                                                        <option value="lp">LP</option>
                                                        <option value="cp">CP</option>
                                                        <option value="frght">FRGHT</option>
                                                        <option value="ship">SHIP</option>
                                                        <option value="label_qty">Label QTY</option>
                                                        <option value="wt_act">WT ACT</option>
                                                        <option value="wt_decl">WT DECL</option>
                                                        <option value="l">L</option>
                                                        <option value="w">W</option>
                                                        <option value="h">H</option>
                                                        <option value="status">Status</option>
                                                    </select>
                                                </div>
                                                <div class="col-3">
                                                    <select class="form-select operation-selector">
                                                        <option value="set">=</option>
                                                        <option value="add">+</option>
                                                        <option value="subtract">-</option>
                                                        <option value="multiply">×</option>
                                                        <option value="divide">÷</option>
                                                    </select>
                                                </div>
                                                <div class="col-4">
                                                    <input type="text" class="form-control field-value"
                                                        placeholder="Enter value">
                                                </div>
                                                <div class="col-2">
                                                    <button type="button" class="btn btn-outline-danger remove-field">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <button id="addFieldBtn" class="btn btn-outline-primary mt-2">
                                        <i class="fas fa-plus"></i> Add Field
                                    </button>

                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Changes will be applied to all selected items
                                    </div>

                                    <div id="batchUpdateResult" class="mt-3"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="applyChangesBtn">Apply
                                        Changes</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selection actions bar -->
                    <div class="selection-actions" id="selectionActions">
                        <span class="selection-count">0 items selected</span>
                        <button class="btn btn-sm btn-light" id="cancelSelection">Cancel</button>
                        <button class="btn btn-sm btn-success" id="processSelected">Process Selected</button>
                    </div>

                    <div class="table-responsive">
                        <table id="row-callback-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="min-width: 60px; width: 60px;">Sl No.</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Channel Name</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Worksheet Link</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Priority Level</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Issues <br> Identified</th>
                                    <th rowspan="2" style="min-width: 140px; width: 140px;">Total Sales <br> (Last 60
                                        Days)</th>
                                    <th rowspan="2" style="min-width: 140px; width: 140px;">Total Sales <br> (Last 30
                                        Days)</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Growth <br> Percentage</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Promotion <br> Status</th>
                                    <th rowspan="2" style="min-width: 160px; width: 160px;">Number of Active <br>
                                        Promotions</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Promotion Type</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">Start Date</th>
                                    <th rowspan="2" style="min-width: 120px; width: 120px;">End Date</th>
                                    <th rowspan="2" style="min-width: 160px; width: 160px;">Number of SKUs <br>
                                        Participated</th>
                                    <th colspan="2" class="text-center" style="min-width: 300px;">Before Promotion
                                        <br> Started
                                    </th>
                                    <th colspan="2" class="text-center" style="min-width: 300px;">After 7 Days of <br>
                                        Promotion Started</th>
                                    <th rowspan="2" style="min-width: 80px; width: 80px;">Actions</th>
                                </tr>
                                <tr>
                                    <th class="text-center" style="min-width: 150px;">L30 Sold Quantity <br> of
                                        Participated SKUs</th>
                                    <th class="text-center" style="min-width: 150px;">L30 Total Sales <br> of Participated
                                        SKUs</th>
                                    <th class="text-center" style="min-width: 150px;">L30 Sold Quantity <br> of
                                        Participated SKUs</th>
                                    <th class="text-center" style="min-width: 150px;">L30 Total Sales <br> of Participated
                                        SKUs</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($channels as $index => $channel)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $channel->channel }}</td>
                                        <td>
                                            @if ($channel->worksheet_link)
                                                <a href="{{ $channel->worksheet_link }}" target="_blank">Worksheet</a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $channel->priority == 1 ? 'Yes' : 'No' }}</td> <!-- Display Priority -->
                                        <td>{{ $channel->issues ?? '-' }}</td>
                                        <td>{{ $channel->t_sales_l60 ?? '-' }}</td>
                                        <td>{{ $channel->t_sales_l30 ?? '-' }}</td>
                                        <td>{{ $channel->growth ?? '-' }}</td>
                                        <td>
                                            @if ($channel->promotion_status)
                                                <div class="status-badge" data-status="{{ $channel->promotion_status }}">
                                                    @if ($channel->promotion_status == 'No Promotion')
                                                        <span class="option-indicator"
                                                            style="background-color: #FFD600;"><i class="fas fa-ban"
                                                                style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                        <span>No Promotion</span>
                                                    @elseif($channel->promotion_status == 'Active')
                                                        <span class="option-indicator"
                                                            style="background-color: #22C55E;"><i
                                                                class="fas fa-check-circle"
                                                                style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                        <span>Active</span>
                                                    @elseif($channel->promotion_status == 'Inactive')
                                                        <span class="option-indicator"
                                                            style="background-color: #EF4444;"><i
                                                                class="fas fa-times-circle"
                                                                style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                        <span>Inactive</span>
                                                    @elseif($channel->promotion_status == 'On Progress')
                                                        <span class="option-indicator"
                                                            style="background-color: #8B5CF6;"><i class="fas fa-clock"
                                                                style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                        <span>On Progress</span>
                                                    @elseif($channel->promotion_status == 'Ended')
                                                        <span class="option-indicator"
                                                            style="background-color: #B91C1C;"><i
                                                                class="fas fa-stop-circle"
                                                                style="color: white; font-size: 12px; margin: 0 auto; display: block; text-align: center; line-height: 20px;"></i></span>
                                                        <span>Ended</span>
                                                    @else
                                                        {{ $channel->promotion_status }}
                                                    @endif
                                                </div>
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $channel->promotion_active_count ?? '-' }}</td>
                                        <td>{{ $channel->promotion_type ?? '-' }}</td>
                                        <td>{{ $channel->start_date ?? '-' }}</td>
                                        <td>{{ $channel->end_date ?? '-' }}</td>
                                        <td>{{ $channel->sku_participated_count ?? '-' }}</td>
                                        <td>{{ $channel->l30_sold_qty_participated_before ?? '-' }}</td>
                                        <td>{{ $channel->l30_t_sales_participated_before ?? '-' }}</td>
                                        <td>{{ $channel->l30_sold_qty_participated_after ?? '-' }}</td>
                                        <td>{{ $channel->l30_t_sales_participated_after ?? '-' }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary edit-channel-btn"
                                                data-bs-toggle="modal" data-bs-target="#addProductModal"
                                                data-channel='@json($channel)'>
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Product Master Data...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script>
        // Set page zoom to 80%
        document.body.style.zoom = "80%";

        // Modern custom dropdown implementation
        $(document).ready(function() {
            // Custom dropdown functionality
            const customSelect = $('#promotion_status_display');
            const dropdownMenu = $('#promotion_options');
            const dropdownItems = $('.dropdown-item');
            const hiddenInput = $('#promotion_status_hidden');
            const selectedOptionText = $('.selected-option');

            // Initialize dropdown on page load - set the first option as default
            updateDropdownSelection('No Promotion');

            // Toggle dropdown menu visibility when clicking on the custom select
            customSelect.on('click', function() {
                $(this).toggleClass('open');
                if (dropdownMenu.is(':hidden')) {
                    dropdownMenu.addClass('show').css('display', 'block');
                } else {
                    dropdownMenu.removeClass('show');
                    setTimeout(() => {
                        dropdownMenu.css('display', 'none');
                    }, 150);
                }
            });

            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.custom-dropdown-container').length) {
                    customSelect.removeClass('open');
                    dropdownMenu.removeClass('show');
                    setTimeout(() => {
                        dropdownMenu.css('display', 'none');
                    }, 150);
                }
            });

            // Handle dropdown item selection
            dropdownItems.on('click', function() {
                const value = $(this).data('value');
                const text = $(this).find('.option-text').text();

                // Update hidden input value
                hiddenInput.val(value);

                // Update displayed text
                selectedOptionText.text(text);

                // Update selected state
                dropdownItems.removeClass('selected');
                $(this).addClass('selected');

                // Close dropdown
                customSelect.removeClass('open');
                dropdownMenu.slideUp(150);
            });

            // Fill form on pen click
            $(document).on('click', '.edit-channel-btn', function() {
                const channel = $(this).data('channel');
                $('#channel').val(channel.channel ?? '');
                $('#worksheet_link').val(channel.b_link ?? '');
                $('#priority').prop('checked', channel.priority == 1);
                $('#issues').val(channel.issues ?? '');
                $('#t_sales_l60').val(channel.t_sales_l60 ?? '');
                $('#t_sales_l30').val(channel.t_sales_l30 ?? '');
                $('#growth').val(channel.growth ?? '');

                // Update custom dropdown
                hiddenInput.val(channel.promotion_status ?? '');
                updateDropdownSelection(channel.promotion_status);

                $('#promotion_active_count').val(channel.promotion_active_count ?? '');
                $('#promotion_type').val(channel.promotion_type ?? '');
                $('#start_date').val(channel.start_date ?? '');
                $('#end_date').val(channel.end_date ?? '');
                $('#sku_participated_count').val(channel.sku_participated_count ?? '');
                $('#l30_sold_qty_participated_before').val(channel.l30_sold_qty_participated_before ?? '');
                $('#l30_t_sales_participated_before').val(channel.l30_t_sales_participated_before ?? '');
                $('#l30_sold_qty_participated_after').val(channel.l30_sold_qty_participated_after ?? '');
                $('#l30_t_sales_participated_after').val(channel.l30_t_sales_participated_after ?? '');
            });

            // Function to update dropdown selection
            function updateDropdownSelection(status) {
                // Update displayed text
                selectedOptionText.text(status || 'Select Promotion Status');

                // Update selected state
                dropdownItems.removeClass('selected');
                if (status) {
                    dropdownItems.filter(`[data-value="${status}"]`).addClass('selected');
                }
            }

            // Submit update form as JSON
            $('#saveProductBtn').on('click', function() {
                const data = {
                    channels: $('#channel').val(),
                    value: {
                        worksheet_link: $('#worksheet_link').val(),
                        priority: $('#priority').is(':checked') ? 1 : 0, // Handle checkbox value
                        issues: $('#issues').val(),
                        t_sales_l60: $('#t_sales_l60').val(),
                        t_sales_l30: $('#t_sales_l30').val(),
                        growth: $('#growth').val(),
                        promotion_status: $('#promotion_status_hidden').val(),
                        promotion_active_count: $('#promotion_active_count').val(),
                        promotion_type: $('#promotion_type').val(),
                        start_date: $('#start_date').val(),
                        end_date: $('#end_date').val(),
                        sku_participated_count: $('#sku_participated_count').val(),
                        l30_sold_qty_participated_before: $('#l30_sold_qty_participated_before').val(),
                        l30_t_sales_participated_before: $('#l30_t_sales_participated_before').val(),
                        l30_sold_qty_participated_after: $('#l30_sold_qty_participated_after').val(),
                        l30_t_sales_participated_after: $('#l30_t_sales_participated_after').val()
                    }
                };

                $.ajax({
                    url: '/channel-promotion/store',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(data),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $('#addProductModal').modal('hide');
                        location.reload();
                    },
                    error: function(xhr) {
                        let msg = 'Update failed';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        $('#form-errors').html('<div class="alert alert-danger">' + msg +
                            '</div>');
                    }
                });
            });

            // Download Excel functionality
            document.getElementById('downloadExcel').addEventListener('click', function() {
                // Get table data
                const table = document.getElementById('row-callback-datatable');
                const rows = table.querySelectorAll('tr');
                let csvContent = '';

                rows.forEach(row => {
                    const cells = row.querySelectorAll('th, td');
                    const rowData = Array.from(cells).map(cell => `"${cell.innerText}"`).join(',');
                    csvContent += rowData + '\n';
                });

                // Create a Blob for the CSV content
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);

                // Create a download link
                const link = document.createElement('a');
                link.href = url;
                link.setAttribute('download', 'channel_promotion_data.csv');
                document.body.appendChild(link);
                link.click();

                // Clean up
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            });

            {{-- Search functionality for the custom search input --}}
            document.getElementById('customSearch').addEventListener('input', function () {
                const searchValue = this.value.toLowerCase();
                const table = document.getElementById('row-callback-datatable');
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const channelCell = row.querySelector('td:nth-child(2)'); // Assuming the channel name is in the second column
                    if (channelCell) {
                        const channelName = channelCell.textContent.toLowerCase();
                        if (channelName.includes(searchValue)) {
                            row.style.display = ''; // Show row
                        } else {
                            row.style.display = 'none'; // Hide row
                        }
                    }
                });
            });

            document.getElementById('clearSearch').addEventListener('click', function () {
                const searchInput = document.getElementById('customSearch');
                searchInput.value = '';
                const table = document.getElementById('row-callback-datatable');
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    row.style.display = ''; // Show all rows
                });
            });
        });
    </script>
@endsection
