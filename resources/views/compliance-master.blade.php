@extends('layouts.vertical', ['title' => 'Compliance Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'sidenav' => 'condensed'])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">

    <style>
        /* Aliexpress-tabulator style: full-height table area */
        #compliance-table-wrapper {
            height: calc(100vh - 188px);
            min-height: 280px;
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        #compliance-tabulator.cm-tabulator-host {
            flex: 1;
            min-height: 0;
            width: 100%;
            border-top: 1px solid #dee2e6;
        }

        #compliance-tabulator .tabulator {
            font-size: 11px;
            width: 100% !important;
            max-width: 100%;
            border: 1px solid #d1d5db;
        }

        #compliance-tabulator .tabulator-tableholder {
            overflow-x: auto;
        }

        #compliance-tabulator .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }

        /* AliExpress tabulator: dense neutral header + vertical titles */
        #compliance-tabulator .tabulator-header {
            background: #e8ecf1;
            color: #1e293b;
            font-weight: 600;
            border-bottom: 1px solid #cbd5e1;
        }

        /* Horizontal titles with the filter control above the name */
        #compliance-tabulator .tabulator-header .tabulator-col {
            border-right: 1px solid #cbd5e1;
            height: auto !important;
            min-height: 72px !important;
        }

        #compliance-tabulator .tabulator-header .tabulator-col-content {
            padding: 4px 3px 6px;
            height: 100%;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            gap: 4px;
        }

        #compliance-tabulator .tabulator-header .tabulator-col .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            text-orientation: mixed;
            white-space: normal;
            transform: none !important;
            height: auto !important;
            display: block;
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
            color: #334155;
            padding: 0;
            margin: 0;
            order: 2;
        }

        #compliance-tabulator .tabulator-header,
        #compliance-tabulator .tabulator-headers,
        #compliance-tabulator .tabulator-header .tabulator-col,
        #compliance-tabulator .tabulator-header .tabulator-col-content,
        #compliance-tabulator .tabulator-header-filter {
            overflow: visible !important;
        }

        #compliance-tabulator .tabulator-header .tabulator-header-filter {
            order: -1;
            width: 100%;
            padding: 0 0 4px;
            position: relative;
            overflow: visible !important;
            display: block !important;
            visibility: visible !important;
            height: auto !important;
            min-height: 24px;
        }

        #compliance-tabulator .tabulator-header .tabulator-col-title-holder {
            order: 2;
            width: 100%;
        }

        #compliance-tabulator .tabulator-header-filter .cm-filter-cell {
            width: 100%;
            min-width: 0;
            padding: 0;
        }

        #compliance-tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0 !important;
        }

        /* Bulk-select column: horizontal header (checkbox) */
        #compliance-tabulator .tabulator-header .tabulator-col.cm-tabulator-cb-header .tabulator-col-title,
        #compliance-tabulator .tabulator-header .tabulator-col[tabulator-field="_cb"] .tabulator-col-title {
            writing-mode: horizontal-tb !important;
            transform: none !important;
            height: auto !important;
            width: 100%;
            min-height: 0;
        }

        #compliance-tabulator .tabulator-header .tabulator-col.cm-tabulator-cb-header .tabulator-col-content,
        #compliance-tabulator .tabulator-header .tabulator-col[tabulator-field="_cb"] .tabulator-col-content {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2px !important;
        }

        #compliance-tabulator .tabulator-header .tabulator-col.cm-tabulator-cb-header {
            height: auto !important;
            min-height: 72px !important;
        }

        #compliance-tabulator .tabulator-cell.cm-compliance-field-col {
            min-width: 0;
            padding-left: 2px;
            padding-right: 2px;
        }

        #compliance-tabulator .compliance-thumb-wrap img,
        #compliance-tabulator .compliance-thumb-img {
            max-width: 32px;
            max-height: 32px;
            width: auto;
            height: auto;
            object-fit: cover;
        }

        #compliance-tabulator .tabulator-row .tabulator-cell {
            padding: 3px 5px;
            vertical-align: middle;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #f1f5f9;
        }

        #compliance-tabulator .tabulator-row .tabulator-cell:last-child {
            border-right: none;
        }

        #compliance-tabulator .cm-status-marble {
            width: 11px;
            height: 11px;
        }

        #compliance-tabulator .tabulator-cell.compliance-status-col .cm-status-cell-inner {
            gap: 0;
        }

        #compliance-tabulator .compliance-na-badge,
        #compliance-tabulator .compliance-req-badge {
            font-size: 11px;
            padding: 0.1rem 0.35rem;
            font-weight: 600;
            line-height: 1.2;
        }

        #compliance-tabulator .tabulator-row .tabulator-cell {
            font-size: 11px;
            line-height: 1.25;
        }

        #compliance-tabulator .compliance-pdf-icon-bg {
            width: 22px;
            height: 22px;
            font-size: 11px;
            border-radius: 4px;
        }

        #compliance-tabulator .edit-btn,
        #compliance-tabulator .delete-btn {
            padding: 1px 5px;
            border-radius: 3px;
            line-height: 1.2;
        }

        #compliance-tabulator .edit-btn:hover,
        #compliance-tabulator .delete-btn:hover {
            transform: none;
            box-shadow: none;
        }

        #compliance-tabulator .tabulator-row.tabulator-row-even .tabulator-cell {
            background-color: #fafafa;
        }

        #compliance-tabulator .tabulator-row:hover .tabulator-cell {
            background-color: #f1f5f9;
        }

        .cm-toolbar-search-strip {
            flex-shrink: 0;
        }

        .cm-toolbar-search-strip {
            flex-shrink: 0;
        }

        .cm-filter-cell.cm-filter-cell--status {
            overflow: visible;
            z-index: 6;
        }

        .cm-filter-cell .form-label {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 10px;
            line-height: 1.2;
        }

        .cm-toolbar-search-strip .form-control-sm {
            font-size: 11px;
            padding-top: 0.2rem;
            padding-bottom: 0.2rem;
        }

        .cm-filter-cell .form-control-sm,
        .cm-filter-cell .form-select-sm {
            font-size: 10px;
            padding-top: 0.15rem;
            padding-bottom: 0.15rem;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
            width: 100%;
            min-width: 0;
        }

        .cm-filter-cell .cm-field-filter {
            font-weight: 600;
            transition: background-color 0.12s ease, color 0.12s ease, border-color 0.12s ease;
        }

        .cm-filter-cell .cm-field-filter.cm-filter-req {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter.cm-filter-na {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter option {
            background-color: #fff;
            color: #212529;
            font-weight: 600;
        }

        .cm-filter-cell .cm-field-filter option.cm-opt-req,
        .cm-filter-cell .cm-field-filter option[value="req"] {
            background-color: #dc3545 !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter option.cm-opt-na,
        .cm-filter-cell .cm-field-filter option[value="na"] {
            background-color: #0d6efd !important;
            color: #fff !important;
        }

        .cm-filter-cell .cm-field-filter option[value="all"] {
            background-color: #fff !important;
            color: #212529 !important;
        }

        #cm-summary-stats {
            padding: 0.5rem 0.65rem !important;
        }

        #cm-summary-stats h6 {
            font-size: 11px;
            margin-bottom: 0.35rem !important;
        }

        #cm-summary-stats .badge {
            font-size: 11px;
            font-weight: 600;
            padding: 0.2rem 0.4rem;
        }

        .cm-filter-cell .cm-status-filter-wrap--toolbar .cm-status-filter-trigger,
        #compliance-tabulator .tabulator-header-filter .cm-status-filter-wrap--toolbar .cm-status-filter-trigger {
            color: #1e293b;
            background: #fff;
            border: 1px solid #cbd5e1;
            padding: 3px 4px;
            border-radius: 5px;
            gap: 4px;
            font-size: 10px;
            width: 100%;
            justify-content: space-between;
        }

        .cm-filter-cell .cm-status-filter-wrap--toolbar .cm-status-filter-trigger:hover,
        #compliance-tabulator .tabulator-header-filter .cm-status-filter-wrap--toolbar .cm-status-filter-trigger:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .cm-filter-cell .cm-status-filter-wrap--toolbar .cm-status-filter-trigger-label,
        #compliance-tabulator .tabulator-header-filter .cm-status-filter-wrap--toolbar .cm-status-filter-trigger-label {
            color: #334155;
        }

        #compliance-table-wrapper .rainbow-loader {
            flex-shrink: 0;
            padding: 16px;
            background: #fafbfc;
            border-top: 1px solid #dee2e6;
        }

        .table-responsive {
            position: relative;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            background-color: white;
        }

        .table-responsive thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%) !important;
            color: white;
            z-index: 10;
            padding: 15px 18px;
            font-weight: 600;
            border-bottom: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-size: 13px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.2s ease;
        }

        .table-responsive thead th:hover {
            background: linear-gradient(135deg, #1a56b7 0%, #0a3d8f 100%) !important;
        }

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

        .table-responsive tbody td {
            padding: 12px 18px;
            vertical-align: middle;
            border-bottom: 1px solid #edf2f9;
            font-size: 13px;
            color: #495057;
            transition: all 0.2s ease;
        }

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

        /* Parent summary rows (SKU or Parent contains PARENT) */
        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword .tabulator-cell {
            background-color: #fffef2 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword:hover .tabulator-cell {
            background-color: #fefce8 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword:hover {
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.06);
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword .tabulator-cell.compliance-status-col {
            background-color: #fffef2 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword:hover .tabulator-cell.compliance-status-col {
            background-color: #fefce8 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword .tabulator-cell.compliance-parent-col:hover {
            background-color: #fffde7 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-com-parent-keyword:hover .tabulator-cell.compliance-parent-col:hover {
            background-color: #fffef2 !important;
        }

        .table-responsive .text-center {
            text-align: center;
        }

        .table {
            margin-bottom: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

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

        .delete-btn {
            border-radius: 6px;
            padding: 6px 12px;
            transition: all 0.2s;
            background: #fff;
            border: 1px solid #dc3545;
            color: #dc3545;
        }

        .delete-btn:hover {
            background: #dc3545;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 3px 8px rgba(220, 53, 69, 0.2);
        }

        .rainbow-loader {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading-text {
            margin-top: 10px;
            font-weight: bold;
        }

        .custom-toast {
            z-index: 2000;
            max-width: 400px;
            width: auto;
            min-width: 300px;
            font-size: 16px;
        }
        
        .toast-body {
            padding: 12px 15px;
            word-wrap: break-word;
            white-space: normal;
        }

        .compliance-thumb-wrap {
            display: inline-block;
            line-height: 0;
            cursor: zoom-in;
        }

        .compliance-thumb-wrap img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }

        .compliance-thumb-wrap:hover img {
            box-shadow: 0 4px 14px rgba(26, 86, 183, 0.35);
            transform: scale(1.05);
        }

        #compliance-img-hover-preview {
            position: fixed;
            z-index: 10050;
            display: none;
            pointer-events: none;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
            border-radius: 8px;
            background: #fff;
            padding: 6px;
            line-height: 0;
        }

        #compliance-img-hover-preview img {
            max-width: min(92vw, 380px);
            max-height: min(85vh, 380px);
            width: auto;
            height: auto;
            object-fit: contain;
            display: block;
            border-radius: 4px;
        }

        /* Parent: shrink with fitColumns; ellipsis until hover expand */
        #compliance-tabulator .tabulator-cell.compliance-parent-col {
            min-width: 0;
            box-sizing: border-box;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap !important;
            text-align: left;
        }

        #compliance-tabulator .tabulator-cell.compliance-parent-col:hover {
            white-space: normal !important;
            word-break: break-word;
            overflow: visible;
            max-width: min(28rem, 78vw);
            width: max-content;
            min-width: 3.5rem;
            position: relative;
            z-index: 25;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
            background-color: #fff;
        }

        #compliance-tabulator .tabulator-row.tabulator-row-even .tabulator-cell.compliance-parent-col:hover {
            background-color: #fafafa;
        }

        #compliance-tabulator .tabulator-row:hover .tabulator-cell.compliance-parent-col:hover {
            background-color: #f1f5f9;
        }

        .cm-status-filter-wrap {
            position: relative;
            width: 100%;
        }

        .cm-status-filter-trigger {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 8px;
            font-size: 11px;
            font-weight: 600;
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.15s ease, border-color 0.15s ease;
        }

        .cm-status-filter-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .cm-status-filter-menu {
            display: none;
            list-style: none;
            margin: 0;
            padding: 8px;
            background: rgba(30, 34, 42, 0.88);
            -webkit-backdrop-filter: blur(12px);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 14px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.35);
            z-index: 4000;
        }

        .cm-status-filter-wrap.is-open .cm-status-filter-menu {
            display: block;
        }

        .cm-status-filter-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 12px;
            margin: 0;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .cm-status-filter-item:hover,
        .cm-status-filter-item.is-selected {
            background: #2563eb;
        }

        .cm-status-filter-check {
            display: inline-flex;
            width: 18px;
            height: 18px;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            flex-shrink: 0;
        }

        .cm-status-filter-item-spacer {
            width: 18px;
            flex-shrink: 0;
        }

        .cm-status-marble {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            box-shadow:
                inset 2px 2px 4px rgba(255, 255, 255, 0.55),
                inset -2px -3px 5px rgba(0, 0, 0, 0.35);
            vertical-align: middle;
        }

        .cm-status-marble--active {
            background: radial-gradient(circle at 32% 28%, #bbf7d0, #22c55e 42%, #14532d);
        }

        .cm-status-marble--inactive,
        .cm-status-marble--dc {
            background: radial-gradient(circle at 32% 28%, #fecaca, #ef4444 42%, #7f1d1d);
        }

        .cm-status-marble--upcoming {
            background: radial-gradient(circle at 32% 28%, #fffef2, #eab308 45%, #713f12);
        }

        .cm-status-marble--2bdc {
            background: radial-gradient(circle at 32% 28%, #bfdbfe, #2563eb 45%, #1e3a8a);
        }

        .cm-status-marble--muted {
            background: radial-gradient(circle at 32% 28%, #e5e7eb, #9ca3af 45%, #374151);
        }

        #compliance-tabulator .tabulator-cell.compliance-status-col {
            background-color: #f8f9fa !important;
            color: #4a5568;
            font-weight: 500;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
        }

        #compliance-tabulator .tabulator-cell.compliance-status-col .cm-status-cell-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        #compliance-tabulator .tabulator-cell.compliance-status-col .cm-status-cell-text {
            line-height: 1.2;
        }

        #compliance-tabulator .tabulator-row:hover .tabulator-cell.compliance-status-col {
            background-color: #f1f5f9 !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-row-even .tabulator-cell.compliance-status-col {
            background-color: #fafafa !important;
        }

        #compliance-tabulator .tabulator-row.tabulator-row-even:hover .tabulator-cell.compliance-status-col {
            background-color: #f1f5f9 !important;
        }

        #compliance-tabulator .tabulator-cell.compliance-checkbox-cell {
            width: 36px;
            max-width: 36px;
            text-align: center;
            vertical-align: middle;
            padding-left: 4px;
            padding-right: 4px;
        }

        #complianceSelectAllCheckbox {
            cursor: pointer;
        }

        .compliance-field-block .btn-check:checked + .btn-outline-secondary {
            background-color: #6c757d;
            color: #fff;
        }

        .compliance-field-block .btn-check:checked + .btn-outline-primary {
            background-color: #0d6efd;
            color: #fff;
        }

        .compliance-field-thumb {
            width: 36px;
            height: 36px;
            object-fit: cover;
            border-radius: 4px;
            vertical-align: middle;
        }

        /* Right-side compliance editor panel — dense, one-screen */
        #addComplianceModal.offcanvas-end {
            width: min(360px, 100vw);
            border-left: 1px solid rgba(15, 23, 42, 0.08);
        }

        #addComplianceModal .offcanvas-header {
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            color: #fff;
            padding: 0.45rem 0.75rem;
        }

        #addComplianceModal .offcanvas-title {
            font-size: 0.85rem;
            font-weight: 600;
        }

        #addComplianceModal .btn-close {
            transform: scale(0.85);
        }

        #addComplianceModal .cm-panel-body {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
            padding: 0;
            overflow: hidden;
        }

        #addComplianceModal .cm-panel-scroll {
            flex: 1 1 auto;
            overflow: hidden;
            padding: 0.45rem 0.55rem 0.25rem;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        #addComplianceModal #addComplianceForm {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }

        #addComplianceModal .cm-panel-footer {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.4rem;
            padding: 0.4rem 0.55rem;
            border-top: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        #addComplianceModal .cm-sku-block {
            flex: 0 0 auto;
            margin-bottom: 0.35rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid #eef2f7;
        }

        #addComplianceModal .cm-sku-block .form-label {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #64748b;
            margin-bottom: 0.1rem;
        }

        #addComplianceModal .cm-meta-row {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.35rem 0.5rem;
            margin-top: 0.25rem;
            font-size: 0.72rem;
        }

        #addComplianceModal .cm-meta-row .small {
            font-size: 0.68rem;
        }

        #addComplianceModal .cm-fields-list {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
            flex: 1 1 auto;
            min-height: 0;
            justify-content: space-evenly;
        }

        #addComplianceModal .compliance-field-block {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #fff;
            padding: 0.2rem 0.35rem;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }

        #addComplianceModal .compliance-field-block.is-req {
            border-color: #fca5a5;
            background: #fef2f2;
        }

        #addComplianceModal .compliance-field-block.is-req.is-complete {
            border-color: #86efac;
            background: #f0fdf4;
        }

        #addComplianceModal .cm-field-row {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        #addComplianceModal .cm-field-label {
            font-size: 0.72rem;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
            min-width: 3.6rem;
            flex: 0 0 3.6rem;
            line-height: 1.1;
        }

        #addComplianceModal .cm-field-row .btn-group {
            flex: 1 1 auto;
        }

        #addComplianceModal .cm-field-row .btn {
            padding: 0.12rem 0.35rem;
            font-size: 0.65rem;
            font-weight: 600;
            line-height: 1.2;
        }

        #addComplianceModal .cm-file-actions {
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
            flex: 0 0 auto;
        }

        #addComplianceModal .cm-file-btn {
            position: relative;
            width: 26px;
            height: 26px;
            padding: 0;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background: #fff;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            line-height: 1;
            cursor: pointer;
        }

        #addComplianceModal .cm-file-btn:hover {
            border-color: #0d9488;
            color: #0f766e;
            background: #f0fdfa;
        }

        #addComplianceModal .cm-file-btn.has-file {
            border-color: #16a34a;
            color: #15803d;
            background: #dcfce7;
        }

        #addComplianceModal .cm-file-btn.missing-file {
            border-color: #ef4444;
            color: #b91c1c;
            background: #fee2e2;
        }

        #addComplianceModal .cm-file-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #ef4444;
            border: 1px solid #fff;
        }

        #addComplianceModal .cm-file-btn.has-file .cm-file-dot {
            background: #22c55e;
        }

        #addComplianceModal .cm-file-preview-link {
            font-size: 0.62rem;
            max-width: 28px;
            overflow: hidden;
            white-space: nowrap;
        }

        #addComplianceModal .cm-file-preview-link img {
            width: 22px;
            height: 22px;
            object-fit: cover;
            border-radius: 3px;
            display: block;
        }

        #addComplianceModal #cm_autosave_status {
            font-size: 0.65rem;
            white-space: nowrap;
        }

        #addComplianceModal .select2-container .select2-selection--single {
            min-height: 28px !important;
            border-color: #d1d5db;
        }

        #addComplianceModal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 26px !important;
            font-size: 0.78rem;
            padding-left: 0.45rem;
        }

        #addComplianceModal .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 26px !important;
        }

        @media (max-height: 720px) {
            #addComplianceModal .cm-field-label {
                min-width: 3.2rem;
                flex-basis: 3.2rem;
                font-size: 0.68rem;
            }
            #addComplianceModal .cm-field-row .btn {
                padding: 0.08rem 0.28rem;
                font-size: 0.6rem;
            }
            #addComplianceModal .cm-file-btn {
                width: 22px;
                height: 22px;
                font-size: 0.62rem;
            }
            #addComplianceModal .compliance-field-block {
                padding: 0.12rem 0.28rem;
            }
            #addComplianceModal .cm-fields-list {
                gap: 0.12rem;
            }
        }

        .compliance-na-badge {
            background-color: #0d6efd !important;
            color: #fff !important;
            font-weight: 600;
            border: none;
        }

        .compliance-nrq-badge {
            background-color: #0d6efd !important;
            color: #fff !important;
            font-weight: 600;
            border: none;
        }

        .compliance-req-badge {
            background-color: #dc3545 !important;
            color: #fff !important;
            font-weight: 600;
        }

        .compliance-req-badge.compliance-req-ok {
            background-color: #198754 !important;
            color: #fff !important;
        }

        .compliance-pdf-link {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
        }

        .compliance-pdf-icon-bg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background-color: #22c55e;
            color: #fff;
            font-size: 15px;
            line-height: 1;
            transition: background-color 0.15s ease;
        }

        .compliance-pdf-link:hover .compliance-pdf-icon-bg,
        .cm-file-chip:hover .compliance-pdf-icon-bg,
        .cm-file-chip.is-menu-open .compliance-pdf-icon-bg {
            background-color: #16a34a;
            color: #fff;
        }

        .cm-file-chip {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            cursor: pointer;
        }

        .cm-file-chip .compliance-field-thumb {
            width: 28px;
            height: 28px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }

        #cm-file-action-menu {
            position: fixed;
            z-index: 10060;
            display: none;
            min-width: 118px;
            padding: 4px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
        }

        #cm-file-action-menu.is-open {
            display: block;
        }

        #cm-file-action-menu button {
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            border: 0;
            background: transparent;
            color: #0f172a;
            font-size: 11px;
            font-weight: 600;
            padding: 6px 8px;
            border-radius: 6px;
            cursor: pointer;
            text-align: left;
        }

        #cm-file-action-menu button:hover {
            background: #f1f5f9;
            color: #0f766e;
        }

        #cm-file-action-menu button i {
            width: 14px;
            text-align: center;
            color: #64748b;
        }

        #cm-file-action-menu button:hover i {
            color: #0f766e;
        }

        #compliance-tabulator .tabulator-cell.cm-compliance-field-col {
            overflow: visible;
        }
    </style>
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $__cmFields = [
            'battery' => 'Battery',
            'wireless' => 'Wireless',
            'electric' => 'Electric',
            'gcc' => 'GCC',
            'rohs' => 'RoHs',
            'blanket' => 'Blanket',
            'bluetooth' => 'Bluetooth',
            'logo' => 'Logo',
            'graph' => 'Graph',
        ];
        $__cmFilterIds = [
            'battery' => 'filterBattery',
            'wireless' => 'filterWireless',
            'electric' => 'filterElectric',
            'gcc' => 'filterGcc',
            'rohs' => 'filterRohs',
            'blanket' => 'filterBlanket',
            'bluetooth' => 'filterBluetooth',
            'logo' => 'filterLogo',
            'graph' => 'filterGraph',
        ];
    @endphp

    @include('layouts.shared.page-title', [
        'page_title' => 'Compliance Masters',
        'sub_title' => 'Compliance Masters Analysis',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 11000;"></div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2">
                    <h4 class="mb-1 fs-5">Compliance Masters</h4>
                    <p class="text-muted small mb-2">Compliance Masters Analysis</p>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="complianceBulkEditBtn" title="Edit compliance fields for all selected rows">
                            <i class="fas fa-pen-to-square me-1"></i> Bulk edit
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="addComplianceBtn">
                            <i class="fas fa-plus me-1"></i> Add Compliance Data
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                            <i class="fas fa-upload me-1"></i> Import Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-success" id="downloadExcel">
                            <i class="fas fa-file-excel me-1"></i> Download Excel
                        </button>
                    </div>
                    <div id="cm-summary-stats" class="mt-1 p-2 bg-light rounded border border-light">
                        <h6 class="mb-2 text-secondary fw-semibold">Summary statistics</h6>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge bg-primary">Parents <span id="cm-summary-parent">(0)</span></span>
                            <span class="badge bg-success">SKUs <span id="cm-summary-sku">(0)</span></span>
                            <span class="badge bg-danger">Battery <span id="cm-summary-battery">(0)</span></span>
                            <span class="badge bg-danger">Wireless <span id="cm-summary-wireless">(0)</span></span>
                            <span class="badge bg-warning text-dark">Electric <span id="cm-summary-electric">(0)</span></span>
                            <span class="badge bg-info">GCC <span id="cm-summary-gcc">(0)</span></span>
                            <span class="badge bg-info">RoHs <span id="cm-summary-rohs">(0)</span></span>
                            <span class="badge bg-secondary">Blanket <span id="cm-summary-blanket">(0)</span></span>
                            <span class="badge bg-dark">Bluetooth <span id="cm-summary-bluetooth">(0)</span></span>
                            <span class="badge bg-primary">Logo <span id="cm-summary-logo">(0)</span></span>
                            <span class="badge" style="background-color: #6f42c1;">Graph <span id="cm-summary-graph">(0)</span></span>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="compliance-table-wrapper">
                        <div class="cm-toolbar-search-strip px-2 py-1 bg-light border-bottom">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                                <input type="text" id="customSearch" class="form-control form-control-sm" placeholder="Search Parent, SKU, or Status...">
                                <button class="btn btn-outline-secondary btn-sm" type="button" id="clearSearch">Clear</button>
                            </div>
                        </div>
                        <div id="compliance-tabulator" class="cm-tabulator-host" aria-label="Compliance data grid"></div>

                        <div id="rainbow-loader" class="rainbow-loader">
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="wave"></div>
                        <div class="loading-text">Loading Compliance Masters Data...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk edit modal -->
            <div class="modal fade" id="complianceBulkEditModal" tabindex="-1" aria-labelledby="complianceBulkEditModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="complianceBulkEditModalLabel"><i class="fas fa-pen-to-square me-2"></i>Bulk edit compliance</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted small mb-2" id="complianceBulkEditCountText">No rows selected.</p>
                                    <p class="small text-secondary mb-3">Each field defaults to <strong>N/A</strong>. Turn on <strong>REQ</strong> to require documentation, then upload an <strong>image</strong> and a <strong>PDF</strong> (both apply to every selected SKU for that field).</p>
                                    <div class="row g-2">
                                        @foreach ($__cmFields as $fkey => $flabel)
                                            <div class="col-md-6">
                                                <div class="compliance-field-block border rounded p-2 h-100" data-bulk-field="{{ $fkey }}">
                                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                                        <span class="fw-bold">{{ $flabel }}</span>
                                                        <div class="btn-group btn-group-sm" role="group">
                                                            <input type="radio" class="btn-check" name="bulk_mode_{{ $fkey }}" id="bulk_na_{{ $fkey }}" value="na" checked autocomplete="off">
                                                            <label class="btn btn-outline-secondary" for="bulk_na_{{ $fkey }}">N/A</label>
                                                            <input type="radio" class="btn-check" name="bulk_mode_{{ $fkey }}" id="bulk_req_{{ $fkey }}" value="req" autocomplete="off">
                                                            <label class="btn btn-outline-primary" for="bulk_req_{{ $fkey }}">REQ</label>
                                                        </div>
                                                    </div>
                                                    <div class="bulk-compliance-req-wrap d-none mt-2" data-bulk-req-wrap="{{ $fkey }}">
                                                        <div class="mb-2">
                                                            <label class="form-label small mb-1">Image</label>
                                                            <input type="file" class="form-control form-control-sm bulk-compliance-img-input" accept="image/*" data-field="{{ $fkey }}">
                                                            <div class="small text-muted mt-1" data-bulk-img-status="{{ $fkey }}"></div>
                                                        </div>
                                                        <div>
                                                            <label class="form-label small mb-1">PDF</label>
                                                            <input type="file" class="form-control form-control-sm bulk-compliance-pdf-input" accept=".pdf,application/pdf" data-field="{{ $fkey }}">
                                                            <div class="small text-muted mt-1" data-bulk-pdf-status="{{ $fkey }}"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="text-danger small mt-3 mb-0 d-none" id="complianceBulkEditError"></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="button" class="btn btn-primary" id="complianceBulkEditApplyBtn" disabled>
                                        <i class="fas fa-save me-1"></i> Apply to selected
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

            <!-- Import Modal -->
            <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                            <h5 class="modal-title" id="importModalLabel">
                                <i class="fas fa-upload me-2"></i>Import Compliance Data
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Instructions:</strong>
                                <ol class="mb-0 mt-2">
                                    <li>Download the sample file below</li>
                                    <li>Use <strong>N/A</strong> or <strong>REQ</strong> per column. After import, open each SKU to attach the <strong>image</strong> and <strong>PDF</strong> required for REQ fields.</li>
                                    <li>Upload the completed file</li>
                                </ol>
                            </div>

                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleBtn">
                                    <i class="fas fa-download me-2"></i>Download Sample File
                                </button>
                            </div>

                            <div class="mb-3">
                                <label for="importFile" class="form-label fw-bold">Select Excel File</label>
                                <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                                <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                                <div id="fileError" class="text-danger mt-2" style="display: none;"></div>
                            </div>

                            <div id="importProgress" class="progress mb-3" style="display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>

                            <div id="importResult" class="alert" style="display: none;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="importBtn" disabled>
                                <i class="fas fa-upload me-2"></i>Import
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add / Edit Compliance Master — right-side panel -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="addComplianceModal" aria-labelledby="addComplianceModalLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title mb-0" id="addComplianceModalLabel">Add Compliance Data</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body cm-panel-body">
            <div class="cm-panel-scroll">
                <form id="addComplianceForm">
                    <div class="cm-sku-block">
                        <label for="addComplianceSku" class="form-label">SKU <span class="text-danger">*</span></label>
                        <select class="form-control form-control-sm" id="addComplianceSku" name="sku" required>
                            <option value="">Select SKU</option>
                        </select>
                        <div class="cm-meta-row">
                            <label class="d-inline-flex align-items-center gap-2 mb-0 small"
                                title="When checked, the same compliance values are saved to all child SKUs under the same parent"
                                style="cursor:pointer;user-select:none;">
                                <input type="checkbox" class="form-check-input m-0" id="cm_apply_siblings" autocomplete="off">
                                <span class="fw-semibold">Siblings</span>
                            </label>
                            <span class="small text-muted" id="cm_siblings_hint"></span>
                            <span class="small text-success d-none ms-auto" id="cm_autosave_status"></span>
                        </div>
                    </div>

                    <div class="cm-fields-list">
                        @foreach ($__cmFields as $fkey => $flabel)
                            <div class="compliance-field-block" data-add-field="{{ $fkey }}">
                                <div class="cm-field-row">
                                    <span class="cm-field-label">{{ $flabel }}</span>
                                    <div class="btn-group btn-group-sm" role="group" aria-label="{{ $flabel }} mode">
                                        <input type="radio" class="btn-check" name="add_mode_{{ $fkey }}" id="add_na_{{ $fkey }}" value="na" checked autocomplete="off">
                                        <label class="btn btn-outline-secondary" for="add_na_{{ $fkey }}">N/A</label>
                                        <input type="radio" class="btn-check" name="add_mode_{{ $fkey }}" id="add_req_{{ $fkey }}" value="req" autocomplete="off">
                                        <label class="btn btn-outline-primary" for="add_req_{{ $fkey }}">REQ</label>
                                    </div>
                                    <div class="cm-file-actions d-none" id="add_{{ $fkey }}_req_wrap">
                                        <input type="file" class="d-none add-compliance-img-input" id="add_{{ $fkey }}_img_file" accept="image/*" data-field="{{ $fkey }}" data-path-input="add_{{ $fkey }}_img_path">
                                        <button type="button" class="cm-file-btn cm-img-btn missing-file" data-trigger-file="add_{{ $fkey }}_img_file" title="Upload image" aria-label="Upload {{ $flabel }} image">
                                            <i class="fas fa-image" aria-hidden="true"></i>
                                            <span class="cm-file-dot" aria-hidden="true"></span>
                                        </button>
                                        <span class="cm-file-preview-link" id="add_{{ $fkey }}_img_preview"></span>

                                        <input type="file" class="d-none add-compliance-pdf-input" id="add_{{ $fkey }}_pdf_file" accept=".pdf,application/pdf" data-field="{{ $fkey }}" data-path-input="add_{{ $fkey }}_pdf_path">
                                        <button type="button" class="cm-file-btn cm-pdf-btn missing-file" data-trigger-file="add_{{ $fkey }}_pdf_file" title="Upload PDF" aria-label="Upload {{ $flabel }} PDF">
                                            <i class="fas fa-file-pdf" aria-hidden="true"></i>
                                            <span class="cm-file-dot" aria-hidden="true"></span>
                                        </button>
                                        <span class="cm-file-preview-link" id="add_{{ $fkey }}_pdf_link"></span>

                                        <span class="visually-hidden" id="add_{{ $fkey }}_img_status"></span>
                                        <span class="visually-hidden" id="add_{{ $fkey }}_pdf_status"></span>
                                    </div>
                                </div>
                                <input type="hidden" id="add_{{ $fkey }}_img_path" value="">
                                <input type="hidden" id="add_{{ $fkey }}_pdf_path" value="">
                            </div>
                        @endforeach
                    </div>
                </form>
            </div>
            <div class="cm-panel-footer">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="offcanvas">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveAddComplianceBtn">
                    <i class="fas fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Store the loaded data globally
            let tableData = [];
            let filteredData = [];
            let complianceTable = null;
            let complianceSearchSetupDone = false;
            let complianceFormMode = 'add';
            let complianceEditSku = '';
            let complianceFormHydrating = false;
            let complianceAutoSaveTimer = null;
            let complianceAutoSaveInFlight = false;
            let complianceAutoSaveQueued = false;

            const COMPLIANCE_BULK_FIELD_KEYS = ['battery', 'wireless', 'electric', 'gcc', 'rohs', 'blanket', 'bluetooth', 'logo', 'graph'];
            let bulkComplianceUploadPaths = {};
            let bulkCompliancePdfPaths = {};

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            function complianceImagePublicUrl(path) {
                if (path == null || path === '') return '';
                const s = String(path).trim();
                if (s.startsWith('http://') || s.startsWith('https://')) return s;
                return '/' + s.replace(/^\/+/, '');
            }

            function complianceFieldStoredValue(item, key) {
                return item[key] != null ? String(item[key]).trim() : '';
            }

            function complianceFieldImagePath(item, key) {
                const ik = key + '_img';
                return item[ik] != null ? String(item[ik]).trim() : '';
            }

            function complianceFieldPdfPath(item, key) {
                const pk = key + '_pdf';
                return item[pk] != null ? String(item[pk]).trim() : '';
            }

            function isComplianceNaValue(value) {
                const upper = String(value == null ? '' : value).trim().toUpperCase();
                return upper === '' || upper === 'N/A' || upper === 'NA' || upper === 'NRQ';
            }

            function isMissingComplianceFieldForItem(item, key) {
                const v = complianceFieldStoredValue(item, key);
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                if (isComplianceNaValue(v)) return true;
                if (v.toUpperCase() === 'REQ') return img === '' || pdf === '';
                return false;
            }

            /** REQ column filter: only rows where that field is REQ (excludes N/A, empty, and other values). */
            function isReqFilterMatchForItem(item, key) {
                return complianceFieldStoredValue(item, key).toUpperCase() === 'REQ';
            }

            function isReqOkFilterMatchForItem(item, key) {
                if (!isReqFilterMatchForItem(item, key)) return false;
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                return img !== '' && pdf !== '';
            }

            function isReqMissingFilterMatchForItem(item, key) {
                if (!isReqFilterMatchForItem(item, key)) return false;
                return !isReqOkFilterMatchForItem(item, key);
            }

            function isNaFilterMatchForItem(item, key) {
                return isComplianceNaValue(complianceFieldStoredValue(item, key));
            }

            function matchesComplianceFieldFilter(item, key, filterValue) {
                if (!filterValue || filterValue === 'all') return true;
                if (filterValue === 'req') return isReqMissingFilterMatchForItem(item, key);
                if (filterValue === 'req-ok') return isReqOkFilterMatchForItem(item, key);
                if (filterValue === 'na') return isNaFilterMatchForItem(item, key);
                return true;
            }

            function syncComplianceFieldFilterStyles() {
                document.querySelectorAll('.cm-field-filter').forEach(function(sel) {
                    sel.classList.remove('cm-filter-req', 'cm-filter-req-ok', 'cm-filter-na');
                    if (sel.value === 'req') sel.classList.add('cm-filter-req');
                    else if (sel.value === 'na') sel.classList.add('cm-filter-na');
                });
            }

            function complianceFieldCellHtml(item, key) {
                const v = complianceFieldStoredValue(item, key);
                const img = complianceFieldImagePath(item, key);
                const pdf = complianceFieldPdfPath(item, key);
                const upper = v.toUpperCase();
                const hasAnyFile = img !== '' || pdf !== '';

                let badge = '';
                if (upper === 'REQ') {
                    // Only show REQ when no files are attached yet
                    if (!hasAnyFile) {
                        badge = '<span class="badge rounded-pill compliance-req-badge" title="REQ — missing image or PDF">REQ</span>';
                    }
                } else if (isComplianceNaValue(v)) {
                    badge = '<span class="badge rounded-pill compliance-na-badge">N/A</span>';
                } else {
                    badge = `<span class="badge rounded-pill bg-info text-dark" title="Legacy value">${escapeHtml(v)}</span>`;
                }
                let thumb = '';
                if (img) {
                    const u = complianceImagePublicUrl(img);
                    thumb = ' ' + complianceFileChipHtml(u, 'image');
                }
                let pdfLink = '';
                if (pdf) {
                    const pu = complianceImagePublicUrl(pdf);
                    pdfLink = ' ' + complianceFileChipHtml(pu, 'pdf');
                }
                return `<span class="d-inline-flex align-items-center gap-1 flex-wrap justify-content-center">${badge}${thumb}${pdfLink}</span>`;
            }

            function complianceFileChipHtml(url, kind) {
                const safeUrl = escapeHtml(String(url || ''));
                const kindSafe = kind === 'pdf' ? 'pdf' : 'image';
                const icon = kindSafe === 'pdf'
                    ? '<span class="compliance-pdf-icon-bg" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>'
                    : `<img class="compliance-field-thumb" src="${safeUrl}" alt="">`;
                return `<span class="cm-file-chip" data-file-url="${safeUrl}" data-file-kind="${kindSafe}" tabindex="0" title="Hover for file actions">${icon}</span>`;
            }

            async function uploadComplianceFieldImageToServer(field, file) {
                const fd = new FormData();
                fd.append('field', field);
                fd.append('image', file);
                fd.append('_token', csrfToken);
                const res = await fetch('/compliance-master/field-image', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'Upload failed');
                }
                return data.path || '';
            }

            async function uploadComplianceFieldPdfToServer(field, file) {
                const fd = new FormData();
                fd.append('field', field);
                fd.append('pdf', file);
                fd.append('_token', csrfToken);
                const res = await fetch('/compliance-master/field-pdf', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: fd
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) {
                    throw new Error(data.message || 'PDF upload failed');
                }
                return data.path || '';
            }

            function resetBulkComplianceModal() {
                bulkComplianceUploadPaths = {};
                bulkCompliancePdfPaths = {};
                const modal = document.getElementById('complianceBulkEditModal');
                if (!modal) return;
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const na = modal.querySelector(`#bulk_na_${k}`);
                    const req = modal.querySelector(`#bulk_req_${k}`);
                    if (na) na.checked = true;
                    if (req) req.checked = false;
                    const wrap = modal.querySelector(`[data-bulk-req-wrap="${k}"]`);
                    if (wrap) {
                        wrap.classList.add('d-none');
                        const fi = wrap.querySelector('.bulk-compliance-img-input');
                        if (fi) fi.value = '';
                        const fp = wrap.querySelector('.bulk-compliance-pdf-input');
                        if (fp) fp.value = '';
                        const st = wrap.querySelector(`[data-bulk-img-status="${k}"]`);
                        if (st) st.textContent = '';
                        const pst = wrap.querySelector(`[data-bulk-pdf-status="${k}"]`);
                        if (pst) pst.textContent = '';
                    }
                });
            }

            function resetComplianceAddFormFields() {
                const modal = document.getElementById('addComplianceModal');
                if (!modal) return;
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const na = document.getElementById(`add_na_${k}`);
                    const req = document.getElementById(`add_req_${k}`);
                    if (na) na.checked = true;
                    if (req) req.checked = false;
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    if (pathEl) pathEl.value = '';
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    if (pdfPathEl) pdfPathEl.value = '';
                    const st = document.getElementById(`add_${k}_img_status`);
                    if (st) st.textContent = '';
                    const pst = document.getElementById(`add_${k}_pdf_status`);
                    if (pst) pst.textContent = '';
                    const fi = modal.querySelector(`.add-compliance-img-input[data-field="${k}"]`);
                    if (fi) fi.value = '';
                    const fp = modal.querySelector(`.add-compliance-pdf-input[data-field="${k}"]`);
                    if (fp) fp.value = '';
                    refreshComplianceFieldFileUi(k);
                });
            }

            function setAddComplianceFormFromItem(item) {
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const raw = complianceFieldStoredValue(item, k);
                    const upper = raw.toUpperCase();
                    const isReq = upper === 'REQ';
                    document.getElementById(`add_na_${k}`).checked = !isReq;
                    document.getElementById(`add_req_${k}`).checked = isReq;
                    const p = complianceFieldImagePath(item, k);
                    const pdfp = complianceFieldPdfPath(item, k);
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    if (pathEl) pathEl.value = p;
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    if (pdfPathEl) pdfPathEl.value = pdfp;
                    const st = document.getElementById(`add_${k}_img_status`);
                    if (st) st.textContent = p ? 'Current image on file.' : '';
                    const pst = document.getElementById(`add_${k}_pdf_status`);
                    if (pst) pst.textContent = pdfp ? 'Current PDF on file.' : '';
                    refreshComplianceFieldFileUi(k);
                });
            }

            function refreshComplianceFieldFileUi(field) {
                const isReq = !!document.getElementById(`add_req_${field}`)?.checked;
                const pathEl = document.getElementById(`add_${field}_img_path`);
                const pdfPathEl = document.getElementById(`add_${field}_pdf_path`);
                const imgPath = pathEl ? pathEl.value.trim() : '';
                const pdfPath = pdfPathEl ? pdfPathEl.value.trim() : '';
                const block = document.querySelector(`#addComplianceModal .compliance-field-block[data-add-field="${field}"]`);
                const wrap = document.getElementById(`add_${field}_req_wrap`);
                const imgBtn = wrap ? wrap.querySelector('.cm-img-btn') : null;
                const pdfBtn = wrap ? wrap.querySelector('.cm-pdf-btn') : null;
                const prev = document.getElementById(`add_${field}_img_preview`);
                const plink = document.getElementById(`add_${field}_pdf_link`);

                if (wrap) wrap.classList.toggle('d-none', !isReq);
                if (block) {
                    block.classList.toggle('is-req', isReq);
                    block.classList.toggle('is-complete', isReq && imgPath !== '' && pdfPath !== '');
                }

                if (imgBtn) {
                    imgBtn.classList.toggle('has-file', imgPath !== '');
                    imgBtn.classList.toggle('missing-file', isReq && imgPath === '');
                    imgBtn.title = imgPath ? 'Replace image' : 'Upload image';
                }
                if (pdfBtn) {
                    pdfBtn.classList.toggle('has-file', pdfPath !== '');
                    pdfBtn.classList.toggle('missing-file', isReq && pdfPath === '');
                    pdfBtn.title = pdfPath ? 'Replace PDF' : 'Upload PDF';
                }

                if (prev) {
                    if (imgPath) {
                        const u = complianceImagePublicUrl(imgPath);
                        prev.innerHTML = `<a href="${escapeHtml(u)}" target="_blank" rel="noopener" title="Open image"><img src="${escapeHtml(u)}" alt=""></a>`;
                    } else {
                        prev.innerHTML = '';
                    }
                }
                if (plink) {
                    if (pdfPath) {
                        const pu = complianceImagePublicUrl(pdfPath);
                        plink.innerHTML = `<a href="${escapeHtml(pu)}" target="_blank" rel="noopener" title="Open PDF"><i class="fas fa-external-link-alt"></i></a>`;
                    } else {
                        plink.innerHTML = '';
                    }
                }
            }

            function toggleAddComplianceReqWrap(field) {
                refreshComplianceFieldFileUi(field);
            }

            function collectComplianceFormPayload(sku) {
                const o = { sku: String(sku || '').trim() };
                COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                    const req = document.querySelector(`#addComplianceModal input[name="add_mode_${k}"]:checked`)?.value === 'req';
                    const pathEl = document.getElementById(`add_${k}_img_path`);
                    const imgPath = pathEl ? pathEl.value.trim() : '';
                    const pdfPathEl = document.getElementById(`add_${k}_pdf_path`);
                    const pdfPath = pdfPathEl ? pdfPathEl.value.trim() : '';
                    if (req) {
                        o[k] = 'REQ';
                        o[k + '_img'] = imgPath;
                        o[k + '_pdf'] = pdfPath;
                    } else {
                        o[k] = 'N/A';
                        o[k + '_img'] = '';
                        o[k + '_pdf'] = '';
                    }
                });
                const siblingsCb = document.getElementById('cm_apply_siblings');
                o.apply_siblings = !!(siblingsCb && siblingsCb.checked);
                return o;
            }

            function getComplianceSiblingSkusForSku(sku) {
                const row = findComplianceRowBySku(sku);
                const parent = String(row?.Parent || '').trim();
                if (!parent) return [];
                const seen = new Set();
                const out = [];
                (Array.isArray(tableData) ? tableData : []).forEach(item => {
                    if (!item) return;
                    const p = String(item.Parent || '').trim();
                    const s = String(item.SKU || '').trim();
                    if (!s || p !== parent) return;
                    if (s.toUpperCase().includes('PARENT')) return;
                    const key = s.toUpperCase();
                    if (seen.has(key)) return;
                    seen.add(key);
                    out.push(s);
                });
                return out;
            }

            function updateComplianceSiblingsHint() {
                const hint = document.getElementById('cm_siblings_hint');
                const cb = document.getElementById('cm_apply_siblings');
                if (!hint) return;

                const skuSelect = document.getElementById('addComplianceSku');
                let sku = '';
                if (complianceFormMode === 'edit') {
                    sku = (complianceEditSku || '').trim();
                } else if (skuSelect) {
                    sku = $(skuSelect).val() ? String($(skuSelect).val()).trim() : '';
                }

                if (!cb || !cb.checked) {
                    hint.textContent = '';
                    return;
                }
                if (!sku) {
                    hint.textContent = 'Select a SKU first';
                    return;
                }
                const siblings = getComplianceSiblingSkusForSku(sku);
                const otherCount = Math.max(0, siblings.length - 1);
                if (otherCount > 0) {
                    hint.textContent = 'Will also update ' + otherCount + ' sibling SKU(s)';
                } else {
                    hint.textContent = 'No sibling children found for this parent';
                }
            }

            function setComplianceAutosaveStatus(text, isError) {
                const el = document.getElementById('cm_autosave_status');
                if (!el) return;
                if (!text) {
                    el.classList.add('d-none');
                    el.textContent = '';
                    return;
                }
                el.classList.remove('d-none', 'text-success', 'text-danger', 'text-muted');
                el.classList.add(isError ? 'text-danger' : 'text-success');
                el.textContent = text;
            }

            function scheduleComplianceAutosave() {
                if (complianceFormHydrating) return;
                if (complianceAutoSaveTimer) clearTimeout(complianceAutoSaveTimer);
                complianceAutoSaveTimer = setTimeout(function() {
                    complianceAutoSaveTimer = null;
                    saveCompliance({ auto: true, keepOpen: true });
                }, 350);
            }

            function patchLocalComplianceRowsFromPayload(formData, siblingSkus) {
                const targets = new Set();
                const primary = String(formData.sku || '').trim().toUpperCase();
                if (primary) targets.add(primary);
                (Array.isArray(siblingSkus) ? siblingSkus : []).forEach(s => {
                    const k = String(s || '').trim().toUpperCase();
                    if (k) targets.add(k);
                });
                if (targets.size === 0) return;

                const applyTo = (rows) => {
                    if (!Array.isArray(rows)) return;
                    rows.forEach(item => {
                        const skuKey = String(item?.SKU || '').trim().toUpperCase();
                        if (!targets.has(skuKey)) return;
                        COMPLIANCE_BULK_FIELD_KEYS.forEach(k => {
                            if (Object.prototype.hasOwnProperty.call(formData, k)) {
                                item[k] = formData[k];
                            }
                            const ik = k + '_img';
                            const pk = k + '_pdf';
                            if (Object.prototype.hasOwnProperty.call(formData, ik)) item[ik] = formData[ik];
                            if (Object.prototype.hasOwnProperty.call(formData, pk)) item[pk] = formData[pk];
                        });
                    });
                };
                applyTo(tableData);
                applyTo(filteredData);
                if (complianceTable) {
                    renderTable(filteredData);
                    updateCounts();
                }
            }

            function buildComplianceBulkPayloadForItem(item) {
                const payload = { sku: String(item.SKU || '').trim() };
                const modal = document.getElementById('complianceBulkEditModal');
                COMPLIANCE_BULK_FIELD_KEYS.forEach(key => {
                    const req = modal && modal.querySelector(`input[name="bulk_mode_${key}"]:checked`)?.value === 'req';
                    const imgKey = key + '_img';
                    const pdfKey = key + '_pdf';
                    if (req) {
                        payload[key] = 'REQ';
                        const pendingImg = bulkComplianceUploadPaths[key];
                        const existingImg = complianceFieldImagePath(item, key);
                        payload[imgKey] = pendingImg ? pendingImg : existingImg;
                        const pendingPdf = bulkCompliancePdfPaths[key];
                        const existingPdf = complianceFieldPdfPath(item, key);
                        payload[pdfKey] = pendingPdf ? pendingPdf : existingPdf;
                    } else {
                        payload[key] = 'N/A';
                        payload[imgKey] = '';
                        payload[pdfKey] = '';
                    }
                });
                return payload;
            }

            // Show loader immediately
            document.getElementById('rainbow-loader').style.display = 'block';

            // Centralized AJAX request function
            function makeRequest(url, method, data = {}) {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                };

                if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase())) {
                    data._token = csrfToken;
                }

                return fetch(url, {
                    method: method,
                    headers: headers,
                    body: method === 'GET' ? null : JSON.stringify(data)
                });
            }

            // Escape HTML to prevent XSS
            function escapeHtml(text) {
                if (text == null) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function setupComplianceImageHoverPreview() {
                const host = document.getElementById('compliance-tabulator');
                const tableScroll = host && host.querySelector('.tabulator-tableholder');
                let previewEl = null;
                let activeWrap = null;

                function getPreview() {
                    if (!previewEl) {
                        previewEl = document.createElement('div');
                        previewEl.id = 'compliance-img-hover-preview';
                        const img = document.createElement('img');
                        previewEl.appendChild(img);
                        document.body.appendChild(previewEl);
                    }
                    return previewEl;
                }

                function hidePreview() {
                    activeWrap = null;
                    if (previewEl) previewEl.style.display = 'none';
                }

                function positionPreview(clientX, clientY) {
                    const el = getPreview();
                    if (el.style.display !== 'block') return;
                    const margin = 14;
                    const pad = 8;
                    requestAnimationFrame(() => {
                        const w = el.offsetWidth || 200;
                        const h = el.offsetHeight || 200;
                        let left = clientX + margin;
                        let top = clientY + margin;
                        if (left + w > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - w - pad);
                        if (top + h > window.innerHeight - pad) top = Math.max(pad, window.innerHeight - h - pad);
                        if (left < pad) left = pad;
                        if (top < pad) top = pad;
                        el.style.left = left + 'px';
                        el.style.top = top + 'px';
                    });
                }

                if (!host) return;

                host.addEventListener('mouseover', function(e) {
                    if (e.target.closest('.cm-file-chip')) {
                        hidePreview();
                        return;
                    }
                    const wrap = e.target.closest('.compliance-thumb-wrap');
                    if (wrap && host.contains(wrap)) {
                        const srcImg = wrap.querySelector('img');
                        if (!srcImg || !srcImg.getAttribute('src')) return;
                        activeWrap = wrap;
                        const el = getPreview();
                        const big = el.querySelector('img');
                        if (big.getAttribute('src') !== srcImg.src) big.src = srcImg.src;
                        el.style.display = 'block';
                        positionPreview(e.clientX, e.clientY);
                    } else {
                        hidePreview();
                    }
                });

                host.addEventListener('mousemove', function(e) {
                    if (!activeWrap) return;
                    if (!activeWrap.contains(e.target)) return;
                    positionPreview(e.clientX, e.clientY);
                });

                host.addEventListener('mouseleave', hidePreview);

                if (tableScroll) {
                    tableScroll.addEventListener('scroll', hidePreview, { passive: true });
                }
            }

            function setupComplianceFileHoverActions() {
                const host = document.getElementById('compliance-tabulator');
                if (!host) return;

                let menuEl = null;
                let activeChip = null;
                let hideTimer = null;

                function getMenu() {
                    if (menuEl) return menuEl;
                    menuEl = document.createElement('div');
                    menuEl.id = 'cm-file-action-menu';
                    menuEl.innerHTML = `
                        <button type="button" data-cm-file-action="copy"><i class="fas fa-copy"></i> Copy</button>
                        <button type="button" data-cm-file-action="download"><i class="fas fa-download"></i> Download</button>
                        <button type="button" data-cm-file-action="view"><i class="fas fa-eye"></i> View</button>
                    `;
                    document.body.appendChild(menuEl);

                    menuEl.addEventListener('mouseenter', function() {
                        if (hideTimer) {
                            clearTimeout(hideTimer);
                            hideTimer = null;
                        }
                    });
                    menuEl.addEventListener('mouseleave', scheduleHideMenu);
                    menuEl.addEventListener('click', async function(e) {
                        const btn = e.target.closest('[data-cm-file-action]');
                        if (!btn || !menuEl.contains(btn)) return;
                        e.preventDefault();
                        e.stopPropagation();
                        const action = btn.getAttribute('data-cm-file-action');
                        const url = menuEl.dataset.fileUrl || '';
                        if (!url) return;
                        await runComplianceFileAction(action, url, menuEl.dataset.fileKind || 'file');
                        hideMenu();
                    });
                    return menuEl;
                }

                function absoluteFileUrl(url) {
                    try {
                        return new URL(url, window.location.origin).href;
                    } catch (err) {
                        return String(url || '');
                    }
                }

                function guessFileName(url, kind) {
                    try {
                        const path = new URL(url, window.location.origin).pathname;
                        const base = path.split('/').filter(Boolean).pop() || '';
                        if (base) return decodeURIComponent(base);
                    } catch (err) {}
                    return kind === 'pdf' ? 'compliance.pdf' : 'compliance-file';
                }

                async function runComplianceFileAction(action, url, kind) {
                    const abs = absoluteFileUrl(url);
                    if (action === 'view') {
                        window.open(abs, '_blank', 'noopener');
                        return;
                    }
                    if (action === 'copy') {
                        try {
                            await navigator.clipboard.writeText(abs);
                            showToast('success', 'Link copied');
                        } catch (err) {
                            showToast('danger', 'Could not copy link');
                        }
                        return;
                    }
                    if (action === 'download') {
                        try {
                            const res = await fetch(abs, { credentials: 'same-origin' });
                            if (!res.ok) throw new Error('Download failed');
                            const blob = await res.blob();
                            const objectUrl = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = objectUrl;
                            a.download = guessFileName(abs, kind);
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            URL.revokeObjectURL(objectUrl);
                        } catch (err) {
                            const a = document.createElement('a');
                            a.href = abs;
                            a.target = '_blank';
                            a.rel = 'noopener';
                            a.download = guessFileName(abs, kind);
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                        }
                    }
                }

                function positionMenu(chip) {
                    const menu = getMenu();
                    const rect = chip.getBoundingClientRect();
                    const pad = 8;
                    menu.style.display = 'block';
                    menu.classList.add('is-open');
                    const mw = menu.offsetWidth || 120;
                    const mh = menu.offsetHeight || 110;
                    let left = rect.left + (rect.width / 2) - (mw / 2);
                    let top = rect.bottom + 6;
                    if (left + mw > window.innerWidth - pad) left = window.innerWidth - mw - pad;
                    if (left < pad) left = pad;
                    if (top + mh > window.innerHeight - pad) top = Math.max(pad, rect.top - mh - 6);
                    menu.style.left = left + 'px';
                    menu.style.top = top + 'px';
                }

                function showMenu(chip) {
                    if (hideTimer) {
                        clearTimeout(hideTimer);
                        hideTimer = null;
                    }
                    if (activeChip && activeChip !== chip) {
                        activeChip.classList.remove('is-menu-open');
                    }
                    activeChip = chip;
                    chip.classList.add('is-menu-open');
                    const menu = getMenu();
                    menu.dataset.fileUrl = chip.getAttribute('data-file-url') || '';
                    menu.dataset.fileKind = chip.getAttribute('data-file-kind') || 'file';
                    positionMenu(chip);
                }

                function hideMenu() {
                    if (hideTimer) {
                        clearTimeout(hideTimer);
                        hideTimer = null;
                    }
                    if (activeChip) activeChip.classList.remove('is-menu-open');
                    activeChip = null;
                    if (menuEl) {
                        menuEl.classList.remove('is-open');
                        menuEl.style.display = 'none';
                    }
                }

                function scheduleHideMenu() {
                    if (hideTimer) clearTimeout(hideTimer);
                    hideTimer = setTimeout(hideMenu, 160);
                }

                host.addEventListener('mouseover', function(e) {
                    const chip = e.target.closest('.cm-file-chip');
                    if (chip && host.contains(chip)) {
                        showMenu(chip);
                    }
                });

                host.addEventListener('mouseout', function(e) {
                    const chip = e.target.closest('.cm-file-chip');
                    if (!chip) return;
                    const to = e.relatedTarget;
                    if (to && (chip.contains(to) || (menuEl && menuEl.contains(to)))) return;
                    scheduleHideMenu();
                });

                host.addEventListener('click', function(e) {
                    const chip = e.target.closest('.cm-file-chip');
                    if (chip && host.contains(chip)) {
                        e.preventDefault();
                        showMenu(chip);
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!menuEl || menuEl.style.display === 'none') return;
                    if (e.target.closest('.cm-file-chip') || e.target.closest('#cm-file-action-menu')) return;
                    hideMenu();
                });

                const tableScroll = host.querySelector('.tabulator-tableholder');
                if (tableScroll) {
                    tableScroll.addEventListener('scroll', hideMenu, { passive: true });
                }
                window.addEventListener('resize', hideMenu);
            }

            // Format number
            function formatNumber(value, decimals = 2) {
                if (value === null || value === undefined || value === '') return '-';
                const num = parseFloat(value);
                if (isNaN(num)) return '-';
                return num.toFixed(decimals);
            }

            /** Same field as product-master: Values.status on product_master (merged into row as status). */
            function resolveProductMasterStatus(item) {
                if (!item) return '';
                let v = item.status;
                if (v === undefined || v === null || v === '') {
                    v = item.Status;
                }
                const s = v != null ? String(v).trim() : '';
                return s;
            }

            /** Canonical labels aligned with product-master status badges / select options. */
            function formatProductMasterStatusLabel(raw) {
                const s = String(raw || '').trim();
                if (!s) return '—';
                const lower = s.toLowerCase();
                const upper = s.toUpperCase();
                if (lower === 'active') return 'Active';
                if (lower === 'inactive') return 'Inactive';
                if (upper === 'DC') return 'DC';
                if (lower === 'upcoming') return 'Upcoming';
                if (upper === '2BDC') return '2BDC';
                return s;
            }

            function getComplianceStatusMarbleModifier(raw) {
                const s = String(raw || '').trim();
                if (!s) return 'muted';
                const lower = s.toLowerCase();
                const upper = s.toUpperCase();
                if (lower === 'active') return 'active';
                if (lower === 'inactive') return 'inactive';
                if (upper === 'DC') return 'dc';
                if (lower === 'upcoming') return 'upcoming';
                if (upper === '2BDC') return '2bdc';
                return 'muted';
            }

            function getComplianceStatusCellHtml(item) {
                const raw = resolveProductMasterStatus(item);
                const trimmed = String(raw || '').trim();
                if (!trimmed) {
                    return '<span class="cm-status-cell-inner"><span class="cm-status-marble cm-status-marble--muted" title="No status"></span></span>';
                }
                const mod = getComplianceStatusMarbleModifier(trimmed);
                const label = formatProductMasterStatusLabel(trimmed);
                const titleAttr = escapeHtml(label === '—' ? trimmed : label);
                return '<span class="cm-status-cell-inner"><span class="cm-status-marble cm-status-marble--' + mod + '" title="' + titleAttr + '"></span></span>';
            }

            function cmStatusFilterOptionLabels() {
                return {
                    all: 'All',
                    missing: 'Missing',
                    active: 'Active',
                    inactive: 'Inactive',
                    DC: 'DC',
                    upcoming: 'Upcoming',
                    '2BDC': '2BDC'
                };
            }

            function positionCmStatusFilterMenu(wrap) {
                const menu = wrap.querySelector('.cm-status-filter-menu');
                const trigger = wrap.querySelector('.cm-status-filter-trigger');
                if (!menu || !trigger) return;
                const r = trigger.getBoundingClientRect();
                const w = Math.max(r.width, 200);
                menu.style.position = 'fixed';
                menu.style.top = (r.bottom + 4) + 'px';
                menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
                menu.style.minWidth = w + 'px';
                menu.style.zIndex = '4000';
            }

            function refreshCmStatusFilterUI() {
                const hidden = document.getElementById('filterComplianceStatus');
                const wrap = document.querySelector('.cm-status-filter-wrap');
                if (!hidden || !wrap) return;
                const trigger = wrap.querySelector('.cm-status-filter-trigger');
                const labelEl = trigger && trigger.querySelector('.cm-status-filter-trigger-label');
                const v = hidden.value || 'all';
                const map = cmStatusFilterOptionLabels();
                if (labelEl) {
                    labelEl.textContent = Object.prototype.hasOwnProperty.call(map, v) ? map[v] : v;
                }
                wrap.querySelectorAll('.cm-status-filter-item').forEach(btn => {
                    btn.classList.toggle('is-selected', btn.getAttribute('data-value') === v);
                });
            }

            let complianceStatusFilterDocClickBound = false;

            function setupComplianceStatusFilter() {
                if (complianceStatusFilterDocClickBound) return;
                complianceStatusFilterDocClickBound = true;

                const statusTrigger = document.getElementById('cmStatusFilterTrigger');
                if (statusTrigger) {
                    statusTrigger.addEventListener('mousedown', function(e) { e.stopPropagation(); });
                }

                document.addEventListener('click', function(e) {
                    const wrap = e.target.closest('.cm-status-filter-wrap');

                    const item = e.target.closest('.cm-status-filter-item');
                    const trigger = e.target.closest('.cm-status-filter-trigger');

                    if (item && wrap) {
                        e.preventDefault();
                        e.stopPropagation();
                        const val = item.getAttribute('data-value');
                        const hidden = document.getElementById('filterComplianceStatus');
                        if (!hidden) return;
                        hidden.value = val;
                        wrap.classList.remove('is-open');
                        const trg = wrap.querySelector('.cm-status-filter-trigger');
                        if (trg) trg.setAttribute('aria-expanded', 'false');
                        refreshCmStatusFilterUI();
                        applyFilters();
                        return;
                    }

                    if (trigger && wrap) {
                        e.preventDefault();
                        e.stopPropagation();
                        const wasOpen = wrap.classList.contains('is-open');
                        document.querySelectorAll('.cm-status-filter-wrap.is-open').forEach(x => {
                            x.classList.remove('is-open');
                            const t = x.querySelector('.cm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                        if (!wasOpen) {
                            wrap.classList.add('is-open');
                            trigger.setAttribute('aria-expanded', 'true');
                            positionCmStatusFilterMenu(wrap);
                        }
                        return;
                    }

                    if (!wrap) {
                        document.querySelectorAll('.cm-status-filter-wrap.is-open').forEach(x => {
                            x.classList.remove('is-open');
                            const t = x.querySelector('.cm-status-filter-trigger');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        });
                    }
                });
            }

            // Load compliance data from server
            function loadData() {
                const cacheParam = '?ts=' + new Date().getTime();
                makeRequest('/compliance-master-data-view' + cacheParam, 'GET')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(response => {
                        if (response && response.data && Array.isArray(response.data)) {
                            tableData = response.data;
                            filteredData = [...tableData];
                            renderTable(filteredData);
                            updateCounts();
                        } else {
                            console.error('Invalid data format received from server');
                        }
                        document.getElementById('rainbow-loader').style.display = 'none';
                    })
                    .catch(error => {
                        console.error('Failed to load compliance data: ' + error.message);
                        document.getElementById('rainbow-loader').style.display = 'none';
                    });
            }

            function complianceRowHasParentKeyword(item) {
                const sku = String(item.SKU || '').toUpperCase();
                const par = String(item.Parent || '').toUpperCase();
                return sku.includes('PARENT') || par.includes('PARENT');
            }

            const CM_FIELD_LABELS = {
                battery: 'Battery',
                wireless: 'Wireless',
                electric: 'Electric',
                gcc: 'GCC',
                rohs: 'RoHs',
                blanket: 'Blanket',
                bluetooth: 'Bluetooth',
                logo: 'Logo',
                graph: 'Graph'
            };

            const CM_FILTER_IDS = {
                battery: 'filterBattery',
                wireless: 'filterWireless',
                electric: 'filterElectric',
                gcc: 'filterGcc',
                rohs: 'filterRohs',
                blanket: 'filterBlanket',
                bluetooth: 'filterBluetooth',
                logo: 'filterLogo',
                graph: 'filterGraph'
            };

            function cmPassAllHeaderFilter() {
                return true;
            }

            function cmTitleWithCount(label, countId) {
                return function() {
                    const el = document.createElement('span');
                    el.innerHTML = escapeHtml(label) + ' <span id="' + countId + '" class="text-danger fw-bold">(0)</span>';
                    return el;
                };
            }

            function cmWrapHeaderFilter(el, extraClass) {
                const wrap = document.createElement('div');
                wrap.className = extraClass ? 'cm-filter-cell ' + extraClass : 'cm-filter-cell';
                wrap.appendChild(el);
                return wrap;
            }

            function cmTextHeaderFilter(id, placeholder) {
                return function() {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.id = id;
                    input.className = 'form-control form-control-sm';
                    input.placeholder = placeholder;
                    input.autocomplete = 'off';
                    input.addEventListener('input', function() {
                        applyFilters();
                    });
                    return cmWrapHeaderFilter(input);
                };
            }

            function cmSelectHeaderFilter(id, fieldKey) {
                return function() {
                    const sel = document.createElement('select');
                    sel.id = id;
                    sel.className = 'form-select form-select-sm cm-field-filter';
                    sel.setAttribute('data-field', fieldKey);
                    sel.innerHTML = '<option value="all">All Data</option><option value="req" class="cm-opt-req">REQ</option><option value="na" class="cm-opt-na">N/A</option>';
                    sel.addEventListener('change', function() {
                        syncComplianceFieldFilterStyles();
                        applyFilters();
                    });
                    return cmWrapHeaderFilter(sel);
                };
            }

            function cmStatusHeaderFilter() {
                const wrap = document.createElement('div');
                wrap.className = 'cm-filter-cell cm-filter-cell--status';
                wrap.innerHTML =
                    '<div class="cm-status-filter-wrap cm-status-filter-wrap--toolbar mt-0">' +
                    '<button type="button" class="cm-status-filter-trigger" aria-expanded="false" aria-haspopup="listbox" id="cmStatusFilterTrigger">' +
                    '<span class="cm-status-filter-trigger-label">All</span>' +
                    '<span style="font-size:9px;opacity:0.75;" aria-hidden="true">▼</span>' +
                    '</button>' +
                    '<input type="hidden" id="filterComplianceStatus" value="all" autocomplete="off">' +
                    '<div class="cm-status-filter-menu" role="listbox" id="cmStatusFilterMenu">' +
                    '<button type="button" class="cm-status-filter-item" data-value="all" role="option"><span class="cm-status-filter-check" aria-hidden="true">✓</span><span>All</span></button>' +
                    '<button type="button" class="cm-status-filter-item" data-value="missing" role="option"><span class="cm-status-filter-item-spacer"></span><span>Missing</span></button>' +
                    '<button type="button" class="cm-status-filter-item" data-value="active" role="option"><span class="cm-status-marble cm-status-marble--active"></span><span>Active</span></button>' +
                    '<button type="button" class="cm-status-filter-item" data-value="inactive" role="option"><span class="cm-status-marble cm-status-marble--inactive"></span><span>Inactive</span></button>' +
                    '<button type="button" class="cm-status-filter-item" data-value="DC" role="option"><span class="cm-status-marble cm-status-marble--dc"></span><span>DC</span></button>' +
                    '<button type="button" class="cm-status-filter-item" data-value="upcoming" role="option"><span class="cm-status-marble cm-status-marble--upcoming"></span><span>Upcoming</span></button>' +
                    '<button type="button" class="cm-status-filter-item" data-value="2BDC" role="option"><span class="cm-status-marble cm-status-marble--2bdc"></span><span>2BDC</span></button>' +
                    '</div></div>';
                return wrap;
            }

            function bindComplianceHeaderFilters() {
                setupSearch();
                refreshCmStatusFilterUI();
                syncComplianceFieldFilterStyles();
                updateCounts();
            }

            function getComplianceTabulatorColumnDefinitions() {
                const cols = [
                    {
                        title: 'Image',
                        field: 'image_path',
                        headerSort: false,
                        width: 48,
                        widthShrink: 1,
                        hozAlign: 'center',
                        formatter: function(cell) {
                            const v = cell.getValue();
                            if (!v) return '-';
                            return '<span class="compliance-thumb-wrap"><img class="compliance-thumb-img" src="' + escapeHtml(String(v)) + '" alt=""></span>';
                        }
                    },
                    {
                        title: 'Parent',
                        field: 'Parent',
                        headerSort: false,
                        cssClass: 'compliance-parent-col',
                        minWidth: 56,
                        widthGrow: 2,
                        titleFormatter: cmTitleWithCount('Parent', 'parentCount'),
                        headerFilter: cmTextHeaderFilter('parentSearch', 'Parent'),
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const raw = item.Parent != null && item.Parent !== '' ? String(item.Parent) : '';
                            if (!raw) return '-';
                            return '<span title="' + escapeHtml(raw) + '">' + escapeHtml(raw) + '</span>';
                        }
                    },
                    {
                        title: 'SKU',
                        field: 'SKU',
                        headerSort: false,
                        minWidth: 56,
                        widthGrow: 2,
                        titleFormatter: cmTitleWithCount('SKU', 'skuCount'),
                        headerFilter: cmTextHeaderFilter('skuSearch', 'SKU'),
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const v = item.SKU != null && String(item.SKU) !== '' ? String(item.SKU) : '';
                            return v ? escapeHtml(v) : '-';
                        }
                    },
                    {
                        title: '',
                        field: '_cb',
                        headerSort: false,
                        headerClass: 'cm-tabulator-cb-header',
                        width: 34,
                        widthShrink: 1,
                        hozAlign: 'center',
                        cssClass: 'compliance-checkbox-cell',
                        titleFormatter: function() {
                            const w = document.createElement('div');
                            w.className = 'text-center';
                            w.innerHTML = '<input type="checkbox" id="complianceSelectAllCheckbox" title="Select all visible rows" aria-label="Select all visible rows">';
                            return w;
                        },
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            if (complianceRowHasParentKeyword(item)) {
                                return '<span class="text-muted user-select-none" title="Parent summary rows cannot be bulk-edited">—</span>';
                            }
                            const sku = String(item.SKU || '');
                            return '<input type="checkbox" class="compliance-row-checkbox" data-sku="' + escapeHtml(sku) + '" aria-label="Select row for bulk edit">';
                        }
                    },
                    {
                        title: 'STATUS',
                        field: 'status',
                        headerSort: false,
                        cssClass: 'compliance-status-col',
                        hozAlign: 'center',
                        minWidth: 56,
                        widthGrow: 1,
                        headerFilter: cmStatusHeaderFilter,
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            return getComplianceStatusCellHtml(cell.getRow().getData());
                        }
                    },
                    {
                        title: 'INV',
                        field: 'shopify_inv',
                        headerSort: false,
                        hozAlign: 'center',
                        width: 48,
                        widthShrink: 1,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            const v = item.shopify_inv;
                            if (v === 0 || v === '0') return '0';
                            if (v === null || v === undefined || v === '') return '-';
                            return escapeHtml(String(v));
                        }
                    }
                ];

                COMPLIANCE_BULK_FIELD_KEYS.forEach(function(fk) {
                    const label = CM_FIELD_LABELS[fk] || fk;
                    cols.push({
                        title: label,
                        field: fk,
                        headerSort: false,
                        hozAlign: 'center',
                        minWidth: 52,
                        widthGrow: 1,
                        cssClass: 'cm-compliance-field-col',
                        titleFormatter: cmTitleWithCount(label, fk + 'MissingCount'),
                        headerFilter: cmSelectHeaderFilter(CM_FILTER_IDS[fk], fk),
                        headerFilterFunc: cmPassAllHeaderFilter,
                        headerFilterLiveFilter: false,
                        formatter: function(cell) {
                            const item = cell.getRow().getData();
                            if (complianceRowHasParentKeyword(item)) {
                                return '<span class="text-muted user-select-none">—</span>';
                            }
                            return complianceFieldCellHtml(item, fk);
                        }
                    });
                });

                cols.push({
                    title: 'Action',
                    field: '_actions',
                    headerSort: false,
                    hozAlign: 'center',
                    width: 78,
                    widthShrink: 1,
                    formatter: function(cell) {
                        const item = cell.getRow().getData();
                        return '<div class="d-inline-flex">' +
                            '<button type="button" class="btn btn-sm btn-outline-warning edit-btn me-1" data-sku="' + escapeHtml(String(item.SKU ?? '')) + '">' +
                            '<i class="bi bi-pencil-square"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger delete-btn" data-id="' + escapeHtml(String(item.id ?? '')) + '" data-sku="' + escapeHtml(String(item.SKU ?? '')) + '">' +
                            '<i class="bi bi-archive"></i></button></div>';
                    }
                });

                return cols;
            }

            function renderTable(data) {
                const d = Array.isArray(data) ? data : [];
                if (typeof Tabulator === 'undefined') {
                    console.error('Tabulator is not loaded');
                    return;
                }
                if (!complianceTable) {
                    complianceTable = new Tabulator('#compliance-tabulator', {
                        data: d,
                        layout: 'fitColumns',
                        layoutColumnsOnNewData: true,
                        height: '100%',
                        placeholder: 'No compliance data found',
                        movableColumns: false,
                        columnDefaults: {
                            headerSort: false
                        },
                        columns: getComplianceTabulatorColumnDefinitions(),
                        rowFormatter: function(row) {
                            const el = row.getElement();
                            if (complianceRowHasParentKeyword(row.getData())) {
                                el.classList.add('tabulator-com-parent-keyword');
                            } else {
                                el.classList.remove('tabulator-com-parent-keyword');
                            }
                        },
                        tableBuilt: function() {
                            bindComplianceHeaderFilters();
                        },
                        renderComplete: function() {
                            bindComplianceHeaderFilters();
                        }
                    });
                    syncComplianceSelectAllCheckbox();
                } else {
                    complianceTable.replaceData(d).then(function() {
                        syncComplianceSelectAllCheckbox();
                        bindComplianceHeaderFilters();
                    });
                }
            }

            function getComplianceRowCheckboxes() {
                return [...document.querySelectorAll('#compliance-tabulator .compliance-row-checkbox')];
            }

            function syncComplianceSelectAllCheckbox() {
                const master = document.getElementById('complianceSelectAllCheckbox');
                if (!master) return;
                const boxes = getComplianceRowCheckboxes();
                const n = boxes.length;
                const checked = boxes.filter(b => b.checked).length;
                master.checked = n > 0 && checked === n;
                master.indeterminate = checked > 0 && checked < n;
            }

            function updateComplianceBulkEditModalState() {
                const n = getComplianceRowCheckboxes().filter(b => b.checked).length;
                const countEl = document.getElementById('complianceBulkEditCountText');
                const applyBtn = document.getElementById('complianceBulkEditApplyBtn');
                if (countEl) {
                    countEl.textContent = n
                        ? `${n} row(s) selected.`
                        : 'No rows selected. Select checkboxes in the table first.';
                }
                if (applyBtn) {
                    applyBtn.disabled = n === 0;
                }
            }

            function setupComplianceBulkEdit() {
                const bulkBtn = document.getElementById('complianceBulkEditBtn');
                const applyBtn = document.getElementById('complianceBulkEditApplyBtn');
                const modalEl = document.getElementById('complianceBulkEditModal');

                if (!bulkBtn || !applyBtn || !modalEl) return;

                document.addEventListener('change', function complianceSelectAllChange(e) {
                    if (e.target.id !== 'complianceSelectAllCheckbox') return;
                    const master = e.target;
                    getComplianceRowCheckboxes().forEach(function(cb) {
                        cb.checked = master.checked;
                    });
                    syncComplianceSelectAllCheckbox();
                    if (modalEl.classList.contains('show')) {
                        updateComplianceBulkEditModalState();
                    }
                });

                document.addEventListener('change', function complianceRowCheckboxChange(e) {
                    if (!e.target.classList.contains('compliance-row-checkbox')) return;
                    const host = document.getElementById('compliance-tabulator');
                    if (!host || !host.contains(e.target)) return;
                    syncComplianceSelectAllCheckbox();
                    if (modalEl.classList.contains('show')) {
                        updateComplianceBulkEditModalState();
                    }
                });

                modalEl.addEventListener('show.bs.modal', function() {
                    resetBulkComplianceModal();
                });

                modalEl.addEventListener('change', function(e) {
                    const name = e.target.getAttribute('name');
                    if (name && name.startsWith('bulk_mode_')) {
                        const wrap = modalEl.querySelector(`[data-bulk-req-wrap="${name.replace('bulk_mode_', '')}"]`);
                        const isReq = modalEl.querySelector(`input[name="${name}"]:checked`)?.value === 'req';
                        if (wrap) wrap.classList.toggle('d-none', !isReq);
                        updateComplianceBulkEditModalState();
                    }
                });

                modalEl.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.bulk-compliance-img-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const wrap = inp.closest('[data-bulk-req-wrap]');
                    const statusEl = wrap ? wrap.querySelector(`[data-bulk-img-status="${field}"]`) : null;
                    try {
                        if (statusEl) statusEl.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldImageToServer(field, inp.files[0]);
                        bulkComplianceUploadPaths[field] = path;
                        if (statusEl) statusEl.textContent = 'Image ready.';
                    } catch (err) {
                        if (statusEl) statusEl.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });

                modalEl.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.bulk-compliance-pdf-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const wrap = inp.closest('[data-bulk-req-wrap]');
                    const statusEl = wrap ? wrap.querySelector(`[data-bulk-pdf-status="${field}"]`) : null;
                    try {
                        if (statusEl) statusEl.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldPdfToServer(field, inp.files[0]);
                        bulkCompliancePdfPaths[field] = path;
                        if (statusEl) statusEl.textContent = 'PDF ready.';
                    } catch (err) {
                        if (statusEl) statusEl.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });

                bulkBtn.addEventListener('click', function() {
                    const n = getComplianceRowCheckboxes().filter(b => b.checked).length;
                    const errEl = document.getElementById('complianceBulkEditError');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    updateComplianceBulkEditModalState();
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                    if (n === 0) {
                        showToast('warning', 'Select at least one row using the checkboxes.');
                    }
                });

                modalEl.addEventListener('shown.bs.modal', function() {
                    updateComplianceBulkEditModalState();
                });

                applyBtn.addEventListener('click', async function() {
                    const errEl = document.getElementById('complianceBulkEditError');
                    errEl.classList.add('d-none');
                    errEl.textContent = '';
                    const skus = getComplianceRowCheckboxes().filter(b => b.checked).map(b => b.dataset.sku).filter(Boolean);
                    if (!skus.length) {
                        showToast('warning', 'No rows selected.');
                        return;
                    }
                    applyBtn.disabled = true;
                    let ok = 0;
                    const failed = [];
                    try {
                        for (const sku of skus) {
                            const item = findComplianceRowBySku(sku);
                            if (!item) {
                                failed.push(sku + ': row not found');
                                continue;
                            }
                            const payload = buildComplianceBulkPayloadForItem(item);
                            const response = await fetch('/compliance-master/update', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify(payload)
                            });
                            const data = await response.json().catch(() => ({}));
                            if (response.ok && data.success !== false) {
                                ok++;
                            } else {
                                failed.push(sku + ': ' + (data.message || response.status));
                            }
                        }
                        if (failed.length === 0) {
                            showToast('success', `Updated ${ok} row(s).`);
                            bootstrap.Modal.getInstance(modalEl)?.hide();
                            resetBulkComplianceModal();
                            loadData();
                        } else {
                            errEl.textContent = failed.slice(0, 8).join('\n') + (failed.length > 8 ? '\n…' : '');
                            errEl.classList.remove('d-none');
                            showToast('warning', `Updated ${ok}; ${failed.length} failed. See message in modal.`);
                            if (ok > 0) {
                                loadData();
                            }
                        }
                    } catch (e) {
                        console.error(e);
                        showToast('danger', e.message || 'Bulk update failed.');
                    } finally {
                        updateComplianceBulkEditModalState();
                    }
                });
            }

            // Check if value is missing (null, undefined, empty)
            function isMissing(value) {
                return value === null || value === undefined || value === '' || (typeof value === 'string' && value.trim() === '');
            }

            // Update counts
            function updateCounts() {
                const parentSet = new Set();
                let skuCount = 0;
                let batteryMissingCount = 0;
                let wirelessMissingCount = 0;
                let electricMissingCount = 0;
                let gccMissingCount = 0;
                let rohsMissingCount = 0;
                let blanketMissingCount = 0;
                let bluetoothMissingCount = 0;
                let logoMissingCount = 0;
                let graphMissingCount = 0;

                filteredData.forEach(item => {
                    if (item.Parent) parentSet.add(item.Parent);
                    if (item.SKU && !String(item.SKU).toUpperCase().includes('PARENT'))
                        skuCount++;
                    
                    // Count missing data for each column (REQ without image = missing)
                    if (isMissingComplianceFieldForItem(item, 'battery')) batteryMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'wireless')) wirelessMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'electric')) electricMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'gcc')) gccMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'rohs')) rohsMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'blanket')) blanketMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'bluetooth')) bluetoothMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'logo')) logoMissingCount++;
                    if (isMissingComplianceFieldForItem(item, 'graph')) graphMissingCount++;
                });
                
                const setText = function(id, text) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = text;
                };
                setText('parentCount', `(${parentSet.size})`);
                setText('skuCount', `(${skuCount})`);
                setText('batteryMissingCount', `(${batteryMissingCount})`);
                setText('wirelessMissingCount', `(${wirelessMissingCount})`);
                setText('electricMissingCount', `(${electricMissingCount})`);
                setText('gccMissingCount', `(${gccMissingCount})`);
                setText('rohsMissingCount', `(${rohsMissingCount})`);
                setText('blanketMissingCount', `(${blanketMissingCount})`);
                setText('bluetoothMissingCount', `(${bluetoothMissingCount})`);
                setText('logoMissingCount', `(${logoMissingCount})`);
                setText('graphMissingCount', `(${graphMissingCount})`);

                const sp = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = `(${val})`;
                };
                sp('cm-summary-parent', parentSet.size);
                sp('cm-summary-sku', skuCount);
                sp('cm-summary-battery', batteryMissingCount);
                sp('cm-summary-wireless', wirelessMissingCount);
                sp('cm-summary-electric', electricMissingCount);
                sp('cm-summary-gcc', gccMissingCount);
                sp('cm-summary-rohs', rohsMissingCount);
                sp('cm-summary-blanket', blanketMissingCount);
                sp('cm-summary-bluetooth', bluetoothMissingCount);
                sp('cm-summary-logo', logoMissingCount);
                sp('cm-summary-graph', graphMissingCount);
            }

            // Apply all filters
            function applyFilters() {
                filteredData = tableData.filter(item => {
                    // Parent search filter
                    const parentSearchEl = document.getElementById('parentSearch');
                    const parentSearch = parentSearchEl ? parentSearchEl.value.toLowerCase() : '';
                    if (parentSearch && !(item.Parent || '').toLowerCase().includes(parentSearch)) {
                        return false;
                    }

                    // SKU search filter
                    const skuSearchEl = document.getElementById('skuSearch');
                    const skuSearch = skuSearchEl ? skuSearchEl.value.toLowerCase() : '';
                    if (skuSearch && !(item.SKU || '').toLowerCase().includes(skuSearch)) {
                        return false;
                    }

                    // Custom search filter
                    const customSearch = document.getElementById('customSearch').value.toLowerCase();
                    if (customSearch) {
                        const parent = (item.Parent || '').toLowerCase();
                        const sku = (item.SKU || '').toLowerCase();
                        const statusHaystack = resolveProductMasterStatus(item).toLowerCase();
                        const statusLabel = formatProductMasterStatusLabel(resolveProductMasterStatus(item)).toLowerCase();
                        if (!parent.includes(customSearch) && !sku.includes(customSearch)
                            && !statusHaystack.includes(customSearch) && !statusLabel.includes(customSearch)) {
                            return false;
                        }
                    }

                    const filterComplianceStatusEl = document.getElementById('filterComplianceStatus');
                    const filterComplianceStatus = filterComplianceStatusEl ? filterComplianceStatusEl.value : 'all';
                    if (filterComplianceStatus === 'missing') {
                        if (!isMissing(resolveProductMasterStatus(item))) {
                            return false;
                        }
                    } else if (filterComplianceStatus !== 'all') {
                        const st = resolveProductMasterStatus(item);
                        if (!st || String(st).toLowerCase() !== String(filterComplianceStatus).toLowerCase()) {
                            return false;
                        }
                    }

                    // Compliance field filters (Battery / Wireless / …)
                    const fieldFilterMap = {
                        battery: 'filterBattery',
                        wireless: 'filterWireless',
                        electric: 'filterElectric',
                        gcc: 'filterGcc',
                        rohs: 'filterRohs',
                        blanket: 'filterBlanket',
                        bluetooth: 'filterBluetooth',
                        logo: 'filterLogo',
                        graph: 'filterGraph'
                    };
                    for (const key of Object.keys(fieldFilterMap)) {
                        const el = document.getElementById(fieldFilterMap[key]);
                        const fv = el ? el.value : 'all';
                        if (!matchesComplianceFieldFilter(item, key, fv)) {
                            return false;
                        }
                    }

                    return true;
                });
                renderTable(filteredData);
                updateCounts();
                syncComplianceFieldFilterStyles();
            }

            // Setup search functionality
            function setupSearch() {
                if (complianceSearchSetupDone) return;

                const parentSearch = document.getElementById('parentSearch');
                const skuSearch = document.getElementById('skuSearch');
                const customSearch = document.getElementById('customSearch');
                const clearSearchBtn = document.getElementById('clearSearch');
                if (!parentSearch || !skuSearch || !customSearch || !clearSearchBtn) return;

                const filterIds = ['filterBattery', 'filterWireless', 'filterElectric', 'filterGcc', 'filterRohs', 'filterBlanket', 'filterBluetooth', 'filterLogo', 'filterGraph'];
                for (let i = 0; i < filterIds.length; i++) {
                    if (!document.getElementById(filterIds[i])) return;
                }

                complianceSearchSetupDone = true;

                parentSearch.addEventListener('input', function() {
                    applyFilters();
                });

                skuSearch.addEventListener('input', function() {
                    applyFilters();
                });

                customSearch.addEventListener('input', function() {
                    applyFilters();
                });

                clearSearchBtn.addEventListener('click', function() {
                    customSearch.value = '';
                    parentSearch.value = '';
                    skuSearch.value = '';
                    // Reset all column filters
                    document.getElementById('filterBattery').value = 'all';
                    document.getElementById('filterWireless').value = 'all';
                    document.getElementById('filterElectric').value = 'all';
                    document.getElementById('filterGcc').value = 'all';
                    document.getElementById('filterRohs').value = 'all';
                    document.getElementById('filterBlanket').value = 'all';
                    document.getElementById('filterBluetooth').value = 'all';
                    document.getElementById('filterLogo').value = 'all';
                    document.getElementById('filterGraph').value = 'all';
                    const fcs = document.getElementById('filterComplianceStatus');
                    if (fcs) fcs.value = 'all';
                    document.querySelectorAll('.cm-status-filter-wrap.is-open').forEach(x => {
                        x.classList.remove('is-open');
                        const t = x.querySelector('.cm-status-filter-trigger');
                        if (t) t.setAttribute('aria-expanded', 'false');
                    });
                    refreshCmStatusFilterUI();
                    applyFilters();
                });

                filterIds.forEach(function(fid) {
                    document.getElementById(fid).addEventListener('change', function() {
                        syncComplianceFieldFilterStyles();
                        applyFilters();
                    });
                });
                syncComplianceFieldFilterStyles();
            }

            // Toast notification function
            function showToast(type, message) {
                document.querySelectorAll('.custom-toast').forEach(t => t.remove());

                const toast = document.createElement('div');
                const toastContainer = document.querySelector('.toast-container');
                const useContainer = !!toastContainer;
                toast.className = useContainer
                    ? `custom-toast toast align-items-center text-bg-${type} border-0 show mb-2`
                    : `custom-toast toast align-items-center text-bg-${type} border-0 show position-fixed top-0 end-0 m-4`;
                if (!useContainer) toast.style.zIndex = '2000';
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                `;
                (toastContainer || document.body).appendChild(toast);

                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                }, 3000);

                toast.querySelector('[data-bs-dismiss="toast"]').onclick = () => {
                    toast.classList.remove('show');
                    setTimeout(() => toast.remove(), 500);
                };
            }

            // Setup Excel export function
            function setupExcelExport() {
                document.getElementById('downloadExcel').addEventListener('click', function() {
                    // Columns to export (excluding Image and Action)
                    const columns = ["Parent", "SKU", "Status", "INV", "Battery", "Wireless", "Electric", "GCC", "RoHs", "Blanket", "Bluetooth", "Logo", "Graph"];

                    // Column definitions with their data keys
                    const columnDefs = {
                        "Parent": {
                            key: "Parent"
                        },
                        "SKU": {
                            key: "SKU"
                        },
                        "Status": {
                            key: "status"
                        },
                        "INV": {
                            key: "shopify_inv"
                        },
                        "Battery": {
                            key: "battery"
                        },
                        "Wireless": {
                            key: "wireless"
                        },
                        "Electric": {
                            key: "electric"
                        },
                        "GCC": {
                            key: "gcc"
                        },
                        "RoHs": {
                            key: "rohs"
                        },
                        "Blanket": {
                            key: "blanket"
                        },
                        "Bluetooth": {
                            key: "bluetooth"
                        },
                        "Logo": {
                            key: "logo"
                        },
                        "Graph": {
                            key: "graph"
                        }
                    };

                    // Show loader or indicate download is in progress
                    document.getElementById('downloadExcel').innerHTML =
                        '<i class="fas fa-spinner fa-spin"></i> Generating...';
                    document.getElementById('downloadExcel').disabled = true;

                    // Compliance field keys that should mirror the on-screen badge logic
                    // (Battery/Wireless/Electric/GCC/RoHs/Blanket/Bluetooth/Logo/Graph). For these
                    // we render the same text the page shows: "REQ", "N/A", or a legacy value.
                    const complianceFieldKeysForExport = new Set([
                        'battery', 'wireless', 'electric', 'gcc', 'rohs', 'blanket', 'bluetooth', 'logo', 'graph'
                    ]);

                    // Mirror complianceFieldCellHtml() as plain text for Excel export so the
                    // file matches what the user sees on the page (e.g. empty DB value -> "N/A").
                    function complianceFieldCellText(item, key) {
                        const v = complianceFieldStoredValue(item, key);
                        const upper = v.toUpperCase();
                        if (upper === 'REQ') {
                            return 'REQ';
                        }
                        if (isComplianceNaValue(v)) {
                            return 'N/A';
                        }
                        return v;
                    }

                    // Use setTimeout to avoid UI freeze for large datasets
                    setTimeout(() => {
                        try {
                            // Always export the full dataset (tableData) so on-screen
                            // search/filter selections never reduce what gets downloaded.
                            // Falls back to filteredData only if tableData is somehow empty.
                            const sourceData = (Array.isArray(tableData) && tableData.length > 0)
                                ? tableData
                                : (Array.isArray(filteredData) ? filteredData : []);

                            const dataToExport = sourceData.filter(item => {
                                if (!item) return false;
                                const sku = String(item.SKU || '').trim();
                                const parent = String(item.Parent || '').trim();
                                if (sku === '' && parent === '') return false;
                                return true;
                            });

                            console.log('[Compliance Export] tableData:', tableData.length,
                                'filteredData:', filteredData.length,
                                'exporting:', dataToExport.length);

                            // Create worksheet data array
                            const wsData = [];

                            // Add header row
                            wsData.push(columns);

                            // Add data rows
                            dataToExport.forEach(item => {
                                // PARENT summary rows on the page leave compliance fields blank
                                // ("—"), so do the same in Excel and don't force N/A on them.
                                const isParentSummaryRow = complianceRowHasParentKeyword(item);

                                const row = [];
                                columns.forEach(col => {
                                    const colDef = columnDefs[col];
                                    if (colDef) {
                                        const key = colDef.key;
                                        let value = item[key] !== undefined && item[key] !== null ? item[key] : '';

                                        if (col === 'Status') {
                                            const lbl = formatProductMasterStatusLabel(resolveProductMasterStatus(item));
                                            value = lbl === '—' ? '' : lbl;
                                        } else if (key === "shopify_inv") {
                                            if (value === 0 || value === "0") {
                                                value = 0;
                                            } else if (value === null || value === undefined || value === "") {
                                                value = '';
                                            } else {
                                                value = parseFloat(value) || 0;
                                            }
                                        } else if (complianceFieldKeysForExport.has(key)) {
                                            value = isParentSummaryRow ? '' : complianceFieldCellText(item, key);
                                        }

                                        row.push(value);
                                    } else {
                                        row.push('');
                                    }
                                });
                                wsData.push(row);
                            });

                            // Create workbook and worksheet
                            const wb = XLSX.utils.book_new();
                            const ws = XLSX.utils.aoa_to_sheet(wsData);

                            // Set column widths
                            const wscols = columns.map(col => {
                                // Adjust width based on column type
                                if (["Parent", "SKU"].includes(col)) {
                                    return { wch: 20 }; // Wider for text columns
                                } else if (["Status", "Battery", "Wireless", "Electric", "GCC", "RoHs", "Blanket", "Bluetooth", "Logo", "Graph"].includes(col)) {
                                    return { wch: 15 };
                                } else {
                                    return { wch: 10 }; // Default width for numeric columns
                                }
                            });
                            ws['!cols'] = wscols;

                            // Style the header row
                            const headerRange = XLSX.utils.decode_range(ws['!ref']);
                            for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                                const cell = XLSX.utils.encode_cell({
                                    r: 0,
                                    c: C
                                });
                                if (!ws[cell]) continue;

                                // Add header style
                                ws[cell].s = {
                                    fill: {
                                        fgColor: {
                                            rgb: "2C6ED5"
                                        }
                                    },
                                    font: {
                                        bold: true,
                                        color: {
                                            rgb: "FFFFFF"
                                        }
                                    },
                                    alignment: {
                                        horizontal: "center"
                                    }
                                };
                            }

                            // Add the worksheet to the workbook
                            XLSX.utils.book_append_sheet(wb, ws, "Compliance Masters");

                            // Generate Excel file and trigger download
                            XLSX.writeFile(wb, "compliance_master_export.xlsx");

                            // Show success toast (include row count so you can confirm full export)
                            showToast('success', `Excel file downloaded successfully! (${dataToExport.length} rows)`);
                        } catch (error) {
                            console.error("Excel export error:", error);
                            showToast('danger', 'Failed to export Excel file.');
                        } finally {
                            // Reset button state
                            document.getElementById('downloadExcel').innerHTML =
                                '<i class="fas fa-file-excel me-1"></i> Download Excel';
                            document.getElementById('downloadExcel').disabled = false;
                        }
                    }, 100); // Small timeout to allow UI to update
                });
            }

            function findComplianceRowBySku(sku) {
                const s = String(sku || '');
                let row = tableData.find(i => String(i.SKU) === s);
                if (row) return row;
                return filteredData.find(i => String(i.SKU) === s);
            }

            // Setup add button handler
            function setupAddButton() {
                document.getElementById('addComplianceBtn').addEventListener('click', function() {
                    openComplianceModal('add');
                });
            }

            function setupActionButtons() {
                const gridHost = document.getElementById('compliance-tabulator');
                if (!gridHost) return;
                gridHost.addEventListener('click', function(e) {
                    const editBtn = e.target.closest('.edit-btn');
                    if (editBtn && this.contains(editBtn)) {
                        e.preventDefault();
                        const sku = editBtn.getAttribute('data-sku');
                        if (sku) {
                            openComplianceModal('edit', sku);
                        }
                    }
                });
            }

            async function openComplianceModal(mode, editSku = null) {
                const modalElement = document.getElementById('addComplianceModal');
                const modalTitle = document.getElementById('addComplianceModalLabel');
                const skuSelect = document.getElementById('addComplianceSku');

                if (mode === 'edit') {
                    const skuStr = String(editSku || '').trim();
                    if (!skuStr) {
                        showToast('warning', 'Could not determine SKU to edit.');
                        return;
                    }
                    if (skuStr.toUpperCase().includes('PARENT')) {
                        showToast('warning', 'Parent summary rows cannot be edited here.');
                        return;
                    }
                    const item = findComplianceRowBySku(skuStr);
                    if (!item) {
                        showToast('warning', 'Row not found. Try refreshing the page.');
                        return;
                    }
                    complianceFormMode = 'edit';
                    complianceEditSku = skuStr;
                    modalTitle.textContent = 'Edit Compliance Data';
                } else {
                    complianceFormMode = 'add';
                    complianceEditSku = '';
                    modalTitle.textContent = 'Add Compliance Data';
                }

                complianceFormHydrating = true;
                document.getElementById('addComplianceForm').reset();
                resetComplianceAddFormFields();
                const siblingsCb = document.getElementById('cm_apply_siblings');
                if (siblingsCb) siblingsCb.checked = false;
                setComplianceAutosaveStatus('');
                updateComplianceSiblingsHint();

                if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                    $(skuSelect).select2('destroy');
                }
                $(skuSelect).prop('disabled', false);

                await loadSkusIntoDropdown();

                if (mode === 'edit') {
                    const item = findComplianceRowBySku(complianceEditSku);
                    $(skuSelect).val(complianceEditSku).trigger('change');
                    $(skuSelect).prop('disabled', true);
                    setAddComplianceFormFromItem(item);
                }

                complianceFormHydrating = false;
                updateComplianceSiblingsHint();

                const saveBtn = document.getElementById('saveAddComplianceBtn');
                const newSaveBtn = saveBtn.cloneNode(true);
                saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
                newSaveBtn.addEventListener('click', async function() {
                    await saveCompliance({ auto: false, keepOpen: false });
                });

                modalElement.addEventListener('hidden.bs.offcanvas', function complianceModalCleanup() {
                    if (complianceAutoSaveTimer) {
                        clearTimeout(complianceAutoSaveTimer);
                        complianceAutoSaveTimer = null;
                    }
                    $(skuSelect).prop('disabled', false);
                    if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                        $(skuSelect).select2('destroy');
                    }
                    complianceFormMode = 'add';
                    complianceEditSku = '';
                    complianceFormHydrating = false;
                    setComplianceAutosaveStatus('');
                }, { once: true });

                const panel = bootstrap.Offcanvas.getOrCreateInstance(modalElement);
                panel.show();
            }

            // Load SKUs into dropdown
            async function loadSkusIntoDropdown() {
                try {
                    const response = await fetch('/general-specific-master/skus', {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.data) {
                        const skuSelect = document.getElementById('addComplianceSku');
                        
                        // Destroy Select2 if already initialized
                        if ($(skuSelect).hasClass('select2-hidden-accessible')) {
                            $(skuSelect).select2('destroy');
                        }
                        
                        // Clear existing options except the first one
                        skuSelect.innerHTML = '<option value="">Select SKU</option>';
                        
                        // Add SKU options
                        data.data.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.sku;
                            option.textContent = item.sku;
                            skuSelect.appendChild(option);
                        });
                        
                        // Initialize Select2 with searchable dropdown
                        $(skuSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Select SKU',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#addComplianceModal')
                        });
                    }
                } catch (error) {
                    console.error('Error loading SKUs:', error);
                    showToast('warning', 'Failed to load SKUs. Please refresh the page.');
                }
            }

            async function saveCompliance(options = {}) {
                const opts = Object.assign({ auto: false, keepOpen: false }, options || {});
                const saveBtn = document.getElementById('saveAddComplianceBtn');
                const originalText = saveBtn ? saveBtn.innerHTML : '';
                const skuSelect = document.getElementById('addComplianceSku');

                let sku = '';
                if (complianceFormMode === 'edit') {
                    sku = (complianceEditSku || '').trim();
                } else {
                    sku = $(skuSelect).val() ? String($(skuSelect).val()).trim() : '';
                }

                if (!sku) {
                    if (!opts.auto) {
                        showToast('warning', complianceFormMode === 'edit' ? 'Missing SKU for update.' : 'Please select SKU');
                        if (complianceFormMode !== 'edit' && $(skuSelect).hasClass('select2-hidden-accessible')) {
                            $(skuSelect).select2('open');
                        }
                    }
                    return;
                }

                if (opts.auto && complianceAutoSaveInFlight) {
                    complianceAutoSaveQueued = true;
                    return;
                }

                let url = complianceFormMode === 'edit' ? '/compliance-master/update' : '/compliance-master/store';
                let usedUpdate = complianceFormMode === 'edit';

                try {
                    complianceAutoSaveInFlight = true;
                    if (opts.auto) {
                        setComplianceAutosaveStatus('Saving…', false);
                    } else if (saveBtn) {
                        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                        saveBtn.disabled = true;
                    }

                    const formData = collectComplianceFormPayload(sku);

                    let response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(formData)
                    });

                    let data = await response.json().catch(() => ({}));

                    // First autosave may hit store while row already has compliance → retry update.
                    if (!usedUpdate && response.status === 409) {
                        url = '/compliance-master/update';
                        usedUpdate = true;
                        response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(formData)
                        });
                        data = await response.json().catch(() => ({}));
                    }

                    if (!response.ok || data.success === false) {
                        throw new Error(data.message || 'Failed to save data');
                    }

                    // After a successful create, treat further autosaves as edits.
                    if (!usedUpdate) {
                        complianceFormMode = 'edit';
                        complianceEditSku = sku;
                        const modalTitle = document.getElementById('addComplianceModalLabel');
                        if (modalTitle) modalTitle.textContent = 'Edit Compliance Data';
                        if (skuSelect) $(skuSelect).prop('disabled', true);
                    }

                    const sibCount = Number(data.siblings_count) || (Array.isArray(data.siblings) ? data.siblings.length : 0);
                    patchLocalComplianceRowsFromPayload(formData, data.siblings || []);
                    updateComplianceSiblingsHint();

                    if (opts.auto || opts.keepOpen) {
                        const msg = sibCount > 0
                            ? ('Saved (+' + sibCount + ' siblings)')
                            : 'Saved';
                        setComplianceAutosaveStatus(msg, false);
                        if (!opts.auto) {
                            showToast('success', data.message || msg);
                        }
                    } else {
                        showToast('success', data.message || (usedUpdate
                            ? 'Compliance data updated successfully!'
                            : 'Compliance Data added successfully!'));
                        const panel = bootstrap.Offcanvas.getInstance(document.getElementById('addComplianceModal'));
                        if (panel) panel.hide();
                        loadData();
                    }
                } catch (error) {
                    console.error('Error saving:', error);
                    if (opts.auto) {
                        setComplianceAutosaveStatus(error.message || 'Save failed', true);
                    } else {
                        showToast('danger', error.message || 'Failed to save data');
                    }
                } finally {
                    complianceAutoSaveInFlight = false;
                    if (saveBtn && !opts.auto) {
                        saveBtn.innerHTML = originalText;
                        saveBtn.disabled = false;
                    }
                    if (complianceAutoSaveQueued) {
                        complianceAutoSaveQueued = false;
                        scheduleComplianceAutosave();
                    }
                }
            }

            // Setup import functionality
            function setupImport() {
                const importFile = document.getElementById('importFile');
                const importBtn = document.getElementById('importBtn');
                const downloadSampleBtn = document.getElementById('downloadSampleBtn');
                const importModal = document.getElementById('importModal');
                const fileError = document.getElementById('fileError');
                const importProgress = document.getElementById('importProgress');
                const importResult = document.getElementById('importResult');

                // Enable/disable import button based on file selection
                importFile.addEventListener('change', function() {
                    if (this.files && this.files.length > 0) {
                        const file = this.files[0];
                        const fileName = file.name.toLowerCase();
                        const validExtensions = ['.xlsx', '.xls', '.csv'];
                        const isValid = validExtensions.some(ext => fileName.endsWith(ext));

                        if (isValid) {
                            importBtn.disabled = false;
                            fileError.style.display = 'none';
                        } else {
                            importBtn.disabled = true;
                            fileError.textContent = 'Please select a valid Excel file (.xlsx, .xls, or .csv)';
                            fileError.style.display = 'block';
                        }
                    } else {
                        importBtn.disabled = true;
                    }
                });

                // Download sample file
                downloadSampleBtn.addEventListener('click', function() {
                    // Create sample data
                    const sampleData = [
                        ['SKU', 'Battery', 'Wireless', 'Electric', 'GCC', 'RoHs', 'Blanket', 'Bluetooth', 'Logo', 'Graph'],
                        ['SKU001', 'N/A', 'REQ', 'N/A', 'REQ', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A'],
                        ['SKU002', 'REQ', 'N/A', 'REQ', 'N/A', 'REQ', 'N/A', 'N/A', 'N/A', 'N/A'],
                        ['SKU003', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'N/A', 'REQ']
                    ];

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    const ws = XLSX.utils.aoa_to_sheet(sampleData);

                    // Set column widths
                    ws['!cols'] = [
                        { wch: 15 }, // SKU
                        { wch: 12 }, // Battery
                        { wch: 12 }, // Wireless
                        { wch: 12 }, // Electric
                        { wch: 12 }, // GCC
                        { wch: 12 }, // RoHs
                        { wch: 12 }, // Blanket
                        { wch: 12 }, // Bluetooth
                        { wch: 12 }, // Logo
                        { wch: 12 }  // Graph
                    ];

                    // Style header row
                    const headerRange = XLSX.utils.decode_range(ws['!ref']);
                    for (let C = headerRange.s.c; C <= headerRange.e.c; ++C) {
                        const cell = XLSX.utils.encode_cell({ r: 0, c: C });
                        if (!ws[cell]) continue;
                        ws[cell].s = {
                            fill: { fgColor: { rgb: "2C6ED5" } },
                            font: { bold: true, color: { rgb: "FFFFFF" } },
                            alignment: { horizontal: "center" }
                        };
                    }

                    XLSX.utils.book_append_sheet(wb, ws, "Compliance Data");
                    XLSX.writeFile(wb, "compliance_master_sample.xlsx");
                    
                    showToast('success', 'Sample file downloaded successfully!');
                });

                // Handle import
                importBtn.addEventListener('click', async function() {
                    const file = importFile.files[0];
                    if (!file) {
                        showToast('danger', 'Please select a file to import');
                        return;
                    }

                    // Disable button and show progress
                    importBtn.disabled = true;
                    importProgress.style.display = 'block';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';

                    const formData = new FormData();
                    formData.append('excel_file', file);
                    formData.append('_token', csrfToken);

                    try {
                        const response = await fetch('/compliance-master/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: formData
                        });

                        const result = await response.json();

                        // Update progress bar
                        const progressBar = importProgress.querySelector('.progress-bar');
                        progressBar.style.width = '100%';

                        if (response.ok && result.success) {
                            importResult.className = 'alert alert-success';
                            importResult.innerHTML = `
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Import Successful!</strong><br>
                                ${result.message || `Successfully imported ${result.imported || 0} records.`}
                                ${result.errors && result.errors.length > 0 ? `<br><small>Errors: ${result.errors.length}</small>` : ''}
                            `;
                            importResult.style.display = 'block';

                            // Reload data after successful import
                            setTimeout(() => {
                                loadData();
                                // Close modal after a delay
                                setTimeout(() => {
                                    const modal = bootstrap.Modal.getInstance(importModal);
                                    if (modal) modal.hide();
                                    // Reset form
                                    importFile.value = '';
                                    importBtn.disabled = true;
                                    importProgress.style.display = 'none';
                                    importResult.style.display = 'none';
                                    progressBar.style.width = '0%';
                                }, 2000);
                            }, 1000);
                        } else {
                            importResult.className = 'alert alert-danger';
                            importResult.innerHTML = `
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Import Failed!</strong><br>
                                ${result.message || 'An error occurred during import.'}
                            `;
                            importResult.style.display = 'block';
                            importBtn.disabled = false;
                        }
                    } catch (error) {
                        console.error('Import error:', error);
                        importResult.className = 'alert alert-danger';
                        importResult.innerHTML = `
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Import Failed!</strong><br>
                            ${error.message || 'An error occurred during import.'}
                        `;
                        importResult.style.display = 'block';
                        importBtn.disabled = false;
                    } finally {
                        // Reset progress bar after a delay
                        setTimeout(() => {
                            const progressBar = importProgress.querySelector('.progress-bar');
                            progressBar.style.width = '0%';
                        }, 2000);
                    }
                });

                // Reset form when modal is closed
                importModal.addEventListener('hidden.bs.modal', function() {
                    importFile.value = '';
                    importBtn.disabled = true;
                    importProgress.style.display = 'none';
                    importResult.style.display = 'none';
                    fileError.style.display = 'none';
                    const progressBar = importProgress.querySelector('.progress-bar');
                    if (progressBar) progressBar.style.width = '0%';
                });
            }

            function setupComplianceAddFormListeners() {
                const modal = document.getElementById('addComplianceModal');
                if (!modal) return;

                modal.addEventListener('click', function(e) {
                    const trigger = e.target.closest('[data-trigger-file]');
                    if (!trigger || !modal.contains(trigger)) return;
                    e.preventDefault();
                    const fileId = trigger.getAttribute('data-trigger-file');
                    const fileInput = fileId ? document.getElementById(fileId) : null;
                    if (fileInput) fileInput.click();
                });

                modal.addEventListener('change', function(e) {
                    if (e.target && e.target.id === 'cm_apply_siblings') {
                        updateComplianceSiblingsHint();
                        scheduleComplianceAutosave();
                        return;
                    }
                    const name = e.target.getAttribute('name');
                    if (name && name.startsWith('add_mode_')) {
                        const k = name.replace('add_mode_', '');
                        toggleAddComplianceReqWrap(k);
                        scheduleComplianceAutosave();
                    }
                });

                $(document).on('change.select2', '#addComplianceSku', function() {
                    updateComplianceSiblingsHint();
                });

                modal.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.add-compliance-img-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const pathInputId = inp.dataset.pathInput;
                    const pathEl = pathInputId ? document.getElementById(pathInputId) : null;
                    const st = document.getElementById(`add_${field}_img_status`);
                    try {
                        if (st) st.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldImageToServer(field, inp.files[0]);
                        if (pathEl) pathEl.value = path;
                        if (st) st.textContent = 'Image saved.';
                        refreshComplianceFieldFileUi(field);
                        scheduleComplianceAutosave();
                    } catch (err) {
                        if (st) st.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });

                modal.addEventListener('change', async function(e) {
                    const inp = e.target.closest('.add-compliance-pdf-input');
                    if (!inp || !inp.files || !inp.files[0]) return;
                    const field = inp.dataset.field;
                    const pathInputId = inp.dataset.pathInput;
                    const pathEl = pathInputId ? document.getElementById(pathInputId) : null;
                    const st = document.getElementById(`add_${field}_pdf_status`);
                    try {
                        if (st) st.textContent = 'Uploading...';
                        const path = await uploadComplianceFieldPdfToServer(field, inp.files[0]);
                        if (pathEl) pathEl.value = path;
                        if (st) st.textContent = 'PDF saved.';
                        refreshComplianceFieldFileUi(field);
                        scheduleComplianceAutosave();
                    } catch (err) {
                        if (st) st.textContent = err.message || 'Upload failed';
                        showToast('danger', err.message || 'Upload failed');
                    }
                });
            }

            // Initialize
            setupComplianceImageHoverPreview();
            setupComplianceFileHoverActions();
            setupComplianceStatusFilter();
            refreshCmStatusFilterUI();
            setupSearch();
            setupComplianceAddFormListeners();
            setupActionButtons();
            setupComplianceBulkEdit();
            loadData();
            setupExcelExport();
            setupAddButton();
            setupImport();
        });
    </script>
@endsection

