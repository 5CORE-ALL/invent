@extends('layouts.vertical', ['title' => 'Purchase Comparison'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
    /* Playback (parent navigation) controls */
    .comparison-playback-group button {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #dee2e6;
        margin-right: 4px;
        transition: all 0.15s ease;
    }

    .comparison-playback-group button i {
        font-size: 1rem;
    }

    #comparison-play-auto {
        color: #28a745;
    }

    #comparison-play-auto:hover {
        background-color: #28a745 !important;
        color: #fff !important;
    }

    #comparison-play-pause {
        color: #ffc107;
    }

    #comparison-play-pause:hover {
        background-color: #ffc107 !important;
        color: #fff !important;
    }

    #comparison-play-backward,
    #comparison-play-forward {
        color: #007bff;
    }

    #comparison-play-backward:hover,
    #comparison-play-forward:hover {
        background-color: #007bff !important;
        color: #fff !important;
    }

    .comparison-playback-group button:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .tabulator {
        font-size: 12px;
        border: 1px solid #dee2e6;
    }

    .tabulator .tabulator-header {
        background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
        border-bottom: 2px solid #2563eb;
        font-weight: 600;
    }

    .tabulator .tabulator-header .tabulator-col {
        border-right: 1px solid #e5e7eb;
    }

    .tabulator-row {
        min-height: 30px !important;
    }

    .tabulator-row:hover {
        background-color: #f8f9fa !important;
    }

    .tabulator-cell {
        padding: 4px 6px !important;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
        padding: 8px 16px;
        margin: 0 4px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
        background: #e0eaff;
        color: #2563eb;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
        background: #2563eb;
        color: white;
    }

    .comparison-cd-btn {
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .comparison-cd-btn i {
        transition: transform 0.15s ease, color 0.15s ease;
    }

    .tabulator-cell[tabulator-field="cd_view"] {
        cursor: pointer;
    }

    .comparison-cd-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        min-height: 28px;
        cursor: pointer;
    }

    .comparison-cd-btn:not(:disabled):hover i {
        color: #1d4ed8 !important;
        transform: scale(1.08);
    }

    .comparison-clink-dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background-color: #1e40af;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
    }

    .comparison-clink-dot-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        min-height: 28px;
        padding: 4px 8px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        text-decoration: none;
        line-height: 1;
    }

    .comparison-clink-dot-link:hover .comparison-clink-dot {
        background-color: #1e3a8a;
    }

    .comparison-clink-dot-muted {
        background-color: #94a3b8;
    }

    .comparison-clink-dot-empty:hover .comparison-clink-dot,
    .comparison-clink-dot-link:hover .comparison-clink-dot-muted {
        background-color: #64748b;
    }

    .comparison-cd-clink-url-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
        min-height: 31px;
        position: relative;
    }

    .comparison-cd-clink-url-wrap .comparison-cd-clink-url-input {
        width: 0;
        min-width: 0;
        max-width: 0;
        padding: 0;
        margin: 0;
        border: 0;
        opacity: 0;
        pointer-events: none;
        transition: max-width 0.15s ease, opacity 0.15s ease;
    }

    .comparison-cd-clink-url-wrap.is-editing .comparison-cd-clink-url-input {
        width: auto;
        min-width: 200px;
        max-width: 320px;
        padding: 0.25rem 0.5rem;
        border: 1px solid #ced4da;
        opacity: 1;
        pointer-events: auto;
    }

    .comparison-cd-clink-url-edit-btn {
        line-height: 1;
        padding: 2px 6px;
    }

    .comparison-company-dot {
        display: inline-block;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background-color: #16a34a;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.15);
        cursor: help;
    }

    #cd-hover-preview {
        position: fixed;
        z-index: 1080;
        max-width: 320px;
        padding: 10px 12px;
        background: #1a2942;
        color: #fff;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        font-size: 12px;
        line-height: 1.5;
        pointer-events: none;
        display: none;
    }

    #cd-hover-preview .cd-hover-label {
        color: #93c5fd;
        font-weight: 600;
    }

    .comparison-history-btn {
        line-height: 1.2;
        max-width: 100%;
    }

    .comparison-history-table {
        font-size: 12px;
    }

    .comparison-history-table th,
    .comparison-history-table td {
        padding: 6px 10px !important;
        vertical-align: middle;
    }

    .comparison-history-table .ch-change {
        word-break: break-word;
    }

    .comparison-history-table .ch-when {
        white-space: nowrap;
        color: #6c757d;
    }

    .tabulator .tabulator-cell.linked-sku-col {
        padding-top: 4px !important;
        padding-bottom: 4px !important;
    }

    .tabulator .tabulator-cell.linked-sku-col .linked-sku-badge:hover {
        background-color: #cffafe !important;
    }

    .linked-sku-badge-wrap {
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .linked-sku-badge-wrap .comparison-linked-sku-remove {
        font-size: 0.55rem;
        opacity: 0.65;
        padding: 0;
        margin-left: 2px;
    }

    .linked-sku-badge-wrap .comparison-linked-sku-remove:hover {
        opacity: 1;
    }

    .comparison-cat-badge-wrap {
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .comparison-cat-badge-wrap .comparison-category-remove {
        font-size: 0.55rem;
        opacity: 0.65;
        padding: 0;
        margin-left: 2px;
    }

    .comparison-cat-badge-wrap .comparison-category-remove:hover {
        opacity: 1;
    }

    .comparison-category-cell {
        width: 100%;
        min-height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 2px 4px;
        border-radius: 4px;
    }

    .comparison-category-cell:hover {
        background: #f1f5f9;
    }

    .comparison-category-dropdown {
        position: fixed;
        z-index: 2000;
        background: #fff;
        border: 1px solid #ced4da;
        border-radius: 6px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        min-width: 240px;
        max-width: 340px;
    }

    .comparison-category-dropdown .dropdown-search-input {
        width: 100%;
        border: none;
        border-bottom: 1px solid #e5e7eb;
        border-radius: 6px 6px 0 0;
        padding: 8px 10px;
        font-size: 13px;
        outline: none;
    }

    .comparison-category-dropdown .dropdown-search-results {
        max-height: 240px;
        overflow-y: auto;
    }

    .comparison-category-dropdown .dropdown-search-item {
        padding: 8px 10px;
        cursor: pointer;
        font-size: 13px;
    }

    .comparison-category-dropdown .dropdown-search-item:hover,
    .comparison-category-dropdown .dropdown-search-item.active {
        background: #e0e7ff;
    }

    .comparison-category-dropdown .dropdown-search-item.no-results {
        cursor: default;
        color: #6c757d;
    }

    .comparison-category-dropdown .dropdown-search-item.clear-option {
        border-bottom: 1px solid #e5e7eb;
        color: #64748b;
        font-style: italic;
    }

    #comparison-cd-modal-sku-badge {
        font-size: 0.85rem;
        font-weight: 600;
        vertical-align: middle;
    }

    .comparison-cd-header-image-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 0.5rem;
        vertical-align: middle;
    }

    .comparison-cd-header-image {
        width: 40px;
        height: 40px;
        object-fit: contain;
        border-radius: 6px;
        border: 1px solid rgba(255, 255, 255, 0.55);
        background: #fff;
        cursor: zoom-in;
        vertical-align: middle;
    }

    .comparison-cd-image-refresh-btn {
        padding: 2px 6px;
        line-height: 1.2;
        border-color: rgba(255, 255, 255, 0.65) !important;
        color: #fff !important;
        background: rgba(255, 255, 255, 0.12) !important;
    }

    .comparison-cd-image-refresh-btn:hover {
        background: rgba(255, 255, 255, 0.28) !important;
        color: #fff !important;
    }

    #comparison-cd-image-hover-preview {
        position: fixed;
        z-index: 2000;
        display: none;
        padding: 6px;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.28);
        pointer-events: none;
    }

    #comparison-cd-image-hover-preview img {
        display: block;
        max-width: 360px;
        max-height: 360px;
        object-fit: contain;
    }

    #comparisonCdModal .modal-dialog {
        max-width: 96vw;
    }

    /* Full-page CD editor mode (opened via /sheet-view or ?cd_only=): render the
       comparison-sheet editor inline as a real page instead of a centered modal. */
    body.cd-page-mode {
        overflow: auto !important;
        padding-right: 0 !important;
    }
    body.cd-page-mode .modal-backdrop {
        display: none !important;
    }
    body.cd-page-mode #comparisonCdModal {
        position: static !important;
        display: block !important;
        overflow: visible !important;
    }
    body.cd-page-mode #comparisonCdModal .modal-dialog {
        max-width: 100% !important;
        width: 100% !important;
        margin: 0 !important;
        height: auto;
    }
    body.cd-page-mode #comparisonCdModal .modal-content {
        border: 0;
        border-radius: 0;
        min-height: 100vh;
        box-shadow: none;
    }
    body.cd-page-mode #comparisonCdModal .btn-close {
        display: none;
    }

    #comparisonCdModal .modal-body {
        min-height: 70vh;
    }

    .cd-sheet-toolbar {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 8px 10px;
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .cd-sheet-toolbar-row {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        gap: 6px;
        min-height: 34px;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }

    .cd-sheet-toolbar-row::-webkit-scrollbar {
        height: 4px;
    }

    .cd-sheet-toolbar-row::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }

    .cd-sheet-toolbar-row > * {
        flex: 0 0 auto;
    }

    .cd-sheet-toolbar-divider {
        width: 1px;
        align-self: stretch;
        background: #dee2e6;
        margin: 2px 2px;
        flex: 0 0 1px;
    }

    .cd-sheet-toolbar-group {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
    }

    .cd-sheet-toolbar .btn-sm {
        white-space: nowrap;
    }

    .cd-sheet-toolbar .cd-sheet-tab-input {
        width: 88px;
    }

    .cd-sheet-wrap {
        overflow: auto;
        max-height: 62vh;
        border: 1px solid #ced4da;
        border-radius: 6px;
        background: #fff;
    }

    .cd-sheet-table {
        border-collapse: collapse;
        min-width: 100%;
        font-size: 12px;
    }

    .cd-sheet-table th,
    .cd-sheet-table td {
        border: 1px solid #d0d7de;
        min-width: 110px;
        max-width: 260px;
        vertical-align: middle;
        text-align: center;
        padding: 0;
        height: 1px;
    }

    .cd-sheet-table th {
        background: #f3f4f6;
        color: #374151;
        font-weight: 600;
        text-align: center;
        padding: 6px 4px;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .cd-sheet-table .cd-row-num {
        min-width: 42px;
        max-width: 42px;
        background: #f3f4f6;
        color: #6b7280;
        text-align: center;
        font-weight: 600;
        position: sticky;
        left: 0;
        z-index: 1;
        cursor: pointer;
        user-select: none;
    }

    .cd-sheet-table .cd-row-num:hover,
    .cd-sheet-table .cd-col-header:hover {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .cd-sheet-table .cd-row-num.cd-axis-selected,
    .cd-sheet-table .cd-col-header.cd-axis-selected {
        background: #2563eb;
        color: #fff;
    }

    .cd-sheet-table tr.cd-row-selected td:not([style*="background-color"]) {
        background-color: #eff6ff;
    }

    .cd-sheet-table td.cd-col-selected:not([style*="background-color"]) {
        background-color: #fef3c7;
    }

    .cd-sheet-table tr.cd-row-selected td.cd-col-selected:not([style*="background-color"]) {
        background-color: #dbeafe;
    }

    .cd-sheet-table td.cd-cell-selected {
        outline: 2px solid #2563eb;
        outline-offset: -2px;
    }

    .cd-sheet-table .cd-col-header {
        cursor: pointer;
        user-select: none;
    }

    /* Drag & drop reordering of rows / columns */
    .cd-sheet-table .cd-row-num,
    .cd-sheet-table .cd-col-header {
        cursor: grab;
    }
    .cd-sheet-wrap.cd-dnd-active .cd-row-num,
    .cd-sheet-wrap.cd-dnd-active .cd-col-header {
        cursor: grabbing;
    }
    .cd-sheet-table .cd-dnd-target {
        outline: 2px dashed #2563eb;
        outline-offset: -2px;
        background: #bfdbfe !important;
        color: #1d4ed8 !important;
    }

    .cd-sheet-table .cd-sheet-cell {
        padding: 4px 6px;
        outline: none;
        white-space: pre-wrap;
        word-break: break-word;
        text-align: center;
        line-height: 1.3;
        min-height: 0;
    }

    .cd-sheet-table .cd-sheet-cell-empty {
        padding: 2px 4px;
    }

    .cd-sheet-table .cd-sheet-cell-image {
        padding: 2px;
        text-align: center;
        line-height: 0;
        overflow: hidden;
        max-width: 100%;
    }

    .cd-sheet-table td:has(img),
    .cd-sheet-table td:has(.cd-sheet-cell-image) {
        overflow: hidden;
    }

    .cd-sheet-table .cd-sheet-img-ph {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 4px;
        background: #e2e8f0;
        color: #64748b;
        font-size: 14px;
        line-height: 1;
    }

    .cd-sheet-table .cd-sheet-cell-link {
        padding: 2px 4px;
        text-align: center;
        line-height: 0;
    }

    .cd-sheet-table .comparison-clink-dot-present,
    .cd-sheet-table .cd-sheet-link-btn .comparison-clink-dot {
        background-color: #16a34a;
    }

    .cd-sheet-table .cd-sheet-link-btn:hover .comparison-clink-dot {
        background-color: #15803d;
    }

    .cd-sheet-table .comparison-clink-dot-missing {
        background-color: #dc2626;
        cursor: pointer;
    }

    .cd-sheet-table .cd-sheet-cell-link-missing {
        cursor: pointer;
    }

    .cd-sheet-table .cd-sheet-cell-company {
        padding: 2px 4px;
        text-align: center;
        line-height: 0;
    }

    .cd-sheet-table th.cd-priority-col,
    .cd-sheet-table td.cd-priority-col {
        min-width: 52px;
        max-width: 58px;
        width: 54px;
        padding: 2px;
    }

    .cd-sheet-table .cd-sheet-cell-priority {
        position: relative;
        padding: 2px;
        text-align: center;
        line-height: 1;
        min-height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cd-sheet-table .cd-priority-wrap {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        min-height: 24px;
    }

    .cd-sheet-table .cd-priority-dot {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.18);
    }

    .cd-sheet-table .cd-priority-dot-critical {
        background-color: #dc2626;
    }

    .cd-sheet-table .cd-priority-dot-important {
        background-color: #2563eb;
    }

    .cd-sheet-table .cd-priority-dot-normal {
        background-color: #eab308;
    }

    .cd-sheet-table th.cd-row-select-col,
    .cd-sheet-table td.cd-row-select-col {
        min-width: 36px;
        max-width: 36px;
        width: 36px;
        padding: 2px;
        text-align: center;
        background: #f8fafc;
        position: sticky;
        left: 42px;
        z-index: 1;
    }

    .cd-sheet-table th.cd-row-select-col {
        z-index: 3;
        top: 0;
    }

    .cd-sheet-table .cd-sheet-row-select,
    .cd-sheet-table #cd-sheet-select-all-rows {
        width: 0.95rem;
        height: 0.95rem;
        margin: 0;
        cursor: pointer;
        vertical-align: middle;
    }

    .cd-sheet-table th.cd-row-edit-col,
    .cd-sheet-table td.cd-row-edit-col {
        min-width: 42px;
        max-width: 42px;
        width: 42px;
        padding: 2px;
        text-align: center;
        background: #f8fafc;
        position: sticky;
        right: 0;
        z-index: 1;
    }

    .cd-sheet-table th.cd-row-edit-col {
        z-index: 3;
        top: 0;
    }

    .cd-sheet-table .cd-sheet-row-edit-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        padding: 0;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        color: #64748b;
        line-height: 1;
        cursor: pointer;
    }

    .cd-sheet-table .cd-sheet-row-edit-btn:hover,
    .cd-sheet-table .cd-sheet-row-edit-btn:focus {
        background: #e2e8f0;
        color: #1e293b;
        border-color: #94a3b8;
    }

    .cd-sheet-table .cd-sheet-row-edit-btn i {
        font-size: 15px;
        line-height: 1;
    }

    .cd-sheet-table .cd-col-header-inner {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        max-width: 100%;
    }

    .cd-sheet-table .cd-col-header-label {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .cd-sheet-table .cd-sheet-col-edit-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        padding: 0;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        background: #fff;
        color: #64748b;
        line-height: 1;
        cursor: pointer;
        flex-shrink: 0;
    }

    .cd-sheet-table .cd-sheet-col-edit-btn:hover,
    .cd-sheet-table .cd-sheet-col-edit-btn:focus {
        background: #e2e8f0;
        color: #1e293b;
        border-color: #94a3b8;
    }

    .cd-sheet-table .cd-sheet-col-edit-btn i {
        font-size: 13px;
        line-height: 1;
    }

    #comparisonColumnEditModal {
        z-index: 1080;
    }

    #comparisonColumnEditModal .modal-dialog {
        max-width: min(920px, 96vw);
    }

    #comparisonColumnEditModal .cd-col-edit-table {
        margin-bottom: 0;
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 0;
        --bs-table-bg: transparent;
        --bs-table-accent-bg: transparent;
    }

    #comparisonColumnEditModal .cd-col-edit-table thead,
    #comparisonColumnEditModal .cd-col-edit-table thead tr {
        background-color: #bfdbfe !important;
    }

    #comparisonColumnEditModal .cd-col-edit-table thead th {
        position: sticky;
        top: 0;
        z-index: 5;
        background-color: #bfdbfe !important;
        background-image: none !important;
        color: #1e3a8a !important;
        font-weight: 700;
        border-bottom: 1px solid #93c5fd !important;
        box-shadow: 0 1px 0 #93c5fd;
        --bs-table-bg: #bfdbfe;
        --bs-table-color: #1e3a8a;
    }

    #comparisonColumnEditModal .cd-col-edit-check {
        width: 42px;
        min-width: 42px;
        max-width: 42px;
        text-align: center;
        vertical-align: middle;
        padding-left: 0.35rem !important;
        padding-right: 0.35rem !important;
    }

    #comparisonColumnEditModal .cd-col-edit-check .form-check-input {
        margin: 0;
        cursor: pointer;
        width: 1rem;
        height: 1rem;
    }

    #comparisonColumnEditModal .cd-col-edit-label {
        width: 28%;
        min-width: 120px;
        max-width: 220px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #475569;
        vertical-align: middle;
        word-break: break-word;
    }

    #comparisonColumnEditModal .cd-col-edit-value {
        width: auto;
        min-width: 200px;
        vertical-align: middle;
    }

    #comparisonColumnEditModal .cd-col-edit-bulk-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        padding: 0.55rem 0.7rem;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }

    #comparisonColumnEditModal .cd-col-edit-bulk-bar .cd-col-edit-bulk-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        margin: 0;
        white-space: nowrap;
    }

    #comparisonColumnEditModal .cd-col-edit-bulk-bar .cd-col-edit-bulk-value {
        flex: 1 1 160px;
        min-width: 140px;
        max-width: 280px;
    }

    #comparisonColumnEditModal .cd-col-edit-bulk-count {
        font-size: 0.78rem;
        color: #64748b;
        margin-left: auto;
    }

    #comparisonColumnEditModal tr.cd-col-edit-row-selected {
        background: #eff6ff;
    }

    #comparisonColumnEditModal .cd-col-edit-input,
    #comparisonColumnEditModal .cd-col-edit-select {
        font-size: 0.85rem;
        min-width: 140px;
        width: 100%;
    }

    #comparisonColumnEditModal .cd-col-edit-hint {
        font-size: 0.75rem;
        color: #64748b;
    }

    #comparisonColumnEditModal .cd-col-edit-actions {
        width: 72px;
        text-align: center;
        white-space: nowrap;
    }

    #comparisonColumnEditModal .cd-col-edit-row-del-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 1px solid #fca5a5;
        border-radius: 6px;
        background: #fff;
        color: #dc2626;
        line-height: 1;
    }

    #comparisonColumnEditModal .cd-col-edit-row-del-btn:hover,
    #comparisonColumnEditModal .cd-col-edit-row-del-btn:focus {
        background: #fef2f2;
        border-color: #ef4444;
        color: #b91c1c;
    }

    #comparisonColumnEditModal .cd-col-edit-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .cd-field-clip-wrap {
        display: flex;
        align-items: stretch;
        gap: 4px;
        width: 100%;
        min-width: 180px;
    }

    .cd-field-clip-wrap .cd-col-edit-field,
    .cd-field-clip-wrap .form-select,
    .cd-field-clip-wrap .form-control {
        flex: 1 1 auto;
        min-width: 140px;
        width: auto;
    }

    .cd-field-clip-btns {
        display: inline-flex;
        align-items: center;
        gap: 2px;
        flex-shrink: 0;
    }

    .cd-field-clip-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        color: #64748b;
        line-height: 1;
        cursor: pointer;
    }

    .cd-field-clip-btn:hover,
    .cd-field-clip-btn:focus {
        background: #e2e8f0;
        color: #1e293b;
        border-color: #94a3b8;
    }

    .cd-field-clip-btn.cd-field-cut-btn:hover,
    .cd-field-clip-btn.cd-field-cut-btn:focus {
        background: #fff7ed;
        color: #c2410c;
        border-color: #fdba74;
    }

    .cd-field-clip-btn.cd-field-paste-btn:hover,
    .cd-field-clip-btn.cd-field-paste-btn:focus {
        background: #ecfdf5;
        color: #047857;
        border-color: #6ee7b7;
    }

    .cd-field-clip-btn i {
        font-size: 14px;
        line-height: 1;
    }

    .cd-sheet-table tr.cd-multi-selected > td:not([style*="background-color"]) {
        background-color: #ecfdf5;
    }

    .cd-sheet-table .cd-priority-menu {
        position: absolute;
        top: calc(100% + 4px);
        left: 50%;
        transform: translateX(-50%);
        z-index: 40;
        min-width: 118px;
        padding: 4px;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.14);
    }

    .cd-sheet-table .cd-priority-menu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 5px 8px;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: #334155;
        font-size: 0.75rem;
        font-weight: 600;
        text-align: left;
        cursor: pointer;
    }

    .cd-sheet-table .cd-priority-menu-item:hover,
    .cd-sheet-table .cd-priority-menu-item.is-active {
        background: #eff6ff;
    }

    .cd-sheet-table .cd-priority-header-label {
        font-size: 0.65rem;
        font-weight: 700;
        color: #64748b;
        letter-spacing: 0.01em;
    }

    .cd-priority-filter-dropdown .dropdown-menu {
        min-width: 170px;
        padding: 6px;
    }

    .cd-priority-filter-dropdown .cd-priority-filter-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding: 6px 8px;
        border-radius: 6px;
        color: #334155;
        font-size: 0.78rem;
        font-weight: 600;
        cursor: pointer;
        user-select: none;
    }

    .cd-priority-filter-dropdown .cd-priority-filter-item:hover {
        background: #f1f5f9;
    }

    .cd-priority-filter-dropdown .cd-priority-filter-item.is-checked {
        background: #eff6ff;
    }

    .cd-priority-filter-dropdown .cd-priority-filter-check {
        width: 14px;
        height: 14px;
        margin: 0;
        accent-color: #2563eb;
        cursor: pointer;
    }

    .cd-priority-filter-dropdown .cd-priority-dot {
        width: 10px;
        height: 10px;
    }

    .cd-priority-filter-dropdown .cd-priority-filter-summary {
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        margin-left: 4px;
    }

    .cd-priority-filter-dropdown .btn.active-filter {
        border-color: #93c5fd;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .cd-sheet-table tr.cd-inner-pkg-row > td {
        background-color: #dbeafe !important;
    }

    .cd-sheet-table tr.cd-ctn-pkg-row > td {
        background-color: #fffef2 !important;
    }

    .cd-sheet-table tr.cd-inner-pkg-row .cd-label-cell,
    .cd-sheet-table tr.cd-ctn-pkg-row .cd-label-cell {
        font-weight: 600;
    }

    .cd-sheet-table tr.cd-pkg-section-header .cd-label-cell {
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }

    #comparison-cd-qc-issues-btn.has-qc-data {
        border-color: #86efac;
        background: #f0fdf4;
        color: #166534;
    }

    #comparison-cd-qc-issues-btn.no-qc-data {
        border-color: #fca5a5;
        background: #fef2f2;
        color: #991b1b;
    }

    #comparison-cd-reviews-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-width: 0;
        padding: 4px 8px;
        line-height: 1.1;
        position: relative;
    }

    #comparison-cd-reviews-btn .cd-reviews-badge-inner {
        display: inline-flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    #comparison-cd-reviews-btn .cd-reviews-rating-line {
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    #comparison-cd-reviews-btn .cd-reviews-count-line {
        font-size: 0.72rem;
        font-weight: 500;
        color: #5c5c5c;
        white-space: nowrap;
    }

    #comparison-cd-reviews-btn.cd-reviews-hot {
        background: #fce7f3;
        border-color: #f9a8d4;
    }

    #comparison-cd-reviews-btn .cd-reviews-action-dots {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        margin-top: 0;
        padding-left: 4px;
        border-left: 1px solid #e2e8f0;
    }

    #comparison-cd-reviews-btn .cd-reviews-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        cursor: pointer;
        border: 1px solid rgba(0, 0, 0, 0.15);
        padding: 0;
        flex-shrink: 0;
    }

    #comparison-cd-reviews-btn .cd-reviews-dot:hover {
        transform: scale(1.25);
    }

    #comparison-cd-reviews-btn .cd-reviews-dot-graph {
        background: #e83e8c;
    }

    #comparison-cd-reviews-btn .cd-reviews-dot-intel {
        background: #0d6efd;
    }

    #comparison-cd-reviews-btn .cd-reviews-dot-amazon {
        background: #ff9900;
    }

    #comparison-cd-reviews-btn .cd-reviews-dot.is-disabled {
        opacity: 0.35;
        cursor: not-allowed;
        transform: none;
    }

    #comparison-cd-siblings-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        cursor: pointer;
        user-select: none;
        border-color: #ced4da;
        background: #fff;
        color: #495057;
    }

    #comparison-cd-siblings-badge .form-check-input {
        margin: 0;
        cursor: pointer;
        width: 0.95rem;
        height: 0.95rem;
    }

    #comparison-cd-siblings-badge.is-active {
        background: #dcfce7;
        border-color: #86efac;
        color: #166534;
        font-weight: 600;
    }

    #comparison-cd-siblings-badge.is-active .form-check-input {
        background-color: #16a34a;
        border-color: #15803d;
    }

    #comparison-cd-siblings-badge.is-disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    #comparison-cd-siblings-count {
        font-size: 0.68rem;
        font-weight: 600;
    }

    #comparison-cd-supplier-count {
        font-size: 0.68rem;
        font-weight: 700;
        min-width: 1.35rem;
        vertical-align: middle;
    }

    #comparison-cd-autopopulate-suppliers-btn #comparison-cd-supplier-count.has-suppliers {
        background: #0d6efd !important;
        color: #fff !important;
    }

    #comparisonQcIssuesModal .cd-qc-issues-table thead th {
        background: #b2ebf2;
        font-size: 0.72rem;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    #comparisonQcIssuesModal .cd-qc-issues-table td {
        text-align: center;
        vertical-align: middle;
        min-width: 70px;
    }

    #comparisonQcIssuesModal .cd-qc-search-icon {
        font-size: 14px;
        cursor: help;
    }

    #comparisonQcIssuesModal .cd-qc-issue-thumb {
        width: 36px;
        height: 36px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #cbd5e1;
        cursor: pointer;
    }

    #comparisonQcIssuesModal .cd-qc-media-btn {
        min-width: 34px;
    }

    .cd-sheet-table .cd-sheet-comm-cell {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        background: #b2ebf2;
        padding: 4px;
    }

    .cd-sheet-table .cd-sheet-comm-btn {
        border: 0;
        background: transparent;
        color: #111827;
        font-size: 18px;
        line-height: 1;
        padding: 2px 6px;
        cursor: pointer;
        border-radius: 4px;
    }

    .cd-sheet-table .cd-sheet-comm-btn:hover {
        background: rgba(255, 255, 255, 0.45);
        transform: scale(1.05);
    }

    .cd-sheet-table tr.cd-comm-row td:not(.cd-label-cell):not(.cd-row-num):not(.cd-row-select-col):not(.cd-row-edit-col) {
        background: #e0f7fa;
    }

    .comparison-comm-plat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }

    .comparison-comm-plat-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        text-align: center;
        text-decoration: none;
        color: inherit;
        transition: box-shadow 0.15s, transform 0.15s;
    }

    .comparison-comm-plat-card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        transform: translateY(-1px);
        color: inherit;
    }

    .comparison-comm-plat-card i {
        font-size: 1.35rem;
        display: block;
        margin-bottom: 6px;
    }

    .cd-sheet-table .cd-sheet-link-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 2px 6px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: transparent;
        text-decoration: none;
        line-height: 0;
    }

    .cd-sheet-table .cd-sheet-img,
    .cd-sheet-table td img,
    .cd-sheet-table .cd-sheet-cell img {
        max-width: 120px !important;
        max-height: 80px !important;
        width: auto !important;
        height: auto !important;
        object-fit: contain !important;
        display: block;
        margin: 0 auto;
        pointer-events: none;
        vertical-align: middle;
    }

    .cd-sheet-table .cd-sheet-cell:focus {
        box-shadow: inset 0 0 0 2px #2563eb;
        background: #eff6ff;
    }

    .cd-sheet-table .cd-label-cell:not([style*="background-color"]) {
        background: #fed7aa;
        color: #111827;
        font-weight: 700;
        text-align: center;
    }

    /* Profit Calculator modal — full-width end-to-end (comparison page only) */
    #comparisonRoiModal.modal {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    #comparisonRoiModal .modal-dialog.comparison-roi-modal-dialog {
        max-width: 100vw;
        width: 100vw;
        height: auto;
        max-height: none;
        margin: 0;
        margin-top: 0;
        align-items: flex-start;
    }

    #comparisonRoiModal.modal.show .modal-dialog {
        transform: none;
    }

    #comparisonRoiModal .modal-content {
        border: none;
        border-radius: 0;
        box-shadow: none;
        overflow: visible;
        height: auto;
        min-height: 0;
        max-height: none;
    }

    #comparisonRoiModal .modal-header {
        background: #e8f1fb;
        border-bottom: 1px solid #b6c9e0;
        padding: 0.9rem 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    #comparisonRoiModal .modal-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: #1e293b;
        margin-right: auto;
    }

    #comparisonRoiModal .comparison-roi-header-image-wrap {
        display: inline-flex;
        align-items: center;
        margin-right: 0.55rem;
        vertical-align: middle;
        flex-shrink: 0;
    }

    #comparisonRoiModal .comparison-roi-header-image {
        width: 44px;
        height: 44px;
        object-fit: contain;
        border-radius: 6px;
        border: 1px solid #94a3b8;
        background: #fff;
        cursor: zoom-in;
        vertical-align: middle;
    }

    #comparison-roi-amz-reviews-slot {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        margin-right: 0.35rem;
        min-height: 2.1rem;
    }

    #comparison-roi-amz-reviews-slot .comparison-roi-amz-reviews-badge {
        transform: scale(1.5);
        transform-origin: center right;
        border-radius: 999px;
        padding: 3px 8px;
    }

    #comparisonRoiModal .modal-body {
        padding: 0.85rem 1.25rem 1rem !important;
        height: auto;
        max-height: none;
        overflow: visible;
    }

    .comparison-roi-table thead th {
        background: #e8f1fb;
        font-size: 14px;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
        padding: 0.55rem 0.65rem;
    }

    .comparison-roi-table tbody td {
        font-size: 14px;
        text-align: center;
        vertical-align: middle;
        padding: 0.45rem 0.55rem;
    }

    .comparison-roi-table .comparison-roi-channel {
        font-weight: 600;
        background: #fff;
        text-align: left;
        font-size: 15px;
        white-space: nowrap;
    }

    .comparison-roi-table .comparison-roi-channel-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: nowrap;
    }

    .comparison-roi-amz-reviews-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 2px 6px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        background: #fff;
        line-height: 1.1;
        cursor: default;
        vertical-align: middle;
    }

    .comparison-roi-amz-reviews-badge.cd-reviews-hot {
        background: #fce7f3;
        border-color: #f9a8d4;
    }

    .comparison-roi-amz-reviews-badge .cd-reviews-badge-inner {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        font-size: 0.72rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .comparison-roi-amz-reviews-badge .cd-reviews-rating-line {
        display: inline-flex;
        align-items: center;
        gap: 2px;
    }

    .comparison-roi-amz-reviews-badge .cd-reviews-count-line {
        font-size: 0.68rem;
        font-weight: 500;
        color: #5c5c5c;
        white-space: nowrap;
    }

    .comparison-roi-amz-reviews-badge .cd-reviews-action-dots {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding-left: 4px;
        border-left: 1px solid #e2e8f0;
    }

    .comparison-roi-amz-reviews-badge .cd-reviews-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        display: inline-block;
        cursor: pointer;
        border: 1px solid rgba(0, 0, 0, 0.15);
        flex-shrink: 0;
    }

    .comparison-roi-amz-reviews-badge .cd-reviews-dot:hover {
        transform: scale(1.25);
    }

    .comparison-roi-amz-reviews-badge .cd-reviews-dot-graph { background: #e83e8c; }
    .comparison-roi-amz-reviews-badge .cd-reviews-dot-intel { background: #0d6efd; }
    .comparison-roi-amz-reviews-badge .cd-reviews-dot-amazon { background: #ff9900; }
    .comparison-roi-amz-reviews-badge .cd-reviews-dot.is-disabled {
        opacity: 0.35;
        cursor: not-allowed;
        transform: none;
    }

    .comparison-roi-table .comparison-roi-input-cell {
        background: #fdba74;
        padding: 3px;
    }

    .comparison-roi-table .comparison-roi-input-cell input {
        width: 100%;
        min-width: 72px;
        border: 1px solid #f97316;
        border-radius: 4px;
        padding: 4px 6px;
        font-size: 14px;
        text-align: center;
        background: #fff7ed;
    }

    /* CP + CBM + GW LB + Shipping — half width */
    .comparison-roi-table th:nth-child(2),
    .comparison-roi-table th:nth-child(3),
    .comparison-roi-table th:nth-child(5),
    .comparison-roi-table th:nth-child(6),
    .comparison-roi-table td:nth-child(2),
    .comparison-roi-table td:nth-child(3),
    .comparison-roi-table td:nth-child(5),
    .comparison-roi-table td:nth-child(6) {
        width: 48px;
        max-width: 52px;
        padding-left: 2px;
        padding-right: 2px;
    }

    .comparison-roi-table .comparison-roi-input-cell input[data-field="cp"],
    .comparison-roi-table .comparison-roi-input-cell input[data-field="cbm"],
    .comparison-roi-table .comparison-roi-input-cell input[data-field="gw"],
    .comparison-roi-table .comparison-roi-input-cell input[data-field="shipping"] {
        min-width: 36px;
        width: 100%;
        max-width: 44px;
        padding: 4px 2px;
        margin: 0 auto;
        display: block;
    }

    .comparison-roi-table .comparison-roi-input-cell input.comparison-roi-input-readonly {
        border-color: #cbd5e1;
        background: #eef2f7;
        color: #475569;
        cursor: not-allowed;
    }

    .comparison-roi-table .comparison-roi-calc-cell {
        background: #e5e7eb;
        font-weight: 600;
        font-size: 14px;
    }

    .comparison-roi-table .comparison-roi-calc-cell.comparison-roi-tier-green {
        background: #86efac;
    }

    .comparison-roi-table .comparison-roi-calc-cell.comparison-roi-tier-red {
        background: #fca5a5;
    }

    .comparison-roi-table .comparison-roi-calc-cell.comparison-roi-tier-magenta {
        background: #f0abfc;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="roi"].comparison-roi-tier-green {
        background: #4ade80;
        color: #14532d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="roi"].comparison-roi-tier-red {
        background: #f87171;
        color: #7f1d1d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="roi"].comparison-roi-tier-magenta {
        background: #e879f9;
        color: #701a75;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="pPct"].comparison-roi-tier-green {
        background: #4ade80;
        color: #14532d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="pPct"].comparison-roi-tier-red {
        background: #f87171;
        color: #7f1d1d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="pPct"].comparison-roi-tier-magenta {
        background: #e879f9;
        color: #701a75;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="npft"].comparison-roi-tier-green,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="nroi"].comparison-roi-tier-green,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteGpft"].comparison-roi-tier-green,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteGroi"].comparison-roi-tier-green,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteNroi"].comparison-roi-tier-green,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteNpft"].comparison-roi-tier-green {
        background: #4ade80;
        color: #14532d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="npft"].comparison-roi-tier-red,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="nroi"].comparison-roi-tier-red,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteGpft"].comparison-roi-tier-red,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteGroi"].comparison-roi-tier-red,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteNroi"].comparison-roi-tier-red,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteNpft"].comparison-roi-tier-red {
        background: #f87171;
        color: #7f1d1d;
    }

    .comparison-roi-table .comparison-roi-calc-cell[data-calc="npft"].comparison-roi-tier-magenta,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="nroi"].comparison-roi-tier-magenta,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteGpft"].comparison-roi-tier-magenta,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteGroi"].comparison-roi-tier-magenta,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteNroi"].comparison-roi-tier-magenta,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="siteNpft"].comparison-roi-tier-magenta {
        background: #e879f9;
        color: #701a75;
    }

    .comparison-roi-table .comparison-roi-lmp-header-btn,
    .comparison-roi-table .comparison-roi-lmp-link {
        font-size: 14px;
        font-weight: 600;
        text-decoration: underline;
        color: #1d4ed8;
        vertical-align: baseline;
    }

    .comparison-roi-table .comparison-roi-lmp-header-btn:hover,
    .comparison-roi-table .comparison-roi-lmp-link:hover {
        color: #1e3a8a;
    }

    .comparison-roi-table .comparison-roi-lmp-cell {
        background: #fff;
        font-weight: 600;
    }

    .comparison-roi-table .comparison-roi-lmp-add-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        padding: 0;
        border: 1.5px solid #16a34a;
        border-radius: 6px;
        background: #ecfdf5;
        color: #15803d;
        font-size: 18px;
        font-weight: 700;
        line-height: 1;
        cursor: pointer;
    }

    .comparison-roi-table .comparison-roi-lmp-add-btn:hover {
        background: #16a34a;
        color: #fff;
    }

    .comparison-roi-table tr.comparison-roi-overall-row td.comparison-roi-channel {
        background: #dbeafe;
        font-weight: 700;
    }

    .comparison-roi-table tr.comparison-roi-overall-row .comparison-roi-lmp-cell {
        background: #eff6ff;
    }

    .comparison-roi-table th.comparison-roi-proposed-metrics-th,
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="profit"],
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="roi"],
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="pPct"],
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="nroi"],
    .comparison-roi-table .comparison-roi-calc-cell[data-calc="npft"] {
        background: #fef9c3;
    }

    .comparison-roi-table th.comparison-roi-proposed-metrics-th {
        background: #fdba74 !important;
        color: #9a3412;
    }

    .comparison-roi-table tr.comparison-roi-overall-row .comparison-roi-calc-cell[data-calc="profit"],
    .comparison-roi-table tr.comparison-roi-overall-row .comparison-roi-calc-cell[data-calc="roi"],
    .comparison-roi-table tr.comparison-roi-overall-row .comparison-roi-calc-cell[data-calc="pPct"],
    .comparison-roi-table tr.comparison-roi-overall-row .comparison-roi-calc-cell[data-calc="nroi"],
    .comparison-roi-table tr.comparison-roi-overall-row .comparison-roi-calc-cell[data-calc="npft"] {
        background: #fef08a;
    }

    .comparison-roi-table th.comparison-roi-c-price-th,
    .comparison-roi-table th.comparison-roi-site-metrics-th {
        background: #86efac !important;
        color: #14532d;
    }

    .comparison-roi-table .comparison-roi-price-after-lmp-cell {
        background: #86efac;
        font-weight: 600;
        color: #14532d;
        min-width: 72px;
        white-space: nowrap;
    }

    .comparison-roi-table tr.comparison-roi-overall-row .comparison-roi-price-after-lmp-cell {
        background: #4ade80;
    }

    .comparison-roi-table .comparison-roi-price-history-dot,
    .comparison-roi-table .comparison-roi-metric-history-dot {
        cursor: pointer;
        font-size: 9px;
        vertical-align: middle;
        margin-left: 5px;
    }

    .comparison-roi-table .comparison-roi-price-history-dot {
        color: #e83e8c;
    }

    .comparison-roi-table .comparison-roi-price-history-dot:hover {
        color: #c2185b;
        transform: scale(1.25);
    }

    /* Comparison page only: Price history chart as bottom modal dialog */
    #comparisonRoiPriceChartModal.modal {
        padding-right: 0 !important;
    }

    #comparisonRoiPriceChartModal .comparison-roi-price-chart-bottom-dialog.modal-bottom {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        top: auto;
        margin: 0 auto;
        width: min(960px, 100%);
        max-width: 100%;
        height: auto;
        max-height: 78vh;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        transform: translateY(100%);
        transition: transform 0.3s ease-out;
    }

    #comparisonRoiPriceChartModal.show .comparison-roi-price-chart-bottom-dialog.modal-bottom {
        transform: translateY(0);
    }

    #comparisonRoiPriceChartModal .comparison-roi-price-chart-bottom-dialog .modal-content {
        border-radius: 16px 16px 0 0;
        max-height: 78vh;
        overflow: hidden;
        box-shadow: 0 -8px 28px rgba(15, 23, 42, 0.22);
    }

    #comparisonRoiPriceChartModal .comparison-roi-price-chart-bottom-dialog .modal-body {
        overflow-y: auto;
        max-height: calc(78vh - 58px);
    }

    .comparison-roi-table .comparison-roi-npft-cell,
    .comparison-roi-table .comparison-roi-nroi-cell {
        font-weight: 600;
        white-space: nowrap;
        min-width: 64px;
    }

    .comparison-roi-table .comparison-roi-metric-history-dot[data-metric="npft"] {
        color: #28a745;
    }

    .comparison-roi-table .comparison-roi-metric-history-dot[data-metric="npft"]:hover {
        color: #1e7e34;
    }

    .comparison-roi-table .comparison-roi-metric-history-dot[data-metric="nroi"] {
        color: #17a2b8;
    }

    .comparison-roi-table .comparison-roi-metric-history-dot[data-metric="nroi"]:hover {
        color: #117a8b;
    }

    #comparisonRoiModal.comparison-roi-modal-stacked {
        z-index: 1075;
    }

    /* Comparison page only: Competitors LMP opens as a bottom modal dialog */
    #comparisonLmpModal.modal {
        padding-right: 0 !important;
    }

    /* Comparison page only: SP box centered in LMP modal header */
    #comparisonLmpModal .modal-header {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    #comparisonLmpModal .modal-title {
        flex: 1 1 auto;
        min-width: 0;
        padding-right: 8.5rem;
    }

    #comparisonLmpModal .lmp-modal-sp-header-wrap {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        z-index: 2;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.35);
        border-radius: 8px;
        padding: 4px 10px;
    }

    #comparisonLmpModal .lmp-modal-sp-header-label {
        color: #fff;
        font-size: 0.85rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    #comparisonLmpModal .lmp-modal-sp-header-wrap .lmp-modal-sp-input {
        width: 5.5rem;
        background: #fff;
        border: 1px solid #cbd5e1;
        color: #0f172a;
    }

    #comparisonLmpModal .comparison-lmp-bottom-dialog.modal-bottom {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        top: auto;
        margin: 0 auto;
        width: min(1200px, 100%);
        max-width: 100%;
        height: auto;
        max-height: 88vh;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        transform: translateY(100%);
        transition: transform 0.3s ease-out;
    }

    #comparisonLmpModal.show .comparison-lmp-bottom-dialog.modal-bottom {
        transform: translateY(0);
    }

    #comparisonLmpModal .comparison-lmp-bottom-dialog .modal-content {
        border-radius: 16px 16px 0 0;
        max-height: 88vh;
        overflow: hidden;
        box-shadow: 0 -8px 28px rgba(15, 23, 42, 0.22);
    }

    #comparisonLmpModal .comparison-lmp-bottom-dialog .modal-body {
        overflow-y: auto;
        max-height: calc(88vh - 58px);
    }

    .cd-sheet-table .cd-label-cell:focus {
        box-shadow: inset 0 0 0 2px #2563eb;
    }

    .cd-sheet-layout-menu .dropdown-header {
        font-size: 11px;
        padding: 4px 12px 2px;
    }

    .cd-sheet-layout-menu .dropdown-item {
        font-size: 12px;
        padding: 4px 12px;
    }

    .cd-sheet-status {
        font-size: 12px;
        color: #6c757d;
    }
</style>
@endsection

@section('content')
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm" id="comparison-main-card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'comparison'])
                        <h4 class="mb-0">
                            <i class="fas fa-balance-scale"></i> Purchase Comparison
                        </h4>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
                        <div class="btn-group comparison-playback-group" role="group" aria-label="Parent navigation">
                            <button id="comparison-play-backward" type="button" class="btn btn-light rounded-circle" title="Previous parent">
                                <i class="mdi mdi-skip-previous"></i>
                            </button>
                            <button id="comparison-play-pause" type="button" class="btn btn-light rounded-circle" title="Show all products" style="display: none;">
                                <i class="mdi mdi-pause"></i>
                            </button>
                            <button id="comparison-play-auto" type="button" class="btn btn-light rounded-circle" title="Start parent navigation">
                                <i class="mdi mdi-play"></i>
                            </button>
                            <button id="comparison-play-forward" type="button" class="btn btn-light rounded-circle" title="Next parent">
                                <i class="mdi mdi-skip-next"></i>
                            </button>
                        </div>
                        <span id="comparison-playback-label" class="text-muted small fw-semibold d-none"></span>
                        <input type="text" id="comparison-search-parent" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search Parent...">
                        <input type="text" id="comparison-search-sku" class="form-control form-control-sm" style="max-width: 220px;" placeholder="Search SKU...">
                        <span id="comparison-selected-badge" class="badge bg-primary ms-auto d-none" style="font-size:0.8rem;">
                            <i class="mdi mdi-checkbox-marked-outline me-1"></i><span id="comparison-selected-count">0</span> selected
                        </span>
                    </div>
                    <div id="comparison-table"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonLinkedSkuModal" tabindex="-1" aria-labelledby="comparisonLinkedSkuModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comparisonLinkedSkuModalLabel">Link Sku Purchase</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Link another SKU to <strong id="comparison-linked-sku-source"></strong>. Both SKUs will show each other.</p>
                <label for="comparison-linked-sku-input" class="form-label mb-1">SKU to link</label>
                <input type="text" id="comparison-linked-sku-input" class="form-control" placeholder="Search or enter SKU..." autocomplete="off">
                <div id="comparison-linked-sku-suggestions" class="list-group mt-2 d-none" style="max-height: 180px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="comparison-linked-sku-save-btn">
                    <i class="mdi mdi-link"></i> Link SKU
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonCategoryPickerModal" tabindex="-1" aria-labelledby="comparisonCategoryPickerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comparisonCategoryPickerModalLabel">Choose Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Set the category for <strong id="comparison-category-picker-sku"></strong>. Click a category to apply it.</p>
                <input type="text" id="comparison-category-picker-search" class="form-control mb-2" placeholder="Search categories..." autocomplete="off">
                <div id="comparison-category-picker-list" class="list-group" style="max-height: 340px; overflow-y: auto;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonCommModal" tabindex="-1" aria-labelledby="comparisonCommModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="comparisonCommModalLabel">
                    <i class="fas fa-comments"></i> Supplier Communication
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1"><strong id="comparison-comm-supplier-name"></strong></p>
                <p class="text-muted small mb-3" id="comparison-comm-supplier-company"></p>
                <div id="comparison-comm-platforms" class="comparison-comm-plat-grid"></div>
                <p class="text-muted small mb-0 d-none" id="comparison-comm-empty">No communication details on file for this supplier.</p>
            </div>
        </div>
    </div>
</div>

<div id="cd-hover-preview"></div>
<div id="comparison-cd-image-hover-preview" aria-hidden="true"></div>

<div class="modal fade" id="comparisonCdModal" tabindex="-1" aria-labelledby="comparisonCdModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-lg-down modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center gap-2" id="comparisonCdModalLabel">
                    <a href="{{ route('comparison.index') }}" class="btn btn-sm btn-light d-none" id="comparison-cd-back-btn" title="Back to comparison list">
                        <i class="mdi mdi-arrow-left"></i> Back
                    </a>
                    <span><i class="fas fa-balance-scale"></i> Comparison Data</span>
                    <span class="badge bg-light text-dark ms-2" id="comparison-cd-modal-sku-badge"></span>
                    <span class="comparison-cd-header-image-wrap d-none" id="comparison-cd-modal-image-wrap">
                        <img id="comparison-cd-modal-image" class="comparison-cd-header-image" src="" alt="SKU image" title="Hover to enlarge">
                        <button type="button" class="btn btn-sm btn-outline-light comparison-cd-image-refresh-btn"
                            id="comparison-cd-image-refresh-btn" title="Refresh SKU image" aria-label="Refresh SKU image">
                            <i class="mdi mdi-refresh"></i>
                        </button>
                    </span>
                    <span class="visually-hidden" id="comparison-cd-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="comparison-cd-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="cd-sheet-tab-btn" data-bs-toggle="tab" data-bs-target="#cd-sheet-tab-pane" type="button" role="tab">
                            <i class="fas fa-table"></i> Comparison Data
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="cd-lmp-tab-btn" data-bs-toggle="tab" data-bs-target="#cd-lmp-tab-pane" type="button" role="tab">
                            <i class="fas fa-shopping-cart"></i> LMP Competitors
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="cd-sheet-tab-pane" role="tabpanel">
                        <div class="cd-sheet-toolbar mb-3">
                            {{-- Line 1: sheet source + primary actions --}}
                            <div class="cd-sheet-toolbar-row" aria-label="Sheet source and primary actions">
                                <div class="cd-sheet-toolbar-group" title="C link Sheet URL">
                                    <div class="comparison-cd-clink-url-wrap" id="comparison-cd-google-url-wrap">
                                        <a id="comparison-cd-google-url-link" href="#" target="_blank" rel="noopener noreferrer"
                                            class="comparison-clink-dot-link comparison-clink-dot-empty"
                                            title="C link Sheet URL">
                                            <span class="comparison-clink-dot comparison-clink-dot-muted" id="comparison-cd-google-url-dot" aria-hidden="true"></span>
                                        </a>
                                        <input type="url" id="comparison-cd-google-url" class="form-control form-control-sm comparison-cd-clink-url-input"
                                            placeholder="Google Sheet URL" autocomplete="off" spellcheck="false">
                                        <button type="button" class="btn btn-sm btn-outline-secondary comparison-cd-clink-url-edit-btn"
                                            id="comparison-cd-google-url-edit-btn" title="Edit C link Sheet URL" aria-label="Edit C link Sheet URL">
                                            <i class="mdi mdi-pencil-outline"></i>
                                        </button>
                                    </div>
                                    <input type="text" id="comparison-cd-google-tab" class="form-control form-control-sm cd-sheet-tab-input"
                                        value="Sheet1" title="Tab name" placeholder="Tab" aria-label="Tab name">
                                </div>
                                <button type="button" class="btn btn-sm btn-success" id="comparison-cd-import-btn" title="Pull data from Google Sheet once (import only — does not push local edits back)">
                                    <i class="fab fa-google"></i> C Link Refresh
                                </button>
                                <span class="cd-sheet-toolbar-divider" aria-hidden="true"></span>
                                <button type="button" class="btn btn-sm btn-info text-white" id="comparison-cd-autopopulate-suppliers-btn" title="Add suppliers into blank columns from column D; update C-link preloaded names when they match supplier.list for this category">
                                    <i class="mdi mdi-account-multiple-plus"></i> Suppliers
                                    <span class="badge rounded-pill bg-light text-dark ms-1" id="comparison-cd-supplier-count">0</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-warning text-dark" id="comparison-cd-roi-btn" title="Open Profit Calculator">
                                    <i class="mdi mdi-percent"></i> Profit Calculator
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="comparison-cd-copy-specs-btn" title="Copy Spec column labels to memory and clipboard">
                                    <i class="mdi mdi-content-copy"></i> Copy Specs
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="comparison-cd-replace-specs-btn" title="Replace Spec column with the saved template from memory">
                                    <i class="mdi mdi-clipboard-arrow-down"></i> Replace Specs
                                </button>
                            </div>

                            {{-- Line 2: filters, insights, layout & fill --}}
                            <div class="cd-sheet-toolbar-row" aria-label="Filters, insights, and formatting">
                                <div class="dropdown cd-priority-filter-dropdown" id="comparison-cd-critical-filters">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                        id="comparison-cd-critical-filter-btn"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        aria-expanded="false" title="Filter rows by Critical (PUR)">
                                        <i class="mdi mdi-filter-outline"></i> Critical
                                        <span class="cd-priority-filter-summary" data-filter-summary="critical">All</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-sm" aria-labelledby="comparison-cd-critical-filter-btn">
                                        <label class="cd-priority-filter-item is-checked">
                                            <input type="checkbox" class="cd-priority-filter-check" data-filter-col="critical" value="Critical" checked>
                                            <span class="cd-priority-dot cd-priority-dot-critical" aria-hidden="true"></span>
                                            Critical
                                        </label>
                                        <label class="cd-priority-filter-item is-checked">
                                            <input type="checkbox" class="cd-priority-filter-check" data-filter-col="critical" value="Important" checked>
                                            <span class="cd-priority-dot cd-priority-dot-important" aria-hidden="true"></span>
                                            Important
                                        </label>
                                        <label class="cd-priority-filter-item is-checked">
                                            <input type="checkbox" class="cd-priority-filter-check" data-filter-col="critical" value="Normal" checked>
                                            <span class="cd-priority-dot cd-priority-dot-normal" aria-hidden="true"></span>
                                            Normal
                                        </label>
                                    </div>
                                </div>
                                <div class="dropdown cd-priority-filter-dropdown" id="comparison-cd-qc-filters">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                        id="comparison-cd-qc-filter-btn"
                                        data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                        aria-expanded="false" title="Filter rows by QC">
                                        <i class="mdi mdi-filter-outline"></i> QC
                                        <span class="cd-priority-filter-summary" data-filter-summary="qc">All</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-sm" aria-labelledby="comparison-cd-qc-filter-btn">
                                        <label class="cd-priority-filter-item is-checked">
                                            <input type="checkbox" class="cd-priority-filter-check" data-filter-col="qc" value="Critical" checked>
                                            <span class="cd-priority-dot cd-priority-dot-critical" aria-hidden="true"></span>
                                            Critical
                                        </label>
                                        <label class="cd-priority-filter-item is-checked">
                                            <input type="checkbox" class="cd-priority-filter-check" data-filter-col="qc" value="Important" checked>
                                            <span class="cd-priority-dot cd-priority-dot-important" aria-hidden="true"></span>
                                            Important
                                        </label>
                                        <label class="cd-priority-filter-item is-checked">
                                            <input type="checkbox" class="cd-priority-filter-check" data-filter-col="qc" value="Normal" checked>
                                            <span class="cd-priority-dot cd-priority-dot-normal" aria-hidden="true"></span>
                                            Normal
                                        </label>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary no-qc-data" id="comparison-cd-qc-issues-btn" title="View QC Masters issues for this SKU">
                                    <i class="fas fa-search me-1"></i> QC Issues
                                    <span class="badge rounded-pill bg-secondary ms-1" id="comparison-cd-qc-issues-dot" aria-hidden="true">•</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="comparison-cd-reviews-btn" title="Rating & reviews from Forecast Analysis (Jungle Scout)">
                                    <span id="comparison-cd-reviews-badge-inner" class="cd-reviews-badge-inner">
                                        <i class="bi bi-star me-1"></i> Reviews
                                    </span>
                                    <span class="cd-reviews-action-dots" id="comparison-cd-reviews-dots">
                                        <span class="cd-reviews-dot cd-reviews-dot-graph is-disabled" data-reviews-action="graph" title="Lifetime rating graph" role="button" tabindex="0" aria-label="Lifetime rating graph"></span>
                                        <span class="cd-reviews-dot cd-reviews-dot-intel is-disabled" data-reviews-action="intel" title="Review Intelligence (parent)" role="button" tabindex="0" aria-label="Open Review Intelligence"></span>
                                        <span class="cd-reviews-dot cd-reviews-dot-amazon is-disabled" data-reviews-action="amazon" title="Amz buyer reviews" role="button" tabindex="0" aria-label="Open Amz reviews"></span>
                                    </span>
                                </button>
                                <label class="btn btn-sm btn-outline-secondary" id="comparison-cd-siblings-badge" title="Sync this sheet to all sibling SKUs under the same parent">
                                    <input type="checkbox" class="form-check-input" id="comparison-cd-siblings-sync" autocomplete="off">
                                    <span>Siblings</span>
                                    <span class="badge rounded-pill bg-secondary" id="comparison-cd-siblings-count">0</span>
                                </label>
                                <span class="cd-sheet-toolbar-divider" aria-hidden="true"></span>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" id="comparison-cd-layout-menu-btn"
                                        data-bs-toggle="dropdown" aria-expanded="false" title="Move, insert, or delete rows and columns">
                                        <i class="mdi mdi-table-edit"></i> Layout
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-sm cd-sheet-layout-menu" aria-labelledby="comparison-cd-layout-menu-btn">
                                        <li><h6 class="dropdown-header">Row</h6></li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-row-up-btn">
                                                <i class="mdi mdi-arrow-up"></i> Move up
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-row-down-btn">
                                                <i class="mdi mdi-arrow-down"></i> Move down
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-insert-row-btn">
                                                <i class="mdi mdi-table-row-plus-after"></i> Insert row
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" id="comparison-cd-delete-row-btn">
                                                <i class="mdi mdi-table-row-remove"></i> Delete row
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><h6 class="dropdown-header">Column</h6></li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-col-left-btn">
                                                <i class="mdi mdi-arrow-left"></i> Move left
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-move-col-right-btn">
                                                <i class="mdi mdi-arrow-right"></i> Move right
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item" id="comparison-cd-insert-col-btn">
                                                <i class="mdi mdi-table-column-plus-after"></i> Insert column
                                            </button>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" id="comparison-cd-delete-col-btn">
                                                <i class="mdi mdi-table-column-remove"></i> Delete column
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="cd-sheet-status d-none" id="comparison-cd-sheet-status" aria-hidden="true"></div>
                        </div>
                        <div id="comparison-cd-sheet-loading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 mb-0">Loading comparison sheet...</p>
                        </div>
                        <div class="cd-sheet-wrap" id="comparison-cd-sheet-wrap">
                            <table class="cd-sheet-table" id="comparison-cd-sheet-table">
                                <thead id="comparison-cd-sheet-head"></thead>
                                <tbody id="comparison-cd-sheet-body"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cd-lmp-tab-pane" role="tabpanel">
                        <div id="comparison-cd-clink-wrap" class="mb-3 d-none">
                            <div class="fw-semibold mb-1">Comparison Link</div>
                            <a id="comparison-cd-clink-link" href="#" target="_blank" rel="noopener noreferrer" class="comparison-clink-dot-link">
                                <span class="comparison-clink-dot" aria-hidden="true"></span> Open link
                            </a>
                            <div id="comparison-cd-clink-text" class="small text-muted mt-1 text-break"></div>
                        </div>
                        <div id="comparison-cd-lmp-wrap">
                            <div class="card mb-3 border-success">
                                <div class="card-header bg-success text-white">
                                    <strong><i class="fa fa-plus-circle"></i> Add New Competitor</strong>
                                </div>
                                <div class="card-body">
                                    <form id="comparison-cd-lmp-add-form" class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label"><strong>SKU</strong></label>
                                            <input type="text" class="form-control" id="comparison-cd-add-comp-sku" readonly>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="comparison-cd-add-comp-asin" placeholder="B07ABC123" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" id="comparison-cd-add-comp-price" placeholder="29.99" step="0.01" min="0.01" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label"><strong>Product Link</strong></label>
                                            <input type="url" class="form-control" id="comparison-cd-add-comp-link" placeholder="https://amazon.com/dp/...">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label"><strong>Marketplace</strong></label>
                                            <select class="form-select" id="comparison-cd-add-comp-marketplace">
                                                <option value="amazon" selected>Amz</option>
                                                <option value="US">US</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fa fa-plus"></i> Add Competitor
                                            </button>
                                            <button type="reset" class="btn btn-secondary">
                                                <i class="fa fa-undo"></i> Clear
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div id="comparison-cd-lmp-list">
                                <div class="text-center py-4 text-muted">Open this tab to load LMP competitors.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonLmpModal" tabindex="-1" aria-labelledby="comparisonLmpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-bottom modal-dialog-scrollable comparison-lmp-bottom-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="comparisonLmpModalLabel">
                    <i class="fa fa-shopping-cart"></i> Competitors for SKU: <span id="comparison-lmp-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="comparison-lmp-add-wrap" class="card mb-3 border-success">
                    <div class="card-header bg-success text-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <strong><i class="fa fa-plus-circle"></i> Add <span id="comparison-lmp-add-platform-label">Amz</span> LMP</strong>
                        <a id="comparison-lmp-site-search-link" href="#" target="_blank" rel="noopener"
                            class="btn btn-sm btn-light text-success fw-semibold">
                            <i class="fa fa-external-link"></i> Search on <span id="comparison-lmp-site-name">Amazon</span>
                        </a>
                    </div>
                    <div class="card-body">
                        <form id="comparison-lmp-add-form" class="row g-3">
                            <input type="hidden" id="comparison-lmp-add-platform" value="amazon">
                            <div class="col-md-3">
                                <label class="form-label"><strong>SKU</strong></label>
                                <input type="text" class="form-control" id="comparison-lmp-add-comp-sku" readonly>
                            </div>
                            <div class="col-md-2 comparison-lmp-field-amazon">
                                <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="comparison-lmp-add-comp-asin" placeholder="B07ABC123">
                            </div>
                            <div class="col-md-2 comparison-lmp-field-ebay d-none">
                                <label class="form-label"><strong>Item ID</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="comparison-lmp-add-comp-item-id" placeholder="123456789012">
                            </div>
                            <div class="col-md-2 comparison-lmp-field-shopify d-none">
                                <label class="form-label"><strong>Product ID</strong> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="comparison-lmp-add-comp-product-id" placeholder="google-product-id">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="comparison-lmp-add-comp-price" placeholder="29.99" step="0.01" min="0.01">
                            </div>
                            <div class="col-md-2 comparison-lmp-field-ebay comparison-lmp-field-temu d-none">
                                <label class="form-label"><strong>Shipping / Del</strong></label>
                                <input type="number" class="form-control" id="comparison-lmp-add-comp-shipping" placeholder="0.00" step="0.01" min="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"><strong>Product Link</strong></label>
                                <input type="url" class="form-control" id="comparison-lmp-add-comp-link" placeholder="https://...">
                            </div>
                            <div class="col-md-2 comparison-lmp-field-amazon">
                                <label class="form-label"><strong>Marketplace</strong></label>
                                <select class="form-select" id="comparison-lmp-add-comp-marketplace">
                                    <option value="amazon" selected>Amz</option>
                                    <option value="US">US</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-plus"></i> Add LMP
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fa fa-undo"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <div id="comparison-lmp-data-list">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 mb-0">Loading competitors...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonHistoryModal" tabindex="-1" aria-labelledby="comparisonHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="comparisonHistoryModalLabel">
                    <i class="fas fa-history"></i> Change History — <span id="comparison-history-modal-sku"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="comparison-history-loading" class="text-center py-4">
                    <div class="spinner-border text-secondary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 mb-0">Loading history...</p>
                </div>
                <div id="comparison-history-empty" class="alert alert-info mb-0 d-none">
                    <i class="fa fa-info-circle"></i> No change history found for this SKU.
                </div>
                <div id="comparison-history-error" class="alert alert-danger mb-0 d-none"></div>
                <div id="comparison-history-table-wrap" class="table-responsive d-none" style="max-height: 65vh;">
                    <table class="table table-bordered table-hover comparison-history-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 130px;">Date</th>
                                <th style="width: 120px;">User</th>
                                <th style="width: 130px;">Field</th>
                                <th>Changes</th>
                            </tr>
                        </thead>
                        <tbody id="comparison-history-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const dataUrl = @json(route('comparison.data'));
    const historyUrl = @json(route('comparison.history'));
    const sheetGetUrl = @json(route('comparison.sheet.get'));
    const sheetSaveUrl = @json(route('comparison.sheet.save'));
    const sheetImageUrl = @json(route('comparison.sheet.image'));
    const sheetSyncClinkUrl = @json(route('comparison.sheet.sync-clink'));
    const suppliersForSkuUrl = @json(route('comparison.suppliers-for-sku'));
    const supplierListUrl = @json(route('supplier.list'));
    const competitorsUrl = @json(route('amazon.competitors.get'));
    const amazonLmpAddUrl = @json(route('amazon.lmp.add'));
    const amazonLmpDeleteUrl = @json(route('amazon.lmp.delete.post'));
    const ebayLmpDataUrl = @json(route('ebay.lmp.data'));
    const ebayLmpAddUrl = @json(route('ebay.lmp.add'));
    const temuLmpDataUrl = @json(route('cvr.master.temu.lmp'));
    const temuLmpSaveUrl = @json(route('temu.lmp.save'));
    const googleLmpDataUrl = @json(route('google.lmp.data'));
    const googleLmpAddUrl = @json(route('google.lmp.add'));
    const updateLinkUrl = @json(route('update.rfq.link'));
    const groupMasterCategoriesUrl = @json(route('group.master.categories'));
    const groupMasterUpdateFieldUrl = @json(route('group.master.update.field'));
    const groupMasterStoreCategoryUrl = @json(route('group.master.store.category'));
    const supplierCategoriesUrl = @json(route('supplier.categories.json'));
    const shippingSlabRateUrl = @json(route('comparison.shipping-slab-rate'));
    const lmpRatesUrl = @json(route('comparison.lmp-rates'));
    const roiSaveUrl = @json(route('comparison.roi.save-cell'));
    const linkedSkuAddUrl = @json(route('comparison.linked-skus.add'));
    const linkedSkuBulkLinkUrl = @json(route('comparison.linked-skus.bulk-link'));
    const linkedSkuRemoveUrl = @json(route('comparison.linked-skus.remove'));
    const comparisonParentsUrl = @json(route('comparison.parents'));
    const comparisonCategorySaveUrl = @json(route('comparison.category.save'));
    const comparisonIndexUrl = @json(route('comparison.index'));
    const comparisonSheetPageUrl = @json(route('comparison.sheet.page'));
    const reviewsIntelligenceUrl = @json(route('reviews.index'));
    const cvrMasterChartDataUrl = @json(route('cvr.master.chart.data'));
    const channelPriceChartDataUrl = @json(route('cvr.master.channel.price.chart'));
    const cvrMasterBreakdownUrl = @json(route('cvr.master.breakdown'));
    // When set, this page is the dedicated full-page CD editor for one SKU.
    const COMPARISON_CD_PAGE_SKU = @json($cdPageSku ?? null);
    const cdHoverPreview = document.getElementById('cd-hover-preview');
    const cdModalEl = document.getElementById('comparisonCdModal');
    const cdModal = cdModalEl ? new bootstrap.Modal(cdModalEl) : null;
    const historyModalEl = document.getElementById('comparisonHistoryModal');
    const historyModal = historyModalEl ? new bootstrap.Modal(historyModalEl) : null;
    const lmpModalEl = document.getElementById('comparisonLmpModal');
    const lmpModal = lmpModalEl ? new bootstrap.Modal(lmpModalEl) : null;
    const linkedSkuModalEl = document.getElementById('comparisonLinkedSkuModal');
    const linkedSkuModal = linkedSkuModalEl ? new bootstrap.Modal(linkedSkuModalEl) : null;
    const commModalEl = document.getElementById('comparisonCommModal');
    const commModal = commModalEl ? new bootstrap.Modal(commModalEl) : null;
    const categoryPickerModalEl = document.getElementById('comparisonCategoryPickerModal');
    const categoryPickerModal = categoryPickerModalEl ? new bootstrap.Modal(categoryPickerModalEl) : null;

    let linkedSkuModalRow = null;
    let categoryPickerRow = null;
    let comparisonSuppliersByName = {};

    // Playback (parent navigation) state
    let comparisonParents = [];
    let comparisonPlaybackActive = false;
    let comparisonPlaybackParent = '';
    let comparisonPlaybackIndex = -1;

    const COMM_PLAT_ICON = {
        Website: 'fas fa-globe',
        Email: 'fas fa-envelope',
        Phone: 'fas fa-phone',
        WhatsApp: 'fab fa-whatsapp',
        WeChat: 'fab fa-weixin',
        QQ: 'mdi mdi-qqchat',
        Alibaba: 'fas fa-store',
        '1688': 'fas fa-shopping-bag',
    };
    const COMM_PLAT_COLOR = {
        Website: '#2563eb',
        Email: '#dc3545',
        Phone: '#0d9488',
        WhatsApp: '#25d366',
        WeChat: '#09b83e',
        QQ: '#1565c0',
        Alibaba: '#ff6a00',
        '1688': '#e65100',
    };

    const ROI_CHANNELS = ['Amz', 'Ebay', 'Temu', 'Shopify'];
    const ROI_OVERALL_CHANNEL = 'Overall';
    const ROI_LMP_SALE_FACTOR = 0.9;
    // Marketplace take-home margins — same as Pricing Master / OV L30 GROI% & GPFT%.
    const ROI_CHANNEL_MARGINS = {
        amazon: 0.80,
        ebay: 0.83,
        temu: 1.00,
        shopify: 0.95,
    };
    const ROI_FIELD_OFFSETS = {
        cp: 1,
        cbm: 2,
        freight: 3,
        gw: 4,
        shipping: 5,
        sale: 6,
        pPct: 7,
        profit: 8,
        roi: 9,
    };

    let currentCdRow = null;
    let comparisonBulkEditSkus = null;
    let currentSheetCells = [];
    let currentSheetFormats = { cells: {}, rows: {}, cols: {} };
    let selectedSheetRow = null;
    let selectedSheetCol = null;
    let selectedSheetCell = null;
    let selectedSheetMultiRows = new Set();
    let priorityBulkEditTargetRows = [];
    let columnEditTargetCol = null;
    let columnEditSelectedRows = new Set();
    let sheetRenderColCache = null;
    let sheetDimWtApplyTimer = null;
    let lmpLoadedForSku = null;
    let currentAmazonLmpSku = null;
    let currentAmazonLmpListEl = null;
    let currentAmazonLmpFormPrefix = null;
    let currentComparisonLmpPlatform = 'amazon';
    let currentComparisonLmpSku = null;
    let comparisonLmpOpenedFromRoi = false;
    let table;

    const SPEC_COLUMN_COLOR = '#fed7aa';
    const LOWEST_PRICE_COLOR = '#bbf7d0';
    const SUPPLIER_NAME_ROW_COLOR = '#42c4f0';
    const FIRST_SUPPLIER_COLUMN = 3; // Column D
    let autoSheetFormats = { cells: {}, rows: {}, cols: {} };
    let sheetEditorHydrating = false;
    let sheetAutoSaveTimer = null;
    let sheetSaveInFlight = false;
    let sheetSaveQueued = false;
    let tableRefreshTimer = null;
    let copiedSpecLabels = [];
    const COPIED_SPECS_STORAGE_KEY = 'comparison_copied_spec_labels';
    let allProductCategories = [];
    let supplierCategoryOptions = [];
    let productCategoriesByName = {};
    let activeCategoryDropdown = null;
    let clinkPreloadedSupplierByCol = {};
    let clinkPreloadedSupplierNames = new Set();
    let roiCellEditPrevious = {};
    let roiSaveInFlight = false;
    let currentDimWtData = {};
    let currentQcIssuesData = null;
    let currentReviewsData = null;
    let currentSiblingsData = { parent: null, siblings: [], count: 0 };
    const SIBLINGS_SYNC_STORAGE_KEY = 'comparison.siblingsSync';
    let siblingsSyncEnabled = false;
    try {
        siblingsSyncEnabled = localStorage.getItem(SIBLINGS_SYNC_STORAGE_KEY) === '1';
    } catch (e) {
        siblingsSyncEnabled = false;
    }
    const comparisonQcIssuesModalEl = document.getElementById('comparisonQcIssuesModal');
    const comparisonQcIssuesModal = comparisonQcIssuesModalEl ? new bootstrap.Modal(comparisonQcIssuesModalEl) : null;
    const comparisonQcIssueTextModalEl = document.getElementById('comparisonQcIssueTextModal');
    const comparisonQcIssueTextModal = comparisonQcIssueTextModalEl ? new bootstrap.Modal(comparisonQcIssueTextModalEl) : null;

    const INNER_PKG_SECTION_COLOR = '#dbeafe';
    const CTN_PKG_SECTION_COLOR = '#fffef2';
    const INNER_PKG_SECTION = {
        header: 'Inner Pkg',
        color: INNER_PKG_SECTION_COLOR,
        rows: [
            { label: 'Item L (IN)', key: 'item_l' },
            { label: 'Item W (IN)', key: 'item_w' },
            { label: 'Item H (IN)', key: 'item_h' },
            { label: 'Itm wt GW', key: 'wt_act' },
            { label: 'Itm CBM', key: 'cbm' },
            { label: 'pkg inst', key: 'instructions_item_pkg', aliases: ['item PKG', 'item pkg', 'Pkg Inst'] },
        ],
        obsoleteLabels: ['Item L / W / H (IN)', 'item L / W / H (IN)'],
    };
    const CTN_PKG_SECTION = {
        header: 'Ctn Pkg',
        color: CTN_PKG_SECTION_COLOR,
        rows: [
            { label: 'CTN L (CM)', key: 'ctn_l' },
            { label: 'CTN W (CM)', key: 'ctn_w' },
            { label: 'CTN H (CM)', key: 'ctn_h' },
            { label: 'CTN QTY', key: 'ctn_qty' },
            { label: 'Carton CBM', key: 'ctn_cbm' },
            { label: 'ctn Instr', key: 'ctn_instructions', aliases: ['Ctn pkg', 'ctn pkg', 'CTN Instructions', 'Instr Carton'] },
        ],
        obsoleteLabels: ['CTN L / W / H (CM)', 'Ctn L / W / H (CM)'],
    };

    function getCopiedSpecLabels() {
        if (copiedSpecLabels.length) {
            return copiedSpecLabels.slice();
        }
        try {
            const stored = sessionStorage.getItem(COPIED_SPECS_STORAGE_KEY);
            if (!stored) {
                return [];
            }
            const parsed = JSON.parse(stored);
            return Array.isArray(parsed) ? parsed.slice() : [];
        } catch (e) {
            return [];
        }
    }

    function saveCopiedSpecLabelsToMemory(labels) {
        copiedSpecLabels = Array.isArray(labels) ? labels.slice() : [];
        try {
            sessionStorage.setItem(COPIED_SPECS_STORAGE_KEY, JSON.stringify(copiedSpecLabels));
        } catch (e) {
            // sessionStorage unavailable — in-memory copy still works for this page session
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeHtmlAttr(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function isGoogleSheetUrl(url) {
        return /^https?:\/\/(docs|sheets)\.google\.com\/spreadsheets/i.test(String(url || '').trim());
    }

    function linkedSkusForRow(row) {
        if (!row) {
            return [];
        }
        return Array.isArray(row.linked_skus) ? row.linked_skus.filter(Boolean) : [];
    }

    function comparisonBulkEditPayload() {
        if (!Array.isArray(comparisonBulkEditSkus) || comparisonBulkEditSkus.length === 0) {
            return [];
        }
        return comparisonBulkEditSkus.filter(Boolean);
    }

    function siblingSkusForSave(row) {
        const fromPayload = Array.isArray(currentSiblingsData?.siblings)
            ? currentSiblingsData.siblings.filter(Boolean)
            : [];
        if (fromPayload.length) {
            return fromPayload;
        }
        const currentSku = String(row?.sku || '').trim();
        return currentSku ? [currentSku] : [];
    }

    function sheetSaveTargetSkus(row) {
        const bulk = comparisonBulkEditPayload();
        if (bulk.length) {
            return bulk;
        }
        const linked = linkedSkusForRow(row);
        if (!siblingsSyncEnabled) {
            return linked;
        }
        const merged = [];
        const seen = new Set();
        [...linked, ...siblingSkusForSave(row)].forEach(function (sku) {
            const text = String(sku || '').trim();
            if (!text) return;
            const key = text.toUpperCase();
            if (seen.has(key)) return;
            seen.add(key);
            merged.push(text);
        });
        return merged;
    }

    function updateSiblingsBadge(siblingsData) {
        const badge = document.getElementById('comparison-cd-siblings-badge');
        const checkbox = document.getElementById('comparison-cd-siblings-sync');
        const countEl = document.getElementById('comparison-cd-siblings-count');
        if (!badge || !checkbox) {
            return;
        }

        currentSiblingsData = siblingsData && typeof siblingsData === 'object'
            ? siblingsData
            : { parent: null, siblings: [], count: 0 };

        const siblings = Array.isArray(currentSiblingsData.siblings)
            ? currentSiblingsData.siblings.filter(Boolean)
            : [];
        const count = Number(currentSiblingsData.count) || siblings.length;
        const otherCount = Math.max(0, count - 1);
        const parent = String(currentSiblingsData.parent || currentCdRow?.parent || '').trim();
        const canSync = otherCount > 0 && parent !== '';

        if (countEl) {
            countEl.textContent = String(count);
            countEl.classList.toggle('bg-success', siblingsSyncEnabled && canSync);
            countEl.classList.toggle('bg-secondary', !(siblingsSyncEnabled && canSync));
        }

        checkbox.disabled = !canSync;
        checkbox.checked = siblingsSyncEnabled && canSync;
        badge.classList.toggle('is-active', siblingsSyncEnabled && canSync);
        badge.classList.toggle('is-disabled', !canSync);

        if (!canSync) {
            badge.title = parent
                ? 'No sibling SKUs under this parent'
                : 'No parent available for sibling sync';
        } else if (siblingsSyncEnabled) {
            badge.title = `Syncing sheet to ${count} sibling SKU(s) under parent ${parent}`;
        } else {
            badge.title = `Sync this sheet to ${otherCount} other sibling SKU(s) under parent ${parent}`;
        }
    }

    function setSiblingsSyncEnabled(enabled, { persist = true, triggerSave = false } = {}) {
        const canSync = (Number(currentSiblingsData?.count) || 0) > 1
            && String(currentSiblingsData?.parent || currentCdRow?.parent || '').trim() !== '';
        siblingsSyncEnabled = !!(enabled && canSync);

        if (persist) {
            try {
                localStorage.setItem(SIBLINGS_SYNC_STORAGE_KEY, siblingsSyncEnabled ? '1' : '0');
            } catch (e) {
                // ignore storage failures
            }
        }

        updateSiblingsBadge(currentSiblingsData);

        if (triggerSave && siblingsSyncEnabled && currentCdRow) {
            scheduleAutoSaveComparisonSheet(400, { rerender: false, refreshTable: false });
            setSheetStatus(`Syncing sheet to ${currentSiblingsData.count} sibling SKU(s)…`, false);
        }
    }

    function getSelectedComparisonRows() {
        return table ? table.getSelectedRows() : [];
    }

    function clearComparisonRowSelection() {
        if (table) {
            table.deselectRow();
        }
    }

    function buildSheetRequestParams(row) {
        const params = new URLSearchParams({ sku: row?.sku || '' });
        const linked = linkedSkusForRow(row);
        if (linked.length) {
            params.set('linked_skus', linked.join(','));
        }
        if (row?.parent) {
            params.set('parent', row.parent);
        }
        return params;
    }

    function buildCdHoverHtml(row) {
        const clink = (row.clink || '').trim();
        const clinkIsSheet = !!row.clink_is_sheet || isGoogleSheetUrl(clink);
        const lmpPrice = row.lmp_price;
        const count = parseInt(row.lmp_entries_total, 10) || 0;
        const hasSheet = !!row.has_sheet_data;
        const supplierCount = parseInt(row.sheet_supplier_count, 10) || 0;
        const sheetSku = row.sheet_sku || row.sku;

        let sheetLabel = 'No sheet saved';
        if (hasSheet) {
            sheetLabel = supplierCount + ' supplier column(s) saved';
            if (sheetSku && sheetSku !== row.sku) {
                sheetLabel += ` (shared from ${sheetSku})`;
            }
        } else if (clinkIsSheet) {
            sheetLabel = 'C link sheet ready — click to load';
        }

        let html = '';
        html += `<div><span class="cd-hover-label">Sheet:</span> ${sheetLabel}</div>`;
        const clinkSku = row.clink_sku && row.clink_sku !== row.sku ? row.clink_sku : '';
        const clinkLabel = clink
            ? (clinkSku ? `${escapeHtml(clink)} (shared from ${escapeHtml(clinkSku)})` : escapeHtml(clink))
            : '—';
        html += `<div><span class="cd-hover-label">C link:</span> ${clinkLabel}</div>`;
        html += `<div><span class="cd-hover-label">LMP:</span> ${lmpPrice ? '$' + parseFloat(lmpPrice).toFixed(2) : 'N/A'}</div>`;
        html += `<div><span class="cd-hover-label">Competitors:</span> ${count}</div>`;
        html += `<div class="mt-1 text-white-50">Click to view and edit</div>`;
        return html;
    }

    function showCdHover(event, row) {
        if (!cdHoverPreview) return;
        cdHoverPreview.innerHTML = buildCdHoverHtml(row);
        cdHoverPreview.style.display = 'block';
        positionCdHover(event);
    }

    function positionCdHover(event) {
        if (!cdHoverPreview) return;
        const offset = 12;
        let left = event.clientX + offset;
        let top = event.clientY + offset;
        const rect = cdHoverPreview.getBoundingClientRect();
        if (left + rect.width > window.innerWidth - 8) {
            left = event.clientX - rect.width - offset;
        }
        if (top + rect.height > window.innerHeight - 8) {
            top = event.clientY - rect.height - offset;
        }
        cdHoverPreview.style.left = `${Math.max(8, left)}px`;
        cdHoverPreview.style.top = `${Math.max(8, top)}px`;
    }

    function hideCdHover() {
        if (cdHoverPreview) {
            cdHoverPreview.style.display = 'none';
        }
    }

    function isCompanyNameRow(rowIndex, cells) {
        if (rowIndex === null || rowIndex === undefined || Number.isNaN(rowIndex)) {
            return false;
        }
        const specCol = detectSpecColumnIndex(cells || currentSheetCells);
        const label = String(((cells || currentSheetCells)[rowIndex] || [])[specCol] || '').trim().toLowerCase();
        return label.includes('company name');
    }

    function isSheetLinkRow(rowIndex, cells) {
        if (rowIndex === null || rowIndex === undefined || Number.isNaN(rowIndex)) {
            return false;
        }
        const specCol = detectSpecColumnIndex(cells || currentSheetCells);
        const label = String(((cells || currentSheetCells)[rowIndex] || [])[specCol] || '').trim().toLowerCase();
        return label === 'link' || label === 'supplier link' || label.startsWith('supplier link ');
    }

    function isCommRow(rowIndex, cells) {
        if (rowIndex === null || rowIndex === undefined || Number.isNaN(rowIndex)) {
            return false;
        }
        const specCol = detectSpecColumnIndex(cells || currentSheetCells);
        const label = String(((cells || currentSheetCells)[rowIndex] || [])[specCol] || '').trim().toLowerCase();
        return label === 'comm' || label.includes('communication');
    }

    function isPriorityValue(value) {
        const text = String(value || '').trim().toLowerCase();
        return text === 'critical' || text === 'important' || text === 'normal';
    }

    function normalizePriorityValue(value) {
        const text = String(value || '').trim().toLowerCase();
        if (text === 'critical') return 'Critical';
        if (text === 'important') return 'Important';
        return 'Normal';
    }

    function priorityDotClass(value) {
        const normalized = normalizePriorityValue(value);
        if (normalized === 'Critical') return 'cd-priority-dot-critical';
        if (normalized === 'Important') return 'cd-priority-dot-important';
        return 'cd-priority-dot-normal';
    }

    function findColumnHeaderIndex(cells, headerName, fromCol) {
        const needle = String(headerName || '').trim().toLowerCase();
        if (!needle) {
            return null;
        }
        const colCount = Math.max(...(cells || []).map(row => (Array.isArray(row) ? row.length : 0)), 0);
        for (let colIndex = Math.max(0, fromCol || 0); colIndex < colCount; colIndex++) {
            const header = String((cells[0] || [])[colIndex] || '').trim().toLowerCase();
            if (header === needle) {
                return colIndex;
            }
        }
        return null;
    }

    function columnLooksLikePriorityOnly(cells, colIndex) {
        if (colIndex < 0) {
            return false;
        }
        let sawPriority = false;
        const maxRows = Math.min(cells.length, 40);
        for (let rowIndex = 0; rowIndex < maxRows; rowIndex++) {
            const text = String((cells[rowIndex] || [])[colIndex] || '').trim();
            if (!text) {
                continue;
            }
            if (rowIndex === 0 && (text.toLowerCase() === 'critical' || text.toLowerCase() === 'qc')) {
                continue;
            }
            if (isPriorityValue(text)) {
                sawPriority = true;
            } else {
                return false;
            }
        }
        return sawPriority;
    }

    function detectCriticalColumnIndex(cells, specCol) {
        cells = cells || currentSheetCells;
        specCol = specCol ?? detectSpecColumnIndex(cells);
        const byHeader = findColumnHeaderIndex(cells, 'Critical', specCol + 1);
        if (byHeader !== null) {
            return byHeader;
        }
        const candidate = specCol + 1;
        const header = String((cells[0] || [])[candidate] || '').trim().toLowerCase();
        if (header === 'qc') {
            return null;
        }
        if (columnLooksLikePriorityOnly(cells, candidate) && header !== 'qc') {
            return candidate;
        }
        return null;
    }

    function detectQcColumnIndex(cells, specCol) {
        cells = cells || currentSheetCells;
        specCol = specCol ?? detectSpecColumnIndex(cells);
        const byHeader = findColumnHeaderIndex(cells, 'QC', specCol + 1);
        if (byHeader !== null) {
            return byHeader;
        }
        const criticalCol = detectCriticalColumnIndex(cells, specCol);
        const candidate = (criticalCol !== null ? criticalCol : specCol) + 1;
        const header = String((cells[0] || [])[candidate] || '').trim().toLowerCase();
        if (header === 'critical') {
            return null;
        }
        if (columnLooksLikePriorityOnly(cells, candidate)) {
            return candidate;
        }
        return null;
    }

    function isSheetCriticalColumn(colIndex) {
        if (sheetRenderColCache) {
            return sheetRenderColCache.criticalCol !== null && colIndex === sheetRenderColCache.criticalCol;
        }
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        return criticalCol !== null && colIndex === criticalCol;
    }

    function isSheetQcColumn(colIndex) {
        if (sheetRenderColCache) {
            return sheetRenderColCache.qcCol !== null && colIndex === sheetRenderColCache.qcCol;
        }
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);
        return qcCol !== null && colIndex === qcCol;
    }

    function isSheetPriorityColumn(colIndex) {
        return isSheetCriticalColumn(colIndex) || isSheetQcColumn(colIndex);
    }

    function priorityCellEditorHtml(value, rowIndex, colIndex, columnLabel) {
        const label = columnLabel || 'Priority';
        if (rowIndex === 0 && String(value || '').trim().toLowerCase() === label.toLowerCase()) {
            return `<div class="cd-sheet-cell cd-sheet-cell-priority" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(label)}" title="${escapeHtmlAttr(label)}">
                <span class="cd-priority-header-label">${escapeHtml(label)}</span>
            </div>`;
        }
        const normalized = normalizePriorityValue(value);
        return `<div class="cd-sheet-cell cd-sheet-cell-priority" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(normalized)}" title="${escapeHtmlAttr(normalized)}" aria-label="${escapeHtmlAttr(label + ': ' + normalized)}">
            <div class="cd-priority-wrap">
                <span class="cd-priority-dot ${priorityDotClass(normalized)}" aria-hidden="true"></span>
            </div>
        </div>`;
    }

    function closeAllPriorityMenus() {
        document.querySelectorAll('.cd-priority-menu').forEach(menu => {
            menu.classList.add('d-none');
        });
    }

    function setPriorityCellValue(cell, value) {
        if (!cell) {
            return;
        }
        const rowIndex = parseInt(cell.dataset.row, 10);
        const colIndex = parseInt(cell.dataset.col, 10);
        const normalized = normalizePriorityValue(value);
        cell.dataset.value = normalized;
        cell.setAttribute('title', normalized);
        if (!Number.isNaN(rowIndex) && !Number.isNaN(colIndex) && currentSheetCells[rowIndex]) {
            currentSheetCells[rowIndex][colIndex] = normalized;
        }

        const dot = cell.querySelector('.cd-priority-wrap > .cd-priority-dot');
        if (dot) {
            dot.classList.remove('cd-priority-dot-critical', 'cd-priority-dot-important', 'cd-priority-dot-normal');
            dot.classList.add(priorityDotClass(normalized));
        }
    }

    function getSelectedSheetMultiRows() {
        return [...selectedSheetMultiRows]
            .map(r => parseInt(r, 10))
            .filter(r => Number.isFinite(r) && r > 0 && r < (currentSheetCells || []).length)
            .sort((a, b) => a - b);
    }

    function syncSheetSelectAllCheckbox() {
        const selectAll = document.getElementById('cd-sheet-select-all-rows');
        if (!selectAll) return;
        const selectable = [];
        for (let r = 1; r < (currentSheetCells || []).length; r++) {
            selectable.push(r);
        }
        const checkedCount = selectable.filter(r => selectedSheetMultiRows.has(r)).length;
        selectAll.disabled = selectable.length === 0;
        selectAll.checked = selectable.length > 0 && checkedCount === selectable.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < selectable.length;
    }

    function setSheetMultiRowSelected(rowIndex, selected) {
        const r = parseInt(rowIndex, 10);
        if (!Number.isFinite(r) || r <= 0) {
            return;
        }
        if (selected) {
            selectedSheetMultiRows.add(r);
        } else {
            selectedSheetMultiRows.delete(r);
        }
        const rowCheckbox = document.querySelector(`.cd-sheet-row-select[data-row="${r}"]`);
        if (rowCheckbox) {
            rowCheckbox.checked = !!selected;
        }
        const tr = document.querySelector(`#comparison-cd-sheet-body tr:nth-child(${r + 1})`);
        if (tr) {
            tr.classList.toggle('cd-multi-selected', !!selected);
        }
        syncSheetSelectAllCheckbox();
    }

    function commonPriorityValueForRows(rows, colIndex) {
        if (colIndex === null || colIndex === undefined || !rows.length) {
            return '';
        }
        let common = null;
        for (let i = 0; i < rows.length; i++) {
            const val = normalizePriorityValue((currentSheetCells[rows[i]] || [])[colIndex]);
            if (common === null) {
                common = val;
            } else if (common !== val) {
                return '';
            }
        }
        return common || '';
    }

    function openPriorityBulkEditModal(anchorRow) {
        let rows = getSelectedSheetMultiRows();
        const anchor = parseInt(anchorRow, 10);
        if (Number.isFinite(anchor) && anchor > 0) {
            if (!rows.includes(anchor)) {
                if (rows.length === 0) {
                    setSheetMultiRowSelected(anchor, true);
                    rows = [anchor];
                } else {
                    setSheetMultiRowSelected(anchor, true);
                    rows = getSelectedSheetMultiRows();
                }
            }
        }
        if (!rows.length) {
            setSheetStatus('Select at least one row to edit Critical / QC.', true);
            return;
        }

        priorityBulkEditTargetRows = rows.slice();
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);

        const countEl = document.getElementById('comparison-priority-bulk-count');
        if (countEl) {
            countEl.textContent = String(rows.length);
        }
        const rowsEl = document.getElementById('comparison-priority-bulk-rows');
        if (rowsEl) {
            rowsEl.textContent = rows.map(r => r + 1).join(', ');
        }

        const criticalSelect = document.getElementById('comparison-priority-bulk-critical');
        const qcSelect = document.getElementById('comparison-priority-bulk-qc');
        if (criticalSelect) {
            criticalSelect.value = commonPriorityValueForRows(rows, criticalCol);
        }
        if (qcSelect) {
            qcSelect.value = commonPriorityValueForRows(rows, qcCol);
        }

        refreshPriorityBulkEditModalMeta();

        const modalEl = document.getElementById('comparisonPriorityBulkEditModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function savePriorityBulkEditModal() {
        const rows = (priorityBulkEditTargetRows || []).filter(r => r > 0 && r < currentSheetCells.length);
        if (!rows.length) {
            setSheetStatus('No rows selected for edit.', true);
            return;
        }

        const criticalSelect = document.getElementById('comparison-priority-bulk-critical');
        const qcSelect = document.getElementById('comparison-priority-bulk-qc');
        const criticalVal = String(criticalSelect?.value || '').trim();
        const qcVal = String(qcSelect?.value || '').trim();

        if (!criticalVal && !qcVal) {
            setSheetStatus('Choose a Critical and/or QC value to apply.', true);
            return;
        }

        readCellsFromEditor();
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);

        rows.forEach(rowIndex => {
            if (!currentSheetCells[rowIndex]) {
                return;
            }
            if (criticalVal && criticalCol !== null) {
                const normalized = normalizePriorityValue(criticalVal);
                currentSheetCells[rowIndex][criticalCol] = normalized;
                const cell = document.querySelector(
                    `.cd-sheet-cell-priority[data-row="${rowIndex}"][data-col="${criticalCol}"]`
                );
                setPriorityCellValue(cell, normalized);
            }
            if (qcVal && qcCol !== null) {
                const normalized = normalizePriorityValue(qcVal);
                currentSheetCells[rowIndex][qcCol] = normalized;
                const cell = document.querySelector(
                    `.cd-sheet-cell-priority[data-row="${rowIndex}"][data-col="${qcCol}"]`
                );
                setPriorityCellValue(cell, normalized);
            }
        });

        applyPriorityRowFilters();
        // Quiet local save — no full sheet rebuild / list reload.
        scheduleAutoSaveComparisonSheet(200, { rerender: false, refreshTable: false });

        const parts = [];
        if (criticalVal) parts.push(`Critical → ${normalizePriorityValue(criticalVal)}`);
        if (qcVal) parts.push(`QC → ${normalizePriorityValue(qcVal)}`);
        setSheetStatus(`Updated ${rows.length} row(s): ${parts.join(', ')}.`, false);

        const modalEl = document.getElementById('comparisonPriorityBulkEditModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }

    function refreshPriorityBulkEditModalMeta() {
        const rows = (priorityBulkEditTargetRows || [])
            .filter(r => r > 0 && r < currentSheetCells.length)
            .sort((a, b) => a - b);
        priorityBulkEditTargetRows = rows;
        const countEl = document.getElementById('comparison-priority-bulk-count');
        if (countEl) {
            countEl.textContent = String(rows.length);
        }
        const rowsEl = document.getElementById('comparison-priority-bulk-rows');
        if (rowsEl) {
            rowsEl.textContent = rows.length ? rows.map(r => r + 1).join(', ') : '—';
        }
        const deleteBtn = document.getElementById('comparison-priority-bulk-delete-row-btn');
        if (deleteBtn) {
            deleteBtn.disabled = !rows.length || currentSheetCells.length <= 1;
        }
        const addBtn = document.getElementById('comparison-priority-bulk-add-row-btn');
        if (addBtn) {
            addBtn.disabled = !rows.length;
        }
    }

    function deleteRowsFromPriorityBulkEditModal() {
        const rows = (priorityBulkEditTargetRows || [])
            .filter(r => r > 0 && r < currentSheetCells.length)
            .sort((a, b) => b - a);
        if (!rows.length) {
            setSheetStatus('No row selected to delete.', true);
            return;
        }
        if (currentSheetCells.length <= 1 || rows.length >= currentSheetCells.length) {
            setSheetStatus('Cannot delete the last row.', true);
            return;
        }

        readCellsFromEditor({ expandImages: false });
        rows.forEach((idx) => {
            if (idx < 0 || idx >= currentSheetCells.length || currentSheetCells.length <= 1) {
                return;
            }
            currentSheetCells.splice(idx, 1);
            currentSheetFormats.rows = shiftNumericFormatMap(currentSheetFormats.rows, idx, -1);
            currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, idx, 'row', -1);

            if (selectedSheetCell && selectedSheetCell.row === idx) {
                selectedSheetCell = null;
            } else if (selectedSheetCell && selectedSheetCell.row > idx) {
                selectedSheetCell = { row: selectedSheetCell.row - 1, col: selectedSheetCell.col };
            }
            if (selectedSheetRow !== null) {
                if (selectedSheetRow === idx) {
                    selectedSheetRow = currentSheetCells.length
                        ? Math.min(idx, currentSheetCells.length - 1)
                        : null;
                } else if (selectedSheetRow > idx) {
                    selectedSheetRow--;
                }
            }
            selectedSheetMultiRows = new Set(
                [...selectedSheetMultiRows]
                    .filter(r => r !== idx)
                    .map(r => (r > idx ? r - 1 : r))
            );
        });

        priorityBulkEditTargetRows = [];
        renderSheetEditor(currentSheetCells, { migrateDimWt: false, sortByPrice: false });
        scheduleAutoSaveComparisonSheet(300, { rerender: false, refreshTable: false });
        setSheetStatus(`Deleted ${rows.length} row(s).`, false);

        const modalEl = document.getElementById('comparisonPriorityBulkEditModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
    }

    function addRowBelowFromPriorityBulkEditModal() {
        const rows = (priorityBulkEditTargetRows || [])
            .filter(r => r > 0 && r < currentSheetCells.length)
            .sort((a, b) => a - b);
        if (!rows.length) {
            setSheetStatus('No row selected to insert below.', true);
            return;
        }

        readCellsFromEditor({ expandImages: false });
        const insertAfter = rows[rows.length - 1];
        const insertAt = Math.min(insertAfter + 1, currentSheetCells.length);
        const colCount = Math.max(...currentSheetCells.map(row => (Array.isArray(row) ? row.length : 0)), 1);
        const newRow = Array.from({ length: colCount }, () => '');
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);
        if (criticalCol !== null) {
            newRow[criticalCol] = 'Normal';
        }
        if (qcCol !== null) {
            newRow[qcCol] = 'Normal';
        }

        currentSheetCells.splice(insertAt, 0, newRow);
        currentSheetFormats.rows = shiftNumericFormatMap(currentSheetFormats.rows, insertAt, 1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, insertAt, 'row', 1);
        if (selectedSheetRow !== null && insertAt <= selectedSheetRow) {
            selectedSheetRow++;
        }
        if (selectedSheetCell && selectedSheetCell.row >= insertAt) {
            selectedSheetCell = { row: selectedSheetCell.row + 1, col: selectedSheetCell.col };
        }
        selectedSheetMultiRows = new Set(
            [...selectedSheetMultiRows].map(r => (r >= insertAt ? r + 1 : r))
        );
        // Keep selection on original rows (shifted) and focus the new blank row.
        priorityBulkEditTargetRows = rows.map(r => (r >= insertAt ? r + 1 : r));
        priorityBulkEditTargetRows.push(insertAt);
        setSheetMultiRowSelected(insertAt, true);

        const criticalSelect = document.getElementById('comparison-priority-bulk-critical');
        const qcSelect = document.getElementById('comparison-priority-bulk-qc');
        if (criticalSelect && !criticalSelect.value) {
            criticalSelect.value = 'Normal';
        }
        if (qcSelect && !qcSelect.value) {
            qcSelect.value = 'Normal';
        }

        renderSheetEditor(currentSheetCells, { migrateDimWt: false, sortByPrice: false });
        refreshPriorityBulkEditModalMeta();
        scheduleAutoSaveComparisonSheet(300, { rerender: false, refreshTable: false });
        setSheetStatus(`Blank row inserted below row ${insertAfter + 1}.`, false);
    }

    function getSheetColumnHeaderLabel(colIndex, cells) {
        const sheetCells = cells || currentSheetCells;
        const specCol = detectSpecColumnIndex(sheetCells);
        const criticalCol = detectCriticalColumnIndex(sheetCells, specCol);
        const qcCol = detectQcColumnIndex(sheetCells, specCol);
        if (colIndex === specCol - 2) return 'Amz';
        if (colIndex === specCol - 1) return '5 Core';
        if (colIndex === specCol) return 'Spec';
        if (criticalCol !== null && colIndex === criticalCol) return 'Critical';
        if (qcCol !== null && colIndex === qcCol) return 'QC';
        const supplierName = getSupplierNameForColumn(colIndex, sheetCells);
        if (supplierName) return supplierName;
        const headerCell = String((sheetCells[0] || [])[colIndex] || '').trim();
        if (headerCell) return headerCell;
        return columnLetter(colIndex);
    }

    function fieldClipboardActionsHtml(fieldId) {
        const idAttr = fieldId ? ` data-field-id="${escapeHtmlAttr(fieldId)}"` : '';
        return `<span class="cd-field-clip-btns">
            <button type="button" class="cd-field-clip-btn cd-field-copy-btn" title="Copy"${idAttr} aria-label="Copy">
                <i class="mdi mdi-content-copy" aria-hidden="true"></i>
            </button>
            <button type="button" class="cd-field-clip-btn cd-field-cut-btn" title="Cut"${idAttr} aria-label="Cut">
                <i class="mdi mdi-content-cut" aria-hidden="true"></i>
            </button>
            <button type="button" class="cd-field-clip-btn cd-field-paste-btn" title="Paste"${idAttr} aria-label="Paste">
                <i class="mdi mdi-content-paste" aria-hidden="true"></i>
            </button>
        </span>`;
    }

    function wrapFieldWithClipboardActions(fieldHtml, fieldId) {
        return `<div class="cd-field-clip-wrap">
            ${fieldHtml}
            ${fieldClipboardActionsHtml(fieldId)}
        </div>`;
    }

    function resolveClipboardField(btn) {
        if (!btn) {
            return null;
        }
        const fieldId = btn.dataset.fieldId || '';
        if (fieldId) {
            const byId = document.getElementById(fieldId);
            if (byId) {
                return byId;
            }
        }
        const wrap = btn.closest('.cd-field-clip-wrap');
        return wrap
            ? wrap.querySelector('.cd-col-edit-field, .form-control, .form-select, input, select, textarea')
            : null;
    }

    function copyFieldValueToClipboard(field) {
        if (!field) {
            return Promise.reject(new Error('No field'));
        }
        const value = String(field.value ?? '');
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(value);
        }
        return new Promise((resolve, reject) => {
            try {
                field.focus();
                if (typeof field.select === 'function') {
                    field.select();
                }
                const ok = document.execCommand('copy');
                if (ok) {
                    resolve();
                } else {
                    reject(new Error('Copy failed'));
                }
            } catch (err) {
                reject(err);
            }
        });
    }

    function cutFieldValueToClipboard(field) {
        return copyFieldValueToClipboard(field).then(() => {
            if (!field || field.readOnly || field.disabled) {
                return;
            }
            if (field.tagName === 'SELECT') {
                const emptyOpt = Array.from(field.options || []).find(opt => opt.value === '');
                field.value = emptyOpt ? '' : (field.options[0] ? field.options[0].value : '');
            } else {
                field.value = '';
            }
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function readClipboardText() {
        if (navigator.clipboard && typeof navigator.clipboard.readText === 'function') {
            return navigator.clipboard.readText();
        }
        return Promise.reject(new Error('Clipboard paste is not available in this browser.'));
    }

    function pasteFieldValueFromClipboard(field) {
        if (!field || field.readOnly || field.disabled) {
            return Promise.reject(new Error('Field is not editable'));
        }
        return readClipboardText().then((text) => {
            const pasted = String(text ?? '').replace(/\r\n/g, '\n').trimEnd();
            if (field.tagName === 'SELECT') {
                const options = Array.from(field.options || []);
                const exact = options.find(opt => opt.value === pasted);
                if (exact) {
                    field.value = exact.value;
                } else {
                    const byLabel = options.find(
                        opt => String(opt.textContent || '').trim().toLowerCase() === pasted.trim().toLowerCase()
                    );
                    if (!byLabel) {
                        throw new Error('Clipboard value is not valid for this field.');
                    }
                    field.value = byLabel.value;
                }
            } else {
                field.value = pasted;
            }
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function applyColumnEditModalDrafts() {
        const col = columnEditTargetCol;
        if (col === null || col === undefined || Number.isNaN(col)) {
            return 0;
        }

        const fields = document.querySelectorAll('#comparison-column-edit-tbody .cd-col-edit-field');
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);
        const isPriorityCol = (criticalCol !== null && col === criticalCol)
            || (qcCol !== null && col === qcCol);
        let changed = 0;

        fields.forEach((field) => {
            const rowIndex = parseInt(field.dataset.row, 10);
            if (Number.isNaN(rowIndex) || rowIndex < 0 || !currentSheetCells[rowIndex]) {
                return;
            }
            if (field.tagName === 'INPUT' && field.type === 'hidden') {
                return;
            }
            if (isCommRow(rowIndex, currentSheetCells) && !isPriorityCol && col !== specCol) {
                return;
            }

            let nextVal = String(field.value ?? '');
            if (isPriorityCol && rowIndex > 0) {
                nextVal = normalizePriorityValue(nextVal);
            }

            const prev = String(currentSheetCells[rowIndex][col] ?? '');
            if (
                (nextVal.startsWith('[embedded-image:') || nextVal.startsWith('[cmp-photo:'))
                && (
                    prev.startsWith('data:image/')
                    || prev.startsWith('[embedded-image:')
                    || prev.startsWith('[cmp-photo:')
                )
                && nextVal === prev
            ) {
                return;
            }
            if (
                nextVal.startsWith('[embedded-image:')
                && (prev.startsWith('data:image/') || prev.startsWith('[cmp-photo:'))
            ) {
                return;
            }
            if (prev === nextVal) {
                return;
            }
            while (currentSheetCells[rowIndex].length <= col) {
                currentSheetCells[rowIndex].push('');
            }
            currentSheetCells[rowIndex][col] = nextVal;
            changed += 1;
        });

        return changed;
    }

    function isProtectedSheetColumn(colIndex, cells) {
        const sheetCells = cells || currentSheetCells;
        const specCol = detectSpecColumnIndex(sheetCells);
        const criticalCol = detectCriticalColumnIndex(sheetCells, specCol);
        const qcCol = detectQcColumnIndex(sheetCells, specCol);
        return colIndex === specCol
            || colIndex === specCol - 1
            || colIndex === specCol - 2
            || (criticalCol !== null && colIndex === criticalCol)
            || (qcCol !== null && colIndex === qcCol);
    }

    function isColumnEditRowSelectable(rowIndex, col, isPriorityCol, isSpecCol) {
        if (rowIndex === null || rowIndex === undefined || Number.isNaN(rowIndex) || rowIndex < 0) {
            return false;
        }
        if (isPriorityCol && rowIndex === 0) {
            return false;
        }
        if (isCommRow(rowIndex, currentSheetCells) && !isSpecCol && !isPriorityCol) {
            return false;
        }
        return true;
    }

    function columnEditRowCheckboxHtml(rowIndex, selectable) {
        if (!selectable) {
            return `<td class="cd-col-edit-check"></td>`;
        }
        const checked = columnEditSelectedRows.has(rowIndex) ? ' checked' : '';
        return `<td class="cd-col-edit-check">
            <input type="checkbox" class="form-check-input cd-col-edit-row-check" data-row="${rowIndex}"
                title="Select row for bulk apply" aria-label="Select row ${rowIndex + 1}"${checked}>
        </td>`;
    }

    function setupColumnEditBulkValueControl(isPriorityCol) {
        const bulkValueWrap = document.getElementById('comparison-column-edit-bulk-value-wrap');
        if (!bulkValueWrap) {
            return;
        }
        const existing = document.getElementById('comparison-column-edit-bulk-value');
        const existingIsSelect = !!(existing && existing.tagName === 'SELECT');
        if (isPriorityCol && existingIsSelect) {
            return;
        }
        if (!isPriorityCol && existing && existing.tagName === 'INPUT' && existing.type === 'text') {
            return;
        }

        if (isPriorityCol) {
            bulkValueWrap.innerHTML = `<select id="comparison-column-edit-bulk-value" class="form-select form-select-sm cd-col-edit-bulk-value" title="Value to apply to selected rows">
                <option value="Normal">Normal</option>
                <option value="Important">Important</option>
                <option value="Critical" selected>Critical</option>
            </select>`;
        } else {
            bulkValueWrap.innerHTML = `<input type="text" id="comparison-column-edit-bulk-value" class="form-control form-control-sm cd-col-edit-bulk-value" value="" placeholder="Value for selected rows" title="Value to apply to selected rows">`;
        }
    }

    function refreshColumnEditBulkBar() {
        const col = columnEditTargetCol;
        const bulkBar = document.getElementById('comparison-column-edit-bulk-bar');
        const countEl = document.getElementById('comparison-column-edit-bulk-count');
        const applyBtn = document.getElementById('comparison-column-edit-bulk-apply-btn');
        const selectAll = document.getElementById('comparison-column-edit-select-all');
        if (!bulkBar) {
            return;
        }

        if (col === null || col === undefined || Number.isNaN(col)) {
            bulkBar.classList.add('d-none');
            return;
        }

        const selectableChecks = document.querySelectorAll('#comparison-column-edit-tbody .cd-col-edit-row-check');
        const selectedCount = columnEditSelectedRows.size;
        const selectableCount = selectableChecks.length;

        bulkBar.classList.remove('d-none');
        if (countEl) {
            countEl.textContent = selectedCount
                ? `${selectedCount} selected`
                : 'Select rows to apply';
        }
        if (applyBtn) {
            applyBtn.disabled = selectedCount === 0;
        }
        if (selectAll) {
            selectAll.disabled = selectableCount === 0;
            selectAll.checked = selectableCount > 0 && selectedCount === selectableCount;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < selectableCount;
        }

        document.querySelectorAll('#comparison-column-edit-tbody tr[data-edit-row]').forEach((tr) => {
            const rowIndex = parseInt(tr.dataset.editRow, 10);
            tr.classList.toggle('cd-col-edit-row-selected', columnEditSelectedRows.has(rowIndex));
        });
    }

    function syncColumnEditSelectedRowsFromDom() {
        columnEditSelectedRows = new Set();
        document.querySelectorAll('#comparison-column-edit-tbody .cd-col-edit-row-check:checked').forEach((check) => {
            const rowIndex = parseInt(check.dataset.row, 10);
            if (!Number.isNaN(rowIndex)) {
                columnEditSelectedRows.add(rowIndex);
            }
        });
        refreshColumnEditBulkBar();
    }

    function applyBulkValueToSelectedColumnEditRows() {
        const bulkField = document.getElementById('comparison-column-edit-bulk-value');
        if (!bulkField) {
            return;
        }
        const selected = [...columnEditSelectedRows].sort((a, b) => a - b);
        if (!selected.length) {
            setSheetStatus('Select one or more rows first.', true);
            return;
        }

        const col = columnEditTargetCol;
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);
        const isPriorityCol = (criticalCol !== null && col === criticalCol)
            || (qcCol !== null && col === qcCol);
        let nextVal = String(bulkField.value ?? '');
        if (isPriorityCol) {
            nextVal = normalizePriorityValue(nextVal);
        }

        let applied = 0;
        selected.forEach((rowIndex) => {
            const field = document.querySelector(
                `#comparison-column-edit-tbody .cd-col-edit-field[data-row="${rowIndex}"]:not([type="hidden"])`
            );
            if (!field || field.readOnly || field.disabled) {
                return;
            }
            if (field.tagName === 'SELECT') {
                const options = Array.from(field.options || []);
                const match = options.find(opt => opt.value === nextVal);
                if (!match) {
                    return;
                }
                field.value = match.value;
            } else {
                field.value = nextVal;
            }
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            applied += 1;
        });

        if (!applied) {
            setSheetStatus('No editable selected rows to update.', true);
            return;
        }
        setSheetStatus(`Applied "${nextVal}" to ${applied} selected row(s). Click Save column to keep changes.`, false);
    }

    function renderColumnEditModalRows() {
        const col = columnEditTargetCol;
        const tbody = document.getElementById('comparison-column-edit-tbody');
        if (col === null || col === undefined || Number.isNaN(col) || !tbody) {
            return;
        }

        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);
        const isPriorityCol = (criticalCol !== null && col === criticalCol)
            || (qcCol !== null && col === qcCol);
        const isSpecCol = col === specCol;

        // Drop stale selections after row count changes.
        columnEditSelectedRows = new Set(
            [...columnEditSelectedRows].filter((rowIndex) =>
                isColumnEditRowSelectable(rowIndex, col, isPriorityCol, isSpecCol)
                && rowIndex < currentSheetCells.length
            )
        );

        const parts = [];
        for (let r = 0; r < currentSheetCells.length; r++) {
            const row = currentSheetCells[r] || [];
            const rawValue = row[col] ?? '';
            const value = String(rawValue ?? '');
            const displayValue = value.startsWith('data:image/')
                ? `[embedded-image:${r}:${col}]`
                : value;
            let rowLabel = isSpecCol
                ? `Row ${r + 1}`
                : String(row[specCol] ?? '').trim();
            if (!rowLabel) {
                rowLabel = r === 0 ? 'Header' : `Row ${r + 1}`;
            }
            const selectable = isColumnEditRowSelectable(r, col, isPriorityCol, isSpecCol);
            const checkHtml = columnEditRowCheckboxHtml(r, selectable);
            const selectedClass = columnEditSelectedRows.has(r) ? ' cd-col-edit-row-selected' : '';

            if (isCommRow(r, currentSheetCells) && !isSpecCol && !isPriorityCol) {
                parts.push(`<tr data-edit-row="${r}" class="${selectedClass.trim()}">
                    ${checkHtml}
                    <td class="cd-col-edit-label">${escapeHtml(rowLabel)}</td>
                    <td class="cd-col-edit-value">
                        <input type="hidden" class="cd-col-edit-field" data-row="${r}" value="">
                        <span class="cd-col-edit-hint">Comm actions are managed in the sheet (not edited here).</span>
                    </td>
                </tr>`);
                continue;
            }

            if (isPriorityCol && r > 0) {
                const normalized = normalizePriorityValue(displayValue);
                const fieldHtml = `<select class="form-select form-select-sm cd-col-edit-field cd-col-edit-select" data-row="${r}">
                            <option value="Normal" ${normalized === 'Normal' ? 'selected' : ''}>Normal</option>
                            <option value="Important" ${normalized === 'Important' ? 'selected' : ''}>Important</option>
                            <option value="Critical" ${normalized === 'Critical' ? 'selected' : ''}>Critical</option>
                        </select>`;
                parts.push(`<tr data-edit-row="${r}" class="${selectedClass.trim()}">
                    ${checkHtml}
                    <td class="cd-col-edit-label">${escapeHtml(rowLabel)}</td>
                    <td class="cd-col-edit-value">${wrapFieldWithClipboardActions(fieldHtml)}</td>
                </tr>`);
                continue;
            }

            if (isPriorityCol && r === 0) {
                const fieldHtml = `<input type="text" class="form-control form-control-sm cd-col-edit-field cd-col-edit-input" data-row="${r}" value="${escapeHtmlAttr(displayValue)}" readonly>`;
                parts.push(`<tr data-edit-row="${r}">
                    ${checkHtml}
                    <td class="cd-col-edit-label">${escapeHtml(rowLabel)}</td>
                    <td class="cd-col-edit-value">${wrapFieldWithClipboardActions(fieldHtml)}</td>
                </tr>`);
                continue;
            }

            const isImage = isSheetImageUrl(displayValue);
            const hint = isImage && (
                String(displayValue).startsWith('[embedded-image:')
                || String(displayValue).startsWith('[cmp-photo:')
            )
                ? '<div class="cd-col-edit-hint mt-1">Embedded photo — leave as-is to keep, or paste an image URL to replace.</div>'
                : '';
            const fieldHtml = `<input type="text" class="form-control form-control-sm cd-col-edit-field cd-col-edit-input" data-row="${r}" value="${escapeHtmlAttr(displayValue)}" ${isImage ? 'placeholder="Image URL or keep embedded placeholder"' : ''}>`;
            parts.push(`<tr data-edit-row="${r}" class="${selectedClass.trim()}">
                ${checkHtml}
                <td class="cd-col-edit-label">${escapeHtml(rowLabel)}</td>
                <td class="cd-col-edit-value">${wrapFieldWithClipboardActions(fieldHtml)}${hint}</td>
            </tr>`);
        }
        tbody.innerHTML = parts.join('');
        setupColumnEditBulkValueControl(isPriorityCol);
        refreshColumnEditBulkBar();
    }

    function refreshColumnEditModalChrome() {
        const col = columnEditTargetCol;
        if (col === null || col === undefined || Number.isNaN(col)) {
            return;
        }
        const colLabel = getSheetColumnHeaderLabel(col);
        const titleEl = document.getElementById('comparisonColumnEditModalLabel');
        if (titleEl) {
            titleEl.innerHTML = `<i class="mdi mdi-pencil-outline me-1"></i> Edit column: ${escapeHtml(colLabel)} <span class="text-muted fw-normal">(${escapeHtml(columnLetter(col))})</span>`;
        }

        const colCount = Math.max(...currentSheetCells.map(row => (Array.isArray(row) ? row.length : 0)), 0);
        const canDeleteCol = colCount > 1 && !isProtectedSheetColumn(col);
        document.querySelectorAll('#comparison-column-edit-delete-col-btn-footer')
            .forEach((btn) => {
                btn.disabled = !canDeleteCol;
                btn.title = canDeleteCol
                    ? `Delete column ${colLabel}`
                    : (isProtectedSheetColumn(col)
                        ? 'Protected column (Amz / 5 Core / Spec / Critical / QC) cannot be deleted'
                        : 'Cannot delete the last column');
            });

        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);
        const isPriorityCol = (criticalCol !== null && col === criticalCol)
            || (qcCol !== null && col === qcCol);
        setupColumnEditBulkValueControl(isPriorityCol);
        refreshColumnEditBulkBar();
    }

    function openColumnEditModal(colIndex) {
        const col = parseInt(colIndex, 10);
        if (Number.isNaN(col) || col < 0) {
            return;
        }
        readCellsFromEditor({ expandImages: false });
        if (!currentSheetCells.length) {
            setSheetStatus('Load a comparison sheet first.', true);
            return;
        }

        columnEditTargetCol = col;
        columnEditSelectedRows = new Set();
        selectedSheetCol = col;
        applySheetSelectionHighlight();
        refreshColumnEditModalChrome();
        renderColumnEditModalRows();

        const modalEl = document.getElementById('comparisonColumnEditModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    }

    function addBlankColumnFromColumnEditModal() {
        const col = columnEditTargetCol;
        if (col === null || col === undefined || Number.isNaN(col)) {
            return;
        }

        applyColumnEditModalDrafts();
        readCellsFromEditor({ expandImages: false });

        const insertAt = col + 1;
        currentSheetCells = currentSheetCells.map(row => {
            const next = Array.isArray(row) ? row.slice() : [String(row || '')];
            next.splice(insertAt, 0, '');
            return next;
        });
        currentSheetFormats.cols = shiftNumericFormatMap(currentSheetFormats.cols, insertAt, 1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, insertAt, 'col', 1);

        if (selectedSheetCell && selectedSheetCell.col >= insertAt) {
            selectedSheetCell = { row: selectedSheetCell.row, col: selectedSheetCell.col + 1 };
        }

        columnEditTargetCol = insertAt;
        selectedSheetCol = insertAt;

        renderSheetEditor(currentSheetCells, { migrateDimWt: false, sortByPrice: false });
        refreshColumnEditModalChrome();
        renderColumnEditModalRows();
        scheduleAutoSaveComparisonSheet(400, { rerender: false, refreshTable: false });
        setSheetStatus(`Blank column inserted at ${columnLetter(insertAt)}.`, false);

        window.setTimeout(function () {
            const input = document.querySelector('#comparison-column-edit-tbody .cd-col-edit-field:not([type="hidden"]):not([readonly])');
            if (input && typeof input.focus === 'function') {
                input.focus();
            }
        }, 50);
    }

    function deleteCurrentColumnFromColumnEditModal() {
        const col = columnEditTargetCol;
        if (col === null || col === undefined || Number.isNaN(col)) {
            return;
        }

        const colCount = Math.max(...currentSheetCells.map(row => (Array.isArray(row) ? row.length : 0)), 0);
        if (colCount <= 1) {
            setSheetStatus('Cannot delete the last column.', true);
            return;
        }
        if (isProtectedSheetColumn(col)) {
            setSheetStatus('Protected column (Amz / 5 Core / Spec / Critical / QC) cannot be deleted.', true);
            return;
        }

        applyColumnEditModalDrafts();
        readCellsFromEditor({ expandImages: false });

        const deletedLabel = getSheetColumnHeaderLabel(col);
        currentSheetCells = currentSheetCells.map(row => {
            const next = Array.isArray(row) ? row.slice() : [String(row || '')];
            if (next.length > col) {
                next.splice(col, 1);
            }
            return next;
        });
        currentSheetFormats.cols = shiftNumericFormatMap(currentSheetFormats.cols, col, -1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, col, 'col', -1);

        if (selectedSheetCell && selectedSheetCell.col === col) {
            selectedSheetCell = null;
        } else if (selectedSheetCell && selectedSheetCell.col > col) {
            selectedSheetCell = { row: selectedSheetCell.row, col: selectedSheetCell.col - 1 };
        }

        const nextColCount = Math.max(...currentSheetCells.map(row => (Array.isArray(row) ? row.length : 0)), 0);
        if (!nextColCount) {
            columnEditTargetCol = null;
            selectedSheetCol = null;
            renderSheetEditor(currentSheetCells, { migrateDimWt: false, sortByPrice: false });
            scheduleAutoSaveComparisonSheet(300, { rerender: false, refreshTable: false });
            const modalEl = document.getElementById('comparisonColumnEditModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
            setSheetStatus(`Column ${deletedLabel} deleted.`, false);
            return;
        }

        columnEditTargetCol = Math.min(col, nextColCount - 1);
        selectedSheetCol = columnEditTargetCol;

        renderSheetEditor(currentSheetCells, { migrateDimWt: false, sortByPrice: false });
        refreshColumnEditModalChrome();
        renderColumnEditModalRows();
        scheduleAutoSaveComparisonSheet(400, { rerender: false, refreshTable: false });
        setSheetStatus(`Column ${deletedLabel} deleted.`, false);
    }

    function saveColumnEditModal() {
        const col = columnEditTargetCol;
        if (col === null || col === undefined || Number.isNaN(col)) {
            setSheetStatus('No column selected for edit.', true);
            return;
        }

        readCellsFromEditor({ expandImages: false });
        const changed = applyColumnEditModalDrafts();

        if (!changed) {
            const modalEl = document.getElementById('comparisonColumnEditModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
            setSheetStatus('No changes in this column.', false);
            return;
        }

        // Light rebuild for this column’s display (priority dots / links / photos).
        renderSheetEditor(currentSheetCells, { migrateDimWt: false, sortByPrice: false });
        scheduleAutoSaveComparisonSheet(300, { rerender: false, refreshTable: false });
        setSheetStatus(`Updated ${changed} cell(s) in column ${getSheetColumnHeaderLabel(col)}.`, false);

        const modalEl = document.getElementById('comparisonColumnEditModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        columnEditTargetCol = null;
    }

    function ensureCriticalColumn(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        const specCol = detectSpecColumnIndex(cells);
        let criticalCol = detectCriticalColumnIndex(cells, specCol);
        let insertedAt = null;
        if (criticalCol === null) {
            // Insert before QC when QC already sits right after Spec.
            cells = insertSheetColumnAt(cells, specCol + 1);
            criticalCol = specCol + 1;
            insertedAt = criticalCol;
        }
        if (!cells[0]) {
            cells[0] = [];
        }
        cells[0][criticalCol] = 'Critical';
        return { cells, insertedAt, criticalCol };
    }

    function ensureQcColumn(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        const specCol = detectSpecColumnIndex(cells);
        const criticalCol = detectCriticalColumnIndex(cells, specCol);
        let qcCol = detectQcColumnIndex(cells, specCol);
        let insertedAt = null;
        if (qcCol === null) {
            const insertAt = (criticalCol !== null ? criticalCol : specCol) + 1;
            cells = insertSheetColumnAt(cells, insertAt);
            qcCol = insertAt;
            insertedAt = qcCol;
        }
        if (!cells[0]) {
            cells[0] = [];
        }
        cells[0][qcCol] = 'QC';
        return { cells, insertedAt, qcCol };
    }

    function getFirstSupplierColumnIndex(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        const qcCol = detectQcColumnIndex(cells, specCol);
        if (qcCol !== null) {
            return qcCol + 1;
        }
        const criticalCol = detectCriticalColumnIndex(cells, specCol);
        if (criticalCol !== null) {
            return criticalCol + 1;
        }
        return specCol + 1;
    }

    function normalizeSpecLabel(value) {
        return String(value || '')
            .replace(/[\u00a0\u2000-\u200b]/g, ' ')
            .replace(/[\/⁄∕]/g, '/')
            .replace(/[×✕✖]/g, 'x')
            .trim()
            .toLowerCase()
            .replace(/\s+/g, ' ');
    }

    function isObsoleteCombinedLwhLabel(label, kind) {
        const text = normalizeSpecLabel(label);
        if (!text) return false;
        if (kind === 'item') {
            return /^item\s*l\s*\/\s*w\s*\/\s*h(\b|\s*\(|$)/.test(text);
        }
        if (kind === 'ctn') {
            return /^ctn\s*l\s*\/\s*w\s*\/\s*h(\b|\s*\(|$)/.test(text);
        }
        return /^((item|ctn)\s*l\s*\/\s*w\s*\/\s*h)(\b|\s*\(|$)/.test(text);
    }

    function parseCombinedLwhParts(value) {
        const text = String(value == null ? '' : value).trim();
        if (!text) {
            return ['', '', ''];
        }
        const parts = text
            .split(/\s*[×xX*\/]\s*/)
            .map(part => String(part || '').trim())
            .filter((part, index, arr) => !(part === '' && index > 0 && index < arr.length - 1));
        return [parts[0] || '', parts[1] || '', parts[2] || ''];
    }

    function isInnerPkgSectionLabel(label) {
        const text = normalizeSpecLabel(label);
        if (!text) return false;
        if (text === normalizeSpecLabel(INNER_PKG_SECTION.header)) return true;
        if (isObsoleteCombinedLwhLabel(label, 'item')) return true;
        return INNER_PKG_SECTION.rows.some(row => normalizeSpecLabel(row.label) === text);
    }

    function isCtnPkgSectionLabel(label) {
        const text = normalizeSpecLabel(label);
        if (!text) return false;
        if (text === normalizeSpecLabel(CTN_PKG_SECTION.header)) return true;
        if (isObsoleteCombinedLwhLabel(label, 'ctn')) return true;
        return CTN_PKG_SECTION.rows.some(row => normalizeSpecLabel(row.label) === text);
    }

    function isPkgSectionHeaderLabel(label) {
        const text = normalizeSpecLabel(label);
        return text === normalizeSpecLabel(INNER_PKG_SECTION.header)
            || text === normalizeSpecLabel(CTN_PKG_SECTION.header);
    }

    function findExactSpecRowIndex(cells, label, specCol) {
        const needle = normalizeSpecLabel(label);
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            if (normalizeSpecLabel((cells[rowIndex] || [])[specCol]) === needle) {
                return rowIndex;
            }
        }
        return null;
    }

    function findSpecRowIndexWithAliases(cells, label, aliases, specCol) {
        let rowIndex = findExactSpecRowIndex(cells, label, specCol);
        if (rowIndex !== null) {
            return rowIndex;
        }
        const aliasList = Array.isArray(aliases) ? aliases : [];
        for (let i = 0; i < aliasList.length; i++) {
            rowIndex = findExactSpecRowIndex(cells, aliasList[i], specCol);
            if (rowIndex !== null) {
                return rowIndex;
            }
        }
        return null;
    }

    function splitObsoleteCombinedLwhRows(cells, section, specCol) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        specCol = specCol ?? detectSpecColumnIndex(cells);
        const kind = normalizeSpecLabel(section.header) === 'inner pkg' ? 'item' : 'ctn';
        const splitLabels = kind === 'item'
            ? ['Item L (IN)', 'Item W (IN)', 'Item H (IN)']
            : ['CTN L (CM)', 'CTN W (CM)', 'CTN H (CM)'];
        const amazonCol = Math.max(0, specCol - 2);
        const fiveCoreCol = Math.max(0, specCol - 1);
        const colCount = Math.max(...cells.map(row => row.length), specCol + 1, 6);
        const obsoleteExact = new Set(
            (Array.isArray(section.obsoleteLabels) ? section.obsoleteLabels : [])
                .map(label => normalizeSpecLabel(label))
        );

        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = (cells[rowIndex] || [])[specCol];
            const normalized = normalizeSpecLabel(label);
            if (!obsoleteExact.has(normalized) && !isObsoleteCombinedLwhLabel(label, kind)) {
                continue;
            }

            // Skip if the three split rows already exist nearby.
            const alreadySplit = splitLabels.every(splitLabel => findExactSpecRowIndex(cells, splitLabel, specCol) !== null);
            if (alreadySplit) {
                cells.splice(rowIndex, 1);
                rowIndex -= 1;
                continue;
            }

            const parts = parseCombinedLwhParts(
                (cells[rowIndex] || [])[fiveCoreCol]
                || (cells[rowIndex] || [])[amazonCol]
                || ''
            );
            const replacement = splitLabels.map((splitLabel, idx) => {
                const newRow = Array.from({ length: colCount }, () => '');
                newRow[specCol] = splitLabel;
                // Defaults belong in 5 Core only; Amazon/suppliers stay blank until edited.
                newRow[fiveCoreCol] = parts[idx] || '';
                return newRow;
            });
            cells.splice(rowIndex, 1, ...replacement);
            rowIndex += replacement.length - 1;
        }

        return cells;
    }

    function ensureDimWtPkgSection(cells, section, specCol) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        specCol = specCol ?? detectSpecColumnIndex(cells);
        cells = splitObsoleteCombinedLwhRows(cells, section, specCol);
        const colCount = Math.max(...cells.map(row => row.length), specCol + 1, 6);
        const ensureLabels = [
            { label: section.header, aliases: [] },
            ...section.rows.map(row => ({ label: row.label, aliases: row.aliases || [] })),
        ];

        let insertAt = findExactSpecRowIndex(cells, section.header, specCol);
        if (insertAt === null) {
            insertAt = cells.length - 1;
        }

        ensureLabels.forEach(entry => {
            let rowIndex = findSpecRowIndexWithAliases(cells, entry.label, entry.aliases, specCol);
            if (rowIndex === null) {
                const newRow = Array.from({ length: colCount }, () => '');
                newRow[specCol] = entry.label;
                insertAt = Math.min(insertAt + 1, cells.length);
                cells.splice(insertAt, 0, newRow);
                rowIndex = insertAt;
            } else {
                cells[rowIndex][specCol] = entry.label;
                insertAt = rowIndex;
            }
            while (cells[rowIndex].length < colCount) {
                cells[rowIndex].push('');
            }
        });

        return cells;
    }

    function ensureDimWtPkgSections(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        const specCol = detectSpecColumnIndex(cells);
        cells = ensureDimWtPkgSection(cells, INNER_PKG_SECTION, specCol);
        cells = ensureDimWtPkgSection(cells, CTN_PKG_SECTION, specCol);
        return cells;
    }

    function sheetNeedsDimWtMigration(cells) {
        cells = cells || [];
        const specCol = detectSpecColumnIndex(cells);
        if (findExactSpecRowIndex(cells, INNER_PKG_SECTION.header, specCol) === null
            || findExactSpecRowIndex(cells, CTN_PKG_SECTION.header, specCol) === null) {
            return true;
        }
        if (findExactSpecRowIndex(cells, 'Item L (IN)', specCol) === null
            || findExactSpecRowIndex(cells, 'CTN L (CM)', specCol) === null) {
            return true;
        }
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = (cells[rowIndex] || [])[specCol];
            if (isObsoleteCombinedLwhLabel(label, 'item') || isObsoleteCombinedLwhLabel(label, 'ctn')) {
                return true;
            }
        }
        return false;
    }

    function applyDimWtDataToSheet(cells, dimWt) {
        cells = cells || [];
        // Only run expensive section migration when headers / split rows are missing.
        if (sheetNeedsDimWtMigration(cells)) {
            cells = ensureDimWtPkgSections(cells);
        }
        const data = dimWt && typeof dimWt === 'object' ? dimWt : {};
        const specCol = detectSpecColumnIndex(cells);
        const fiveCoreCol = Math.max(0, specCol - 1);
        const criticalCol = detectCriticalColumnIndex(cells, specCol);
        const qcCol = detectQcColumnIndex(cells, specCol);
        const protectedCols = new Set(
            [specCol, fiveCoreCol, criticalCol, qcCol].filter(col => col !== null && col !== undefined)
        );

        const writeValue = (label, value) => {
            const rowIndex = findExactSpecRowIndex(cells, label, specCol);
            if (rowIndex === null) {
                return;
            }
            const text = value == null ? '' : String(value);
            const row = cells[rowIndex];
            while (row.length <= Math.max(fiveCoreCol, specCol)) {
                row.push('');
            }

            // Dim/Wt defaults fill 5 Core only. Other columns stay blank unless user-edited.
            row[fiveCoreCol] = text;

            // Clear mirrored auto-fill leftovers (Amazon/suppliers) that still match the default.
            for (let c = 0; c < row.length; c++) {
                if (protectedCols.has(c)) {
                    continue;
                }
                const cellVal = String(row[c] ?? '').trim();
                if (cellVal === '') {
                    continue;
                }
                if (text !== '' && cellVal === text) {
                    row[c] = '';
                }
            }
        };

        writeValue(INNER_PKG_SECTION.header, '');
        INNER_PKG_SECTION.rows.forEach(row => writeValue(row.label, data[row.key] || ''));
        writeValue(CTN_PKG_SECTION.header, '');
        CTN_PKG_SECTION.rows.forEach(row => writeValue(row.label, data[row.key] || ''));

        return cells;
    }

    function applyDimWtSectionFormats(cells, formats) {
        formats = normalizeSheetFormats(formats || currentSheetFormats);
        const specCol = detectSpecColumnIndex(cells || currentSheetCells);
        (cells || currentSheetCells).forEach((row, rowIndex) => {
            const label = (row || [])[specCol];
            if (isInnerPkgSectionLabel(label)) {
                formats.rows[String(rowIndex)] = INNER_PKG_SECTION_COLOR;
            } else if (isCtnPkgSectionLabel(label)) {
                formats.rows[String(rowIndex)] = CTN_PKG_SECTION_COLOR;
            }
        });
        return formats;
    }

    function updateQcIssuesBadge(qcIssues) {
        const btn = document.getElementById('comparison-cd-qc-issues-btn');
        const dot = document.getElementById('comparison-cd-qc-issues-dot');
        if (!btn) {
            return;
        }
        const data = qcIssues && typeof qcIssues === 'object' ? qcIssues : {};
        const hasData = !!data.has_data;
        btn.classList.toggle('has-qc-data', hasData);
        btn.classList.toggle('no-qc-data', !hasData);
        btn.title = hasData
            ? 'QC issues recorded — click to view'
            : 'No QC issues recorded — click to view';
        if (dot) {
            dot.className = 'badge rounded-pill ms-1 ' + (hasData ? 'bg-success' : 'bg-danger');
            dot.textContent = '•';
        }
    }

    function qcIssuesSearchIconHtml(value) {
        const text = String(value || '').trim();
        const hasData = text !== '';
        const color = hasData ? '#28a745' : '#dc3545';
        const title = hasData ? text : 'No data';
        return `<i class="fas fa-search cd-qc-search-icon" style="color:${color};" title="${escapeHtmlAttr(title)}" data-qc-text="${escapeHtmlAttr(text)}" data-qc-title="${escapeHtmlAttr(hasData ? 'Details' : 'No data')}"></i>`;
    }

    function renderQcIssuesModal(qcIssues) {
        const data = qcIssues && typeof qcIssues === 'object' ? qcIssues : {};
        const skuLabel = document.getElementById('comparison-qc-issues-sku-label');
        const tbody = document.getElementById('comparison-qc-issues-tbody');
        const historyEl = document.getElementById('comparison-qc-issues-history');
        if (skuLabel) {
            skuLabel.textContent = data.sku || currentCdRow?.sku || '—';
        }
        if (!tbody) {
            return;
        }

        const problemHtml = qcIssuesSearchIconHtml(data.problem_issue);
        const suggestionHtml = qcIssuesSearchIconHtml(data.suggestion_improve);

        let imageHtml = '';
        if (data.image_path) {
            const kb = data.image_size_kb != null ? `${data.image_size_kb} KB` : 'View image';
            imageHtml = `<img src="${escapeHtmlAttr(data.image_path)}" class="cd-qc-issue-thumb" alt="QC issue" title="${escapeHtmlAttr(kb)}" data-qc-image="${escapeHtmlAttr(data.image_path)}">`;
        } else {
            imageHtml = `<button type="button" class="btn btn-sm btn-outline-secondary cd-qc-media-btn" disabled title="No image"><i class="fas fa-camera"></i></button>`;
        }

        let videoHtml = '';
        if (data.video_path) {
            const kb = data.video_size_kb != null ? `${data.video_size_kb} KB` : 'Play video';
            videoHtml = `<a href="${escapeHtmlAttr(data.video_path)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-success cd-qc-media-btn" title="${escapeHtmlAttr(kb)}"><i class="fas fa-play"></i></a>`;
        } else {
            videoHtml = `<button type="button" class="btn btn-sm btn-outline-secondary cd-qc-media-btn" disabled title="No video"><i class="fas fa-video"></i></button>`;
        }

        tbody.innerHTML = `<tr>
            <td>${problemHtml}</td>
            <td>${suggestionHtml}</td>
            <td>${imageHtml}</td>
            <td>${videoHtml}</td>
        </tr>`;

        if (historyEl) {
            historyEl.textContent = data.user_history_label
                ? `Last update: ${data.user_history_label}`
                : 'No QC Masters history for this SKU.';
        }
    }

    function openComparisonQcIssuesModal() {
        renderQcIssuesModal(currentQcIssuesData || {
            sku: currentCdRow?.sku || '',
            has_data: false,
        });
        comparisonQcIssuesModal?.show();
    }

    function updateReviewsBadge(reviewsData) {
        const btn = document.getElementById('comparison-cd-reviews-btn');
        const inner = document.getElementById('comparison-cd-reviews-badge-inner');
        if (!btn || !inner) {
            return;
        }

        const data = reviewsData && typeof reviewsData === 'object' ? reviewsData : {};
        currentReviewsData = data;
        const rawR = data.rating;
        const rawRev = data.reviews;
        const rVal = parseFloat(rawR);
        const hasRating = rawR !== null && rawR !== undefined && String(rawR).trim() !== '' && Number.isFinite(rVal);
        const revParsed = parseInt(String(rawRev == null ? '' : rawRev).replace(/,/g, ''), 10);
        const hasReviews = Number.isFinite(revParsed) && revParsed >= 0 && String(rawRev).trim() !== '';

        const parent = String(data.parent || currentCdRow?.parent || '').trim();
        const sku = String(data.sku || currentCdRow?.sku || '').trim();
        const amazonUrl = String(data.amazon_reviews_url || data.amazon_buyer_url || '').trim();

        btn.classList.remove('cd-reviews-hot');
        btn.title = 'Rating & reviews from Forecast Analysis (Jungle Scout)';

        if (!hasRating && !hasReviews) {
            inner.innerHTML = '<span class="cd-reviews-rating-line text-muted"><i class="bi bi-star"></i> Reviews</span>';
        } else {
            let starColor = '#dc2626';
            if (hasRating) {
                if (rVal >= 4.5) {
                    starColor = '#9d174d';
                    btn.classList.add('cd-reviews-hot');
                } else if (rVal >= 4) {
                    starColor = '#15803d';
                } else if (rVal >= 3.5) {
                    starColor = '#a16207';
                } else {
                    starColor = '#dc2626';
                }
            }

            const ratingLine = hasRating
                ? (Number.isInteger(rVal) ? String(rVal) : rVal.toFixed(1))
                : '—';
            const revLine = hasReviews
                ? `(${revParsed.toLocaleString('en-US')})`
                : (hasRating ? '(0)' : '');
            const revMuted = hasRating && rVal >= 4.5 ? '#861657' : '#5c5c5c';

            inner.innerHTML =
                `<span class="cd-reviews-rating-line" style="color:${starColor};">` +
                `<i class="bi bi-star-fill" style="font-size:0.72rem;"></i>` +
                `<span>${ratingLine}</span></span>` +
                (revLine ? `<span class="cd-reviews-count-line" style="color:${revMuted};">${revLine}</span>` : '');

            btn.title = `Rating ${hasRating ? ratingLine : '—'} · Reviews ${hasReviews ? revParsed.toLocaleString('en-US') : '—'} (Forecast Analysis / Jungle Scout)`;
        }

        const graphDot = btn.querySelector('[data-reviews-action="graph"]');
        const intelDot = btn.querySelector('[data-reviews-action="intel"]');
        const amazonDot = btn.querySelector('[data-reviews-action="amazon"]');

        if (graphDot) {
            const canGraph = !!(sku || parent);
            graphDot.classList.toggle('is-disabled', !canGraph);
            graphDot.title = canGraph
                ? `Lifetime rating graph${parent ? ' (parent)' : ' (SKU)'}`
                : 'No SKU/parent for graph';
        }
        if (intelDot) {
            const canIntel = !!parent;
            intelDot.classList.toggle('is-disabled', !canIntel);
            intelDot.title = canIntel
                ? `Open Review Intelligence for parent ${parent}`
                : 'No parent available for Review Intelligence';
        }
        if (amazonDot) {
            const canAmazon = !!amazonUrl;
            amazonDot.classList.toggle('is-disabled', !canAmazon);
            amazonDot.title = canAmazon
                ? 'Open Amz buyer reviews'
                : 'No Amz buyer/ASIN link for this SKU';
        }

        updateRoiAmzReviewsBadge();
    }

    let comparisonReviewsChart = null;
    let comparisonReviewsChartDays = 0;

    function openComparisonReviewsGraph() {
        const data = currentReviewsData || {};
        const parent = String(data.parent || currentCdRow?.parent || '').trim();
        const sku = String(data.sku || currentCdRow?.sku || '').trim();
        if (!parent && !sku) {
            return;
        }

        comparisonReviewsChartDays = 0;
        const rangeEl = document.getElementById('comparison-reviews-chart-range');
        if (rangeEl) rangeEl.value = '0';

        const titleEl = document.getElementById('comparisonReviewsChartModalLabel');
        const label = parent || sku;
        if (titleEl) {
            titleEl.innerHTML = `<i class="fas fa-chart-line me-1"></i> Rating status — ${escapeHtml(label)}${parent ? ' (Parent)' : ''} · Lifetime`;
        }

        const modalEl = document.getElementById('comparisonReviewsChartModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
        loadComparisonReviewsChart();
    }

    function loadComparisonReviewsChart() {
        const loading = document.getElementById('comparison-reviews-chart-loading');
        const container = document.getElementById('comparison-reviews-chart-container');
        const noData = document.getElementById('comparison-reviews-chart-nodata');
        if (loading) loading.style.display = '';
        if (container) container.style.display = 'none';
        if (noData) noData.style.display = 'none';

        const data = currentReviewsData || {};
        const parent = String(data.parent || currentCdRow?.parent || '').trim();
        const sku = String(data.sku || currentCdRow?.sku || '').trim();
        const params = new URLSearchParams();
        params.set('metric', 'rating');
        params.set('days', String(comparisonReviewsChartDays || 0));
        if (parent) {
            params.set('parent', parent);
        } else {
            params.set('sku', sku);
        }

        fetch(cvrMasterChartDataUrl + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(response => {
                if (loading) loading.style.display = 'none';
                const points = response && response.success && Array.isArray(response.data) ? response.data : [];
                if (!points.length) {
                    if (noData) noData.style.display = '';
                    return;
                }
                if (container) container.style.display = '';
                renderComparisonReviewsChart(points);
            })
            .catch(() => {
                if (loading) loading.style.display = 'none';
                if (noData) noData.style.display = '';
            });
    }

    function renderComparisonReviewsChart(points) {
        const el = document.getElementById('comparison-reviews-chart');
        if (!el || typeof Highcharts === 'undefined') {
            return;
        }
        const categories = points.map(p => p.date);
        const values = points.map(p => {
            const v = parseFloat(p.value);
            return Number.isFinite(v) ? Math.round(v * 100) / 100 : null;
        });

        if (comparisonReviewsChart) {
            try { comparisonReviewsChart.destroy(); } catch (e) {}
            comparisonReviewsChart = null;
        }

        comparisonReviewsChart = Highcharts.chart(el, {
            chart: { type: 'line', height: 260, backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            xAxis: { categories, tickInterval: Math.max(1, Math.floor(categories.length / 8)) },
            yAxis: {
                title: { text: 'Rating' },
                min: 0,
                max: 5,
                tickInterval: 0.5,
            },
            legend: { enabled: false },
            tooltip: {
                pointFormatter: function () {
                    return `<b>${Number(this.y).toFixed(1)}</b> stars`;
                },
            },
            plotOptions: {
                line: {
                    marker: { enabled: true, radius: 3 },
                    color: '#e83e8c',
                    lineWidth: 2,
                },
            },
            series: [{ name: 'Rating', data: values }],
        });
    }

    function openComparisonReviewsIntelligence() {
        const parent = String((currentReviewsData && currentReviewsData.parent) || currentCdRow?.parent || '').trim();
        if (!parent) {
            return;
        }
        const url = reviewsIntelligenceUrl + '?parent=' + encodeURIComponent(parent);
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function openComparisonAmazonReviews() {
        const data = currentReviewsData || {};
        const url = String(data.amazon_reviews_url || data.amazon_buyer_url || '').trim();
        if (!url) {
            return;
        }
        window.open(url, '_blank', 'noopener,noreferrer');
    }

    function handleReviewsBadgeAction(action) {
        if (action === 'graph') {
            openComparisonReviewsGraph();
        } else if (action === 'intel') {
            openComparisonReviewsIntelligence();
        } else if (action === 'amazon') {
            openComparisonAmazonReviews();
        }
    }

    function getSelectedPriorityFilters(filterCol) {
        const checked = Array.from(
            document.querySelectorAll(`.cd-priority-filter-check[data-filter-col="${filterCol}"]:checked`)
        ).map(el => normalizePriorityValue(el.value));
        return new Set(checked);
    }

    function syncPriorityFilterBadgeStyles() {
        document.querySelectorAll('.cd-priority-filter-item').forEach(label => {
            const input = label.querySelector('.cd-priority-filter-check');
            label.classList.toggle('is-checked', !!(input && input.checked));
        });

        ['critical', 'qc'].forEach(filterCol => {
            const checked = Array.from(
                document.querySelectorAll(`.cd-priority-filter-check[data-filter-col="${filterCol}"]:checked`)
            ).map(el => normalizePriorityValue(el.value));
            const summaryEl = document.querySelector(`[data-filter-summary="${filterCol}"]`);
            const btn = document.getElementById(
                filterCol === 'critical' ? 'comparison-cd-critical-filter-btn' : 'comparison-cd-qc-filter-btn'
            );
            let summary = 'All';
            if (!checked.length) {
                summary = 'None';
            } else if (checked.length < 3) {
                summary = String(checked.length);
            }
            if (summaryEl) {
                summaryEl.textContent = summary;
            }
            btn?.classList.toggle('active-filter', checked.length > 0 && checked.length < 3);
        });
    }

    function rowMatchesPriorityFilters(rowIndex, cells, criticalCol, qcCol, criticalAllowed, qcAllowed) {
        // Keep the stamped header row visible so column labels stay in place.
        if (rowIndex === 0) {
            return true;
        }

        if (criticalCol !== null && criticalAllowed.size > 0 && criticalAllowed.size < 3) {
            const criticalValue = normalizePriorityValue((cells[rowIndex] || [])[criticalCol]);
            if (!criticalAllowed.has(criticalValue)) {
                return false;
            }
        }

        if (qcCol !== null && qcAllowed.size > 0 && qcAllowed.size < 3) {
            const qcValue = normalizePriorityValue((cells[rowIndex] || [])[qcCol]);
            if (!qcAllowed.has(qcValue)) {
                return false;
            }
        }

        return true;
    }

    function applyPriorityRowFilters() {
        syncPriorityFilterBadgeStyles();
        const body = document.getElementById('comparison-cd-sheet-body');
        if (!body) {
            return;
        }

        const cells = currentSheetCells || [];
        const specCol = detectSpecColumnIndex(cells);
        const criticalCol = detectCriticalColumnIndex(cells, specCol);
        const qcCol = detectQcColumnIndex(cells, specCol);
        const criticalAllowed = getSelectedPriorityFilters('critical');
        const qcAllowed = getSelectedPriorityFilters('qc');

        Array.from(body.children).forEach((tr, rowIndex) => {
            if (!tr || tr.tagName !== 'TR') {
                return;
            }
            const visible = rowMatchesPriorityFilters(
                rowIndex,
                cells,
                criticalCol,
                qcCol,
                criticalAllowed,
                qcAllowed
            );
            tr.style.display = visible ? '' : 'none';
        });
    }

    function isCommDataCell(rowIndex, colIndex) {
        if (isSheetSpecColumn(colIndex) || isSheetPriorityColumn(colIndex) || !isCommRow(rowIndex, currentSheetCells)) {
            return false;
        }
        return true;
    }

    function getSupplierNameForColumn(colIndex, cells) {
        const sheetCells = cells || currentSheetCells;
        const specCol = detectSpecColumnIndex(sheetCells);
        const supplierRowIndex = findSupplierNameRowIndex(sheetCells, specCol);
        if (supplierRowIndex === null) {
            return '';
        }
        return String((sheetCells[supplierRowIndex] || [])[colIndex] || '').trim();
    }

    function countNamedSupplierColumns(cells) {
        const sheetCells = cells || currentSheetCells || [];
        if (!sheetCells.length) {
            return 0;
        }
        const specCol = detectSpecColumnIndex(sheetCells);
        const firstSupplierCol = getFirstSupplierColumnIndex(sheetCells, specCol);
        const colCount = Math.max(...sheetCells.map(row => (Array.isArray(row) ? row.length : 0)), 0);
        let count = 0;
        for (let c = firstSupplierCol; c < colCount; c++) {
            if (getSupplierNameForColumn(c, sheetCells)) {
                count++;
            }
        }
        return count;
    }

    function updateSupplierCountBadge(cells) {
        const countEl = document.getElementById('comparison-cd-supplier-count');
        const btn = document.getElementById('comparison-cd-autopopulate-suppliers-btn');
        if (!countEl) {
            return;
        }
        const count = countNamedSupplierColumns(cells);
        countEl.textContent = String(count);
        countEl.classList.toggle('has-suppliers', count > 0);
        countEl.classList.toggle('bg-light', count === 0);
        countEl.classList.toggle('text-dark', count === 0);
        if (btn) {
            btn.title = count > 0
                ? `${count} supplier(s) in this sheet — click to add/update from supplier.list for this category`
                : 'Add suppliers into blank columns from column D; update C-link preloaded names when they match supplier.list for this category';
        }
    }

    function cacheComparisonSuppliers(suppliers) {
        comparisonSuppliersByName = {};
        (suppliers || []).forEach(function (supplier) {
            const key = normalizeSupplierNameKey(supplier?.name);
            if (key) {
                comparisonSuppliersByName[key] = supplier;
            }
        });
    }

    function loadComparisonSuppliersForCategory(category) {
        // Accept a single name, a comma-joined string, or an array of category names,
        // so suppliers from ALL of a SKU's categories are cached (not just one).
        let names = Array.isArray(category)
            ? category.map(function (c) { return String(c || '').trim(); })
            : String(category || '').split(',').map(function (s) { return s.trim(); });
        names = names.filter(Boolean);
        if (!names.length || !currentCdRow?.sku) {
            return Promise.resolve([]);
        }

        const params = new URLSearchParams();
        params.set('sku', currentCdRow.sku || '');
        params.set('category', names.join(', '));
        params.set('by_category', '1');
        names.forEach(function (name) { params.append('categories[]', name); });

        return fetch(`${suppliersForSkuUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            const suppliers = data.success ? (data.suppliers || []) : [];
            cacheComparisonSuppliers(suppliers);
            return suppliers;
        })
        .catch(function () {
            return [];
        });
    }

    function commCellEditorHtml(rowIndex, colIndex, supplierName) {
        const name = String(supplierName || '').trim();
        if (!name) {
            return `<div class="cd-sheet-comm-cell cd-sheet-comm-cell-empty"><span class="text-muted">-</span></div>`;
        }
        return `<div class="cd-sheet-comm-cell">
            <button type="button" class="cd-sheet-comm-btn" data-row="${rowIndex}" data-col="${colIndex}"
                data-supplier-name="${escapeHtmlAttr(name)}"
                title="Communication: ${escapeHtmlAttr(name)}" aria-label="Open communication details for ${escapeHtmlAttr(name)}">
                <i class="fas fa-comments"></i>
            </button>
        </div>`;
    }

    function openComparisonCommModal(supplierName) {
        if (!commModal) {
            return;
        }

        const name = String(supplierName || '').trim();
        let supplier = comparisonSuppliersByName[normalizeSupplierNameKey(name)] || null;

        if (!supplier && name) {
            const categoryNames = comparisonCdRowCategoryNames();
            if (categoryNames.length && Object.keys(comparisonSuppliersByName).length === 0) {
                loadComparisonSuppliersForCategory(categoryNames).then(function () {
                    openComparisonCommModal(supplierName);
                });
                return;
            }
        }

        const links = supplier?.platform_links || [];
        const nameEl = document.getElementById('comparison-comm-supplier-name');
        const companyEl = document.getElementById('comparison-comm-supplier-company');
        const gridEl = document.getElementById('comparison-comm-platforms');
        const emptyEl = document.getElementById('comparison-comm-empty');

        if (nameEl) {
            nameEl.textContent = name || 'Supplier';
        }
        if (companyEl) {
            const company = String(supplier?.company || '').trim();
            companyEl.textContent = company;
            companyEl.classList.toggle('d-none', !company);
        }

        if (!links.length) {
            if (gridEl) {
                gridEl.innerHTML = '';
                gridEl.classList.add('d-none');
            }
            emptyEl?.classList.remove('d-none');
        } else {
            emptyEl?.classList.add('d-none');
            if (gridEl) {
                gridEl.classList.remove('d-none');
                gridEl.innerHTML = links.map(function (link) {
                    const icon = COMM_PLAT_ICON[link.label] || 'fas fa-link';
                    const color = COMM_PLAT_COLOR[link.label] || '#6b7280';
                    const display = link.display || link.url || link.label;
                    const title = escapeHtmlAttr(link.label + (link.display ? ': ' + link.display : ''));
                    if (link.url) {
                        const ext = link.external ? ' target="_blank" rel="noopener noreferrer"' : '';
                        return `<a href="${escapeHtmlAttr(link.url)}" class="comparison-comm-plat-card"${ext} title="${title}">
                            <i class="${icon}" style="color:${color};"></i>
                            <div class="fw-semibold small">${escapeHtml(link.label)}</div>
                            <div class="text-muted small text-truncate">${escapeHtml(String(display))}</div>
                        </a>`;
                    }
                    return `<div class="comparison-comm-plat-card" title="${title}">
                        <i class="${icon}" style="color:${color};"></i>
                        <div class="fw-semibold small">${escapeHtml(link.label)}</div>
                        <div class="text-muted small text-truncate">${escapeHtml(String(display))}</div>
                    </div>`;
                }).join('');
            }
        }

        commModal.show();
    }

    function ensureCommRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findRowIndexByLabel(cells, 'comm', specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Comm';

        let insertAt = 0;
        const supplierNameRow = findSupplierNameRowIndex(cells, specCol);
        if (supplierNameRow !== null) {
            insertAt = supplierNameRow + 1;
        } else {
            const companyRowIndex = findRowIndexByLabel(cells, 'company name', specCol);
            if (companyRowIndex !== null) {
                insertAt = companyRowIndex;
            } else {
                const supplierLinkRow = findSheetLinkRowIndex(cells, specCol);
                if (supplierLinkRow !== null) {
                    insertAt = supplierLinkRow + 1;
                }
            }
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);

        return { cells: nextCells, rowIndex: insertAt };
    }

    function syncCommRowOnSheet() {
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const commEnsured = ensureCommRow(currentSheetCells, specCol);
        currentSheetCells = commEnsured.cells;
    }

    function isCompanyNameDataCell(rowIndex, colIndex, forceText) {
        if (forceText || isSheetSpecColumn(colIndex) || isSheetPriorityColumn(colIndex)) {
            return false;
        }
        return isCompanyNameRow(rowIndex, currentSheetCells);
    }

    function showSheetCellTooltip(event, text) {
        if (!cdHoverPreview || !text) return;
        cdHoverPreview.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
        cdHoverPreview.style.display = 'block';
        positionCdHover(event);
    }

    function showComparisonToast(type, message) {
        const bg = (type === 'error' || type === 'danger') ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            container.style.zIndex = '20000';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${bg} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message || '')}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
        container.appendChild(toast);
        bootstrap.Toast.getOrCreateInstance(toast).show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    function amazonSellerTypeBadge(type) {
        if (!type) {
            return '<span class="text-muted">—</span>';
        }
        const normalized = String(type).toUpperCase();
        let cls = 'secondary';
        if (normalized === 'FBA') {
            cls = 'warning';
        } else if (normalized === 'AMZ') {
            cls = 'dark';
        }
        return `<span class="badge bg-${cls}">${escapeHtml(type)}</span>`;
    }

    function renderAmazonCompetitorsListHtml(competitors, lowestPrice) {
        if (!competitors || competitors.length === 0) {
            return '<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No competitors found for this SKU.</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm mb-0">';
        html += `<thead class="table-light"><tr>
            <th style="width:30px;">#</th>
            <th style="width:60px;">Image</th>
            <th style="width:100px;">ASIN</th>
            <th style="width:250px;">Product Title</th>
            <th>Seller</th>
            <th style="width:80px;">Price</th>
            <th style="width:90px;">Revenue<br><small>(30d)</small></th>
            <th style="width:70px;">Units<br><small>(30d)</small></th>
            <th style="width:100px;">Buy Box</th>
            <th style="width:60px;">Type</th>
            <th style="width:70px;">Rating</th>
            <th style="width:70px;">Reviews</th>
            <th style="width:140px;">Delivery</th>
            <th style="width:60px;">Link</th>
            <th style="width:80px;">Actions</th>
        </tr></thead><tbody>`;

        competitors.forEach(function (item, index) {
            const basePrice = parseFloat(item.price) || 0;
            let shipCost = 0;
            if (item.delivery) {
                const paidMatch = String(item.delivery).match(/\$\s*([\d,]+\.?\d*)\s*delivery/i);
                if (paidMatch) {
                    shipCost = parseFloat(paidMatch[1].replace(/,/g, '')) || 0;
                }
            }
            const totalPrice = basePrice + shipCost;
            const isLowest = lowestPrice != null && Math.abs(parseFloat(item.price) - parseFloat(lowestPrice)) < 0.01;
            const rowClass = isLowest ? 'table-success' : '';
            const totalFormatted = '$' + totalPrice.toFixed(2);
            const priceInner = shipCost > 0
                ? `${totalFormatted}<br><small style="color:#888;font-weight:400;">$${basePrice.toFixed(2)} + $${shipCost.toFixed(2)} ship</small>`
                : totalFormatted;
            const priceBadge = isLowest
                ? `<span class="badge bg-success">${priceInner} <i class="fa fa-trophy"></i></span>`
                : `<strong>${priceInner}</strong>`;

            const productLink = item.link || item.product_link || '#';
            const productTitle = item.title || item.product_title || 'N/A';
            const sellerName = item.seller_name || '—';
            const imageUrl = item.image || '';
            const imageHtml = imageUrl
                ? `<img src="${escapeHtmlAttr(imageUrl)}" style="width:50px;height:50px;object-fit:contain;" alt="">`
                : '<span style="color:#999;">—</span>';
            const revenue = item.monthly_revenue
                ? `<span style="color:#28a745;font-weight:600;">$${parseFloat(item.monthly_revenue).toFixed(0)}</span>`
                : '<span style="color:#999;">—</span>';
            const units = item.monthly_units_sold
                ? `<span style="color:#007bff;font-weight:600;">${parseInt(item.monthly_units_sold, 10)}</span>`
                : '<span style="color:#999;">—</span>';
            const buyBox = item.buy_box_owner
                ? `<span style="font-size:11px;">${escapeHtml(item.buy_box_owner)}</span>`
                : '<span style="color:#999;">—</span>';
            const rating = item.rating
                ? `<span style="color:#ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fa fa-star"></i></span>`
                : '<span style="color:#999;">—</span>';
            const reviews = item.reviews
                ? `<span>${parseInt(item.reviews, 10).toLocaleString()}</span>`
                : '<span style="color:#999;">—</span>';

            let deliveryHtml = '<span style="color:#999;">—</span>';
            if (item.delivery) {
                const isFree = /free/i.test(item.delivery);
                const paidMatch = String(item.delivery).match(/\$\s*([\d,]+\.?\d*)\s*delivery/i);
                if (paidMatch) {
                    deliveryHtml = `<span style="color:#dc3545;font-weight:600;" title="${escapeHtmlAttr(item.delivery)}">$${paidMatch[1]} ship</span>`;
                } else if (isFree) {
                    deliveryHtml = `<span style="color:#28a745;font-weight:600;" title="${escapeHtmlAttr(item.delivery)}">FREE</span>`;
                } else {
                    const deliveryText = String(item.delivery);
                    deliveryHtml = `<span style="font-size:10px;" title="${escapeHtmlAttr(deliveryText)}">${escapeHtml(deliveryText.substring(0, 22))}${deliveryText.length > 22 ? '…' : ''}</span>`;
                }
            }

            html += `<tr class="${rowClass}">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td class="text-center">${imageHtml}</td>
                <td><span class="text-primary fw-semibold" style="font-size:11px;">${escapeHtml(item.asin || 'N/A')}</span></td>
                <td style="font-size:11px;" title="${escapeHtmlAttr(productTitle)}">${escapeHtml(productTitle.length > 60 ? productTitle.substring(0, 60) + '…' : productTitle)}</td>
                <td style="font-size:11px;">${escapeHtml(sellerName)}</td>
                <td>${priceBadge}</td>
                <td class="text-center">${revenue}</td>
                <td class="text-center">${units}</td>
                <td style="font-size:11px;">${buyBox}</td>
                <td class="text-center">${amazonSellerTypeBadge(item.seller_type)}</td>
                <td class="text-center">${rating}</td>
                <td class="text-center">${reviews}</td>
                <td class="text-center">${deliveryHtml}</td>
                <td class="text-center">
                    <a href="${escapeHtmlAttr(productLink)}" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="View Product on Amz">
                        <i class="fa fa-external-link"></i>
                    </a>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger comparison-delete-lmp-btn"
                        data-id="${escapeHtmlAttr(String(item.id ?? ''))}"
                        data-asin="${escapeHtmlAttr(item.asin || '')}"
                        data-price="${escapeHtmlAttr(String(item.price ?? ''))}"
                        title="Delete this competitor">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        return html;
    }

    function renderCompetitorsList(competitors, lowestPrice) {
        return renderAmazonCompetitorsListHtml(competitors, lowestPrice);
    }

    function amazonLmpLoadingHtml() {
        return `<div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Loading competitors...</p>
        </div>`;
    }

    function fillAmazonLmpAddForm(prefix, sku) {
        const skuInput = document.getElementById(`${prefix}-add-comp-sku`);
        const asinInput = document.getElementById(`${prefix}-add-comp-asin`);
        const priceInput = document.getElementById(`${prefix}-add-comp-price`);
        const linkInput = document.getElementById(`${prefix}-add-comp-link`);
        const marketplaceInput = document.getElementById(`${prefix}-add-comp-marketplace`);
        if (skuInput) skuInput.value = sku || '';
        if (asinInput) asinInput.value = '';
        if (priceInput) priceInput.value = '';
        if (linkInput) linkInput.value = '';
        if (marketplaceInput) marketplaceInput.value = 'amazon';
    }

    function loadAmazonCompetitors(sku, listEl, fieldPrefix) {
        if (!sku || !listEl) {
            return Promise.resolve();
        }

        currentAmazonLmpSku = sku;
        currentAmazonLmpListEl = listEl;
        currentAmazonLmpFormPrefix = fieldPrefix || null;
        if (fieldPrefix) {
            fillAmazonLmpAddForm(fieldPrefix, sku);
        }

        listEl.innerHTML = amazonLmpLoadingHtml();

        return fetch(`${competitorsUrl}?sku=${encodeURIComponent(sku)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                listEl.innerHTML = renderAmazonCompetitorsListHtml(data.competitors || [], data.lowest_price);
            } else {
                listEl.innerHTML = '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!</div>';
            }
        })
        .catch(() => {
            listEl.innerHTML = '<div class="alert alert-warning mb-0"><i class="fa fa-info-circle"></i> No competitors found yet. Add your first competitor above!</div>';
        });
    }

    function reloadCurrentAmazonLmp() {
        if (currentAmazonLmpSku && currentAmazonLmpListEl) {
            return loadAmazonCompetitors(currentAmazonLmpSku, currentAmazonLmpListEl, currentAmazonLmpFormPrefix);
        }
        return Promise.resolve();
    }

    function submitAmazonLmpAddForm(fieldPrefix, formId) {
        const sku = document.getElementById(`${fieldPrefix}-add-comp-sku`)?.value.trim();
        const asin = document.getElementById(`${fieldPrefix}-add-comp-asin`)?.value.trim();
        const price = parseFloat(document.getElementById(`${fieldPrefix}-add-comp-price`)?.value);
        const link = document.getElementById(`${fieldPrefix}-add-comp-link`)?.value.trim();
        const marketplace = document.getElementById(`${fieldPrefix}-add-comp-marketplace`)?.value || 'amazon';
        const form = document.getElementById(formId);

        if (!asin) {
            showComparisonToast('error', 'ASIN is required');
            return Promise.resolve();
        }
        if (!price || price <= 0) {
            showComparisonToast('error', 'Valid price is required');
            return Promise.resolve();
        }

        const submitBtn = form?.querySelector('button[type="submit"]');
        const originalHtml = submitBtn?.innerHTML || '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
        }

        return fetch(amazonLmpAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku,
                asin,
                price,
                product_link: link || null,
                product_title: null,
                marketplace,
            }),
        })
        .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
        .then(({ ok, status, data }) => {
            if (!ok) {
                let errorMsg = 'Failed to add competitor';
                if (status === 409) {
                    errorMsg = 'This ASIN is already saved for this SKU';
                } else if (data?.error) {
                    errorMsg = data.error;
                } else if (data?.message) {
                    errorMsg = data.message;
                }
                throw new Error(errorMsg);
            }
            showComparisonToast('success', 'Competitor added successfully');
            document.getElementById(`${fieldPrefix}-add-comp-asin`).value = '';
            document.getElementById(`${fieldPrefix}-add-comp-price`).value = '';
            document.getElementById(`${fieldPrefix}-add-comp-link`).value = '';
            clearTimeout(tableRefreshTimer);
            tableRefreshTimer = setTimeout(() => table?.replaceData(), 500);
            const reloadPromise = fieldPrefix === 'comparison-lmp'
                ? reloadCurrentComparisonLmpList()
                : reloadCurrentAmazonLmp();
            return reloadPromise;
        })
        .catch(err => {
            showComparisonToast('error', err.message || 'Failed to add competitor');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    }

    function deleteAmazonLmpCompetitor(button) {
        const id = button.dataset.id;
        const asin = button.dataset.asin || '';
        const price = button.dataset.price || '';

        if (!id) {
            showComparisonToast('error', 'Invalid competitor ID');
            return;
        }
        if (!confirm(`Delete competitor ${asin} ($${price}) from tracking?`)) {
            return;
        }

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch(amazonLmpDeleteUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ id }),
        })
        .then(response => response.json().then(data => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) {
                throw new Error(data?.error || 'Failed to delete competitor');
            }
            showComparisonToast('success', 'Competitor deleted successfully');
            clearTimeout(tableRefreshTimer);
            tableRefreshTimer = setTimeout(() => table?.replaceData(), 500);
            return reloadCurrentAmazonLmp();
        })
        .catch(err => {
            button.disabled = false;
            button.innerHTML = originalHtml;
            showComparisonToast('error', err.message || 'Failed to delete competitor');
        });
    }

    function renderEbayCompetitorsList(competitors, lowestPrice) {
        if (!competitors || competitors.length === 0) {
            return '<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No eBay competitors found for this SKU.</div>';
        }

        let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm mb-0">';
        html += `<thead class="table-light"><tr>
            <th>#</th><th>Image</th><th>Item ID</th><th>Product Title</th>
            <th>Price</th><th>Shipping</th><th>Total</th><th>Link</th>
        </tr></thead><tbody>`;

        competitors.forEach(function (item, index) {
            const total = parseFloat(item.total_price ?? 0);
            const isLowest = lowestPrice != null && Math.abs(total - parseFloat(lowestPrice)) < 0.01;
            const rowClass = isLowest ? 'table-success' : '';
            const productLink = item.link || '#';
            const productTitle = item.title || 'N/A';
            const imageUrl = item.image || '';
            const imageHtml = imageUrl
                ? `<img src="${escapeHtmlAttr(imageUrl)}" style="width:50px;height:50px;object-fit:contain;" alt="">`
                : '<span class="text-muted">—</span>';

            html += `<tr class="${rowClass}">
                <td class="text-center"><strong>${index + 1}</strong></td>
                <td class="text-center">${imageHtml}</td>
                <td><span class="text-primary fw-semibold" style="font-size:11px;">${escapeHtmlAttr(item.item_id || 'N/A')}</span></td>
                <td style="font-size:11px;" title="${escapeHtmlAttr(productTitle)}">${escapeHtml(productTitle.length > 60 ? productTitle.substring(0, 60) + '…' : productTitle)}</td>
                <td>$${parseFloat(item.price || 0).toFixed(2)}</td>
                <td>$${parseFloat(item.shipping_cost || 0).toFixed(2)}</td>
                <td><strong>$${total.toFixed(2)}${isLowest ? ' <i class="fa fa-trophy text-success"></i>' : ''}</strong></td>
                <td class="text-center">
                    <a href="${escapeHtmlAttr(productLink)}" target="_blank" rel="noopener" class="btn btn-sm btn-info" title="View listing">
                        <i class="fa fa-external-link"></i>
                    </a>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div>';
        return html;
    }

    function lmpFormatter(cell) {
        const rowData = cell.getRow().getData();
        const lmpPrice = cell.getValue();
        const sku = rowData.sku || '';
        const totalCompetitors = parseInt(rowData.lmp_entries_total, 10) || 0;
        const lmpLink = rowData.lmp_link || '';

        if (!lmpPrice && totalCompetitors === 0) {
            return '<span style="color:#999;">N/A</span>';
        }

        let html = '<div style="display:flex;flex-direction:column;align-items:center;gap:4px;">';

        if (lmpPrice) {
            const priceFormatted = '$' + parseFloat(lmpPrice).toFixed(2);
            if (lmpLink) {
                html += `<a href="${escapeHtmlAttr(lmpLink)}" target="_blank" rel="noopener"
                    style="color:#28a745;font-weight:600;font-size:14px;text-decoration:none;"
                    title="Lowest competitor link">${priceFormatted}</a>`;
            } else {
                html += `<span style="color:#28a745;font-weight:600;font-size:14px;">${priceFormatted}</span>`;
            }
        }

        if (totalCompetitors > 0) {
            html += `<a href="#" class="comparison-view-lmp-competitors" data-sku="${escapeHtmlAttr(sku)}"
                style="color:#007bff;text-decoration:none;cursor:pointer;font-size:11px;">
                <i class="fa fa-eye"></i> View ${totalCompetitors}
            </a>`;
        }

        html += '</div>';
        return html;
    }

    function comparisonLmpSiteMeta(platform, sku) {
        const key = channelLmpKey(platform);
        const q = encodeURIComponent(String(sku || '').trim());
        if (key === 'ebay') {
            return {
                key: 'ebay',
                label: 'eBay',
                siteName: 'eBay',
                searchUrl: `https://www.ebay.com/sch/i.html?_nkw=${q}`,
                linkPlaceholder: 'https://www.ebay.com/itm/...',
            };
        }
        if (key === 'temu') {
            return {
                key: 'temu',
                label: 'Temu',
                siteName: 'Temu',
                searchUrl: `https://www.temu.com/search_result.html?search_key=${q}`,
                linkPlaceholder: 'https://www.temu.com/...',
            };
        }
        if (key === 'shopify') {
            return {
                key: 'shopify',
                label: 'Shopify',
                siteName: 'Google Shopping',
                searchUrl: `https://www.google.com/search?tbm=shop&q=${q}`,
                linkPlaceholder: 'https://...',
            };
        }
        return {
            key: 'amazon',
            label: 'Amz',
            siteName: 'Amazon',
            searchUrl: `https://www.amazon.com/s?k=${q}`,
            linkPlaceholder: 'https://www.amazon.com/dp/...',
        };
    }

    function configureComparisonLmpAddForm(platform, sku) {
        const meta = comparisonLmpSiteMeta(platform, sku);
        currentComparisonLmpPlatform = meta.key;
        currentComparisonLmpSku = sku || '';

        const platformInput = document.getElementById('comparison-lmp-add-platform');
        if (platformInput) {
            platformInput.value = meta.key;
        }
        const platformLabel = document.getElementById('comparison-lmp-add-platform-label');
        if (platformLabel) {
            platformLabel.textContent = meta.label;
        }
        const siteName = document.getElementById('comparison-lmp-site-name');
        if (siteName) {
            siteName.textContent = meta.siteName;
        }
        const siteLink = document.getElementById('comparison-lmp-site-search-link');
        if (siteLink) {
            siteLink.href = meta.searchUrl;
            siteLink.classList.toggle('disabled', !sku);
        }

        document.querySelectorAll('.comparison-lmp-field-amazon').forEach(el => {
            el.classList.toggle('d-none', meta.key !== 'amazon');
        });
        // Item ID (ebay only)
        document.querySelectorAll('.comparison-lmp-field-ebay:not(.comparison-lmp-field-temu)').forEach(el => {
            el.classList.toggle('d-none', meta.key !== 'ebay');
        });
        // Shipping / Delivery (ebay + temu)
        document.querySelectorAll('.comparison-lmp-field-ebay.comparison-lmp-field-temu').forEach(el => {
            el.classList.toggle('d-none', meta.key !== 'ebay' && meta.key !== 'temu');
        });
        document.querySelectorAll('.comparison-lmp-field-shopify').forEach(el => {
            el.classList.toggle('d-none', meta.key !== 'shopify');
        });

        const skuInput = document.getElementById('comparison-lmp-add-comp-sku');
        if (skuInput) {
            skuInput.value = sku || '';
        }
        const asinInput = document.getElementById('comparison-lmp-add-comp-asin');
        if (asinInput) {
            asinInput.value = '';
        }
        const itemIdInput = document.getElementById('comparison-lmp-add-comp-item-id');
        if (itemIdInput) {
            itemIdInput.value = '';
        }
        const productIdInput = document.getElementById('comparison-lmp-add-comp-product-id');
        if (productIdInput) {
            productIdInput.value = '';
        }
        const priceInput = document.getElementById('comparison-lmp-add-comp-price');
        if (priceInput) {
            priceInput.value = '';
        }
        const shippingInput = document.getElementById('comparison-lmp-add-comp-shipping');
        if (shippingInput) {
            shippingInput.value = '';
        }
        const linkInput = document.getElementById('comparison-lmp-add-comp-link');
        if (linkInput) {
            linkInput.value = '';
            linkInput.placeholder = meta.linkPlaceholder;
        }
        const marketplaceInput = document.getElementById('comparison-lmp-add-comp-marketplace');
        if (marketplaceInput) {
            marketplaceInput.value = 'amazon';
        }

        const addWrap = document.getElementById('comparison-lmp-add-wrap');
        if (addWrap) {
            addWrap.classList.remove('d-none');
        }
    }

    function loadComparisonLmpModal(sku, platform, fromRoi) {
        if (!sku) {
            return;
        }

        platform = channelLmpKey(platform || 'amazon');
        comparisonLmpOpenedFromRoi = !!fromRoi;
        const meta = comparisonLmpSiteMeta(platform, sku);

        const lmpModalEl = document.getElementById('comparisonLmpModal');
        if (!lmpModalEl || !window.bootstrap?.Modal) {
            return;
        }

        if (lmpModalEl.parentElement !== document.body) {
            document.body.appendChild(lmpModalEl);
        }

        document.getElementById('comparison-lmp-modal-sku').textContent = sku;
        configureComparisonLmpAddForm(platform, sku);

        const listEl = document.getElementById('comparison-lmp-data-list');
        listEl.innerHTML = amazonLmpLoadingHtml();

        const lmpModalInstance = bootstrap.Modal.getOrCreateInstance(lmpModalEl);
        lmpModalEl.addEventListener('shown.bs.modal', function () {
            const openModals = document.querySelectorAll('.modal.show');
            const baseZ = 1050 + (openModals.length * 20);
            lmpModalEl.style.zIndex = String(baseZ + 10);
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length) {
                backdrops[backdrops.length - 1].style.zIndex = String(baseZ);
            }
        }, { once: true });
        lmpModalInstance.show();

        if (platform === 'ebay' || platform === 'temu' || platform === 'shopify') {
            const dataUrl = platform === 'temu'
                ? temuLmpDataUrl
                : (platform === 'shopify' ? googleLmpDataUrl : ebayLmpDataUrl);
            fetch(`${dataUrl}?sku=${encodeURIComponent(sku)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const lowest = data.lowest_price != null ? data.lowest_price : data.lmp;
                    listEl.innerHTML = renderEbayCompetitorsList(data.competitors || [], lowest);
                } else {
                    listEl.innerHTML = `<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No ${meta.label} LMP yet. Add one above from ${meta.siteName}.</div>`;
                }
            })
            .catch(() => {
                listEl.innerHTML = `<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No ${meta.label} LMP yet. Add one above from ${meta.siteName}.</div>`;
            });
            return;
        }

        loadAmazonCompetitors(sku, listEl, 'comparison-lmp');
    }

    async function refreshRoiModalAfterLmpChange(platform) {
        const tbody = document.getElementById('comparison-roi-tbody');
        const sku = (currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim();
        if (!tbody?.roiRows || !sku) {
            return;
        }

        const lmpRates = await fetchPlatformLmpRates(sku);
        const target = channelLmpKey(platform);

        tbody.roiRows.forEach(function (row) {
            if (row.isOverall || channelLmpKey(row.channel) !== target) {
                return;
            }
            row.lmp = getChannelRawLmp(row.channel, lmpRates);
            row.priceAfterLmp = getChannelPriceAfterLmp(row.channel, lmpRates);
            const saleFromLmp = getChannelLmpSale(row.channel, lmpRates);
            if (saleFromLmp) {
                // Prefer live LMP-derived sale when this row had no sale yet.
                if (!row.sale || parseSheetNumber(row.sale) == null) {
                    row.sale = saleFromLmp;
                }
            }
            applyRoiCalcToRow(row);
        });

        const withOverall = appendOverallRoiRow(tbody.roiRows.filter(r => !r.isOverall), currentSheetCells);
        renderRoiModalTable(withOverall);
    }

    function reloadCurrentComparisonLmpList() {
        const sku = currentComparisonLmpSku;
        const platform = currentComparisonLmpPlatform || 'amazon';
        const listEl = document.getElementById('comparison-lmp-data-list');
        if (!sku || !listEl) {
            return Promise.resolve();
        }
        if (platform === 'ebay' || platform === 'temu' || platform === 'shopify') {
            const dataUrl = platform === 'temu'
                ? temuLmpDataUrl
                : (platform === 'shopify' ? googleLmpDataUrl : ebayLmpDataUrl);
            const meta = comparisonLmpSiteMeta(platform, sku);
            listEl.innerHTML = amazonLmpLoadingHtml();
            return fetch(`${dataUrl}?sku=${encodeURIComponent(sku)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const lowest = data.lowest_price != null ? data.lowest_price : data.lmp;
                    listEl.innerHTML = renderEbayCompetitorsList(data.competitors || [], lowest);
                } else {
                    listEl.innerHTML = `<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No ${meta.label} LMP yet. Add one above from ${meta.siteName}.</div>`;
                }
            })
            .catch(() => {
                listEl.innerHTML = `<div class="alert alert-info mb-0"><i class="fa fa-info-circle"></i> No ${meta.label} LMP yet. Add one above from ${meta.siteName}.</div>`;
            });
        }
        return loadAmazonCompetitors(sku, listEl, 'comparison-lmp');
    }

    function submitEbayLmpAdd() {
        const sku = document.getElementById('comparison-lmp-add-comp-sku')?.value.trim();
        const itemId = document.getElementById('comparison-lmp-add-comp-item-id')?.value.trim();
        const price = parseFloat(document.getElementById('comparison-lmp-add-comp-price')?.value);
        const shipping = parseFloat(document.getElementById('comparison-lmp-add-comp-shipping')?.value) || 0;
        const link = document.getElementById('comparison-lmp-add-comp-link')?.value.trim();
        const form = document.getElementById('comparison-lmp-add-form');

        if (!sku) {
            showComparisonToast('error', 'SKU is required');
            return Promise.resolve();
        }
        if (!itemId && !link) {
            showComparisonToast('error', 'eBay Item ID or product link is required');
            return Promise.resolve();
        }
        if (!price || price <= 0) {
            showComparisonToast('error', 'Valid price is required');
            return Promise.resolve();
        }

        const submitBtn = form?.querySelector('button[type="submit"]');
        const originalHtml = submitBtn?.innerHTML || '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
        }

        return fetch(ebayLmpAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku,
                item_id: itemId || 'from-link',
                price,
                shipping_cost: shipping,
                product_link: link || null,
            }),
        })
        .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
        .then(({ ok, status, data }) => {
            if (!ok) {
                let errorMsg = data?.error || data?.message || 'Failed to add eBay LMP';
                if (status === 409) {
                    errorMsg = 'This eBay item is already saved for this SKU';
                }
                throw new Error(errorMsg);
            }
            showComparisonToast('success', 'eBay LMP added');
            document.getElementById('comparison-lmp-add-comp-item-id').value = '';
            document.getElementById('comparison-lmp-add-comp-price').value = '';
            document.getElementById('comparison-lmp-add-comp-shipping').value = '';
            document.getElementById('comparison-lmp-add-comp-link').value = '';
            return reloadCurrentComparisonLmpList().then(function () {
                if (comparisonLmpOpenedFromRoi || document.getElementById('comparisonRoiModal')?.classList.contains('show')) {
                    return refreshRoiModalAfterLmpChange('ebay');
                }
            });
        })
        .catch(err => {
            showComparisonToast('error', err.message || 'Failed to add eBay LMP');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    }

    function submitTemuLmpAdd() {
        const sku = document.getElementById('comparison-lmp-add-comp-sku')?.value.trim();
        const price = parseFloat(document.getElementById('comparison-lmp-add-comp-price')?.value);
        const deliveryRaw = document.getElementById('comparison-lmp-add-comp-shipping')?.value;
        const delivery = deliveryRaw !== '' && deliveryRaw != null ? parseFloat(deliveryRaw) : 0;
        const link = document.getElementById('comparison-lmp-add-comp-link')?.value.trim();
        const form = document.getElementById('comparison-lmp-add-form');

        if (!sku) {
            showComparisonToast('error', 'SKU is required');
            return Promise.resolve();
        }
        if (!price || price <= 0) {
            showComparisonToast('error', 'Valid price is required');
            return Promise.resolve();
        }

        const submitBtn = form?.querySelector('button[type="submit"]');
        const originalHtml = submitBtn?.innerHTML || '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
        }

        // temu-lmp/save replaces entries — merge with existing competitors first.
        return fetch(`${temuLmpDataUrl}?sku=${encodeURIComponent(sku)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            const existing = Array.isArray(data?.competitors) ? data.competitors : [];
            const lmpEntries = existing.map(function (c) {
                return {
                    price: c.base_price != null ? c.base_price : c.price,
                    delivery: c.delivery != null ? c.delivery : (c.shipping_cost || 0),
                    link: c.product_link || c.link || null,
                    ignored: !!c.ignored,
                    source_sku: c.source_sku || sku,
                };
            });
            lmpEntries.push({
                price,
                delivery: Number.isFinite(delivery) && delivery > 0 ? delivery : 0,
                link: link || null,
                ignored: false,
                source_sku: sku,
            });

            return fetch(temuLmpSaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ sku, lmp_entries: lmpEntries }),
            });
        })
        .then(response => response.json().then(data => ({ ok: response.ok, data })))
        .then(({ ok, data }) => {
            if (!ok || data?.success === false) {
                throw new Error(data?.message || data?.error || 'Failed to add Temu LMP');
            }
            showComparisonToast('success', 'Temu LMP added');
            document.getElementById('comparison-lmp-add-comp-price').value = '';
            document.getElementById('comparison-lmp-add-comp-shipping').value = '';
            document.getElementById('comparison-lmp-add-comp-link').value = '';
            return reloadCurrentComparisonLmpList().then(function () {
                if (comparisonLmpOpenedFromRoi || document.getElementById('comparisonRoiModal')?.classList.contains('show')) {
                    return refreshRoiModalAfterLmpChange('temu');
                }
            });
        })
        .catch(err => {
            showComparisonToast('error', err.message || 'Failed to add Temu LMP');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    }

    function submitShopifyLmpAdd() {
        const sku = document.getElementById('comparison-lmp-add-comp-sku')?.value.trim();
        let productId = document.getElementById('comparison-lmp-add-comp-product-id')?.value.trim();
        const price = parseFloat(document.getElementById('comparison-lmp-add-comp-price')?.value);
        const link = document.getElementById('comparison-lmp-add-comp-link')?.value.trim();
        const form = document.getElementById('comparison-lmp-add-form');

        if (!sku) {
            showComparisonToast('error', 'SKU is required');
            return Promise.resolve();
        }
        if (!price || price <= 0) {
            showComparisonToast('error', 'Valid price is required');
            return Promise.resolve();
        }
        if (!productId) {
            // Allow link-only adds with a generated product id.
            productId = link
                ? ('manual-' + String(link).replace(/[^a-zA-Z0-9]/g, '').slice(-24))
                : ('manual-' + Date.now());
        }

        const submitBtn = form?.querySelector('button[type="submit"]');
        const originalHtml = submitBtn?.innerHTML || '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
        }

        return fetch(googleLmpAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku,
                product_id: productId,
                source: 'manual',
                price,
                product_link: link || null,
            }),
        })
        .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
        .then(({ ok, status, data }) => {
            if (!ok) {
                let errorMsg = data?.error || data?.message || 'Failed to add Shopify LMP';
                if (status === 409) {
                    errorMsg = 'This Google offer is already saved for this SKU';
                }
                throw new Error(errorMsg);
            }
            showComparisonToast('success', 'Shopify (Google) LMP added');
            document.getElementById('comparison-lmp-add-comp-product-id').value = '';
            document.getElementById('comparison-lmp-add-comp-price').value = '';
            document.getElementById('comparison-lmp-add-comp-link').value = '';
            return reloadCurrentComparisonLmpList().then(function () {
                if (comparisonLmpOpenedFromRoi || document.getElementById('comparisonRoiModal')?.classList.contains('show')) {
                    return refreshRoiModalAfterLmpChange('shopify');
                }
            });
        })
        .catch(err => {
            showComparisonToast('error', err.message || 'Failed to add Shopify LMP');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }
        });
    }

    function submitComparisonLmpAddForm() {
        const platform = channelLmpKey(
            document.getElementById('comparison-lmp-add-platform')?.value
            || currentComparisonLmpPlatform
            || 'amazon'
        );
        if (platform === 'ebay') {
            return submitEbayLmpAdd();
        }
        if (platform === 'temu') {
            return submitTemuLmpAdd();
        }
        if (platform === 'shopify') {
            return submitShopifyLmpAdd();
        }
        return submitAmazonLmpAddForm('comparison-lmp', 'comparison-lmp-add-form').then(function () {
            if (comparisonLmpOpenedFromRoi || document.getElementById('comparisonRoiModal')?.classList.contains('show')) {
                return refreshRoiModalAfterLmpChange('amazon');
            }
        });
    }

    function columnLetter(index) {
        let result = '';
        let n = index + 1;
        while (n > 0) {
            const rem = (n - 1) % 26;
            result = String.fromCharCode(65 + rem) + result;
            n = Math.floor((n - 1) / 26);
        }
        return result;
    }

    function normalizeSheetColor(color) {
        color = String(color || '').trim();
        if (/^#[0-9a-f]{6}$/i.test(color)) {
            return color.toLowerCase();
        }
        if (/^#[0-9a-f]{3}$/i.test(color)) {
            return '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
        }
        return '';
    }

    function normalizeSheetFormats(formats) {
        const source = formats || {};
        const next = { cells: {}, rows: {}, cols: {} };
        ['cells', 'rows', 'cols'].forEach(type => {
            const map = source[type] || {};
            Object.keys(map).forEach(key => {
                const color = normalizeSheetColor(map[key]);
                if (color) {
                    next[type][String(key)] = color;
                }
            });
        });
        return next;
    }

    function resolveSheetCellBackground(rowIndex, colIndex, isSpec) {
        const cellKey = `${rowIndex}:${colIndex}`;
        if (currentSheetFormats.cells[cellKey]) {
            return currentSheetFormats.cells[cellKey];
        }
        if (autoSheetFormats.cells[cellKey]) {
            return autoSheetFormats.cells[cellKey];
        }
        if (currentSheetFormats.rows[String(rowIndex)]) {
            return currentSheetFormats.rows[String(rowIndex)];
        }
        if (autoSheetFormats.rows[String(rowIndex)]) {
            return autoSheetFormats.rows[String(rowIndex)];
        }
        if (currentSheetFormats.cols[String(colIndex)]) {
            return currentSheetFormats.cols[String(colIndex)];
        }
        if (autoSheetFormats.cols[String(colIndex)]) {
            return autoSheetFormats.cols[String(colIndex)];
        }
        if (isSpec) {
            return SPEC_COLUMN_COLOR;
        }
        return '';
    }

    function sheetCellTdStyle(rowIndex, colIndex, isSpec) {
        const bg = resolveSheetCellBackground(rowIndex, colIndex, isSpec);
        return bg ? ` style="background-color:${bg};"` : '';
    }

    function looksLikeHeavyCellValue(value) {
        if (value == null || value === '') {
            return false;
        }
        if (typeof value !== 'string') {
            return false;
        }
        // Never run trim/toLowerCase on multi-MB base64 — that freezes the tab.
        return value.startsWith('data:image/')
            || value.startsWith('[embedded-image:')
            || value.length > 400;
    }

    function sanitizeSheetCellsForUi(cells) {
        return (cells || []).map((row, rowIndex) => {
            if (!Array.isArray(row)) {
                const text = String(row || '');
                return text.startsWith('data:image/')
                    ? [`[embedded-image:${rowIndex}:0]`]
                    : [text];
            }
            return row.map((value, colIndex) => {
                if (typeof value === 'string' && value.startsWith('data:image/')) {
                    return `[embedded-image:${rowIndex}:${colIndex}]`;
                }
                return value == null ? '' : value;
            });
        });
    }

    function detectSpecColumnIndex(cells) {
        const scores = {};
        const maxRows = Math.min(cells.length, 30);
        for (let rowIndex = 0; rowIndex < maxRows; rowIndex++) {
            const row = cells[rowIndex] || [];
            for (let colIndex = 0; colIndex < row.length; colIndex++) {
                const raw = row[colIndex];
                if (raw == null || raw === '' || looksLikeHeavyCellValue(raw)) {
                    continue;
                }
                const text = String(raw).trim().toLowerCase();
                if (!text || text.startsWith('http')) {
                    continue;
                }
                if (text.includes('supplier') || text.includes('product photo') || text.includes('person name review') || text.includes('company name')) {
                    scores[colIndex] = (scores[colIndex] || 0) + 1;
                }
            }
        }
        const keys = Object.keys(scores);
        if (!keys.length) {
            return 2;
        }
        keys.sort((a, b) => scores[b] - scores[a]);
        return parseInt(keys[0], 10);
    }

    function detectLabelColumnIndex(cells) {
        return detectSpecColumnIndex(cells);
    }

    function columnMatchesKeywords(cells, colIndex, keywords) {
        if (colIndex < 0) {
            return false;
        }
        for (let rowIndex = 0; rowIndex < Math.min(cells.length, 8); rowIndex++) {
            const text = String((cells[rowIndex] || [])[colIndex] || '').trim().toLowerCase();
            if (!text) {
                continue;
            }
            if (keywords.some(keyword => text.includes(keyword.toLowerCase()))) {
                return true;
            }
        }
        return false;
    }

    function insertSheetColumnAt(cells, index) {
        index = Math.max(0, index);
        return cells.map(row => {
            const next = Array.isArray(row) ? row.slice() : [String(row || '')];
            next.splice(index, 0, '');
            return next;
        });
    }

    function stampColumnHeader(cells, colIndex, header) {
        const existing = String((cells[0] || [])[colIndex] || '').trim();
        if (existing !== '' && !/^[A-Z]{1,3}$/.test(existing)) {
            if (isSheetImageUrl(existing) || /^https?:\/\//i.test(existing)) {
                return;
            }
        }
        if (!cells[0]) {
            cells[0] = [];
        }
        cells[0][colIndex] = header;
    }

    function ensureLeadColumns(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        let specCol = detectSpecColumnIndex(cells);

        if (specCol < 2 && !columnMatchesKeywords(cells, 0, ['amazon'])) {
            let insertAt = Math.max(0, specCol - 1);
            if (specCol === 1 && columnMatchesKeywords(cells, 0, ['5 core', '5core', '5-core'])) {
                insertAt = 0;
            }
            cells = insertSheetColumnAt(cells, insertAt);
            specCol = detectSpecColumnIndex(cells);
        }

        while (specCol < 2) {
            cells = insertSheetColumnAt(cells, 0);
            specCol++;
        }

        specCol = detectSpecColumnIndex(cells);
        const amazonCol = specCol - 2;
        const fiveCoreCol = specCol - 1;

        if (!columnMatchesKeywords(cells, amazonCol, ['amazon'])) {
            stampColumnHeader(cells, amazonCol, 'Amz');
        }
        if (!columnMatchesKeywords(cells, fiveCoreCol, ['5 core', '5core', '5-core'])) {
            stampColumnHeader(cells, fiveCoreCol, '5 Core');
        }

        const criticalEnsured = ensureCriticalColumn(cells);
        cells = criticalEnsured.cells;
        if (criticalEnsured.insertedAt !== null) {
            shiftFormatsForInsertedColumn(criticalEnsured.insertedAt);
        }

        const qcEnsured = ensureQcColumn(cells);
        cells = qcEnsured.cells;
        if (qcEnsured.insertedAt !== null) {
            shiftFormatsForInsertedColumn(qcEnsured.insertedAt);
        }

        const colCount = Math.max(...cells.map(row => row.length), 1);
        return cells.map(row => {
            while (row.length < colCount) {
                row.push('');
            }
            return row.slice(0, colCount);
        });
    }

    function shiftFormatsForInsertedColumn(insertAt) {
        if (insertAt === null || insertAt === undefined) {
            return;
        }
        currentSheetFormats.cols = shiftNumericFormatMap(currentSheetFormats.cols, insertAt, 1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, insertAt, 'col', 1);
    }

    function parseSheetNumber(value) {
        const text = String(value || '').trim();
        if (!text) {
            return null;
        }
        // Sheet GW/NW cells often look like "8.82 / 9.26". Stripping "/" would make
        // "8.829.26", and Number("8.829.26") is NaN — so take the first number only.
        const firstPart = text.split('/')[0];
        const match = String(firstPart).replace(/,/g, '').match(/-?\d+(\.\d+)?/);
        if (!match) {
            return null;
        }
        const num = parseFloat(match[0]);
        return (Number.isFinite(num) && num > 0) ? num : null;
    }

    function findRowIndexByLabel(cells, labelNeedle, labelCol) {
        const needle = labelNeedle.toLowerCase();
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = String((cells[rowIndex] || [])[labelCol] || '').trim().toLowerCase();
            if (label.includes(needle)) {
                return rowIndex;
            }
        }
        return null;
    }

    function findLowestSupplierColumn(cells, specCol, labelNeedle) {
        const rowIndex = findRowIndexByLabel(cells, labelNeedle, specCol);
        if (rowIndex === null) {
            return null;
        }

        const firstSupplierCol = getFirstSupplierColumnIndex(cells, specCol);
        const colCount = Math.max(...cells.map(row => row.length), 0);
        let bestCol = null;
        let bestValue = Infinity;

        for (let colIndex = firstSupplierCol; colIndex < colCount; colIndex++) {
            const value = parseSheetNumber((cells[rowIndex] || [])[colIndex]);
            if (value === null || value >= bestValue) {
                continue;
            }
            bestValue = value;
            bestCol = colIndex;
        }

        return bestCol;
    }

    function moveSheetColumnData(cells, fromIndex, toIndex) {
        if (fromIndex === toIndex) {
            return cells;
        }

        return cells.map(row => {
            const next = Array.isArray(row) ? row.slice() : [String(row || '')];
            const value = next[fromIndex] ?? '';
            next.splice(fromIndex, 1);
            next.splice(toIndex, 0, value);
            return next;
        });
    }

    function moveLowestPriceSupplierAfterSpec(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        const specCol = detectSpecColumnIndex(cells);
        const firstSupplierCol = getFirstSupplierColumnIndex(cells, specCol);
        const colCount = Math.max(...cells.map(row => row.length), 0);

        if (firstSupplierCol >= colCount) {
            return { cells, moved: false, from: null, to: firstSupplierCol };
        }

        let bestCol = findLowestSupplierColumn(cells, specCol, 'usd');
        if (bestCol === null) {
            bestCol = findLowestSupplierColumn(cells, specCol, 'rmb');
        }

        if (bestCol === null || bestCol === firstSupplierCol) {
            return { cells, moved: false, from: bestCol, to: firstSupplierCol };
        }

        return {
            cells: moveSheetColumnData(cells, bestCol, firstSupplierCol),
            moved: true,
            from: bestCol,
            to: firstSupplierCol,
        };
    }

    // Order ALL supplier columns (everything after Spec/Critical/QC) by PRICE USD ascending, keeping
    // equal prices side-by-side in their original order (stable). Columns without a price
    // stay after the priced ones, in their original order.
    function sortSupplierColumnsByPrice(cells) {
        cells = (cells || []).map(row => Array.isArray(row) ? row.slice() : [String(row || '')]);
        const specCol = detectSpecColumnIndex(cells);
        const firstSupplierCol = getFirstSupplierColumnIndex(cells, specCol);
        const colCount = Math.max(...cells.map(row => row.length), 0);
        if (firstSupplierCol >= colCount) {
            return { cells, mapping: null };
        }

        let priceRow = findRowIndexByLabel(cells, 'usd', specCol);
        if (priceRow === null) {
            priceRow = findRowIndexByLabel(cells, 'rmb', specCol);
        }
        if (priceRow === null) {
            return { cells, mapping: null };
        }

        const supplierCols = [];
        for (let c = firstSupplierCol; c < colCount; c++) {
            const price = parseSheetNumber((cells[priceRow] || [])[c]);
            supplierCols.push({ index: c, price: price, order: c });
        }

        supplierCols.sort((a, b) => {
            if (a.price === null && b.price === null) return a.order - b.order;
            if (a.price === null) return 1;
            if (b.price === null) return -1;
            if (a.price === b.price) return a.order - b.order;
            return a.price - b.price;
        });

        const newOrder = supplierCols.map(x => x.index);

        let changed = false;
        for (let i = 0; i < newOrder.length; i++) {
            if (newOrder[i] !== firstSupplierCol + i) { changed = true; break; }
        }
        if (!changed) {
            return { cells, mapping: null };
        }

        const mapping = {};
        for (let c = 0; c < firstSupplierCol; c++) mapping[c] = c;
        newOrder.forEach((origCol, k) => { mapping[origCol] = firstSupplierCol + k; });

        const newCells = cells.map(row => {
            const head = row.slice(0, firstSupplierCol);
            const tail = newOrder.map(origCol => (row[origCol] !== undefined ? row[origCol] : ''));
            return head.concat(tail);
        });

        return { cells: newCells, mapping };
    }

    // Remap format keys (cols + cells "row:col") through an old→new column mapping.
    function remapFormatColumns(formats, mapping) {
        formats = normalizeSheetFormats(formats);
        if (!mapping) {
            return formats;
        }

        const cols = {};
        Object.keys(formats.cols).forEach(key => {
            const oldC = parseInt(key, 10);
            if (Number.isNaN(oldC)) return;
            const newC = mapping.hasOwnProperty(oldC) ? mapping[oldC] : oldC;
            cols[String(newC)] = formats.cols[key];
        });

        const cells = {};
        Object.keys(formats.cells).forEach(key => {
            const parts = key.split(':');
            const rowIndex = parseInt(parts[0], 10);
            const oldC = parseInt(parts[1], 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(oldC)) return;
            const newC = mapping.hasOwnProperty(oldC) ? mapping[oldC] : oldC;
            cells[`${rowIndex}:${newC}`] = formats.cells[key];
        });

        return { cells, rows: { ...formats.rows }, cols };
    }

    function computeAutoSheetFormats(cells) {
        const formats = { cells: {}, rows: {}, cols: {} };
        if (!cells.length) {
            return formats;
        }

        const specCol = detectSpecColumnIndex(cells);
        formats.cols[String(specCol)] = SPEC_COLUMN_COLOR;

        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = (cells[rowIndex] || [])[specCol];
            if (isInnerPkgSectionLabel(label)) {
                formats.rows[String(rowIndex)] = INNER_PKG_SECTION_COLOR;
            } else if (isCtnPkgSectionLabel(label)) {
                formats.rows[String(rowIndex)] = CTN_PKG_SECTION_COLOR;
            } else if (isSupplierNameRow(cells, rowIndex, specCol)) {
                formats.rows[String(rowIndex)] = SUPPLIER_NAME_ROW_COLOR;
            }
        }

        const firstSupplierCol = getFirstSupplierColumnIndex(cells, specCol);
        const colCount = Math.max(...cells.map(row => row.length), 0);

        ['usd', 'rmb'].forEach(needle => {
            const rowIndex = findRowIndexByLabel(cells, needle, specCol);
            if (rowIndex === null) {
                return;
            }

            let bestCol = null;
            let bestValue = Infinity;
            for (let colIndex = firstSupplierCol; colIndex < colCount; colIndex++) {
                const value = parseSheetNumber((cells[rowIndex] || [])[colIndex]);
                if (value === null || value >= bestValue) {
                    continue;
                }
                bestValue = value;
                bestCol = colIndex;
            }

            if (bestCol !== null) {
                formats.cells[`${rowIndex}:${bestCol}`] = LOWEST_PRICE_COLOR;
            }
        });

        return formats;
    }

    function refreshAutoSheetFormats(cells) {
        autoSheetFormats = computeAutoSheetFormats(cells || currentSheetCells);
    }

    function isSupplierNameRowLabel(text) {
        const label = String(text || '').trim().toLowerCase();
        if (!label || label.includes('company name')) {
            return false;
        }
        if (label.includes('supplier name')) {
            return true;
        }
        return label === 'supplier' || label === 'suppliers';
    }

    function isSupplierNameRow(cells, rowIndex, specCol) {
        const row = cells[rowIndex] || [];
        for (let colIndex = 0; colIndex < row.length; colIndex++) {
            const text = String(row[colIndex] || '').trim();
            if (!text || !isSupplierNameRowLabel(text)) {
                continue;
            }
            if (colIndex === specCol) {
                return true;
            }
            if (text.length <= 48) {
                return true;
            }
        }
        return false;
    }

    function applyAutoSheetFormatsFromPayload(data, cells) {
        if (data?.auto_formats) {
            autoSheetFormats = normalizeSheetFormats(data.auto_formats);
            return;
        }
        refreshAutoSheetFormats(cells || data?.cells || currentSheetCells);
    }

    function shiftNumericFormatMap(map, index, delta) {
        const next = {};
        Object.keys(map || {}).forEach(key => {
            const rowIndex = parseInt(key, 10);
            if (Number.isNaN(rowIndex)) {
                return;
            }
            if (delta < 0 && rowIndex === index) {
                return;
            }
            let nextIndex = rowIndex;
            if (delta > 0 && rowIndex >= index) {
                nextIndex = rowIndex + delta;
            } else if (delta < 0 && rowIndex > index) {
                nextIndex = rowIndex + delta;
            }
            if (nextIndex >= 0) {
                next[String(nextIndex)] = map[key];
            }
        });
        return next;
    }

    function shiftCellFormatMap(map, index, axis, delta) {
        const next = {};
        Object.keys(map || {}).forEach(key => {
            const parts = key.split(':');
            if (parts.length !== 2) {
                return;
            }
            let rowIndex = parseInt(parts[0], 10);
            let colIndex = parseInt(parts[1], 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
                return;
            }
            if (axis === 'row') {
                if (delta < 0 && rowIndex === index) {
                    return;
                }
                if (delta > 0 && rowIndex >= index) {
                    rowIndex += delta;
                } else if (delta < 0 && rowIndex > index) {
                    rowIndex += delta;
                }
            } else {
                if (delta < 0 && colIndex === index) {
                    return;
                }
                if (delta > 0 && colIndex >= index) {
                    colIndex += delta;
                } else if (delta < 0 && colIndex > index) {
                    colIndex += delta;
                }
            }
            if (rowIndex >= 0 && colIndex >= 0) {
                next[`${rowIndex}:${colIndex}`] = map[key];
            }
        });
        return next;
    }

    function moveFormatRow(formats, from, to) {
        formats = normalizeSheetFormats(formats);
        const rows = {};
        Object.keys(formats.rows).forEach(key => {
            let rowIndex = parseInt(key, 10);
            if (Number.isNaN(rowIndex)) {
                return;
            }
            if (rowIndex === from) {
                rowIndex = to;
            } else if (from < to && rowIndex > from && rowIndex <= to) {
                rowIndex--;
            } else if (from > to && rowIndex >= to && rowIndex < from) {
                rowIndex++;
            }
            rows[String(rowIndex)] = formats.rows[key];
        });

        const cells = {};
        Object.keys(formats.cells).forEach(key => {
            const parts = key.split(':');
            let rowIndex = parseInt(parts[0], 10);
            const colIndex = parseInt(parts[1], 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
                return;
            }
            if (rowIndex === from) {
                rowIndex = to;
            } else if (from < to && rowIndex > from && rowIndex <= to) {
                rowIndex--;
            } else if (from > to && rowIndex >= to && rowIndex < from) {
                rowIndex++;
            }
            cells[`${rowIndex}:${colIndex}`] = formats.cells[key];
        });

        return { cells, rows, cols: { ...formats.cols } };
    }

    function moveFormatColumn(formats, from, to) {
        formats = normalizeSheetFormats(formats);
        const cols = {};
        Object.keys(formats.cols).forEach(key => {
            let colIndex = parseInt(key, 10);
            if (Number.isNaN(colIndex)) {
                return;
            }
            if (colIndex === from) {
                colIndex = to;
            } else if (from < to && colIndex > from && colIndex <= to) {
                colIndex--;
            } else if (from > to && colIndex >= to && colIndex < from) {
                colIndex++;
            }
            cols[String(colIndex)] = formats.cols[key];
        });

        const cells = {};
        Object.keys(formats.cells).forEach(key => {
            const parts = key.split(':');
            const rowIndex = parseInt(parts[0], 10);
            let colIndex = parseInt(parts[1], 10);
            if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
                return;
            }
            if (colIndex === from) {
                colIndex = to;
            } else if (from < to && colIndex > from && colIndex <= to) {
                colIndex--;
            } else if (from > to && colIndex >= to && colIndex < from) {
                colIndex++;
            }
            cells[`${rowIndex}:${colIndex}`] = formats.cells[key];
        });

        return { cells, rows: { ...formats.rows }, cols };
    }

    function parseCmpPhotoId(value) {
        const match = String(value || '').match(/^\[cmp-photo:([A-Za-z0-9._-]+\.(?:jpe?g|png|gif|webp))\]$/i);
        return match ? match[1] : '';
    }

    function parseEmbeddedImageCoords(value, fallbackRow, fallbackCol) {
        const match = String(value || '').match(/^\[embedded-image:(\d+):(\d+)\]$/);
        if (!match) {
            return { row: fallbackRow, col: fallbackCol };
        }
        return {
            row: parseInt(match[1], 10),
            col: parseInt(match[2], 10),
        };
    }

    function sheetEmbeddedImageSrc(rowIndex, colIndex, value) {
        const sheetSku = String(currentCdRow?.sheet_sku || currentCdRow?.sku || '').trim();
        if (!sheetSku || !sheetImageUrl) {
            return '';
        }
        const params = new URLSearchParams({
            sheet_sku: sheetSku,
            sku: String(currentCdRow?.sku || sheetSku).trim(),
            v: String(currentCdRow?.sheet_image_v || 1),
        });
        const photoId = parseCmpPhotoId(value);
        if (photoId) {
            params.set('photo', photoId);
        } else {
            params.set('row', String(rowIndex));
            params.set('col', String(colIndex));
        }
        return `${sheetImageUrl}?${params.toString()}`;
    }

    function isSheetImageUrl(value) {
        if (value == null || value === '') {
            return false;
        }
        if (typeof value === 'string') {
            if (
                value.startsWith('data:image/')
                || value.startsWith('[embedded-image:')
                || value.startsWith('[cmp-photo:')
            ) {
                return true;
            }
            // Skip expensive checks on huge non-URL blobs.
            if (value.length > 2000) {
                return false;
            }
        }
        const url = String(value).trim();
        if (!url) {
            return false;
        }
        if (url.startsWith('/')) {
            return /\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i.test(url)
                || url.includes('/storage/');
        }
        if (!/^https?:\/\//i.test(url)) {
            return false;
        }
        return /\.(jpe?g|png|gif|webp|bmp|svg)(\?|$)/i.test(url)
            || /googleusercontent\.com/i.test(url)
            || /ggpht\.com/i.test(url)
            || /cdn\.shopify\.com/i.test(url)
            || /docs\.google\.com\/feeds/i.test(url)
            || /drive\.google\.com\/thumbnail/i.test(url);
    }

    function isSheetLinkUrl(value) {
        const url = String(value || '').trim();
        if (!/^https?:\/\//i.test(url)) {
            return false;
        }
        return !isSheetImageUrl(url);
    }

    function isSheetSpecColumn(colIndex) {
        const specCol = detectSpecColumnIndex(currentSheetCells);
        return specCol !== null && colIndex === specCol;
    }

    function getSheetCellPlainText(cell) {
        return (cell.innerText || cell.textContent || '').replace(/\s+/g, ' ').trim();
    }

    function canPasteSheetImageIntoCell(cell) {
        if (!cell) {
            return false;
        }
        const colIndex = parseInt(cell.dataset.col, 10);
        const rowIndex = parseInt(cell.dataset.row, 10);
        if (Number.isNaN(colIndex) || Number.isNaN(rowIndex)) {
            return false;
        }
        if (isSheetSpecColumn(colIndex) || isSheetCriticalColumn(colIndex) || isSheetQcColumn(colIndex)) {
            return false;
        }
        if (isCompanyNameRow(rowIndex, currentSheetCells) || isCommRow(rowIndex, currentSheetCells)) {
            return false;
        }
        return true;
    }

    function resolveSheetPasteCell(eventTarget) {
        const fromTarget = eventTarget && eventTarget.closest
            ? eventTarget.closest('.cd-sheet-cell')
            : null;
        if (fromTarget) {
            return fromTarget;
        }
        if (selectedSheetCell) {
            return document.querySelector(
                `.cd-sheet-cell[data-row="${selectedSheetCell.row}"][data-col="${selectedSheetCell.col}"]`
            );
        }
        return null;
    }

    function getClipboardImageFile(clipboardData) {
        if (!clipboardData) {
            return null;
        }
        const items = clipboardData.items;
        if (items) {
            for (let i = 0; i < items.length; i++) {
                if (items[i].type && items[i].type.indexOf('image/') === 0) {
                    const file = items[i].getAsFile();
                    if (file) {
                        return file;
                    }
                }
            }
        }
        const files = clipboardData.files;
        if (files) {
            for (let i = 0; i < files.length; i++) {
                if (files[i].type && files[i].type.indexOf('image/') === 0) {
                    return files[i];
                }
            }
        }
        return null;
    }

    function getClipboardHtmlImageSrc(clipboardData) {
        const html = clipboardData && clipboardData.getData ? (clipboardData.getData('text/html') || '') : '';
        if (!html || html.length > 2000000) {
            return '';
        }
        const match = html.match(/<img[^>]+src=["']([^"']+)["']/i);
        return match ? match[1].trim() : '';
    }

    function fitSheetCellImageEl(img) {
        if (!img) {
            return;
        }
        img.removeAttribute('width');
        img.removeAttribute('height');
        img.classList.add('cd-sheet-img');
        img.style.maxWidth = '120px';
        img.style.maxHeight = '80px';
        img.style.width = 'auto';
        img.style.height = 'auto';
        img.style.objectFit = 'contain';
        img.style.display = 'block';
        img.style.margin = '0 auto';
    }

    function renderPastedSheetImagePreview(cell, previewUrl, storedValue) {
        const rowIndex = parseInt(cell.dataset.row, 10);
        const colIndex = parseInt(cell.dataset.col, 10);
        const td = cell.closest('td');
        if (!td || Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
            return false;
        }
        if (currentSheetCells[rowIndex]) {
            currentSheetCells[rowIndex][colIndex] = storedValue;
        }
        const token = storedValue.startsWith('data:image/')
            ? `[embedded-image:${rowIndex}:${colIndex}]`
            : storedValue;
        td.innerHTML = `<div class="cd-sheet-cell cd-sheet-cell-image" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(token)}" data-embedded="1" title="Product photo">
            <img src="${escapeHtmlAttr(previewUrl)}" class="cd-sheet-img" alt="Product photo">
        </div>`;
        return true;
    }

    function applyPastedImageFileToSheetCell(cell, file) {
        const previewUrl = URL.createObjectURL(file);
        const reader = new FileReader();
        reader.onload = function () {
            const dataUrl = String(reader.result || '');
            if (!dataUrl.startsWith('data:image/')) {
                URL.revokeObjectURL(previewUrl);
                setSheetStatus('Could not read pasted image.', true);
                return;
            }
            renderPastedSheetImagePreview(cell, previewUrl, dataUrl);
            scheduleAutoSaveComparisonSheet(400, { rerender: true, refreshTable: false });
        };
        reader.onerror = function () {
            URL.revokeObjectURL(previewUrl);
            setSheetStatus('Could not read pasted image.', true);
        };
        reader.readAsDataURL(file);
    }

    function applyPastedImageSrcToSheetCell(cell, src) {
        if (src.startsWith('data:image/')) {
            fetch(src).then(function (response) { return response.blob(); }).then(function (blob) {
                applyPastedImageFileToSheetCell(cell, blob);
            }).catch(function () {
                renderPastedSheetImagePreview(cell, src, src);
                scheduleAutoSaveComparisonSheet(400, { rerender: true, refreshTable: false });
            });
            return;
        }
        convertSheetCellValue(cell, src, false);
        scheduleAutoSaveComparisonSheet(1000, { rerender: false, refreshTable: false });
    }

    function convertSheetCellValue(cell, value, forceText) {
        const rowIndex = parseInt(cell.dataset.row, 10);
        const colIndex = parseInt(cell.dataset.col, 10);
        if (Number.isNaN(rowIndex) || Number.isNaN(colIndex)) {
            return false;
        }

        const td = cell.closest('td');
        if (!td) {
            return false;
        }

        td.innerHTML = sheetCellEditorHtml(value, rowIndex, colIndex, forceText || isSheetSpecColumn(colIndex));
        if (currentSheetCells[rowIndex]) {
            currentSheetCells[rowIndex][colIndex] = value;
        }
        return true;
    }

    function maybeConvertSheetCellToLink(cell) {
        if (!cell || cell.classList.contains('cd-sheet-cell-link')) {
            return false;
        }
        if (cell.getAttribute('contenteditable') !== 'true') {
            return false;
        }

        const colIndex = parseInt(cell.dataset.col, 10);
        if (isSheetSpecColumn(colIndex)) {
            return false;
        }
        if (isCompanyNameRow(parseInt(cell.dataset.row, 10), currentSheetCells)) {
            return false;
        }

        const text = getSheetCellPlainText(cell);
        if (!isSheetLinkUrl(text)) {
            return false;
        }

        return convertSheetCellValue(cell, text, false);
    }

    function maybeRefreshCompanyNameCell(cell) {
        if (!cell || cell.classList.contains('cd-sheet-cell-company')) {
            return false;
        }
        if (cell.getAttribute('contenteditable') !== 'true') {
            return false;
        }

        const rowIndex = parseInt(cell.dataset.row, 10);
        const colIndex = parseInt(cell.dataset.col, 10);
        if (isSheetSpecColumn(colIndex) || !isCompanyNameRow(rowIndex, currentSheetCells)) {
            return false;
        }

        const text = (cell.innerText || '').trimEnd();
        convertSheetCellValue(cell, text, false);
        return true;
    }

    function sheetCellEditorHtml(value, rowIndex, colIndex, forceText) {
        if (!forceText && isSheetCriticalColumn(colIndex)) {
            return priorityCellEditorHtml(value, rowIndex, colIndex, 'Critical');
        }
        if (!forceText && isSheetQcColumn(colIndex)) {
            return priorityCellEditorHtml(value, rowIndex, colIndex, 'QC');
        }
        const rawText = String(value ?? '');
        const text = rawText.trim();
        if (!forceText && isSheetImageUrl(text)) {
            const isStoredPhoto = text.startsWith('[cmp-photo:')
                || text.startsWith('data:image/')
                || text.startsWith('[embedded-image:');
            const attrValue = text.startsWith('data:image/')
                ? `[embedded-image:${rowIndex}:${colIndex}]`
                : text;
            // NEVER put megabyte base64 data-URLs into innerHTML — that freezes the page.
            // Load stored photos via /sheet/image?photo=… (stable) or legacy row/col.
            if (isStoredPhoto) {
                // Request by current cell position so the server can read this cell's value;
                // legacy [embedded-image:r:c] coords inside the value still locate the file.
                const src = sheetEmbeddedImageSrc(rowIndex, colIndex, attrValue);
                if (src) {
                    return `<div class="cd-sheet-cell cd-sheet-cell-image" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(attrValue)}" data-embedded="1" title="Product photo">
                        <img src="${escapeHtmlAttr(src)}" class="cd-sheet-img" alt="Product photo" loading="lazy" decoding="async">
                    </div>`;
                }
                return `<div class="cd-sheet-cell cd-sheet-cell-image" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(attrValue)}" data-embedded="1" title="Embedded image">
                    <span class="cd-sheet-img-ph" aria-hidden="true"><i class="mdi mdi-image-outline"></i></span>
                </div>`;
            }
            return `<div class="cd-sheet-cell cd-sheet-cell-image" contenteditable="true" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(attrValue)}" title="Product photo"><img src="${escapeHtmlAttr(text)}" class="cd-sheet-img" alt="Product photo" referrerpolicy="no-referrer" loading="lazy" decoding="async"></div>`;
        }
        if (!forceText && isSheetLinkUrl(text)) {
            return `<div class="cd-sheet-cell cd-sheet-cell-link" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(text)}" title="${escapeHtmlAttr(text)}">
                <a href="${escapeHtmlAttr(text)}" target="_blank" rel="noopener noreferrer"
                    class="cd-sheet-link-btn"
                    title="Open link" aria-label="Open link">
                    <span class="comparison-clink-dot comparison-clink-dot-present" aria-hidden="true"></span>
                </a>
            </div>`;
        }
        if (!forceText && !text && isSheetLinkRow(rowIndex)
            && !isSheetSpecColumn(colIndex)
            && !isSheetCriticalColumn(colIndex)
            && !isSheetQcColumn(colIndex)) {
            return `<div class="cd-sheet-cell cd-sheet-cell-link cd-sheet-cell-link-missing" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="" title="No link — double-click to add" aria-label="No link">
                <span class="comparison-clink-dot comparison-clink-dot-missing" aria-hidden="true"></span>
            </div>`;
        }
        if (!forceText && isCompanyNameDataCell(rowIndex, colIndex, forceText) && text) {
            const storedName = rawText.replace(/\r\n/g, '\n').trimEnd();
            return `<div class="cd-sheet-cell cd-sheet-cell-company" contenteditable="false" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}" data-value="${escapeHtmlAttr(storedName)}" title="${escapeHtmlAttr(storedName)}" aria-label="${escapeHtmlAttr(storedName)}">
                <span class="comparison-company-dot" aria-hidden="true"></span>
            </div>`;
        }
        if (!forceText && isCommDataCell(rowIndex, colIndex)) {
            return commCellEditorHtml(rowIndex, colIndex, getSupplierNameForColumn(colIndex));
        }
        if (!text) {
            return `<div class="cd-sheet-cell cd-sheet-cell-empty" contenteditable="true" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}"></div>`;
        }
        return `<div class="cd-sheet-cell" contenteditable="true" spellcheck="false" data-row="${rowIndex}" data-col="${colIndex}">${escapeHtml(text)}</div>`;
    }

    function renderSheetEditor(cells, options) {
        const opts = Object.assign({ migrateDimWt: false, sortByPrice: false }, options || {});
        // Avoid deep-copy + column surgery on every paint unless migration requested.
        if (opts.migrateDimWt) {
            currentSheetCells = ensureLeadColumns(cells || []);
            currentSheetCells = ensureDimWtPkgSections(currentSheetCells);
        } else {
            currentSheetCells = Array.isArray(cells) ? cells : (currentSheetCells || []);
            if (!currentSheetCells.length) {
                currentSheetCells = ensureLeadColumns([]);
            }
        }
        if (currentSheetCells.length === 0) {
            currentSheetCells = [['Amz', '5 Core', 'Product Photo', 'Critical', 'QC', '', '']];
        }

        const colCountBeforeMove = Math.max(...currentSheetCells.map(row => row.length), 1);
        for (let r = 0; r < currentSheetCells.length; r++) {
            const row = currentSheetCells[r];
            if (!Array.isArray(row)) {
                currentSheetCells[r] = Array.from({ length: colCountBeforeMove }, () => '');
                continue;
            }
            while (row.length < colCountBeforeMove) row.push('');
            if (row.length > colCountBeforeMove) {
                currentSheetCells[r] = row.slice(0, colCountBeforeMove);
            }
        }

        if (opts.sortByPrice) {
            const priceSort = sortSupplierColumnsByPrice(currentSheetCells);
            currentSheetCells = priceSort.cells;
            if (priceSort.mapping) {
                currentSheetFormats = remapFormatColumns(currentSheetFormats, priceSort.mapping);
            }
        }

        const colCount = Math.max(...currentSheetCells.map(row => row.length), 1);
        refreshAutoSheetFormats(currentSheetCells);
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const criticalCol = detectCriticalColumnIndex(currentSheetCells, specCol);
        const qcCol = detectQcColumnIndex(currentSheetCells, specCol);
        const head = document.getElementById('comparison-cd-sheet-head');
        const body = document.getElementById('comparison-cd-sheet-body');
        if (!head || !body) return;

        // Cache column roles for cell helpers during this render.
        sheetRenderColCache = { specCol, criticalCol, qcCol };

        selectedSheetMultiRows = new Set(
            [...selectedSheetMultiRows].filter(r => r > 0 && r < currentSheetCells.length)
        );
        const selectableCount = Math.max(0, currentSheetCells.length - 1);
        const checkedCount = selectedSheetMultiRows.size;
        const allSelectableChecked = selectableCount > 0 && checkedCount === selectableCount;

        let headHtml = '<tr><th class="cd-row-num">#</th>';
        headHtml += `<th class="cd-row-select-col" title="Select rows for bulk Critical / QC edit">
            <input type="checkbox" id="cd-sheet-select-all-rows" title="Select all rows" ${allSelectableChecked ? 'checked' : ''} ${selectableCount ? '' : 'disabled'}>
        </th>`;
        for (let c = 0; c < colCount; c++) {
            const selectedClass = selectedSheetCol === c ? ' cd-axis-selected' : '';
            const isPriorityCol = (criticalCol !== null && c === criticalCol) || (qcCol !== null && c === qcCol);
            let headerText = getSheetColumnHeaderLabel(c, currentSheetCells);
            if (c !== specCol - 2 && c !== specCol - 1 && c !== specCol
                && !(criticalCol !== null && c === criticalCol)
                && !(qcCol !== null && c === qcCol)) {
                // Keep short letter for unnamed supplier cols when no supplier name yet.
                if (!getSupplierNameForColumn(c, currentSheetCells) && !String((currentSheetCells[0] || [])[c] || '').trim()) {
                    headerText = columnLetter(c);
                }
            }
            headHtml += `<th class="cd-col-header cd-select-col${isPriorityCol ? ' cd-priority-col' : ''}${selectedClass}" data-col="${c}" draggable="true" title="Click to select · drag to move column ${escapeHtmlAttr(headerText)}">
                <span class="cd-col-header-inner">
                    <span class="cd-col-header-label">${escapeHtml(headerText)}</span>
                    <button type="button" class="cd-sheet-col-edit-btn" data-col="${c}" draggable="false" title="Edit column ${escapeHtmlAttr(headerText)}" aria-label="Edit column ${escapeHtmlAttr(headerText)}">
                        <i class="mdi mdi-pencil-outline" aria-hidden="true"></i>
                    </button>
                </span>
            </th>`;
        }
        headHtml += '<th class="cd-row-edit-col" title="Edit Critical / QC for selected rows">Edit</th>';
        headHtml += '</tr>';
        head.innerHTML = headHtml;

        const parts = [];
        for (let r = 0; r < currentSheetCells.length; r++) {
            const row = currentSheetCells[r] || [];
            const rowSelectedClass = selectedSheetRow === r ? ' cd-axis-selected' : '';
            const multiSelectedClass = selectedSheetMultiRows.has(r) ? ' cd-multi-selected' : '';
            const commRowClass = isCommRow(r, currentSheetCells) ? ' cd-comm-row' : '';
            const specLabel = row[specCol] ?? '';
            const innerPkgClass = isInnerPkgSectionLabel(specLabel) ? ' cd-inner-pkg-row' : '';
            const ctnPkgClass = isCtnPkgSectionLabel(specLabel) ? ' cd-ctn-pkg-row' : '';
            const pkgHeaderClass = isPkgSectionHeaderLabel(specLabel) ? ' cd-pkg-section-header' : '';
            const canSelectRow = r > 0;
            let rowHtml = `<tr class="${selectedSheetRow === r ? 'cd-row-selected' : ''}${multiSelectedClass}${commRowClass}${innerPkgClass}${ctnPkgClass}${pkgHeaderClass}"><td class="cd-row-num cd-select-row${rowSelectedClass}" data-row="${r}" draggable="true" title="Click to select · drag to move row ${r + 1}">${r + 1}</td>`;
            rowHtml += `<td class="cd-row-select-col">`;
            if (canSelectRow) {
                rowHtml += `<input type="checkbox" class="cd-sheet-row-select" data-row="${r}" title="Select row ${r + 1}" ${selectedSheetMultiRows.has(r) ? 'checked' : ''}>`;
            }
            rowHtml += `</td>`;
            for (let c = 0; c < colCount; c++) {
                const value = row[c] ?? '';
                const isSpec = c === specCol;
                const isPriority = (criticalCol !== null && c === criticalCol) || (qcCol !== null && c === qcCol);
                const colSelectedClass = selectedSheetCol === c ? ' cd-col-selected' : '';
                const cellSelectedClass = selectedSheetCell && selectedSheetCell.row === r && selectedSheetCell.col === c
                    ? ' cd-cell-selected'
                    : '';
                let cellInner = sheetCellEditorHtml(value, r, c, isSpec);
                if (!isSpec && !isPriority && isCommRow(r, currentSheetCells)) {
                    cellInner = commCellEditorHtml(r, c, getSupplierNameForColumn(c));
                }
                rowHtml += `<td data-sheet-col="${c}" class="${isSpec ? 'cd-label-cell' : ''}${isPriority ? ' cd-priority-col' : ''}${colSelectedClass}${cellSelectedClass}"${sheetCellTdStyle(r, c, isSpec)}>${cellInner}</td>`;
            }
            rowHtml += `<td class="cd-row-edit-col">`;
            if (canSelectRow) {
                rowHtml += `<button type="button" class="cd-sheet-row-edit-btn" data-row="${r}" title="Edit Critical / QC" aria-label="Edit Critical and QC for selected rows">
                    <i class="mdi mdi-pencil-outline" aria-hidden="true"></i>
                </button>`;
            }
            rowHtml += `</td></tr>`;
            parts.push(rowHtml);
        }
        body.innerHTML = parts.join('');
        sheetRenderColCache = null;

        applyPriorityRowFilters();
        updateSupplierCountBadge(currentSheetCells);
    }

    function applySheetSelectionHighlight() {
        const table = document.getElementById('comparison-cd-sheet-table');
        if (!table) return;

        table.querySelectorAll('.cd-select-row').forEach(el => {
            const row = parseInt(el.dataset.row, 10);
            el.classList.toggle('cd-axis-selected', selectedSheetRow === row);
        });
        table.querySelectorAll('.cd-select-col').forEach(el => {
            const col = parseInt(el.dataset.col, 10);
            el.classList.toggle('cd-axis-selected', selectedSheetCol === col);
        });
        table.querySelectorAll('#comparison-cd-sheet-body tr').forEach((tr, index) => {
            tr.classList.toggle('cd-row-selected', selectedSheetRow === index);
            tr.classList.toggle('cd-multi-selected', selectedSheetMultiRows.has(index));
            tr.querySelectorAll('td[data-sheet-col]').forEach((td) => {
                const dataCol = parseInt(td.dataset.sheetCol, 10);
                if (Number.isNaN(dataCol)) return;
                td.classList.toggle('cd-col-selected', selectedSheetCol === dataCol);
                td.classList.toggle(
                    'cd-cell-selected',
                    !!selectedSheetCell && selectedSheetCell.row === index && selectedSheetCell.col === dataCol
                );
            });
        });
    }

    function insertSheetRow() {
        readCellsFromEditor();
        const colCount = currentSheetCells[0]?.length || 6;
        const insertAt = selectedSheetRow !== null
            ? selectedSheetRow + 1
            : currentSheetCells.length;
        currentSheetCells.splice(insertAt, 0, Array.from({ length: colCount }, () => ''));
        currentSheetFormats.rows = shiftNumericFormatMap(currentSheetFormats.rows, insertAt, 1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, insertAt, 'row', 1);
        if (selectedSheetRow !== null && insertAt <= selectedSheetRow) {
            selectedSheetRow++;
        }
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Row inserted at position ${insertAt + 1}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function deleteSheetRow() {
        readCellsFromEditor();
        if (currentSheetCells.length <= 1) {
            setSheetStatus('Cannot delete the last row.', true);
            return;
        }

        const idx = selectedSheetRow !== null ? selectedSheetRow : currentSheetCells.length - 1;
        currentSheetCells.splice(idx, 1);
        currentSheetFormats.rows = shiftNumericFormatMap(currentSheetFormats.rows, idx, -1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, idx, 'row', -1);
        if (selectedSheetCell && selectedSheetCell.row === idx) {
            selectedSheetCell = null;
        } else if (selectedSheetCell && selectedSheetCell.row > idx) {
            selectedSheetCell = { row: selectedSheetCell.row - 1, col: selectedSheetCell.col };
        }
        selectedSheetRow = currentSheetCells.length ? Math.min(idx, currentSheetCells.length - 1) : null;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Row ${idx + 1} deleted.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function insertSheetColumn() {
        readCellsFromEditor();
        const insertAt = selectedSheetCol !== null
            ? selectedSheetCol + 1
            : (currentSheetCells[0]?.length || 0);
        currentSheetCells = currentSheetCells.map(row => {
            row.splice(insertAt, 0, '');
            return row;
        });
        currentSheetFormats.cols = shiftNumericFormatMap(currentSheetFormats.cols, insertAt, 1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, insertAt, 'col', 1);
        if (selectedSheetCol !== null && insertAt <= selectedSheetCol) {
            selectedSheetCol++;
        }
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Column inserted at ${columnLetter(insertAt)}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function deleteSheetColumn() {
        readCellsFromEditor();
        const colCount = currentSheetCells[0]?.length || 0;
        if (colCount <= 1) {
            setSheetStatus('Cannot delete the last column.', true);
            return;
        }

        const idx = selectedSheetCol !== null ? selectedSheetCol : colCount - 1;
        currentSheetCells = currentSheetCells.map(row => {
            row.splice(idx, 1);
            return row;
        });
        currentSheetFormats.cols = shiftNumericFormatMap(currentSheetFormats.cols, idx, -1);
        currentSheetFormats.cells = shiftCellFormatMap(currentSheetFormats.cells, idx, 'col', -1);
        if (selectedSheetCell && selectedSheetCell.col === idx) {
            selectedSheetCell = null;
        } else if (selectedSheetCell && selectedSheetCell.col > idx) {
            selectedSheetCell = { row: selectedSheetCell.row, col: selectedSheetCell.col - 1 };
        }
        selectedSheetCol = Math.min(idx, (currentSheetCells[0]?.length || 1) - 1);
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Column ${columnLetter(idx)} deleted.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function moveSheetRow(direction) {
        readCellsFromEditor();
        if (selectedSheetRow === null) {
            setSheetStatus('Select a row first (click the row number).', true);
            return;
        }

        const target = direction === 'up' ? selectedSheetRow - 1 : selectedSheetRow + 1;
        if (target < 0 || target >= currentSheetCells.length) {
            setSheetStatus(direction === 'up' ? 'Row is already at the top.' : 'Row is already at the bottom.', true);
            return;
        }

        const from = selectedSheetRow;
        const moved = currentSheetCells.splice(from, 1)[0];
        currentSheetCells.splice(target, 0, moved);
        currentSheetFormats = moveFormatRow(currentSheetFormats, from, target);
        if (selectedSheetCell) {
            let rowIndex = selectedSheetCell.row;
            if (rowIndex === from) {
                rowIndex = target;
            } else if (from < target && rowIndex > from && rowIndex <= target) {
                rowIndex--;
            } else if (from > target && rowIndex >= target && rowIndex < from) {
                rowIndex++;
            }
            selectedSheetCell = { row: rowIndex, col: selectedSheetCell.col };
        }
        selectedSheetRow = target;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Row moved ${direction === 'up' ? 'up' : 'down'} to position ${target + 1}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    function moveSheetColumn(direction) {
        readCellsFromEditor();
        if (selectedSheetCol === null) {
            setSheetStatus('Select a column first (click the column letter).', true);
            return;
        }

        const colCount = currentSheetCells[0]?.length || 0;
        const target = direction === 'left' ? selectedSheetCol - 1 : selectedSheetCol + 1;
        if (target < 0 || target >= colCount) {
            setSheetStatus(direction === 'left' ? 'Column is already at the left edge.' : 'Column is already at the right edge.', true);
            return;
        }

        const from = selectedSheetCol;
        currentSheetCells = currentSheetCells.map(row => {
            const moved = row.splice(from, 1)[0];
            row.splice(target, 0, moved);
            return row;
        });
        currentSheetFormats = moveFormatColumn(currentSheetFormats, from, target);
        if (selectedSheetCell) {
            let colIndex = selectedSheetCell.col;
            if (colIndex === from) {
                colIndex = target;
            } else if (from < target && colIndex > from && colIndex <= target) {
                colIndex--;
            } else if (from > target && colIndex >= target && colIndex < from) {
                colIndex++;
            }
            selectedSheetCell = { row: selectedSheetCell.row, col: colIndex };
        }
        selectedSheetCol = target;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Column moved ${direction === 'left' ? 'left' : 'right'} to ${columnLetter(target)}.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    // Drag-and-drop reorder: move a row from one position to another (keeps formats aligned).
    function moveSheetRowTo(from, to) {
        readCellsFromEditor();
        if (from == null || to == null || isNaN(from) || isNaN(to) || from === to) return;
        if (from < 0 || from >= currentSheetCells.length) return;
        if (to < 0 || to >= currentSheetCells.length) return;

        const moved = currentSheetCells.splice(from, 1)[0];
        currentSheetCells.splice(to, 0, moved);
        currentSheetFormats = moveFormatRow(currentSheetFormats, from, to);
        selectedSheetRow = to;
        selectedSheetCol = null;
        selectedSheetCell = null;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Row moved to position ${to + 1}.`, false);
        scheduleAutoSaveComparisonSheet(0);
    }

    // Drag-and-drop reorder: move a column from one position to another (keeps formats aligned).
    function moveSheetColumnTo(from, to) {
        readCellsFromEditor();
        const colCount = currentSheetCells[0]?.length || 0;
        if (from == null || to == null || isNaN(from) || isNaN(to) || from === to) return;
        if (from < 0 || from >= colCount || to < 0 || to >= colCount) return;

        currentSheetCells = moveSheetColumnData(currentSheetCells, from, to);
        currentSheetFormats = moveFormatColumn(currentSheetFormats, from, to);
        selectedSheetCol = to;
        selectedSheetRow = null;
        selectedSheetCell = null;
        renderSheetEditor(currentSheetCells);
        setSheetStatus(`Column moved to ${columnLetter(to)}.`, false);
        scheduleAutoSaveComparisonSheet(0);
    }

    // options.expandImages=true (default) resolves photo cells to full img.src (needed for save).
    // options.expandImages=false keeps [embedded-image:…] placeholders — much faster for ROI/UI reads.
    function readCellsFromEditor(options) {
        const expandImages = !options || options.expandImages !== false;
        const body = document.getElementById('comparison-cd-sheet-body');
        if (!body) return currentSheetCells;

        const baseColCount = Math.max(
            ...((currentSheetCells || []).map(row => (Array.isArray(row) ? row.length : 0))),
            1
        );
        const rows = [];
        const trList = body.children;
        for (let rowIndex = 0; rowIndex < trList.length; rowIndex++) {
            const tr = trList[rowIndex];
            if (!tr || tr.tagName !== 'TR') {
                continue;
            }

            // Prefer data-sheet-col tds (ignores # / select / Edit chrome columns).
            const dataTds = tr.querySelectorAll('td[data-sheet-col]');
            const row = Array.from({ length: baseColCount }, (_, colIndex) =>
                (currentSheetCells[rowIndex] || [])[colIndex] || ''
            );

            if (!dataTds.length) {
                rows.push(row);
                continue;
            }

            dataTds.forEach(td => {
                const colIndex = parseInt(td.dataset.sheetCol, 10);
                if (Number.isNaN(colIndex) || colIndex < 0) {
                    return;
                }
                while (row.length <= colIndex) {
                    row.push('');
                }

                if (isCommRow(rowIndex, currentSheetCells)) {
                    row[colIndex] = (currentSheetCells[rowIndex] || [])[colIndex] || '';
                    return;
                }

                const cell = td.firstElementChild && td.firstElementChild.classList?.contains('cd-sheet-cell')
                    ? td.firstElementChild
                    : td.querySelector('.cd-sheet-cell');
                if (!cell) {
                    row[colIndex] = (currentSheetCells[rowIndex] || [])[colIndex] || '';
                    return;
                }

                const stored = cell.dataset.value || '';

                if (cell.classList.contains('cd-sheet-cell-priority')) {
                    row[colIndex] = normalizePriorityValue(
                        stored || (currentSheetCells[rowIndex] || [])[colIndex] || 'Normal'
                    );
                    return;
                }

                // Photo cells: keep tokens / data:image in memory; never pull base64 from DOM.
                if (cell.classList.contains('cd-sheet-cell-image')) {
                    const fromMemory = (currentSheetCells[rowIndex] || [])[colIndex] || '';
                    const memoryText = String(fromMemory || '');
                    const storedText = String(stored || '');
                    // Preserve ALL photo token forms, including legacy [embedded-image:r:c]
                    // (coords point at the stored file — do not rewrite them here).
                    if (
                        memoryText.startsWith('data:image/')
                        || memoryText.startsWith('[cmp-photo:')
                        || memoryText.startsWith('[embedded-image:')
                    ) {
                        row[colIndex] = fromMemory;
                        return;
                    }
                    if (
                        storedText.startsWith('[cmp-photo:')
                        || storedText.startsWith('[embedded-image:')
                        || storedText.startsWith('data:image/')
                    ) {
                        row[colIndex] = stored;
                        return;
                    }
                    if (!expandImages) {
                        row[colIndex] = fromMemory || stored || '';
                        return;
                    }
                    const img = cell.querySelector('img');
                    row[colIndex] = (img && img.src && !img.src.startsWith('data:') && !img.src.includes('/sheet/image'))
                        ? img.src
                        : (fromMemory || stored || '');
                    return;
                }

                if (stored) {
                    row[colIndex] = stored;
                    return;
                }
                row[colIndex] = (cell.textContent || '').trimEnd();
            });

            rows.push(row);
        }

        currentSheetCells = rows;
        return rows;
    }

    function setSheetStatus(message, isError) {
        const el = document.getElementById('comparison-cd-sheet-status');
        if (!el) {
            return;
        }
        if (!isError) {
            el.classList.add('d-none');
            el.setAttribute('aria-hidden', 'true');
            el.textContent = '';
            return;
        }
        el.textContent = message;
        el.classList.remove('d-none');
        el.setAttribute('aria-hidden', 'false');
        el.classList.add('text-danger');
        el.classList.remove('text-success');
    }

    let sheetAutoSaveOpts = { rerender: false, refreshTable: false };
    let sheetAutoSaveQueuedOpts = null;

    function cancelScheduledAutoSave() {
        clearTimeout(sheetAutoSaveTimer);
        sheetAutoSaveTimer = null;
    }

    function scheduleAutoSaveComparisonSheet(delay = 1200, options) {
        if (sheetEditorHydrating || !currentCdRow) return;
        sheetAutoSaveOpts = Object.assign({ rerender: false, refreshTable: false }, options || {});
        clearTimeout(sheetAutoSaveTimer);
        sheetAutoSaveTimer = setTimeout(() => autoSaveComparisonSheet(sheetAutoSaveOpts), delay);
    }

    function autoSaveComparisonSheet(options) {
        if (sheetEditorHydrating || !currentCdRow) return;
        const opts = Object.assign({ rerender: false, refreshTable: false }, options || sheetAutoSaveOpts || {});
        if (sheetSaveInFlight) {
            sheetSaveQueued = true;
            sheetAutoSaveQueuedOpts = opts;
            return;
        }

        // Keep placeholders — expanding embedded images on every autosave freezes the UI.
        const cells = readCellsFromEditor({ expandImages: false });

        sheetSaveInFlight = true;

        fetch(sheetSaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: currentCdRow.sku,
                parent: currentCdRow.parent || '',
                linked_skus: sheetSaveTargetSkus(currentCdRow),
                bulk_edit_skus: comparisonBulkEditPayload(),
                cells: cells,
                formats: currentSheetFormats,
                // Persist URL metadata only — Google Sheet is pull-only via C Link Refresh.
                google_sheet_url: document.getElementById('comparison-cd-google-url').value.trim(),
                google_sheet_tab: document.getElementById('comparison-cd-google-tab').value.trim() || 'Sheet1',
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Save failed.');
            }
            currentSheetFormats = normalizeSheetFormats(data.formats || currentSheetFormats);
            const returnedCells = sanitizeSheetCellsForUi(data.cells || cells);
            // Keep editor state in sync without rebuilding DOM (quiet save).
            currentSheetCells = returnedCells;
            applyAutoSheetFormatsFromPayload(data, returnedCells);

            // Quiet save by default: no full sheet rebuild / Tabulator reload (those hang the page).
            if (opts.rerender) {
                renderSheetEditor(returnedCells, { migrateDimWt: false, sortByPrice: false });
            }
            if (opts.refreshTable) {
                clearTimeout(tableRefreshTimer);
                tableRefreshTimer = setTimeout(() => { if (table) table.replaceData(); }, 400);
            }
        })
        .catch(err => {
            setSheetStatus(err.message || 'Auto-save failed.', true);
        })
        .finally(() => {
            sheetSaveInFlight = false;
            if (sheetSaveQueued) {
                sheetSaveQueued = false;
                const queuedOpts = sheetAutoSaveQueuedOpts || { rerender: false, refreshTable: false };
                sheetAutoSaveQueuedOpts = null;
                scheduleAutoSaveComparisonSheet(500, queuedOpts);
            }
        });
    }

    function applySheetPayload(data, row) {
        sheetEditorHydrating = true;
        cancelScheduledAutoSave();

        const clink = (row?.clink || data.clink || '').trim();
        const sheetUrl = data.google_sheet_url || (isGoogleSheetUrl(clink) ? clink : '');
        document.getElementById('comparison-cd-google-url').value = sheetUrl;
        updateCdGoogleUrlDotUI();
        document.getElementById('comparison-cd-google-tab').value = data.google_sheet_tab || 'Sheet1';
        if (data && data.dim_wt && typeof data.dim_wt === 'object') {
            currentDimWtData = data.dim_wt;
        }
        if (data && data.qc_issues && typeof data.qc_issues === 'object') {
            currentQcIssuesData = data.qc_issues;
        }
        if (data && data.reviews && typeof data.reviews === 'object') {
            currentReviewsData = data.reviews;
        }
        if (data && data.siblings && typeof data.siblings === 'object') {
            currentSiblingsData = data.siblings;
        } else if (row?.parent) {
            currentSiblingsData = {
                parent: row.parent,
                siblings: linkedSkusForRow(row),
                count: linkedSkusForRow(row).length || 1,
            };
        }
        updateQcIssuesBadge(currentQcIssuesData);
        updateReviewsBadge(currentReviewsData);
        updateSiblingsBadge(currentSiblingsData);
        currentSheetFormats = normalizeSheetFormats(data.formats || {});
        // Drop any residual data:image values before any scans / DOM work.
        const safeCells = sanitizeSheetCellsForUi(data.cells || []);
        applyAutoSheetFormatsFromPayload(data, safeCells);
        // Fast first paint: skip dim/wt migration + price sort (those block the UI).
        let sheetCells = ensureLeadColumns(safeCells);
        const specCol = detectSpecColumnIndex(sheetCells);
        sheetCells = ensureCommRow(sheetCells, specCol).cells;
        renderSheetEditor(sheetCells, { migrateDimWt: false, sortByPrice: false });
        captureClinkPreloadedSuppliers(currentSheetCells);
        const categoryNames = (Array.isArray(row?.categories) && row.categories.length)
            ? row.categories.map(function (c) { return String((c && c.name != null) ? c.name : '').trim(); }).filter(Boolean)
            : String(row?.category || currentCdRow?.category || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        loadComparisonSuppliersForCategory(categoryNames);
        sheetEditorHydrating = false;

        // Apply Dim/Wt rows after the page is responsive (cancel prior timer on rapid SKU switches).
        const applySku = String(row?.sku || currentCdRow?.sku || '');
        if (sheetDimWtApplyTimer) {
            clearTimeout(sheetDimWtApplyTimer);
            sheetDimWtApplyTimer = null;
        }
        sheetDimWtApplyTimer = window.setTimeout(function () {
            sheetDimWtApplyTimer = null;
            if (!currentCdRow || String(currentCdRow.sku || '') !== applySku) {
                return;
            }
            sheetEditorHydrating = true;
            try {
                let next = applyDimWtDataToSheet(currentSheetCells, currentDimWtData);
                next = sanitizeSheetCellsForUi(next);
                currentSheetFormats = applyDimWtSectionFormats(next, currentSheetFormats);
                renderSheetEditor(next, { migrateDimWt: false, sortByPrice: false });
            } finally {
                sheetEditorHydrating = false;
            }
        }, 250);
    }

    function syncComparisonFromClink(row, options) {
        const opts = options || {};
        const importBtn = document.getElementById('comparison-cd-import-btn');
        let importBtnOriginalHtml = null;
        if (importBtn) {
            importBtn.disabled = true;
            importBtnOriginalHtml = importBtn.innerHTML;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Refreshing...';
        }
        setSheetStatus(opts.message || 'Loading comparison sheet from C link...', false);

        return fetch(sheetSyncClinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: row.sku,
                parent: row.parent || '',
                google_sheet_tab: document.getElementById('comparison-cd-google-tab').value.trim() || 'Sheet1',
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Could not load C link sheet.');
            }
            applySheetPayload(data, row);
            if (opts.refreshTable && table) {
                table.replaceData();
            }
            return data;
        })
        .finally(() => {
            if (importBtn) {
                importBtn.disabled = false;
                if (importBtnOriginalHtml !== null) {
                    importBtn.innerHTML = importBtnOriginalHtml;
                }
            }
        });
    }

    function loadComparisonSheet(row) {
        const loadingEl = document.getElementById('comparison-cd-sheet-loading');
        const wrapEl = document.getElementById('comparison-cd-sheet-wrap');
        loadingEl?.classList.remove('d-none');
        wrapEl?.classList.add('d-none');
        setSheetStatus('Loading comparison sheet...', false);

        fetch(`${sheetGetUrl}?${buildSheetRequestParams(row).toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load sheet.');
            }

            if (data.sheet_sku) {
                row.sheet_sku = data.sheet_sku;
            }
            if (Array.isArray(data.linked_skus) && data.linked_skus.length) {
                row.linked_skus = data.linked_skus;
            }
            if (data.clink) {
                row.clink = data.clink;
                row.clink_is_sheet = !!data.clink_is_sheet;
                row.clink_sku = data.clink_sku || null;
            }
            currentCdRow = {
                ...row,
                sheet_sku: data.sheet_sku || row.sheet_sku,
                sheet_image_v: data.updated_at || Date.now(),
            };

            applySheetPayload(data, currentCdRow || row);

            // Google Sheet is pull-only via "C Link Refresh". Do not auto-import on open
            // (that sync was a major cause of repeated Page Unresponsive freezes).
            if ((!!data.clink_is_sheet || isGoogleSheetUrl(row.clink)) && !data.has_sheet_data) {
                setSheetStatus('No local sheet yet. Click "C Link Refresh" to pull from Google Sheet.', false);
            }
        })
        .catch(err => {
            sheetEditorHydrating = true;
            cancelScheduledAutoSave();
            currentSheetFormats = normalizeSheetFormats({});
            renderSheetEditor([]);
            sheetEditorHydrating = false;
            setSheetStatus(err.message || 'Could not load comparison sheet.', true);
        })
        .finally(() => {
            loadingEl?.classList.add('d-none');
            wrapEl?.classList.remove('d-none');
        });
    }

    function importComparisonGoogleSheet() {
        if (!currentCdRow) return;

        const clink = (currentCdRow.clink || '').trim();
        if (!isGoogleSheetUrl(clink) && !document.getElementById('comparison-cd-google-url').value.trim()) {
            setSheetStatus('Set a Google Sheet URL in the C link column first.', true);
            return;
        }

        syncComparisonFromClink(currentCdRow, {
            message: 'Refreshing comparison sheet from C link...',
            refreshTable: true,
        }).catch(err => {
            setSheetStatus(err.message || 'Could not refresh C link sheet.', true);
        });
    }

    function loadLmpTab(row) {
        const sku = row.sku || '';
        if (lmpLoadedForSku === sku) return;
        lmpLoadedForSku = sku;

        const clinkWrap = document.getElementById('comparison-cd-clink-wrap');
        const clinkLink = document.getElementById('comparison-cd-clink-link');
        const clinkText = document.getElementById('comparison-cd-clink-text');
        const clink = (row.clink || '').trim();

        if (clink) {
            clinkWrap.classList.remove('d-none');
            clinkLink.href = clink;
            clinkText.textContent = clink;
        } else {
            clinkWrap.classList.add('d-none');
            clinkLink.href = '#';
            clinkText.textContent = '';
        }

        document.getElementById('comparison-cd-lmp-list').innerHTML = amazonLmpLoadingHtml();
        loadAmazonCompetitors(sku, document.getElementById('comparison-cd-lmp-list'), 'comparison-cd');
    }

    function findSupplierNameRowIndex(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            if (isSupplierNameRow(cells, rowIndex, specCol)) {
                return rowIndex;
            }
        }
        return null;
    }

    function findSheetLinkRowIndex(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findRowIndexByLabel(cells, 'supplier link', specCol);
        if (rowIndex !== null) {
            return rowIndex;
        }
        // Sheets often label this row simply "Link".
        for (let r = 0; r < (cells || []).length; r++) {
            const label = String((cells[r] || [])[specCol] || '').trim().toLowerCase();
            if (label === 'link') {
                return r;
            }
        }
        return null;
    }

    function ensureSupplierLinkRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findSheetLinkRowIndex(cells, specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Supplier Link';

        let insertAt = 0;
        const personRowIndex = findRowIndexByLabel(cells, 'person name review', specCol);
        const photoRowIndex = findRowIndexByLabel(cells, 'product photo', specCol);
        if (personRowIndex !== null) {
            insertAt = personRowIndex + 1;
        } else if (photoRowIndex !== null) {
            insertAt = photoRowIndex + 1;
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);
        return { cells: nextCells, rowIndex: insertAt };
    }

    function ensureSupplierNameRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findSupplierNameRowIndex(cells, specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Supplier Name';

        const companyRowIndex = findRowIndexByLabel(cells, 'company name', specCol);
        let insertAt = 0;
        if (companyRowIndex !== null) {
            insertAt = companyRowIndex;
        } else {
            const supplierLinkRow = findSheetLinkRowIndex(cells, specCol);
            if (supplierLinkRow !== null) {
                insertAt = supplierLinkRow + 1;
            }
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);

        return { cells: nextCells, rowIndex: insertAt };
    }

    function ensureCompanyNameRow(cells, specCol) {
        specCol = specCol ?? detectSpecColumnIndex(cells);
        let rowIndex = findRowIndexByLabel(cells, 'company name', specCol);
        if (rowIndex !== null) {
            return { cells, rowIndex };
        }

        const colCount = Math.max(...cells.map(row => row.length), 6);
        const newRow = Array.from({ length: colCount }, () => '');
        newRow[specCol] = 'Company Name';

        let insertAt = 0;
        const supplierNameRow = findSupplierNameRowIndex(cells, specCol);
        if (supplierNameRow !== null) {
            insertAt = supplierNameRow + 1;
        } else {
            const supplierLinkRow = findSheetLinkRowIndex(cells, specCol);
            if (supplierLinkRow !== null) {
                insertAt = supplierLinkRow + 1;
            }
        }

        const nextCells = cells.slice();
        nextCells.splice(insertAt, 0, newRow);

        return { cells: nextCells, rowIndex: insertAt };
    }

    function normalizeSupplierNameKey(name) {
        return String(name || '').trim().toLowerCase();
    }

    function supplierNamesMatch(a, b) {
        return normalizeSupplierNameKey(a) === normalizeSupplierNameKey(b);
    }

    function captureClinkPreloadedSuppliers(cells) {
        clinkPreloadedSupplierByCol = {};
        clinkPreloadedSupplierNames = new Set();
        if (!Array.isArray(cells) || !cells.length) {
            return;
        }

        const specCol = detectSpecColumnIndex(cells);
        const supplierRowIndex = findSupplierNameRowIndex(cells, specCol);
        if (supplierRowIndex === null) {
            return;
        }

        const firstSupplierCol = getFirstSupplierColumnIndex(cells, specCol);
        const row = cells[supplierRowIndex] || [];
        for (let col = firstSupplierCol; col < row.length; col++) {
            if (isProtectedSheetColumn(col, cells)) {
                continue;
            }
            const name = String(row[col] || '').trim();
            if (!name) {
                continue;
            }
            clinkPreloadedSupplierByCol[col] = name;
            clinkPreloadedSupplierNames.add(normalizeSupplierNameKey(name));
        }
    }

    function isSupplierNameColumnBlank(cells, supplierRowIndex, col) {
        return !String((cells[supplierRowIndex] || [])[col] || '').trim();
    }

    function writeSupplierToColumn(cells, col, supplier, supplierRowIndex, supplierLinkRowIndex, companyRowIndex) {
        if (!cells[supplierRowIndex]) {
            cells[supplierRowIndex] = [];
        }
        cells[supplierRowIndex][col] = supplier.name || '';

        if (supplierLinkRowIndex !== null) {
            if (!cells[supplierLinkRowIndex]) {
                cells[supplierLinkRowIndex] = [];
            }
            cells[supplierLinkRowIndex][col] = supplier.link || '';
        }

        if (companyRowIndex !== null) {
            if (!cells[companyRowIndex]) {
                cells[companyRowIndex] = [];
            }
            cells[companyRowIndex][col] = supplier.company || '';
        }
    }

    function applySuppliersAddOnly(suppliers, supplierRowIndex, supplierLinkRowIndex, companyRowIndex) {
        const placedIds = new Set();
        let updated = 0;
        let added = 0;
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const firstSupplierCol = getFirstSupplierColumnIndex(currentSheetCells, specCol);

        let maxCol = Math.max(
            ...currentSheetCells.map(row => row.length),
            firstSupplierCol
        );

        Object.keys(clinkPreloadedSupplierByCol).forEach(key => {
            const col = parseInt(key, 10);
            if (Number.isNaN(col) || col < firstSupplierCol || isProtectedSheetColumn(col, currentSheetCells)) {
                return;
            }

            const preloadedName = clinkPreloadedSupplierByCol[col];
            const existingName = String((currentSheetCells[supplierRowIndex] || [])[col] || '').trim();
            const supplier = suppliers.find(item => {
                if (placedIds.has(item.id)) {
                    return false;
                }
                return supplierNamesMatch(item.name, preloadedName)
                    || (existingName && supplierNamesMatch(item.name, existingName));
            });

            if (!supplier) {
                return;
            }

            writeSupplierToColumn(
                currentSheetCells,
                col,
                supplier,
                supplierRowIndex,
                supplierLinkRowIndex,
                companyRowIndex
            );
            placedIds.add(supplier.id);
            updated++;
        });

        let col = firstSupplierCol;
        while (placedIds.size < suppliers.length) {
            const supplier = suppliers.find(item => !placedIds.has(item.id));
            if (!supplier) {
                break;
            }

            while (
                col < maxCol
                && (
                    isProtectedSheetColumn(col, currentSheetCells)
                    || !isSupplierNameColumnBlank(currentSheetCells, supplierRowIndex, col)
                )
            ) {
                col++;
            }

            if (col >= maxCol) {
                currentSheetCells = ensureSupplierColumnCount(
                    currentSheetCells,
                    firstSupplierCol,
                    col - firstSupplierCol + 1
                );
                maxCol = Math.max(...currentSheetCells.map(row => row.length), maxCol + 1);
            }

            writeSupplierToColumn(
                currentSheetCells,
                col,
                supplier,
                supplierRowIndex,
                supplierLinkRowIndex,
                companyRowIndex
            );
            placedIds.add(supplier.id);
            added++;
            col++;
        }

        return { added, updated, placed: placedIds.size, total: suppliers.length };
    }

    function ensureSupplierColumnCount(cells, firstSupplierCol, neededCount) {
        const startCol = Math.max(0, parseInt(firstSupplierCol, 10) || 0);
        const colCount = Math.max(...cells.map(row => row.length), startCol + 1);
        const currentSupplierCols = Math.max(0, colCount - startCol);
        if (neededCount <= currentSupplierCols) {
            return cells;
        }

        const toAdd = neededCount - currentSupplierCols;
        return cells.map(row => {
            while (row.length < colCount) {
                row.push('');
            }
            for (let i = 0; i < toAdd; i++) {
                row.push('');
            }
            return row;
        });
    }

    function copySpecsToMemory() {
        readCellsFromEditor();
        if (!currentSheetCells.length) {
            setSheetStatus('No comparison sheet loaded.', true);
            return;
        }

        const specCol = detectSpecColumnIndex(currentSheetCells);
        saveCopiedSpecLabelsToMemory(currentSheetCells.map(row => String((row || [])[specCol] ?? '').trimEnd()));
        const text = copiedSpecLabels.join('\n');
        const nonEmptyCount = copiedSpecLabels.filter(label => label.trim() !== '').length;

        const finish = (message, isError) => {
            setSheetStatus(message, isError);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text)
                .then(() => finish(`Copied ${copiedSpecLabels.length} spec row(s) to memory (${nonEmptyCount} with labels).`, false))
                .catch(() => finish(`Saved ${copiedSpecLabels.length} spec row(s) to memory (${nonEmptyCount} with labels).`, false));
            return;
        }

        finish(`Saved ${copiedSpecLabels.length} spec row(s) to memory (${nonEmptyCount} with labels).`, false);
    }

    function formatRoiNumber(value, decimals) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const num = parseFloat(value);
        if (Number.isNaN(num)) {
            return String(value).trim();
        }
        return Number(num.toFixed(decimals ?? 2)).toString();
    }

    function formatRoiCbm(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }
        const num = parseFloat(value);
        if (Number.isNaN(num)) {
            return String(value).trim();
        }
        return num.toFixed(3);
    }

    function computeFreightFromCbm(cbm) {
        const num = parseSheetNumber(cbm);
        if (num == null) {
            return '';
        }
        return formatRoiNumber(200 * num);
    }

    async function fetchPlatformLmpRates(sku) {
        const empty = {
            amazon: null,
            ebay: null,
            temu: null,
            shopify: null,
            prices: { amazon: null, ebay: null, temu: null, shopify: null },
            npft: { amazon: null, ebay: null, temu: null, shopify: null },
            nroi: { amazon: null, ebay: null, temu: null, shopify: null },
        };
        if (!sku) {
            return empty;
        }

        try {
            const res = await fetch(`${lmpRatesUrl}?sku=${encodeURIComponent(sku)}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (!data.success) {
                return empty;
            }
            return {
                amazon: data.amazon_lmp != null ? data.amazon_lmp : null,
                ebay: data.ebay_lmp != null ? data.ebay_lmp : null,
                temu: data.temu_lmp != null ? data.temu_lmp : null,
                shopify: data.shopify_lmp != null ? data.shopify_lmp : null,
                prices: {
                    amazon: data.amazon_price != null ? data.amazon_price : null,
                    ebay: data.ebay_price != null ? data.ebay_price : null,
                    temu: data.temu_price != null ? data.temu_price : null,
                    shopify: data.shopify_price != null ? data.shopify_price : null,
                },
                npft: { amazon: null, ebay: null, temu: null, shopify: null },
                nroi: { amazon: null, ebay: null, temu: null, shopify: null },
            };
        } catch (e) {
            return empty;
        }
    }

    function mapBreakdownMarketplaceKey(marketplace) {
        const mp = String(marketplace || '').toLowerCase().replace(/\s+/g, '');
        if (mp === 'amazon' || mp === 'fba') return 'amazon';
        if (mp === 'ebay' || mp === 'ebay1') return 'ebay';
        if (mp === 'temu') return 'temu';
        if (mp === 'shopify' || mp === 'sb2c' || mp === 'shopifyb2c') return 'shopify';
        return '';
    }

    function channelRoiMargin(channel, lmpRates) {
        const key = channelLmpKey(channel);
        const fromRates = lmpRates?.margin?.[key];
        if (fromRates != null && Number.isFinite(Number(fromRates)) && Number(fromRates) > 0) {
            return Number(fromRates);
        }
        return ROI_CHANNEL_MARGINS[key] ?? ROI_CHANNEL_MARGINS.amazon;
    }

    function channelRoiAdsPct(channel, lmpRates) {
        const key = channelLmpKey(channel);
        const fromRates = lmpRates?.ads?.[key];
        if (fromRates != null && Number.isFinite(Number(fromRates)) && Number(fromRates) >= 0) {
            return Number(fromRates);
        }
        return 0;
    }

    /** Read Ads% + margin from OV L30 / Pricing Master breakdown (per channel). */
    async function fetchChannelFeeMeta(sku) {
        const empty = {
            ads: { amazon: null, ebay: null, temu: null, shopify: null },
            margin: { amazon: null, ebay: null, temu: null, shopify: null },
        };
        if (!sku) {
            return empty;
        }
        try {
            const res = await fetch(`${cvrMasterBreakdownUrl}?sku=${encodeURIComponent(sku)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const rows = await res.json();
            if (!Array.isArray(rows)) {
                return empty;
            }
            const out = {
                ads: { amazon: null, ebay: null, temu: null, shopify: null },
                margin: { amazon: null, ebay: null, temu: null, shopify: null },
            };
            rows.forEach((item) => {
                const key = mapBreakdownMarketplaceKey(item?.marketplace);
                if (!key) {
                    return;
                }
                const margin = parseFloat(item?.margin);
                if (Number.isFinite(margin) && margin > 0) {
                    out.margin[key] = margin;
                }
                const ads = parseFloat(item?.tacos_ch != null ? item.tacos_ch : item?.ad);
                if (Number.isFinite(ads) && ads >= 0) {
                    out.ads[key] = ads;
                }
            });
            return out;
        } catch (e) {
            return empty;
        }
    }

    // Back-compat alias used by older call sites.
    async function fetchChannelNpftNroi(sku) {
        return fetchChannelFeeMeta(sku);
    }

    function getChannelPriceAfterLmp(channel, lmpRates) {
        const key = channelLmpKey(channel);
        if (key === 'overall') {
            const prices = Object.values(lmpRates?.prices || {})
                .map(v => (v != null && Number.isFinite(Number(v)) ? Number(v) : null))
                .filter(v => v != null && v > 0);
            if (!prices.length) {
                return '';
            }
            const avg = prices.reduce((sum, n) => sum + n, 0) / prices.length;
            return formatRoiNumber(avg);
        }
        const price = lmpRates?.prices?.[key];
        return price != null ? formatRoiNumber(price) : '';
    }

    function channelLmpKey(channel) {
        const key = String(channel || '').trim().toLowerCase();
        if (key === 'amz' || key === 'amazon') {
            return 'amazon';
        }
        if (key === 'ebay') {
            return 'ebay';
        }
        if (key === 'temu') {
            return 'temu';
        }
        if (key === 'shopify') {
            return 'shopify';
        }
        if (key === 'overall') {
            return 'overall';
        }
        return key;
    }

    function isRoiOverallChannel(channel) {
        return channelLmpKey(channel) === 'overall';
    }

    function getChannelRawLmp(channel, lmpRates) {
        const key = channelLmpKey(channel);
        const lmp = lmpRates?.[key];
        return lmp != null ? formatRoiNumber(lmp) : '';
    }

    function getChannelLmpSale(channel, lmpRates) {
        const key = channelLmpKey(channel);
        const lmp = lmpRates?.[key];
        if (lmp == null) {
            return '';
        }
        return formatRoiNumber(lmp * ROI_LMP_SALE_FACTOR);
    }

    function setRoiSaveStatus(message, isError) {
        const el = document.getElementById('comparison-roi-save-status');
        if (!el) {
            return;
        }
        el.textContent = message;
        el.classList.toggle('text-danger', !!isError);
        el.classList.toggle('text-success', !isError && !!message);
    }

    function applyProposedPrcFromLmp() {
        const tbody = document.getElementById('comparison-roi-tbody');
        if (!tbody || !Array.isArray(tbody.roiRows)) {
            return;
        }

        let applied = 0;
        tbody.roiRows.forEach(function (row, rowIndex) {
            if (row.isOverall) {
                return;
            }
            const lmp = parseSheetNumber(row.lmp);
            if (lmp == null) {
                return;
            }
            const oldSale = String(row.sale || '').trim();
            const sale = formatRoiNumber(lmp * ROI_LMP_SALE_FACTOR);
            row.sale = sale;
            const tr = tbody.children[rowIndex];
            const saleInput = tr?.querySelector('[data-field="sale"]');
            if (saleInput) {
                saleInput.value = sale;
            }
            refreshRoiRowCalculations(tr, tbody, rowIndex);
            saveRoiCellEdit(rowIndex, 'sale', oldSale, sale, sale);
            applied += 1;
        });

        const withOverall = appendOverallRoiRow(tbody.roiRows.filter(r => !r.isOverall), currentSheetCells);
        renderRoiModalTable(withOverall);

        if (!applied) {
            setRoiSaveStatus('No LMP available to apply Proposed PRC (LMP × 90%).', true);
            return;
        }
        setRoiSaveStatus(`Proposed PRC applied = LMP × 90% on ${applied} platform(s).`, false);
    }

    function writeRoiChannelToSheet(cells, channel, rowData) {
        const specCol = detectSpecColumnIndex(cells);
        let rowIndex = findCostCalculatorChannelRow(cells, channel, specCol);
        const colCount = Math.max(...cells.map(row => row.length), specCol + 10, 6);
        const sheetChannel = normalizeRoiSaveChannel(channel);

        if (rowIndex === null) {
            const newRow = Array.from({ length: colCount }, () => '');
            newRow[specCol] = sheetChannel;
            cells.push(newRow);
            rowIndex = cells.length - 1;
        }

        while (cells[rowIndex].length < colCount) {
            cells[rowIndex].push('');
        }

        // Spec+1 / Spec+2 are Critical / QC priority columns — never write CP/CBM there.
        // CP is sourced from the CD USD price column; CBM from the sheet CBM row.
        const skipSheetFields = new Set(['cp', 'cbm']);
        Object.entries(ROI_FIELD_OFFSETS).forEach(([key, offset]) => {
            if (skipSheetFields.has(key)) {
                return;
            }
            let value = rowData[key] ?? '';
            if ((key === 'pPct' || key === 'roi') && value) {
                value = String(value).replace('%', '');
            }
            cells[rowIndex][specCol + offset] = value;
        });

        return cells;
    }

    function saveRoiCellEdit(rowIndex, field, oldValue, newValue, displayNewValue) {
        if (!currentCdRow?.sku || roiSaveInFlight) {
            return Promise.resolve();
        }

        const tbody = document.getElementById('comparison-roi-tbody');
        const row = tbody?.roiRows?.[rowIndex];
        if (!row || !field) {
            return Promise.resolve();
        }

        // CP is always derived from CD PRICE USD — do not persist into the channel row
        // (Spec+1 is Critical priority). Overall is computed from platform rows.
        if (field === 'cp' || field === 'cbm' || row.isOverall) {
            return Promise.resolve();
        }

        const normalizedOld = String(oldValue ?? '').trim();
        const normalizedNew = String(displayNewValue ?? newValue ?? '').trim();
        if (normalizedOld === normalizedNew) {
            return Promise.resolve();
        }

        readCellsFromEditor();
        currentSheetCells = writeRoiChannelToSheet(currentSheetCells, row.channel, row);

        roiSaveInFlight = true;
        setRoiSaveStatus('Saving...', false);

        return fetch(roiSaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: currentCdRow.sku,
                parent: currentCdRow.parent || '',
                linked_skus: sheetSaveTargetSkus(currentCdRow),
                bulk_edit_skus: comparisonBulkEditPayload(),
                channel: normalizeRoiSaveChannel(row.channel),
                field,
                old_value: normalizedOld,
                new_value: normalizedNew,
                row,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Save failed.');
            }
            if (Array.isArray(data.cells)) {
                currentSheetCells = data.cells;
            }
            setRoiSaveStatus(`Saved by ${data.updated_by || 'N/A'} at ${data.updated_at || new Date().toLocaleTimeString()}`, false);
            clearTimeout(tableRefreshTimer);
            tableRefreshTimer = setTimeout(() => table?.replaceData(), 500);
        })
        .catch(err => {
            setRoiSaveStatus(err.message || 'Save failed.', true);
        })
        .finally(() => {
            roiSaveInFlight = false;
        });
    }

    async function fetchShippingSlabRate(weightLb, sku) {
        const weight = parseSheetNumber(weightLb);
        const skuKey = String(sku || '').trim();
        // Weight is optional when SKU is present — Product Master ship is preferred.
        if (weight == null && !skuKey) {
            return null;
        }

        const params = new URLSearchParams({
            carrier: 'ship',
        });
        if (weight != null) {
            params.set('weight_lb', String(weight));
        } else {
            // Backend requires a numeric weight_lb; 0 resolves to the 0 lb slab and is ignored when PM ship exists.
            params.set('weight_lb', '0');
        }
        if (skuKey) {
            params.set('sku', skuKey);
        }

        try {
            const res = await fetch(`${shippingSlabRateUrl}?${params.toString()}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            return data.success ? data : null;
        } catch (e) {
            return null;
        }
    }

    function refreshRoiRowCalculations(tr, tbody, rowIndex) {
        const row = tbody.roiRows[rowIndex];
        if (!row) {
            return;
        }

        applyRoiCalcToRow(row);

        if (tr) {
            const pPctCell = tr.querySelector('[data-calc="pPct"]');
            const profitCell = tr.querySelector('[data-calc="profit"]');
            const roiCell = tr.querySelector('[data-calc="roi"]');
            const siteGroiCell = tr.querySelector('[data-calc="siteGroi"]');
            const siteGpftCell = tr.querySelector('[data-calc="siteGpft"]');
            const siteNroiCell = tr.querySelector('[data-calc="siteNroi"]');
            const siteNpftCell = tr.querySelector('[data-calc="siteNpft"]');
            const npftCell = tr.querySelector('[data-calc="npft"]');
            const nroiCell = tr.querySelector('[data-calc="nroi"]');
            if (pPctCell) {
                applyRoiCalcCellTier(pPctCell, 'pPct', row.pPct);
            }
            if (profitCell) {
                profitCell.textContent = row.profit;
            }
            if (roiCell) {
                applyRoiCalcCellTier(roiCell, 'roi', row.roi);
            }
            if (siteGroiCell) {
                applyRoiCalcCellTier(siteGroiCell, 'siteGroi', row.siteGroi);
            }
            if (siteGpftCell) {
                applyRoiCalcCellTier(siteGpftCell, 'siteGpft', row.siteGpft);
            }
            if (siteNroiCell) {
                applyRoiCalcCellTier(siteNroiCell, 'siteNroi', row.siteNroi);
            }
            if (siteNpftCell) {
                applyRoiCalcCellTier(siteNpftCell, 'siteNpft', row.siteNpft);
            }
            if (npftCell) {
                applyRoiCalcCellTier(npftCell, 'npft', row.npft);
            }
            if (nroiCell) {
                applyRoiCalcCellTier(nroiCell, 'nroi', row.nroi);
            }
        }

        if (!row.isOverall) {
            syncOverallRoiRowInTable(tbody);
        }
    }

    function syncOverallRoiRowInTable(tbody) {
        if (!tbody?.roiRows) {
            return;
        }
        const platformRows = tbody.roiRows.filter(r => !r.isOverall);
        const overallIndex = tbody.roiRows.findIndex(r => r.isOverall);
        if (overallIndex < 0) {
            return;
        }
        const rebuilt = buildOverallRoiRow(platformRows, currentSheetCells, {
            specCol: detectSpecColumnIndex(currentSheetCells || []),
            cp: parseSheetNumber(platformRows[0]?.cp),
        });
        tbody.roiRows[overallIndex] = rebuilt;
        const tr = tbody.children[overallIndex];
        if (!tr) {
            return;
        }
        ['cp', 'cbm', 'gw', 'shipping', 'sale'].forEach(function (field) {
            const input = tr.querySelector(`[data-field="${field}"]`);
            if (input) {
                input.value = rebuilt[field] || '';
            }
        });
        const freightCell = tr.querySelector('[data-calc="freight"]');
        if (freightCell) {
            freightCell.textContent = rebuilt.freight || '';
        }
        const lmpCell = tr.querySelector('.comparison-roi-lmp-cell');
        if (lmpCell) {
            lmpCell.textContent = rebuilt.lmp || '—';
            lmpCell.classList.toggle('text-muted', !rebuilt.lmp);
        }
        const priceAfterCell = tr.querySelector('.comparison-roi-price-after-lmp-cell');
        if (priceAfterCell) {
            priceAfterCell.textContent = rebuilt.priceAfterLmp || '—';
            priceAfterCell.classList.toggle('text-muted', !rebuilt.priceAfterLmp);
        }
        refreshRoiRowCalculations(tr, tbody, overallIndex);
    }

    function parseRoiPercentValue(value) {
        const num = parseFloat(String(value || '').replace('%', '').trim());
        return Number.isFinite(num) ? num : null;
    }

    function metricKindForRoiCalcField(field) {
        if (field === 'roi' || field === 'siteGroi') return 'groi';
        if (field === 'pPct' || field === 'siteGpft') return 'gpft';
        if (field === 'nroi' || field === 'siteNroi') return 'nroi';
        if (field === 'npft' || field === 'siteNpft') return 'npft';
        return null;
    }

    /** Pricing Master / MetricPctColors bands for GROI · GPFT · NROI · NPFT. */
    function roiMetricColoredHtml(field, displayValue) {
        const text = String(displayValue || '').trim();
        if (!text) {
            return '';
        }
        const kind = metricKindForRoiCalcField(field);
        const pct = parseRoiPercentValue(text);
        if (kind && pct != null && window.MetricPctColors && typeof MetricPctColors.htmlFor === 'function') {
            return MetricPctColors.htmlFor(kind, pct, { decimals: 0, empty: '' }) || escapeHtml(text);
        }
        return escapeHtml(text);
    }

    function applyRoiCalcCellTier(cell, field, displayValue) {
        if (!cell) {
            return;
        }
        cell.classList.remove('comparison-roi-tier-red', 'comparison-roi-tier-green', 'comparison-roi-tier-magenta');
        if (metricKindForRoiCalcField(field)) {
            cell.innerHTML = roiMetricColoredHtml(field, displayValue);
            return;
        }
        cell.textContent = displayValue || '';
    }

    function roiCalcCellHtml(rowIndex, field, value) {
        const inner = metricKindForRoiCalcField(field)
            ? roiMetricColoredHtml(field, value)
            : escapeHtml(value || '');
        return `<td class="comparison-roi-calc-cell" data-row="${rowIndex}" data-calc="${field}">${inner}</td>`;
    }

    function getSheetCellText(cells, rowIndex, colIndex) {
        if (rowIndex === null || rowIndex === undefined || colIndex === null || colIndex === undefined) {
            return '';
        }
        return String((cells[rowIndex] || [])[colIndex] ?? '').trim();
    }

    function costCalculatorChannelNeedles(channel) {
        const key = String(channel || '').trim().toLowerCase();
        if (key === 'amz' || key === 'amazon') {
            return ['amz', 'amazon'];
        }
        if (key === 'ebay') {
            return ['ebay'];
        }
        if (key === 'temu') {
            return ['temu'];
        }
        if (key === 'shopify') {
            return ['shopify'];
        }
        if (key === 'overall') {
            return ['overall'];
        }
        return key ? [key] : [];
    }

    function normalizeRoiSaveChannel(channel) {
        const key = String(channel || '').trim().toLowerCase();
        if (key === 'ebay') {
            return 'Ebay';
        }
        if (key === 'temu') {
            return 'Temu';
        }
        if (key === 'shopify') {
            return 'Shopify';
        }
        if (key === 'overall') {
            return 'Overall';
        }
        return 'Amazon';
    }

    function roiChannelDisplayLabel(channel) {
        const key = channelLmpKey(channel);
        if (key === 'ebay') {
            return 'eBay';
        }
        if (key === 'temu') {
            return 'Temu';
        }
        if (key === 'shopify') {
            return 'Shopify';
        }
        if (key === 'overall') {
            return 'Overall';
        }
        return 'Amz';
    }

    function findCostCalculatorChannelRow(cells, channel, specCol) {
        const needles = costCalculatorChannelNeedles(channel);
        for (let rowIndex = 0; rowIndex < cells.length; rowIndex++) {
            const label = getSheetCellText(cells, rowIndex, specCol).toLowerCase();
            for (const needle of needles) {
                if (label === needle || label.startsWith(needle + ' ')) {
                    return rowIndex;
                }
            }
        }
        return null;
    }

    function readCostCalculatorRowFromSheet(cells, channel, specCol) {
        const rowIndex = findCostCalculatorChannelRow(cells, channel, specCol);
        if (rowIndex === null) {
            return {};
        }

        const data = {};
        Object.entries(ROI_FIELD_OFFSETS).forEach(([key, offset]) => {
            data[key] = getSheetCellText(cells, rowIndex, specCol + offset);
        });
        return data;
    }

    function extractLowestPriceColumnMetrics(cells) {
        const specCol = detectSpecColumnIndex(cells);
        // Cost-calculator CP must come from the CD PRICE USD row (lowest supplier col).
        // RMB is only a fallback when no USD price exists on the sheet.
        let priceLabel = 'usd';
        let lowestCol = findLowestSupplierColumn(cells, specCol, priceLabel);
        let priceRow = findRowIndexByLabel(cells, 'usd', specCol)
            ?? findRowIndexByLabel(cells, 'price usd', specCol)
            ?? findRowIndexByLabel(cells, 'supplier price', specCol);
        if (lowestCol === null || priceRow === null) {
            priceLabel = 'rmb';
            lowestCol = findLowestSupplierColumn(cells, specCol, priceLabel);
            priceRow = findRowIndexByLabel(cells, 'rmb', specCol)
                ?? findRowIndexByLabel(cells, 'supplier price', specCol);
        }
        if (lowestCol === null) {
            lowestCol = getFirstSupplierColumnIndex(cells, specCol);
        }

        // Sheet label is often "GW /Unit (LB)" (no space after /).
        const gwRow = findRowIndexByLabel(cells, 'gw /unit', specCol)
            ?? findRowIndexByLabel(cells, 'gw / unit', specCol)
            ?? findRowIndexByLabel(cells, 'gw/unit', specCol)
            ?? findRowIndexByLabel(cells, 'g.w', specCol)
            ?? findRowIndexByLabel(cells, 'gross weight', specCol)
            ?? findRowIndexByLabel(cells, 'gw', specCol)
            ?? findRowIndexByLabel(cells, 'weight', specCol);
        const cbmRow = findRowIndexByLabel(cells, 'cbm', specCol);

        const cp = parseSheetNumber(getSheetCellText(cells, priceRow, lowestCol));
        const gw = parseSheetNumber(getSheetCellText(cells, gwRow, lowestCol));
        const cbm = parseSheetNumber(getSheetCellText(cells, cbmRow, lowestCol));
        const freight = cbm != null ? 200 * cbm : null;

        return {
            specCol,
            col: lowestCol,
            colLetter: columnLetter(lowestCol),
            priceLabel,
            cp,
            gw,
            cbm,
            freight,
        };
    }

    /**
     * Proposed PRC metrics (same formulas as Pricing Master / site GROI · GPFT · NROI · NPFT):
     *   gross$  = ProposedPRC × margin − CP − Shipping
     *   PGROI%  = gross$ / CP × 100
     *   PGPFT%  = gross$ / ProposedPRC × 100
     *   PNPFT%  = PGPFT% − Ads%
     *   PNROI%  = (gross$ − ProposedPRC×Ads%/100) / CP × 100
     *             (Temu: PGROI% − Ads%, skip subtract when Ads%=100)
     * Margins: Amz 80%, Ebay 83%, Temu 100%, Shopify 95%.
     */
    function calculateRoiMetrics(row) {
        const cp = parseSheetNumber(row.cp) ?? 0;
        const shipping = parseSheetNumber(row.shipping) ?? 0;
        const sale = parseSheetNumber(row.sale) ?? 0;
        const key = channelLmpKey(row.channel);
        const margin = (row.margin != null && Number.isFinite(Number(row.margin)) && Number(row.margin) > 0)
            ? Number(row.margin)
            : channelRoiMargin(row.channel);
        const adsRaw = row.adsPct != null ? Number(row.adsPct) : 0;
        const adsPct = Number.isFinite(adsRaw) && adsRaw >= 0 ? adsRaw : 0;
        const hasInputs = sale > 0 || cp > 0 || shipping > 0;

        if (!hasInputs || !(sale > 0)) {
            return { profit: null, pPct: null, roi: null, npft: null, nroi: null };
        }

        const grossProfit = (sale * margin) - cp - shipping;
        const pPct = (grossProfit / sale) * 100;
        const roi = cp > 0 ? (grossProfit / cp) * 100 : null;
        const npft = pPct - adsPct;
        let nroi = null;
        if (cp > 0) {
            if (key === 'temu') {
                nroi = adsPct === 100 ? roi : (roi - adsPct);
            } else {
                nroi = ((grossProfit - (sale * (adsPct / 100))) / cp) * 100;
            }
        }

        return {
            profit: grossProfit,
            pPct,
            roi,
            npft,
            nroi,
        };
    }

    function applyRoiCalcToRow(row) {
        const calc = calculateRoiMetrics(row);
        row.profit = calc.profit != null ? formatRoiNumber(calc.profit) : '';
        row.pPct = calc.pPct != null ? `${formatRoiNumber(calc.pPct, 0)}%` : '';
        row.roi = calc.roi != null ? `${formatRoiNumber(calc.roi, 0)}%` : '';
        row.npft = calc.npft != null ? `${formatRoiNumber(calc.npft, 0)}%` : '';
        row.nroi = calc.nroi != null ? `${formatRoiNumber(calc.nroi, 0)}%` : '';
        applySiteRoiMetricsToRow(row);
        return row;
    }

    /**
     * Current listing price (C Price) metrics — same formulas as Pricing Master / site:
     *   gross$  = C Price × margin − CP − Shipping
     *   GROI%   = gross$ / CP × 100
     *   GPFT%   = gross$ / C Price × 100
     *   NPFT%   = GPFT% − Ads%
     *   NROI%   = (gross$ − C Price×Ads%/100) / CP × 100
     *             (Temu: GROI% − Ads%, skip subtract when Ads%=100)
     */
    function calculateSiteRoiMetrics(row) {
        const cp = parseSheetNumber(row.cp) ?? 0;
        const shipping = parseSheetNumber(row.shipping) ?? 0;
        const price = parseSheetNumber(row.priceAfterLmp) ?? 0;
        const key = channelLmpKey(row.channel);
        const margin = (row.margin != null && Number.isFinite(Number(row.margin)) && Number(row.margin) > 0)
            ? Number(row.margin)
            : channelRoiMargin(row.channel);
        const adsRaw = row.adsPct != null ? Number(row.adsPct) : 0;
        const adsPct = Number.isFinite(adsRaw) && adsRaw >= 0 ? adsRaw : 0;

        if (!(price > 0)) {
            return { siteGroi: null, siteGpft: null, siteNroi: null, siteNpft: null };
        }

        const grossProfit = (price * margin) - cp - shipping;
        const siteGpft = (grossProfit / price) * 100;
        const siteGroi = cp > 0 ? (grossProfit / cp) * 100 : null;
        const siteNpft = siteGpft - adsPct;
        let siteNroi = null;
        if (cp > 0 && siteGroi != null) {
            if (key === 'temu') {
                siteNroi = adsPct === 100 ? siteGroi : (siteGroi - adsPct);
            } else {
                siteNroi = ((grossProfit - (price * (adsPct / 100))) / cp) * 100;
            }
        }

        return { siteGroi, siteGpft, siteNroi, siteNpft };
    }

    function applySiteRoiMetricsToRow(row) {
        const calc = calculateSiteRoiMetrics(row);
        row.siteGroi = calc.siteGroi != null ? `${formatRoiNumber(calc.siteGroi, 0)}%` : '';
        row.siteGpft = calc.siteGpft != null ? `${formatRoiNumber(calc.siteGpft, 0)}%` : '';
        row.siteNroi = calc.siteNroi != null ? `${formatRoiNumber(calc.siteNroi, 0)}%` : '';
        row.siteNpft = calc.siteNpft != null ? `${formatRoiNumber(calc.siteNpft, 0)}%` : '';
        return row;
    }

    function buildRoiChannelRow(channel, cells, metrics, slabShipping, lmpRates) {
        const fromSheet = readCostCalculatorRowFromSheet(cells, channel, metrics.specCol);
        // CBM must use the EXACT value from the sheet's "CBM" spec row (metrics.cbm),
        // not the cost-calculator channel row's own CBM cell (fromSheet.cbm), which can
        // hold a stale/imported value that overrides the real per-unit CBM.
        const cbm = metrics.cbm != null
            ? formatRoiCbm(metrics.cbm)
            : (fromSheet.cbm ? formatRoiCbm(fromSheet.cbm) : '');
        const lmpSale = getChannelLmpSale(channel, lmpRates);
        const rawLmp = getChannelRawLmp(channel, lmpRates);
        const priceAfterLmp = getChannelPriceAfterLmp(channel, lmpRates);
        // GW must use the sheet's "GW / Unit (LB)" spec row (metrics.gw), same as CBM —
        // not a stale/empty cost-calculator cell that would blank the ROI GW LB field.
        const gw = metrics.gw != null
            ? formatRoiNumber(metrics.gw)
            : (fromSheet.gw ? formatRoiNumber(fromSheet.gw) : '');
        const row = {
            channel,
            // CP always from CD PRICE USD (lowest supplier column) — never from the
            // Amz/Ebay channel row, where Spec+1 is the Critical priority cell ("Normal").
            cp: metrics.cp != null ? formatRoiNumber(metrics.cp) : '',
            cbm,
            freight: computeFreightFromCbm(metrics.cbm != null ? metrics.cbm : (fromSheet.cbm || cbm)),
            gw,
            // Shipping is sourced from Product Master Values.ship for the SKU
            // (via shipping-slab-rate API; slab consensus is only a fallback).
            // Never from the comparison sheet, so stale/imported sheet values cannot override.
            shipping: (slabShipping != null && slabShipping !== '')
                ? formatRoiNumber(slabShipping)
                : '',
            // Sale prefers sheet/manual, then LMP×0.9. Channel listing price is shown separately.
            sale: fromSheet.sale || lmpSale || '',
            lmp: rawLmp,
            // Current listing price from the respective marketplace channel.
            priceAfterLmp,
            margin: channelRoiMargin(channel, lmpRates),
            adsPct: channelRoiAdsPct(channel, lmpRates),
            isOverall: false,
        };

        applyRoiCalcToRow(row);
        return row;
    }

    function buildOverallRoiRow(platformRows, cells, metrics, lmpRates) {
        const fromSheet = readCostCalculatorRowFromSheet(cells || [], ROI_OVERALL_CHANNEL, metrics?.specCol ?? 0);
        const template = (platformRows || [])[0] || {};
        const lmps = (platformRows || [])
            .map(r => parseSheetNumber(r.lmp))
            .filter(n => n != null);
        const sales = (platformRows || [])
            .map(r => parseSheetNumber(r.sale))
            .filter(n => n != null);
        const overallLmp = lmps.length ? Math.min(...lmps) : null;
        const avgSale = sales.length
            ? (sales.reduce((sum, n) => sum + n, 0) / sales.length)
            : null;
        const sale = avgSale != null
            ? formatRoiNumber(avgSale)
            : (fromSheet.sale || (overallLmp != null ? formatRoiNumber(overallLmp * ROI_LMP_SALE_FACTOR) : ''));
        const priceAfterVals = (platformRows || [])
            .map(r => parseSheetNumber(r.priceAfterLmp))
            .filter(n => n != null);
        const priceAfterLmp = priceAfterVals.length
            ? formatRoiNumber(priceAfterVals.reduce((sum, n) => sum + n, 0) / priceAfterVals.length)
            : '';

        const avgPct = (getter) => {
            const vals = (platformRows || []).map(getter).filter(n => n != null && Number.isFinite(n));
            if (!vals.length) return null;
            return vals.reduce((sum, n) => sum + n, 0) / vals.length;
        };
        const avgMargin = avgPct(r => (r.margin != null ? Number(r.margin) : null));
        const avgAds = avgPct(r => (r.adsPct != null ? Number(r.adsPct) : null));

        const row = {
            channel: ROI_OVERALL_CHANNEL,
            isOverall: true,
            cp: template.cp || (metrics?.cp != null ? formatRoiNumber(metrics.cp) : ''),
            cbm: template.cbm || '',
            freight: template.freight || '',
            gw: template.gw || '',
            shipping: template.shipping || '',
            sale,
            lmp: overallLmp != null ? formatRoiNumber(overallLmp) : '',
            priceAfterLmp,
            margin: avgMargin != null ? avgMargin : channelRoiMargin('amazon', lmpRates),
            adsPct: avgAds != null ? avgAds : 0,
        };

        applyRoiCalcToRow(row);
        return row;
    }

    function appendOverallRoiRow(platformRows, cells, lmpRates) {
        const rows = (platformRows || []).filter(r => !r.isOverall);
        const metrics = {
            specCol: detectSpecColumnIndex(cells || currentSheetCells || []),
            cp: parseSheetNumber(rows[0]?.cp),
        };
        return rows.concat([buildOverallRoiRow(rows, cells || currentSheetCells, metrics, lmpRates)]);
    }

    function buildAllRoiRows(cells, metrics, slabShipping, lmpRates) {
        const platformRows = ROI_CHANNELS.map(channel => buildRoiChannelRow(
            channel,
            cells,
            metrics,
            slabShipping,
            lmpRates
        ));
        return appendOverallRoiRow(platformRows, cells, lmpRates);
    }

    function roiLmpCellHtml(rowIndex, row) {
        const platform = channelLmpKey(row.channel || 'amazon');
        const label = roiChannelDisplayLabel(platform);
        const display = row.lmp || '';
        if (row.isOverall || platform === 'overall') {
            return display
                ? `<td class="comparison-roi-lmp-cell" title="Lowest LMP across Amz / Ebay / Temu / Shopify">${escapeHtml(display)}</td>`
                : `<td class="comparison-roi-lmp-cell text-muted" title="No platform LMP yet">—</td>`;
        }
        if (!display) {
            return `<td class="comparison-roi-lmp-cell">
                <button type="button" class="comparison-roi-lmp-add-btn"
                    data-row="${rowIndex}" data-platform="${escapeHtmlAttr(platform)}"
                    title="Add ${escapeHtml(label)} LMP">+</button>
            </td>`;
        }
        return `<td class="comparison-roi-lmp-cell">
            <button type="button" class="btn btn-link comparison-roi-lmp-link p-0 border-0"
                data-row="${rowIndex}" data-platform="${escapeHtmlAttr(platform)}"
                title="View / add ${escapeHtml(label)} LMP competitors">
                ${escapeHtml(display)}
            </button>
        </td>`;
    }

    function roiChannelMarketplaceKey(channel) {
        const key = channelLmpKey(channel);
        if (key === 'amazon') return 'amazon';
        if (key === 'ebay') return 'ebay1';
        if (key === 'temu') return 'temu';
        if (key === 'shopify') return 'shopify';
        return '';
    }

    function roiPriceAfterLmpCellHtml(row) {
        const display = row.priceAfterLmp || '';
        const platform = roiChannelDisplayLabel(row.channel);
        const marketplace = roiChannelMarketplaceKey(row.channel);
        const historyDot = (!row.isOverall && marketplace)
            ? ` <i class="fas fa-circle comparison-roi-price-history-dot"
                data-sku="${escapeHtmlAttr((currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim())}"
                data-marketplace="${escapeHtmlAttr(marketplace)}"
                data-metric="price"
                data-current-price="${escapeHtmlAttr(display)}"
                data-platform-label="${escapeHtmlAttr(platform)}"
                role="button" tabindex="0"
                title="View ${escapeHtmlAttr(platform)} Price history (Rolling L30)"></i>`
            : '';
        if (!display) {
            return `<td class="comparison-roi-price-after-lmp-cell text-muted" title="No ${escapeHtmlAttr(platform)} listing price found">—${historyDot}</td>`;
        }
        const title = row.isOverall
            ? 'Average listing price across channels'
            : `Current ${platform} listing price`;
        return `<td class="comparison-roi-price-after-lmp-cell" title="${escapeHtmlAttr(title)}">${escapeHtml(display)}${historyDot}</td>`;
    }

    function roiMetricPctCellHtml(row, metric) {
        const key = metric === 'nroi' ? 'nroi' : 'npft';
        const display = row[key] || '';
        const platform = roiChannelDisplayLabel(row.channel);
        const marketplace = roiChannelMarketplaceKey(row.channel);
        const label = key.toUpperCase();
        const cellClass = key === 'nroi' ? 'comparison-roi-nroi-cell' : 'comparison-roi-npft-cell';
        if (!display) {
            return `<td class="${cellClass} text-muted" title="No ${escapeHtmlAttr(platform)} ${label} available">—</td>`;
        }
        const title = row.isOverall
            ? `Average ${label} across channels`
            : `${platform} ${label} (Pricing Master / OV L30)`;
        const historyDot = (!row.isOverall && marketplace)
            ? ` <i class="fas fa-circle comparison-roi-metric-history-dot"
                data-sku="${escapeHtmlAttr((currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim())}"
                data-marketplace="${escapeHtmlAttr(marketplace)}"
                data-metric="${escapeHtmlAttr(key)}"
                data-current-value="${escapeHtmlAttr(String(display).replace('%', ''))}"
                data-platform-label="${escapeHtmlAttr(platform)}"
                title="View ${escapeHtmlAttr(platform)} ${label} history (Rolling L30)"></i>`
            : '';
        return `<td class="${cellClass}" title="${escapeHtmlAttr(title)}">${escapeHtml(display)}${historyDot}</td>`;
    }

    function roiAmzReviewsBadgeHtml() {
        const data = currentReviewsData && typeof currentReviewsData === 'object' ? currentReviewsData : {};
        const rawR = data.rating;
        const rawRev = data.reviews;
        const rVal = parseFloat(rawR);
        const hasRating = rawR !== null && rawR !== undefined && String(rawR).trim() !== '' && Number.isFinite(rVal);
        const revParsed = parseInt(String(rawRev == null ? '' : rawRev).replace(/,/g, ''), 10);
        const hasReviews = Number.isFinite(revParsed) && revParsed >= 0 && String(rawRev).trim() !== '';

        const parent = String(data.parent || currentCdRow?.parent || '').trim();
        const sku = String(data.sku || currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim();
        const amazonUrl = String(data.amazon_reviews_url || data.amazon_buyer_url || '').trim();

        let hotClass = '';
        let title = 'Amazon rating & reviews from Jungle Scout';
        let innerHtml = '<span class="cd-reviews-rating-line text-muted"><i class="bi bi-star"></i> Reviews</span>';

        if (hasRating || hasReviews) {
            let starColor = '#dc2626';
            if (hasRating) {
                if (rVal >= 4.5) {
                    starColor = '#9d174d';
                    hotClass = ' cd-reviews-hot';
                } else if (rVal >= 4) {
                    starColor = '#15803d';
                } else if (rVal >= 3.5) {
                    starColor = '#a16207';
                } else {
                    starColor = '#dc2626';
                }
            }
            const ratingLine = hasRating
                ? (Number.isInteger(rVal) ? String(rVal) : rVal.toFixed(1))
                : '—';
            const revLine = hasReviews
                ? `(${revParsed.toLocaleString('en-US')})`
                : (hasRating ? '(0)' : '');
            const revMuted = hasRating && rVal >= 4.5 ? '#861657' : '#5c5c5c';
            innerHtml =
                `<span class="cd-reviews-rating-line" style="color:${starColor};">` +
                `<i class="bi bi-star-fill" style="font-size:0.68rem;"></i>` +
                `<span>${ratingLine}</span></span>` +
                (revLine ? `<span class="cd-reviews-count-line" style="color:${revMuted};">${revLine}</span>` : '');
            title = `Amz Rating ${hasRating ? ratingLine : '—'} · Reviews ${hasReviews ? revParsed.toLocaleString('en-US') : '—'} (Jungle Scout)`;
        }

        const graphDisabled = !(sku || parent) ? ' is-disabled' : '';
        const intelDisabled = !parent ? ' is-disabled' : '';
        const amazonDisabled = !amazonUrl ? ' is-disabled' : '';
        const graphTitle = (sku || parent)
            ? `Lifetime rating graph${parent ? ' (parent)' : ' (SKU)'}`
            : 'No SKU/parent for graph';
        const intelTitle = parent
            ? `Open Review Intelligence for parent ${parent}`
            : 'No parent available for Review Intelligence';
        const amazonTitle = amazonUrl ? 'Open Amz buyer reviews' : 'No Amz buyer/ASIN link for this SKU';

        return `<span class="comparison-roi-amz-reviews-badge${hotClass}" title="${escapeHtmlAttr(title)}">` +
            `<span class="cd-reviews-badge-inner">${innerHtml}</span>` +
            `<span class="cd-reviews-action-dots">` +
            `<span class="cd-reviews-dot cd-reviews-dot-graph${graphDisabled}" data-reviews-action="graph" title="${escapeHtmlAttr(graphTitle)}" role="button" tabindex="0" aria-label="Lifetime rating graph"></span>` +
            `<span class="cd-reviews-dot cd-reviews-dot-intel${intelDisabled}" data-reviews-action="intel" title="${escapeHtmlAttr(intelTitle)}" role="button" tabindex="0" aria-label="Open Review Intelligence"></span>` +
            `<span class="cd-reviews-dot cd-reviews-dot-amazon${amazonDisabled}" data-reviews-action="amazon" title="${escapeHtmlAttr(amazonTitle)}" role="button" tabindex="0" aria-label="Open Amz reviews"></span>` +
            `</span></span>`;
    }

    function updateRoiAmzReviewsBadge() {
        const slot = document.getElementById('comparison-roi-amz-reviews-slot');
        if (!slot) {
            return;
        }
        slot.innerHTML = roiAmzReviewsBadgeHtml();
    }

    function renderRoiModalTable(rows) {
        const tbody = document.getElementById('comparison-roi-tbody');
        if (!tbody) {
            return;
        }

        tbody.innerHTML = rows.map((row, rowIndex) => {
            const inputCell = (field, value, readonly) => {
                if (readonly) {
                    return `<td class="comparison-roi-input-cell"><input type="text" class="comparison-roi-input comparison-roi-input-readonly" data-row="${rowIndex}" data-field="${field}" value="${escapeHtmlAttr(value || '')}" readonly tabindex="-1" title="Overall uses average Proposed PRC / lowest LMP across platforms."></td>`;
                }
                return `<td class="comparison-roi-input-cell"><input type="text" class="comparison-roi-input" data-row="${rowIndex}" data-field="${field}" value="${escapeHtmlAttr(value || '')}"></td>`;
            };
            const readonlyInputCell = (field, value) =>
                `<td class="comparison-roi-input-cell"><input type="text" class="comparison-roi-input comparison-roi-input-readonly" data-row="${rowIndex}" data-field="${field}" value="${escapeHtmlAttr(value || '')}" readonly tabindex="-1" title="Auto from Product Master (ship). Not editable."></td>`;
            const overall = !!row.isOverall;
            const rowClass = overall ? ' class="comparison-roi-overall-row"' : '';

            return `<tr${rowClass}>
                <td class="comparison-roi-channel">${escapeHtml(row.channel)}</td>
                ${inputCell('cp', row.cp, overall)}
                ${inputCell('cbm', row.cbm, overall)}
                ${roiCalcCellHtml(rowIndex, 'freight', row.freight)}
                ${inputCell('gw', row.gw, overall)}
                ${readonlyInputCell('shipping', row.shipping)}
                ${inputCell('sale', row.sale, overall)}
                ${roiLmpCellHtml(rowIndex, row)}
                ${roiCalcCellHtml(rowIndex, 'profit', row.profit)}
                ${roiCalcCellHtml(rowIndex, 'roi', row.roi)}
                ${roiCalcCellHtml(rowIndex, 'pPct', row.pPct)}
                ${roiCalcCellHtml(rowIndex, 'nroi', row.nroi)}
                ${roiCalcCellHtml(rowIndex, 'npft', row.npft)}
                ${roiPriceAfterLmpCellHtml(row)}
                ${roiCalcCellHtml(rowIndex, 'siteGroi', row.siteGroi)}
                ${roiCalcCellHtml(rowIndex, 'siteGpft', row.siteGpft)}
                ${roiCalcCellHtml(rowIndex, 'siteNroi', row.siteNroi)}
                ${roiCalcCellHtml(rowIndex, 'siteNpft', row.siteNpft)}
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.comparison-roi-input:not([readonly])').forEach(input => {
            input.addEventListener('input', handleRoiInputChange);
            input.addEventListener('focus', handleRoiInputFocus);
            input.addEventListener('blur', handleRoiInputBlur);
        });
        tbody.querySelectorAll('.comparison-roi-lmp-link, .comparison-roi-lmp-add-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const sku = (currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim();
                const platform = btn.dataset.platform || 'amazon';
                if (sku && platform !== 'overall') {
                    loadComparisonLmpModal(sku, platform, true);
                }
            });
        });
        tbody.querySelectorAll('.comparison-roi-price-history-dot, .comparison-roi-metric-history-dot').forEach(dot => {
            dot.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                openComparisonRoiPriceHistoryChart({
                    sku: dot.dataset.sku || (currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim(),
                    marketplace: dot.dataset.marketplace || '',
                    metric: dot.dataset.metric || 'price',
                    currentPrice: dot.dataset.currentPrice || '',
                    currentValue: dot.dataset.currentValue || '',
                    platformLabel: dot.dataset.platformLabel || '',
                });
            });
        });
        tbody.roiRows = rows;
        updateRoiAmzReviewsBadge();
    }

    let comparisonRoiPriceChart = null;
    let comparisonRoiPriceChartDays = 30;
    let comparisonRoiPriceChartContext = null;

    function comparisonRoiMetricLabel(metric) {
        if (metric === 'npft') return 'NPFT';
        if (metric === 'nroi') return 'NROI';
        return 'Price';
    }

    function openComparisonRoiPriceHistoryChart(ctx) {
        const sku = String(ctx?.sku || '').trim();
        const marketplace = String(ctx?.marketplace || '').trim();
        const metric = String(ctx?.metric || 'price').toLowerCase();
        if (!sku || !marketplace) {
            showComparisonToast('error', 'SKU / marketplace missing for history chart');
            return;
        }

        comparisonRoiPriceChartContext = {
            sku,
            marketplace,
            metric: ['npft', 'nroi'].includes(metric) ? metric : 'price',
            currentPrice: ctx?.currentPrice || '',
            currentValue: ctx?.currentValue || '',
            platformLabel: ctx?.platformLabel || marketplace,
        };
        comparisonRoiPriceChartDays = 30;
        const rangeEl = document.getElementById('comparison-roi-price-chart-range');
        if (rangeEl) {
            rangeEl.value = '30';
        }

        const metricLabel = comparisonRoiMetricLabel(comparisonRoiPriceChartContext.metric);
        const titleEl = document.getElementById('comparisonRoiPriceChartModalLabel');
        if (titleEl) {
            titleEl.innerHTML = `<i class="fas fa-chart-line me-1"></i> ${escapeHtml(comparisonRoiPriceChartContext.platformLabel)} ${escapeHtml(metricLabel)} — ${escapeHtml(sku)} · Rolling L30`;
        }
        const loadingEl = document.getElementById('comparison-roi-price-chart-loading');
        if (loadingEl) {
            loadingEl.textContent = '';
            loadingEl.innerHTML = `<div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading ${escapeHtml(metricLabel)} history…`;
        }
        const noDataEl = document.getElementById('comparison-roi-price-chart-nodata');
        if (noDataEl) {
            noDataEl.textContent = `No ${metricLabel} history for this channel / SKU yet.`;
        }

        const modalEl = document.getElementById('comparisonRoiPriceChartModal');
        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        modalEl.addEventListener('shown.bs.modal', function () {
            const openModals = document.querySelectorAll('.modal.show');
            const baseZ = 1050 + (openModals.length * 20);
            modalEl.style.zIndex = String(baseZ + 10);
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length) {
                backdrops[backdrops.length - 1].style.zIndex = String(baseZ);
            }
            if (comparisonRoiPriceChart && typeof comparisonRoiPriceChart.reflow === 'function') {
                try { comparisonRoiPriceChart.reflow(); } catch (e) {}
            }
        }, { once: true });
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        loadComparisonRoiPriceHistoryChart();
    }

    function loadComparisonRoiPriceHistoryChart() {
        const loading = document.getElementById('comparison-roi-price-chart-loading');
        const container = document.getElementById('comparison-roi-price-chart-container');
        const noData = document.getElementById('comparison-roi-price-chart-nodata');
        if (loading) loading.style.display = '';
        if (container) container.style.display = 'none';
        if (noData) noData.style.display = 'none';

        const ctx = comparisonRoiPriceChartContext;
        if (!ctx?.sku || !ctx?.marketplace) {
            if (loading) loading.style.display = 'none';
            if (noData) noData.style.display = '';
            return;
        }

        const params = new URLSearchParams({
            sku: ctx.sku,
            marketplace: ctx.marketplace,
            days: String(comparisonRoiPriceChartDays || 30),
            metric: ctx.metric || 'price',
        });
        if (ctx.currentPrice) {
            params.set('current_price', String(ctx.currentPrice).replace(/[$,]/g, ''));
        }
        if (ctx.currentValue !== '' && ctx.currentValue != null) {
            params.set('current_value', String(ctx.currentValue).replace('%', ''));
        }

        fetch(`${channelPriceChartDataUrl}?${params.toString()}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(response => {
                if (loading) loading.style.display = 'none';
                const points = response && response.success && Array.isArray(response.data) ? response.data : [];
                if (!points.length) {
                    if (noData) noData.style.display = '';
                    return;
                }
                if (container) container.style.display = '';
                renderComparisonRoiPriceHistoryChart(points);
            })
            .catch(() => {
                if (loading) loading.style.display = 'none';
                if (noData) noData.style.display = '';
            });
    }

    function renderComparisonRoiPriceHistoryChart(points) {
        const el = document.getElementById('comparison-roi-price-chart');
        if (!el || typeof Highcharts === 'undefined') {
            return;
        }
        const metric = comparisonRoiPriceChartContext?.metric || 'price';
        const metricLabel = comparisonRoiMetricLabel(metric);
        const isPct = metric === 'npft' || metric === 'nroi';
        const categories = points.map(p => p.date);
        const values = points.map(p => {
            const v = parseFloat(p.value);
            if (!Number.isFinite(v)) return null;
            return isPct ? Math.round(v * 100) / 100 : Math.round(v * 100) / 100;
        });

        if (comparisonRoiPriceChart) {
            try { comparisonRoiPriceChart.destroy(); } catch (e) {}
            comparisonRoiPriceChart = null;
        }

        const color = metric === 'npft' ? '#28a745' : (metric === 'nroi' ? '#17a2b8' : '#e83e8c');

        comparisonRoiPriceChart = Highcharts.chart(el, {
            chart: { type: 'line', height: 260, backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            xAxis: { categories, tickInterval: Math.max(1, Math.floor(categories.length / 8)) },
            yAxis: {
                title: { text: isPct ? `${metricLabel} (%)` : 'Price ($)' },
            },
            legend: { enabled: false },
            tooltip: {
                pointFormatter: function () {
                    if (isPct) {
                        return `<b>${Number(this.y).toFixed(1)}%</b>`;
                    }
                    return `<b>$${Number(this.y).toFixed(2)}</b>`;
                },
            },
            plotOptions: {
                line: {
                    marker: { enabled: true, radius: 3 },
                    color,
                    lineWidth: 2,
                },
            },
            series: [{ name: metricLabel, data: values }],
        });
    }

    function handleRoiCbmBlur(event) {
        const input = event.target;
        const formatted = formatRoiCbm(input.value);
        if (formatted === '') {
            return;
        }
        input.value = formatted;
        const tbody = document.getElementById('comparison-roi-tbody');
        const rowIndex = parseInt(input.dataset.row, 10);
        if (!tbody?.roiRows || Number.isNaN(rowIndex) || !tbody.roiRows[rowIndex]) {
            return;
        }
        tbody.roiRows[rowIndex].cbm = formatted;
    }

    function handleRoiInputFocus(event) {
        const input = event.target;
        if (!input.classList.contains('comparison-roi-input')) {
            return;
        }
        const key = `${input.dataset.row}-${input.dataset.field}`;
        roiCellEditPrevious[key] = input.value;
    }

    async function handleRoiInputBlur(event) {
        const input = event.target;
        if (!input.classList.contains('comparison-roi-input')) {
            return;
        }

        const rowIndex = parseInt(input.dataset.row, 10);
        const field = input.dataset.field;
        const tbody = document.getElementById('comparison-roi-tbody');
        if (Number.isNaN(rowIndex) || !field || !tbody?.roiRows?.[rowIndex]) {
            return;
        }

        if (field === 'cbm') {
            handleRoiCbmBlur(event);
        }

        const tr = input.closest('tr');
        if (field === 'gw') {
            await fetchShippingSlabRate(input.value, (currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim()).then(function (slabInfo) {
                const shipRate = slabInfo?.rate != null ? formatRoiNumber(slabInfo.rate) : '';
                tbody.roiRows[rowIndex].shipping = shipRate;
                const shippingInput = tr?.querySelector('[data-field="shipping"]');
                if (shippingInput) {
                    shippingInput.value = shipRate;
                }
            });
        }

        refreshRoiRowCalculations(tr, tbody, rowIndex);

        const key = `${rowIndex}-${field}`;
        const oldValue = roiCellEditPrevious[key] ?? '';
        const newValue = input.value;
        delete roiCellEditPrevious[key];

        await saveRoiCellEdit(rowIndex, field, oldValue, newValue, input.value);
    }

    function handleRoiInputChange(event) {
        const input = event.target;
        const tbody = document.getElementById('comparison-roi-tbody');
        if (!tbody || !tbody.roiRows) {
            return;
        }

        const rowIndex = parseInt(input.dataset.row, 10);
        const field = input.dataset.field;
        if (Number.isNaN(rowIndex) || !field || !tbody.roiRows[rowIndex]) {
            return;
        }

        tbody.roiRows[rowIndex][field] = input.value;

        const tr = input.closest('tr');
        if (field === 'cbm') {
            const freightVal = computeFreightFromCbm(input.value);
            tbody.roiRows[rowIndex].freight = freightVal;
            const freightCell = tr?.querySelector('[data-calc="freight"]');
            if (freightCell) {
                freightCell.textContent = freightVal;
            }
        }

        if (field === 'gw') {
            fetchShippingSlabRate(input.value, (currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim()).then(function (slabInfo) {
                const shipRate = slabInfo?.rate != null ? formatRoiNumber(slabInfo.rate) : '';
                tbody.roiRows[rowIndex].shipping = shipRate;
                const shippingInput = tr?.querySelector('[data-field="shipping"]');
                if (shippingInput) {
                    shippingInput.value = shipRate;
                }
                refreshRoiRowCalculations(tr, tbody, rowIndex);
            });
        }

        refreshRoiRowCalculations(tr, tbody, rowIndex);
    }

    function getRoiModalElement() {
        const el = document.getElementById('comparisonRoiModal');
        if (!el) {
            return null;
        }
        if (el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        return el;
    }

    function getRoiModalInstance() {
        const el = getRoiModalElement();
        if (!el || !window.bootstrap?.Modal) {
            return null;
        }
        return bootstrap.Modal.getOrCreateInstance(el, { backdrop: true, focus: true });
    }

    function fixRoiModalStacking() {
        const el = getRoiModalElement();
        if (!el) {
            return;
        }

        el.classList.add('comparison-roi-modal-stacked');
        const openModals = document.querySelectorAll('.modal.show');
        const baseZ = 1050 + (openModals.length * 20);
        el.style.zIndex = String(baseZ + 10);

        const backdrops = document.querySelectorAll('.modal-backdrop');
        if (backdrops.length) {
            backdrops[backdrops.length - 1].style.zIndex = String(baseZ);
        }
    }

    function updateRoiModalSkuHeader(rowOrSku) {
        const row = (rowOrSku && typeof rowOrSku === 'object')
            ? rowOrSku
            : (currentCdRow || { sku: rowOrSku || '' });
        const sku = String(row?.sku || rowOrSku || COMPARISON_CD_PAGE_SKU || '').trim();
        const el = document.getElementById('comparison-roi-modal-sku');
        if (el) {
            el.textContent = sku ? `— ${sku}` : '';
            el.title = sku ? `SKU: ${sku}` : '';
        }

        const wrap = document.getElementById('comparison-roi-modal-image-wrap');
        const img = document.getElementById('comparison-roi-modal-image');
        if (!wrap || !img) {
            return;
        }
        const url = getComparisonSkuImageUrl(row, false);
        if (!url) {
            img.removeAttribute('src');
            img.dataset.fullSrc = '';
            wrap.classList.add('d-none');
            return;
        }
        img.dataset.fullSrc = String(row?.image || '').trim();
        img.src = url;
        img.alt = (sku || 'SKU') + ' image';
        wrap.classList.remove('d-none');
    }

    async function openRoiModal() {
        const roiModal = getRoiModalInstance();
        if (!roiModal) {
            setSheetStatus('ROI modal unavailable. Refresh the page and try again.', true);
            return;
        }

        const sku = (currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim();
        updateRoiModalSkuHeader(currentCdRow || { sku });
        const emptyLmp = {
            amazon: null,
            ebay: null,
            temu: null,
            shopify: null,
            prices: { amazon: null, ebay: null, temu: null, shopify: null },
            ads: { amazon: null, ebay: null, temu: null, shopify: null },
            margin: { amazon: null, ebay: null, temu: null, shopify: null },
        };
        setRoiSaveStatus('', false);

        // Paint the modal on this click — do NOT scan the sheet first.
        // Product-photo cells hold huge data: URLs; reading them blocked the UI ~5s.
        const placeholderRows = ROI_CHANNELS.map(channel => ({
            channel,
            cp: '',
            cbm: '',
            freight: '',
            gw: '',
            shipping: '',
            sale: '',
            lmp: '',
            priceAfterLmp: '',
            npft: '',
            nroi: '',
            profit: '',
            pPct: '',
            roi: '',
            margin: channelRoiMargin(channel),
            adsPct: 0,
            isOverall: false,
        })).concat([{
            channel: ROI_OVERALL_CHANNEL,
            cp: '',
            cbm: '',
            freight: '',
            gw: '',
            shipping: '',
            sale: '',
            lmp: '',
            priceAfterLmp: '',
            npft: '',
            nroi: '',
            profit: '',
            pPct: '',
            roi: '',
            margin: ROI_CHANNEL_MARGINS.amazon,
            adsPct: 0,
            isOverall: true,
        }]);
        renderRoiModalTable(placeholderRows);

        const roiModalEl = getRoiModalElement();
        roiModalEl?.addEventListener('shown.bs.modal', fixRoiModalStacking, { once: true });
        roiModal.show();

        // Yield so the browser can paint the modal before the sheet DOM scan.
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

        // Prefer already-loaded in-memory cells (correct GW/CBM/CP from sheet load).
        // Only fall back to a lightweight DOM sync when memory is empty.
        if (!currentSheetCells.length) {
            readCellsFromEditor({ expandImages: false });
        } else {
            // Sync recent text edits without expanding huge product-photo data URLs.
            try {
                readCellsFromEditor({ expandImages: false });
            } catch (e) {
                // Keep prior in-memory cells if DOM sync fails.
            }
        }
        if (!currentSheetCells.length) {
            setSheetStatus('Load a comparison sheet first.', true);
            return;
        }

        const metrics = extractLowestPriceColumnMetrics(currentSheetCells);
        renderRoiModalTable(buildAllRoiRows(currentSheetCells, metrics, null, emptyLmp));

        // Prefer Product Master ship for SKU; GW is used only for slab fallback when PM ship is empty.
        // Ads%/margin from Pricing Master breakdown drive NPFT%/NROI% on Proposed PRC.
        const [slabInfo, lmpRates, feeMeta] = await Promise.all([
            fetchShippingSlabRate(metrics.gw, sku),
            fetchPlatformLmpRates(sku),
            fetchChannelFeeMeta(sku),
        ]);
        lmpRates.ads = feeMeta.ads || emptyLmp.ads;
        lmpRates.margin = feeMeta.margin || emptyLmp.margin;

        // Ignore stale responses if the user closed/reopened for another SKU.
        const stillSameSku = (currentCdRow?.sku || COMPARISON_CD_PAGE_SKU || '').trim() === sku;
        if (!stillSameSku || !document.getElementById('comparisonRoiModal')?.classList.contains('show')) {
            return;
        }

        renderRoiModalTable(buildAllRoiRows(currentSheetCells, metrics, slabInfo?.rate, lmpRates));
        setRoiSaveStatus('', false);
    }

    function replaceSpecsFromMemory() {
        readCellsFromEditor();
        const template = getCopiedSpecLabels();
        if (!template.length) {
            setSheetStatus('No copied specs in memory. Use Copy Specs on a template sheet first.', true);
            return;
        }

        const specCol = detectSpecColumnIndex(currentSheetCells);
        let colCount = Math.max(
            ...currentSheetCells.map(row => row.length),
            getFirstSupplierColumnIndex(currentSheetCells, specCol),
            6
        );

        while (currentSheetCells.length < template.length) {
            currentSheetCells.push(Array.from({ length: colCount }, () => ''));
        }

        template.forEach((label, rowIndex) => {
            if (!currentSheetCells[rowIndex]) {
                currentSheetCells[rowIndex] = Array.from({ length: colCount }, () => '');
            }
            while (currentSheetCells[rowIndex].length < colCount) {
                currentSheetCells[rowIndex].push('');
            }
            currentSheetCells[rowIndex][specCol] = label;
            colCount = Math.max(colCount, currentSheetCells[rowIndex].length);
        });

        renderSheetEditor(currentSheetCells);
        const appliedCount = template.length;
        setSheetStatus(`Replaced spec labels for ${appliedCount} row(s) from saved template.`, false);
        scheduleAutoSaveComparisonSheet(400);
    }

    // All Mfr Category names for the CD-modal's current row, from its `categories`
    // array (falling back to the live table row and the comma-joined `category` field).
    function comparisonCdRowCategoryNames() {
        function fromCategories(arr) {
            return (Array.isArray(arr) ? arr : [])
                .map(function (c) { return String((c && c.name != null) ? c.name : '').trim(); })
                .filter(Boolean);
        }
        function fromCategoryString(str) {
            return String(str || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        }

        let cats = fromCategories(currentCdRow && currentCdRow.categories);
        if (!cats.length && table && currentCdRow && currentCdRow.sku) {
            const liveRow = table.getRows().find(function (row) { return row.getData().sku === currentCdRow.sku; });
            if (liveRow) {
                const ld = liveRow.getData();
                cats = fromCategories(ld.categories);
                if (!cats.length) {
                    cats = fromCategoryString(ld.category);
                }
            }
        }
        if (!cats.length) {
            cats = fromCategoryString(currentCdRow && currentCdRow.category);
        }

        const seen = new Set();
        const out = [];
        cats.forEach(function (c) {
            const key = c.toLowerCase();
            if (!seen.has(key)) {
                seen.add(key);
                out.push(c);
            }
        });
        return out;
    }

    function autopopulateSupplierNamesFromList() {
        if (!currentCdRow) {
            setSheetStatus('Open a comparison row first.', true);
            return;
        }

        // Gather ALL categories on this row (a SKU can carry several Mfr Categories),
        // so suppliers from every category are fetched — not just the primary one.
        const categoryNames = comparisonCdRowCategoryNames();
        if (!categoryNames.length) {
            setSheetStatus('Set a category on this row before autopopulating suppliers.', true);
            return;
        }
        const category = categoryNames.join(', ');

        readCellsFromEditor();
        const specCol = detectSpecColumnIndex(currentSheetCells);
        const linkEnsured = ensureSupplierLinkRow(currentSheetCells, specCol);
        currentSheetCells = linkEnsured.cells;
        const supplierLinkRowIndex = linkEnsured.rowIndex;
        const nameEnsured = ensureSupplierNameRow(currentSheetCells, specCol);
        currentSheetCells = nameEnsured.cells;
        const supplierRowIndex = nameEnsured.rowIndex;
        const companyEnsured = ensureCompanyNameRow(currentSheetCells, specCol);
        currentSheetCells = companyEnsured.cells;
        const companyRowIndex = companyEnsured.rowIndex;

        const btn = document.getElementById('comparison-cd-autopopulate-suppliers-btn');
        if (btn) btn.disabled = true;
        setSheetStatus(`Loading suppliers for ${categoryNames.length > 1 ? categoryNames.length + ' categories' : 'category "' + category + '"'} from supplier.list...`, false);

        const params = new URLSearchParams();
        params.set('sku', currentCdRow.sku || '');
        params.set('category', category);
        params.set('by_category', '1');
        categoryNames.forEach(function (name) { params.append('categories[]', name); });

        fetch(`${suppliersForSkuUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Could not load suppliers.');
            }

            const suppliers = data.suppliers || [];
            if (!suppliers.length) {
                setSheetStatus(`No suppliers found on supplier.list for category "${category}".`, true);
                return;
            }

            const result = applySuppliersAddOnly(
                suppliers,
                supplierRowIndex,
                supplierLinkRowIndex,
                companyRowIndex
            );

            cacheComparisonSuppliers(suppliers);
            syncCommRowOnSheet();
            renderSheetEditor(currentSheetCells);
            const skipped = result.total - result.placed;
            let statusMsg = `Added ${result.added} supplier(s) in blank columns`;
            if (result.updated) {
                statusMsg += ` and updated ${result.updated} C-link match(es)`;
            }
            statusMsg += ` for "${category}".`;
            if (skipped > 0) {
                statusMsg += ` ${skipped} supplier(s) skipped (no blank columns left).`;
            }
            setSheetStatus(statusMsg, false);
            scheduleAutoSaveComparisonSheet(400);
        })
        .catch(err => {
            setSheetStatus(err.message || 'Could not autopopulate suppliers.', true);
        })
        .finally(() => {
            if (btn) btn.disabled = false;
        });
    }

    function getComparisonSkuImageUrl(row, bustCache) {
        const raw = String(row?.image || '').trim();
        if (!raw) return '';
        if (!bustCache) return raw;
        const sep = raw.includes('?') ? '&' : '?';
        return raw + sep + '_ts=' + Date.now();
    }

    function setComparisonHeaderSkuImage(row, options) {
        const wrap = document.getElementById('comparison-cd-modal-image-wrap');
        const img = document.getElementById('comparison-cd-modal-image');
        const refreshBtn = document.getElementById('comparison-cd-image-refresh-btn');
        const bustCache = !!(options && options.bustCache);
        const url = getComparisonSkuImageUrl(row, bustCache);
        hideComparisonImageHover();
        if (!wrap || !img) return;
        if (!url) {
            img.removeAttribute('src');
            img.dataset.fullSrc = '';
            wrap.classList.add('d-none');
            if (refreshBtn) refreshBtn.classList.add('d-none');
            return;
        }
        img.dataset.fullSrc = String(row?.image || '').trim();
        img.src = url;
        img.alt = (row?.sku || 'SKU') + ' image';
        wrap.classList.remove('d-none');
        if (refreshBtn) refreshBtn.classList.remove('d-none');
    }

    function hideComparisonImageHover() {
        const preview = document.getElementById('comparison-cd-image-hover-preview');
        if (preview) {
            preview.style.display = 'none';
            preview.innerHTML = '';
        }
    }

    function showComparisonImageHover(url, clientX, clientY) {
        const preview = document.getElementById('comparison-cd-image-hover-preview');
        if (!preview || !url) return;
        preview.innerHTML = `<img src="${escapeHtmlAttr(url)}" alt="SKU preview">`;
        preview.style.display = 'block';
        const pad = 16;
        const maxLeft = window.innerWidth - 380;
        const maxTop = window.innerHeight - 380;
        let left = (clientX || 0) + pad;
        let top = (clientY || 0) + pad;
        if (left > maxLeft) left = Math.max(8, (clientX || 0) - 380);
        if (top > maxTop) top = Math.max(8, (clientY || 0) - 380);
        preview.style.left = left + 'px';
        preview.style.top = top + 'px';
    }

    function openComparisonModal(row) {
        if (!cdModal) return;

        currentCdRow = row;
        cancelScheduledAutoSave();
        sheetEditorHydrating = false;
        lmpLoadedForSku = null;
        selectedSheetRow = null;
        selectedSheetCol = null;
        selectedSheetCell = null;
        currentSheetFormats = normalizeSheetFormats({});
        const skuBadge = document.getElementById('comparison-cd-modal-sku-badge');
        const skuHidden = document.getElementById('comparison-cd-modal-sku');
        let badgeText = row.sheet_sku && row.sheet_sku !== row.sku
            ? `${row.sku} (sheet: ${row.sheet_sku})`
            : (row.sku || '');
        if (Array.isArray(comparisonBulkEditSkus) && comparisonBulkEditSkus.length >= 1) {
            badgeText = comparisonBulkEditSkus.length === 1
                ? `Edit selected: ${comparisonBulkEditSkus[0]}`
                : `Bulk edit: ${comparisonBulkEditSkus.length} selected SKUs (${comparisonBulkEditSkus.slice(0, 3).join(', ')}${comparisonBulkEditSkus.length > 3 ? '…' : ''})`;
        }
        if (skuBadge) {
            skuBadge.textContent = badgeText;
            skuBadge.title = Array.isArray(comparisonBulkEditSkus) && comparisonBulkEditSkus.length >= 1
                ? `Changes save to selected only: ${comparisonBulkEditSkus.join(', ')}`
                : (row.sheet_sku && row.sheet_sku !== row.sku
                    ? `Shared comparison sheet from linked SKU ${row.sheet_sku}`
                    : '');
        }
        if (skuHidden) {
            skuHidden.textContent = row.sku || '';
        }
        setComparisonHeaderSkuImage(row);

        const sheetTabBtn = document.getElementById('cd-sheet-tab-btn');
        if (sheetTabBtn) {
            bootstrap.Tab.getOrCreateInstance(sheetTabBtn).show();
        }

        cdModal.show();
        hideCdHover();
        hideComparisonImageHover();
        loadComparisonSheet(row);
    }

    function openHistoryModal(row) {
        if (!historyModal) return;

        const sku = row.sku || '';
        const parent = row.parent || '';
        document.getElementById('comparison-history-modal-sku').textContent = sku;

        const loadingEl = document.getElementById('comparison-history-loading');
        const emptyEl = document.getElementById('comparison-history-empty');
        const errorEl = document.getElementById('comparison-history-error');
        const tableWrap = document.getElementById('comparison-history-table-wrap');
        const tbody = document.getElementById('comparison-history-tbody');

        loadingEl.classList.remove('d-none');
        emptyEl.classList.add('d-none');
        errorEl.classList.add('d-none');
        errorEl.textContent = '';
        tableWrap.classList.add('d-none');
        tbody.innerHTML = '';

        historyModal.show();

        const params = new URLSearchParams({ sku: sku });
        if (parent) {
            params.set('parent', parent);
        }

        fetch(`${historyUrl}?${params.toString()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(response => response.json())
        .then(data => {
            loadingEl.classList.add('d-none');

            if (!data.success) {
                errorEl.textContent = data.message || 'Failed to load history.';
                errorEl.classList.remove('d-none');
                return;
            }

            const rows = data.history || [];
            if (rows.length === 0) {
                emptyEl.classList.remove('d-none');
                return;
            }

            tbody.innerHTML = rows.map(function (item) {
                return `<tr>
                    <td class="ch-when">${escapeHtml(item.updated_at || '—')}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(item.updated_by || 'N/A')}</span></td>
                    <td>${escapeHtml(item.field_label || item.field || '—')}</td>
                    <td class="ch-change">${escapeHtml(item.changes || '—')}</td>
                </tr>`;
            }).join('');

            tableWrap.classList.remove('d-none');
        })
        .catch(() => {
            loadingEl.classList.add('d-none');
            errorEl.textContent = 'Could not load change history.';
            errorEl.classList.remove('d-none');
        });
    }

    function cdFormatter(cell) {
        const row = cell.getRow().getData();
        const hasSheet = !!row.has_sheet_data;
        const clinkIsSheet = !!row.clink_is_sheet || isGoogleSheetUrl(row.clink);
        const sharedFrom = row.sheet_sku && row.sheet_sku !== row.sku ? row.sheet_sku : '';
        const title = hasSheet
            ? (sharedFrom
                ? `View/edit shared comparison sheet (latest from ${sharedFrom})`
                : 'View/edit comparison sheet')
            : (clinkIsSheet ? 'Load comparison sheet from C link' : 'View comparison data');
        const color = hasSheet ? '#16a34a' : (clinkIsSheet ? '#d97706' : '#2563eb');

        return `<div class="comparison-cd-cell" role="button" tabindex="0" title="${escapeHtmlAttr(title)}" aria-label="${escapeHtmlAttr(title)}">
            <span class="comparison-cd-btn">
                <i class="mdi mdi-magnify" style="font-size:18px;color:${color};line-height:1;"></i>
            </span>
        </div>`;
    }

    function historyFormatter(cell) {
        const row = cell.getRow().getData();
        const count = parseInt(row.history_count, 10) || 0;

        if (count === 0) {
            return '<span class="text-muted">—</span>';
        }

        const date = row.latest_history_at || '—';
        const user = row.latest_history_by || 'N/A';
        const change = row.latest_change || 'View history';
        const tooltip = `${change}\nLast: ${date} by ${user}`;

        return `<button type="button" class="btn btn-sm btn-link p-0 comparison-history-btn position-relative"
            title="${escapeHtmlAttr(tooltip)}" aria-label="View history">
            <i class="fas fa-history text-secondary" style="font-size:1.1rem;"></i>
            <span class="badge bg-secondary" style="font-size:0.6rem;position:absolute;top:-6px;right:-10px;">${count}</span>
        </button>`;
    }

    function imageFormatter(cell) {
        const url = (cell.getValue() || '').trim();
        if (!url) {
            return '<span class="text-muted">No Image</span>';
        }

        return `<img src="${escapeHtmlAttr(url)}" alt="Product" class="comparison-table-sku-image"
            data-full-src="${escapeHtmlAttr(url)}"
            style="height:40px;max-width:60px;border-radius:4px;border:1px solid #ccc;object-fit:contain;cursor:zoom-in;">`;
    }

    function supplierListCategoryUrl(category, searchSku) {
        const params = new URLSearchParams();
        if (category) {
            params.set('category', category);
        }
        if (searchSku) {
            params.set('search', searchSku);
        }
        const query = params.toString();
        return query ? `${supplierListUrl}?${query}` : supplierListUrl;
    }

    function linkedSkuFormatter(cell) {
        const row = cell.getRow().getData();
        const category = (row.category || '').trim();
        const rowSku = String(row.sku || '').trim();
        let skus = row.linked_skus || [];
        if (typeof skus === 'string') {
            try {
                skus = JSON.parse(skus) || [];
            } catch (e) {
                skus = [];
            }
        }
        if (!Array.isArray(skus)) {
            skus = [];
        }
        if (!skus.length && rowSku) {
            skus = [rowSku];
        }

        const badges = skus.length
            ? skus.map(function (sku) {
                const skuText = String(sku || '').trim();
                const isSelf = skuText.toUpperCase() === rowSku.toUpperCase();
                const href = supplierListCategoryUrl(category, skuText);
                const removeBtn = isSelf
                    ? ''
                    : `<button type="button" class="btn-close comparison-linked-sku-remove"
                        data-linked-sku="${escapeHtmlAttr(skuText)}" aria-label="Remove link to ${escapeHtmlAttr(skuText)}"></button>`;
                return `<span class="linked-sku-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1">
                    <a href="${escapeHtmlAttr(href)}" target="_blank" rel="noopener noreferrer"
                        class="text-decoration-none text-dark linked-sku-badge"
                        title="Open ${escapeHtmlAttr(category || 'supplier.list')} for ${escapeHtmlAttr(skuText)}">${escapeHtml(skuText)}</a>${removeBtn}
                </span>`;
            }).join('')
            : '<span class="text-muted fst-italic">No SKUs</span>';

        return `<div class="d-flex flex-wrap align-items-start py-1" style="line-height:1.6;">${badges}</div>`;
    }

    function linkedSkuAddFormatter(cell) {
        const rowSku = String(cell.getRow().getData().sku || '').trim();
        if (!rowSku) {
            return '';
        }

        return `<div class="d-flex align-items-center justify-content-center py-1">
            <button type="button" class="btn btn-sm btn-outline-primary comparison-linked-sku-add-btn"
                title="Link another SKU" style="padding:2px 8px;" data-sku="${escapeHtmlAttr(rowSku)}">
                <i class="mdi mdi-plus"></i>
            </button>
        </div>`;
    }

    function applyAffectedLinkedSkuRows(affected) {
        if (!table || !Array.isArray(affected)) {
            return;
        }

        const bySku = {};
        affected.forEach(function (item) {
            if (item?.sku) {
                bySku[item.sku] = item;
            }
        });

        // Remember which rows are selected so we can restore the checkbox after updating,
        // since the update reformats cells (including the shared C link / CD indicators).
        const selectedSkuKeys = new Set(
            getSelectedComparisonRows().map(function (row) {
                return String(row.getData().sku || '').trim().toUpperCase();
            })
        );

        const shareableFields = [
            'clink', 'clink_sku', 'clink_is_sheet',
            'sheet_sku', 'has_sheet_data', 'sheet_supplier_count',
            'category', 'category_id', 'categories', 'suppliers',
        ];

        table.getRows().forEach(function (row) {
            const data = row.getData();
            if (!Object.prototype.hasOwnProperty.call(bySku, data.sku)) {
                return;
            }

            const item = bySku[data.sku];
            const fields = { linked_skus: item.linked_skus || [] };
            shareableFields.forEach(function (key) {
                if (Object.prototype.hasOwnProperty.call(item, key)) {
                    fields[key] = item[key];
                }
            });

            const wasSelected = selectedSkuKeys.has(String(data.sku || '').trim().toUpperCase());
            const done = function () {
                // Reformat so the CD column (whose formatter reads data-only fields) refreshes,
                // then re-assert selection so the row-selection checkbox stays visible/checked.
                if (typeof row.reformat === 'function') {
                    row.reformat();
                }
                if (wasSelected && typeof row.select === 'function') {
                    row.select();
                }
            };

            const res = row.update(fields);
            if (res && typeof res.then === 'function') {
                res.then(done);
            } else {
                done();
            }
        });
    }

    function bulkLinkSelectedSkus(rowData, addBtn) {
        // Merge the clicked row's SKU with the current selection. Tabulator can toggle
        // the clicked row's selection when the "+" is pressed, so relying only on
        // getSelectedRows() may drop the count below 2 and wrongly open the modal.
        const clickedSku = String(rowData?.sku || '').trim();
        const selectedSkus = [clickedSku].concat(
            getSelectedComparisonRows().map(function (row) { return String(row.getData().sku || '').trim(); })
        ).filter(Boolean);

        const seen = new Set();
        const uniqueSkus = [];
        selectedSkus.forEach(function (sku) {
            const key = sku.toUpperCase();
            if (!seen.has(key)) {
                seen.add(key);
                uniqueSkus.push(sku);
            }
        });

        if (uniqueSkus.length < 2) {
            openLinkedSkuModal(rowData);
            return;
        }

        const original = addBtn?.innerHTML || '';
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        }

        fetch(linkedSkuBulkLinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ skus: uniqueSkus }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not link selected SKUs.');
            }
            applyAffectedLinkedSkuRows(res.affected);
            showComparisonToast('success', uniqueSkus.length + ' SKUs linked. C link and comparison data are now shared.');
        })
        .catch(function (err) {
            alert(err.message || 'Could not link selected SKUs.');
        })
        .finally(function () {
            if (addBtn) {
                addBtn.disabled = false;
                addBtn.innerHTML = original;
            }
        });
    }

    function openLinkedSkuModal(rowData) {
        if (!linkedSkuModal || !rowData?.sku) {
            return;
        }

        linkedSkuModalRow = rowData;
        document.getElementById('comparison-linked-sku-source').textContent = rowData.sku;
        const input = document.getElementById('comparison-linked-sku-input');
        input.value = '';
        renderLinkedSkuSuggestions('');
        linkedSkuModal.show();
        setTimeout(function () { input?.focus(); }, 200);
    }

    function renderLinkedSkuSuggestions(term) {
        const wrap = document.getElementById('comparison-linked-sku-suggestions');
        if (!wrap || !table) {
            return;
        }

        const query = String(term || '').trim().toLowerCase();
        const currentSku = String(linkedSkuModalRow?.sku || '').trim().toUpperCase();
        const existing = new Set(
            (Array.isArray(linkedSkuModalRow?.linked_skus) ? linkedSkuModalRow.linked_skus : [])
                .map(function (sku) { return String(sku || '').trim().toUpperCase(); })
        );

        const matches = table.getData()
            .filter(function (row) {
                const sku = String(row.sku || '').trim();
                if (!sku) return false;
                const norm = sku.toUpperCase();
                if (norm === currentSku || existing.has(norm)) return false;
                if (!query) return true;
                return sku.toLowerCase().includes(query)
                    || String(row.parent || '').toLowerCase().includes(query);
            })
            .map(function (row) { return String(row.sku || '').trim(); })
            .slice(0, 8);

        if (!query || !matches.length) {
            wrap.classList.add('d-none');
            wrap.innerHTML = '';
            return;
        }

        wrap.classList.remove('d-none');
        wrap.innerHTML = matches.map(function (sku) {
            return `<button type="button" class="list-group-item list-group-item-action py-2 comparison-linked-sku-suggestion"
                data-sku="${escapeHtmlAttr(sku)}">${escapeHtml(sku)}</button>`;
        }).join('');
    }

    function saveLinkedSkuFromModal() {
        if (!linkedSkuModalRow?.sku) {
            return;
        }

        const linkedSku = document.getElementById('comparison-linked-sku-input')?.value.trim();
        if (!linkedSku) {
            alert('Enter a SKU to link.');
            return;
        }

        const btn = document.getElementById('comparison-linked-sku-save-btn');
        const original = btn?.innerHTML || '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Linking...';
        }

        fetch(linkedSkuAddUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: linkedSkuModalRow.sku,
                linked_sku: linkedSku,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not link SKU.');
            }
            applyAffectedLinkedSkuRows(res.affected);
            linkedSkuModal?.hide();
            showComparisonToast('success', 'Linked SKU updated for all related rows.');
        })
        .catch(function (err) {
            alert(err.message || 'Could not link SKU.');
        })
        .finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = original;
            }
        });
    }

    function removeLinkedSkuFromRow(rowData, linkedSku) {
        if (!rowData?.sku || !linkedSku) {
            return;
        }

        if (!confirm(`Remove link between "${rowData.sku}" and "${linkedSku}"?`)) {
            return;
        }

        fetch(linkedSkuRemoveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: rowData.sku,
                linked_sku: linkedSku,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (!res.success) {
                throw new Error(res.message || 'Could not remove linked SKU.');
            }
            applyAffectedLinkedSkuRows(res.affected);
            showComparisonToast('success', 'Linked SKU removed from related rows.');
        })
        .catch(function (err) {
            alert(err.message || 'Could not remove linked SKU.');
        });
    }

    function clinkFormatter(cell) {
        const row = cell.getRow().getData();
        const url = (cell.getValue() || row.clink || '').trim();
        if (!url) {
            return '<span class="text-muted">-</span>';
        }

        const sharedFrom = row.clink_sku && row.clink_sku !== row.sku ? row.clink_sku : '';
        const title = sharedFrom
            ? `Shared C link from linked SKU ${sharedFrom}`
            : 'Open comparison link';

        return `<div style="display:flex;align-items:center;justify-content:center;">
            <a href="${escapeHtmlAttr(url)}" target="_blank" rel="noopener noreferrer"
                class="comparison-clink-dot-link"
                title="${escapeHtmlAttr(title)}" aria-label="${escapeHtmlAttr(title)}">
                <span class="comparison-clink-dot" aria-hidden="true"></span>
            </a>
        </div>`;
    }

    function saveClinkUpdate(cell, value) {
        const rowData = cell.getRow().getData();
        const sku = rowData.sku;
        if (!sku) return;

        fetch(updateLinkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                sku: sku,
                column: 'Clink',
                value: value,
                linked_skus: linkedSkusForRow(rowData),
            }),
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) {
                alert('Error: ' + (res.message || 'Could not save C link.'));
                return;
            }
            applyAffectedClinkRows(res.affected || [{ sku: sku, clink: value }]);
            if (currentCdRow && currentCdRow.sku === sku) {
                currentCdRow.clink = value;
            }
            showComparisonToast('success', 'C link saved for all linked SKUs.');
        })
        .catch(() => alert('Could not save C link.'));
    }

    function applyAffectedClinkRows(affected) {
        if (!table || !Array.isArray(affected)) {
            return;
        }

        const bySku = {};
        affected.forEach(function (item) {
            if (item?.sku) {
                bySku[item.sku] = item.clink || '';
            }
        });

        table.getRows().forEach(function (row) {
            const data = row.getData();
            if (!Object.prototype.hasOwnProperty.call(bySku, data.sku)) {
                return;
            }
            const clink = bySku[data.sku];
            row.update({
                clink: clink,
                clink_is_sheet: isGoogleSheetUrl(clink),
                clink_sku: null,
            });
        });
    }

    function loadProductCategories() {
        return Promise.all([
            fetch(supplierCategoriesUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(res => res.json()).catch(() => ({ categories: [] })),
            fetch(groupMasterCategoriesUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            }).then(res => res.json()).catch(() => ({ success: false, categories: [] })),
        ]).then(function ([supplierRes, productRes]) {
            supplierCategoryOptions = Array.isArray(supplierRes.categories) ? supplierRes.categories : [];
            allProductCategories = productRes.success && Array.isArray(productRes.categories)
                ? productRes.categories
                : [];
            productCategoriesByName = {};
            allProductCategories.forEach(function (cat) {
                const key = String(cat.category_name || '').trim().toLowerCase();
                if (key) {
                    productCategoriesByName[key] = cat;
                }
            });
        });
    }

    function categoryFormatter(cell) {
        const row = cell.getRow().getData();
        const cats = Array.isArray(row.categories) ? row.categories : [];
        if (!cats.length) {
            return `<div class="comparison-category-cell text-muted" title="Click to add a category">—</div>`;
        }
        const badges = cats.map(function (c) {
            const name = String(c.name || '').trim();
            if (!name) {
                return '';
            }
            return `<span class="comparison-cat-badge-wrap badge bg-info-subtle text-dark border me-1 mb-1">
                <span class="comparison-cat-badge">${escapeHtml(name)}</span>
                <button type="button" class="btn-close comparison-category-remove"
                    data-category-id="${escapeHtmlAttr(String(c.id))}" aria-label="Remove ${escapeHtmlAttr(name)}"></button>
            </span>`;
        }).join('');
        return `<div class="comparison-category-cell d-flex flex-wrap align-items-start py-1" title="Click to add a category" style="line-height:1.6;">${badges}</div>`;
    }

    function suppliersColumnFormatter(cell) {
        const suppliers = Array.isArray(cell.getRow().getData().suppliers) ? cell.getRow().getData().suppliers : [];
        if (!suppliers.length) {
            return '<span class="text-muted fst-italic">No suppliers</span>';
        }
        const badges = suppliers.map(function (s) {
            const name = String(s.name || '').trim();
            if (!name) {
                return '';
            }
            const first = name.split(/\s+/).filter(Boolean)[0] || name;
            const link = String(s.link || '').trim();
            const title = s.company ? `${name} — ${s.company}` : name;
            if (link) {
                return `<a href="${escapeHtmlAttr(link)}" target="_blank" rel="noopener noreferrer"
                    class="badge bg-secondary-subtle text-dark border text-decoration-none me-1 mb-1 comparison-supplier-badge"
                    title="${escapeHtmlAttr(title)}">${escapeHtml(first)}</a>`;
            }
            return `<span class="badge bg-secondary-subtle text-dark border me-1 mb-1 comparison-supplier-badge"
                title="${escapeHtmlAttr(title)}">${escapeHtml(first)}</span>`;
        }).join('');
        return `<div class="d-flex flex-wrap align-items-start py-1" style="line-height:1.6;">${badges}</div>`;
    }

    function rowCategoryIds(rowData) {
        const cats = Array.isArray(rowData?.categories) ? rowData.categories : [];
        return cats
            .map(function (c) { return parseInt(c.id, 10); })
            .filter(function (n) { return Number.isInteger(n) && n > 0; });
    }

    function saveCategoryIdsForRow(row, categoryIds, cellEl) {
        const rowData = row.getData();
        const sku = rowData.sku;
        if (!sku) {
            return;
        }
        if (cellEl) {
            cellEl.style.opacity = '0.6';
        }
        fetch(comparisonCategorySaveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ sku: sku, category_ids: categoryIds }),
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            if (cellEl) {
                cellEl.style.opacity = '1';
            }
            if (!res.success) {
                alert('Error: ' + (res.message || 'Could not save category.'));
                return;
            }
            if (Array.isArray(res.affected)) {
                applyAffectedLinkedSkuRows(res.affected);
            }
        })
        .catch(function () {
            if (cellEl) {
                cellEl.style.opacity = '1';
            }
            alert('Could not save category.');
        });
    }

    function addCategoryToRow(row, categoryName) {
        if (row) {
            addCategoryToRows([row], categoryName);
        }
    }

    // Rows the category action should apply to: when the origin row is part of a
    // multi-row selection, apply to every selected row (bulk); otherwise just the
    // origin row. This makes "select 2 rows → add Mfr Category" affect both.
    function comparisonCategoryTargetRows(originRow) {
        if (!originRow) {
            return [];
        }
        // Merge the clicked row with the current selection, deduped by SKU — mirrors the
        // Sku Link bulk behaviour. Clicking the "+" can toggle the clicked row's own
        // selection off, so getSelectedRows()/isSelected() alone would drop it and the
        // action would wrongly affect only one row.
        const rows = [originRow].concat(getSelectedComparisonRows());
        const seen = new Set();
        const unique = [];
        rows.forEach(function (row) {
            if (!row || typeof row.getData !== 'function') {
                return;
            }
            const sku = String(row.getData().sku || '').trim().toUpperCase();
            if (!sku || seen.has(sku)) {
                return;
            }
            seen.add(sku);
            unique.push(row);
        });
        return unique;
    }

    function addCategoryToRows(rows, categoryName) {
        const name = String(categoryName || '').trim();
        if (!name || !Array.isArray(rows) || rows.length === 0) {
            return;
        }
        resolveProductCategoryId(name).then(function (categoryId) {
            const id = parseInt(categoryId, 10);
            if (!Number.isInteger(id) || id <= 0) {
                return;
            }
            rows.forEach(function (row) {
                if (!row) {
                    return;
                }
                const ids = rowCategoryIds(row.getData());
                if (ids.includes(id)) {
                    return;
                }
                ids.push(id);
                saveCategoryIdsForRow(row, ids, row.getCell('category')?.getElement());
            });
        });
    }

    function removeCategoryFromRow(row, categoryId) {
        const target = parseInt(categoryId, 10);
        if (!row || !Number.isInteger(target)) {
            return;
        }
        const ids = rowCategoryIds(row.getData()).filter(function (id) { return id !== target; });
        saveCategoryIdsForRow(row, ids, row.getCell('category')?.getElement());
    }

    function clearCategoriesForRow(row) {
        if (row) {
            saveCategoryIdsForRow(row, [], row.getCell('category')?.getElement());
        }
    }

    function categoryAddFormatter(cell) {
        if (!String(cell.getRow().getData().sku || '').trim()) {
            return '';
        }
        return `<div class="d-flex align-items-center justify-content-center py-1">
            <button type="button" class="btn btn-sm btn-outline-primary comparison-category-add-btn"
                title="Choose category from list" style="padding:2px 8px;">
                <i class="mdi mdi-plus"></i>
            </button>
        </div>`;
    }

    function openCategoryPickerModal(row) {
        if (!categoryPickerModal || !row) {
            return;
        }
        categoryPickerRow = row;
        const rowData = row.getData();
        const sourceEl = document.getElementById('comparison-category-picker-sku');
        if (sourceEl) {
            const targets = comparisonCategoryTargetRows(row);
            sourceEl.textContent = targets.length > 1
                ? (targets.length + ' selected rows')
                : String(rowData.sku || '').trim();
        }
        const search = document.getElementById('comparison-category-picker-search');
        if (search) {
            search.value = '';
        }
        renderCategoryPickerList('');
        categoryPickerModal.show();
        setTimeout(function () { search?.focus(); }, 200);
    }

    function renderCategoryPickerList(term) {
        const listEl = document.getElementById('comparison-category-picker-list');
        if (!listEl) {
            return;
        }

        const query = String(term || '').trim().toLowerCase();
        const addedNames = new Set(
            (Array.isArray(categoryPickerRow?.getData().categories) ? categoryPickerRow.getData().categories : [])
                .map(function (c) { return String(c.name || '').trim().toLowerCase(); })
                .filter(Boolean)
        );
        const matches = (query
            ? supplierCategoryOptions.filter(function (cat) {
                return String(cat.name || '').toLowerCase().includes(query);
            })
            : supplierCategoryOptions.slice());

        listEl.innerHTML = '';

        if (!supplierCategoryOptions.length) {
            listEl.innerHTML = '<div class="list-group-item text-muted">No categories loaded.</div>';
            return;
        }
        if (!matches.length) {
            listEl.innerHTML = '<div class="list-group-item text-muted">No matching categories.</div>';
            return;
        }

        matches.forEach(function (cat) {
            const name = String(cat.name || '').trim();
            if (!name) {
                return;
            }
            const isAdded = addedNames.has(name.toLowerCase());
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center comparison-category-picker-item'
                + (isAdded ? ' active' : '');
            btn.dataset.category = name;
            btn.innerHTML = `<span>${escapeHtml(name)}</span>`
                + (isAdded ? '<span class="badge bg-success">Added</span>' : '<span class="badge bg-light text-dark">Add</span>');
            listEl.appendChild(btn);
        });
    }

    function applyCategoryFromPicker(categoryName) {
        if (!categoryPickerRow) {
            return;
        }
        const name = String(categoryName || '').trim();
        if (name) {
            // Applies to all selected rows when the picker was opened on a multi-selection.
            addCategoryToRows(comparisonCategoryTargetRows(categoryPickerRow), name);
        }
        // Keep the modal open so multiple categories can be added; refresh the list
        // shortly after so the newly added one shows as "Added".
        const search = document.getElementById('comparison-category-picker-search');
        setTimeout(function () { renderCategoryPickerList(search?.value || ''); }, 400);
    }

    function closeCategoryDropdown() {
        if (activeCategoryDropdown) {
            activeCategoryDropdown.remove();
            activeCategoryDropdown = null;
        }
    }

    function positionCategoryDropdown(dropdown, cellEl) {
        const rect = cellEl.getBoundingClientRect();
        dropdown.style.left = `${Math.max(8, rect.left)}px`;
        dropdown.style.top = `${rect.bottom + 4}px`;
        const dropdownRect = dropdown.getBoundingClientRect();
        if (dropdownRect.right > window.innerWidth - 8) {
            dropdown.style.left = `${Math.max(8, window.innerWidth - dropdownRect.width - 8)}px`;
        }
        if (dropdownRect.bottom > window.innerHeight - 8) {
            dropdown.style.top = `${Math.max(8, rect.top - dropdownRect.height - 4)}px`;
        }
    }

    function renderCategoryDropdownResults(resultsEl, searchTerm, onSelect) {
        const term = String(searchTerm || '').trim().toLowerCase();
        const filtered = term
            ? supplierCategoryOptions.filter(cat => String(cat.name || '').toLowerCase().includes(term))
            : supplierCategoryOptions.slice();

        resultsEl.innerHTML = '';

        const clearItem = document.createElement('div');
        clearItem.className = 'dropdown-search-item clear-option';
        clearItem.textContent = '— No Category —';
        clearItem.addEventListener('mousedown', function (e) {
            e.preventDefault();
            onSelect('');
        });
        resultsEl.appendChild(clearItem);

        if (!filtered.length) {
            const empty = document.createElement('div');
            empty.className = 'dropdown-search-item no-results';
            empty.textContent = term ? 'No matching categories' : 'No categories loaded';
            resultsEl.appendChild(empty);
            return;
        }

        filtered.forEach(cat => {
            const item = document.createElement('div');
            item.className = 'dropdown-search-item';
            item.textContent = cat.name || '';
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                onSelect(cat.name || '');
            });
            resultsEl.appendChild(item);
        });
    }

    function refreshComparisonRowFromServer(sku, extraSkus) {
        if (!sku || !table) return;

        const pending = [sku, ...(Array.isArray(extraSkus) ? extraSkus : [])].filter(Boolean);
        const params = new URLSearchParams({ skus: pending.join(',') });

        fetch(`${dataUrl}?${params.toString()}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(res => res.json())
        .then(res => {
            if (!res.success) return;
            const rows = res.data || [];
            const pending = [sku, ...(Array.isArray(extraSkus) ? extraSkus : [])].filter(Boolean);
            const seen = new Set();

            while (pending.length) {
                const targetSku = pending.pop();
                if (seen.has(targetSku)) {
                    continue;
                }
                seen.add(targetSku);

                const updated = rows.find(row => row.sku === targetSku);
                if (!updated) {
                    continue;
                }

                const tabulatorRow = table.getRows().find(row => row.getData().sku === targetSku);
                if (tabulatorRow) {
                    tabulatorRow.update({
                        category_id: updated.category_id,
                        category: updated.category,
                        categories: updated.categories,
                        suppliers: updated.suppliers,
                        linked_skus: updated.linked_skus,
                        has_sheet_data: updated.has_sheet_data,
                        sheet_sku: updated.sheet_sku,
                        clink: updated.clink,
                        clink_is_sheet: updated.clink_is_sheet,
                        clink_sku: updated.clink_sku,
                    });
                }

                if (Array.isArray(updated.linked_skus)) {
                    updated.linked_skus.forEach(function (relatedSku) {
                        if (relatedSku && !seen.has(relatedSku)) {
                            pending.push(relatedSku);
                        }
                    });
                }
            }
        })
        .catch(() => {});
    }

    function resolveProductCategoryId(categoryName) {
        const key = String(categoryName || '').trim().toLowerCase();
        if (!key) {
            return Promise.resolve(null);
        }

        const existing = productCategoriesByName[key];
        if (existing) {
            return Promise.resolve(parseInt(existing.id, 10));
        }

        return fetch(groupMasterStoreCategoryUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                category_name: String(categoryName).trim(),
                status: 'active',
            }),
        })
        .then(res => res.json())
        .then(function (res) {
            if (res.success && res.category) {
                productCategoriesByName[key] = res.category;
                allProductCategories.push(res.category);
                return parseInt(res.category.id, 10);
            }

            return loadProductCategories().then(function () {
                const refreshed = productCategoriesByName[key];
                return refreshed ? parseInt(refreshed.id, 10) : null;
            });
        })
        .catch(function () {
            return loadProductCategories().then(function () {
                const refreshed = productCategoriesByName[key];
                return refreshed ? parseInt(refreshed.id, 10) : null;
            });
        });
    }

    function saveProductCategory(cell, categoryName) {
        const rowData = cell.getRow().getData();
        const productId = rowData.id;
        const sku = rowData.sku;
        if (!productId || !sku) return;

        const normalizedName = String(categoryName || '').trim();
        const currentName = String(rowData.category || '').trim();
        if (normalizedName === currentName) {
            return;
        }

        const cellEl = cell.getElement();
        cellEl.style.opacity = '0.6';

        let resolvedCategoryId = null;
        resolveProductCategoryId(normalizedName).then(function (productCategoryId) {
            resolvedCategoryId = productCategoryId;
            // Save via the comparison endpoint so the category is propagated to all
            // linked SKUs (same group-sharing behaviour as the C link / CD columns).
            return fetch(comparisonCategorySaveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    sku: sku,
                    category_id: productCategoryId,
                }),
            });
        })
        .then(res => res.json())
        .then(function (res) {
            cellEl.style.opacity = '1';
            if (!res.success) {
                alert('Error: ' + (res.message || 'Could not save category.'));
                return;
            }

            if (Array.isArray(res.affected) && res.affected.length) {
                applyAffectedLinkedSkuRows(res.affected);
            } else {
                cell.getRow().update({
                    category_id: resolvedCategoryId ?? null,
                    category: normalizedName,
                });
            }
        })
        .catch(function () {
            cellEl.style.opacity = '1';
            alert('Could not save category.');
        });
    }

    function openCategoryDropdown(cell) {
        closeCategoryDropdown();

        const cellEl = cell.getElement();
        const rowData = cell.getRow().getData();
        const currentCategoryName = String(rowData.category || '').trim();

        const dropdown = document.createElement('div');
        dropdown.className = 'comparison-category-dropdown comparison-category-dropdown-panel';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'dropdown-search-input';
        input.placeholder = 'Search categories...';
        input.autocomplete = 'off';

        const results = document.createElement('div');
        results.className = 'dropdown-search-results';

        dropdown.appendChild(input);
        dropdown.appendChild(results);
        document.body.appendChild(dropdown);
        activeCategoryDropdown = dropdown;
        positionCategoryDropdown(dropdown, cellEl);

        const handleSelect = function (categoryName) {
            closeCategoryDropdown();
            const nextName = String(categoryName || '').trim();
            if (nextName === '') {
                // "— No Category —" clears all categories for the group.
                clearCategoriesForRow(cell.getRow());
                return;
            }
            addCategoryToRows(comparisonCategoryTargetRows(cell.getRow()), nextName);
        };

        renderCategoryDropdownResults(results, '', handleSelect);
        input.focus();

        input.addEventListener('input', function () {
            renderCategoryDropdownResults(results, input.value, handleSelect);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closeCategoryDropdown();
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (!activeCategoryDropdown) return;
        if (e.target.closest('.comparison-category-dropdown-panel')) return;
        if (e.target.closest('.comparison-category-cell')) return;
        closeCategoryDropdown();
    });

    function enterCdPageMode() {
        document.body.classList.add('cd-page-mode');
        document.getElementById('comparison-main-card')?.classList.add('d-none');
        const backBtn = document.getElementById('comparison-cd-back-btn');
        if (backBtn) {
            backBtn.classList.remove('d-none');
            backBtn.setAttribute('href', comparisonIndexUrl);
        }
    }

    loadProductCategories().then(function () {
        const comparisonPageParams = new URLSearchParams(window.location.search);
        // Dedicated full-page CD editor: driven by the /sheet-view route ($cdPageSku)
        // or the legacy ?cd_only= URL param.
        const comparisonCdOnlySku = (
            (COMPARISON_CD_PAGE_SKU || '')
            || comparisonPageParams.get('cd_only')
            || (comparisonPageParams.has('cd_only') ? comparisonPageParams.get('sku') : '')
            || ''
        ).trim();

        if (comparisonCdOnlySku) {
            enterCdPageMode();

            const params = new URLSearchParams({ skus: comparisonCdOnlySku });
            fetch(`${dataUrl}?${params.toString()}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                const row = (res.success && Array.isArray(res.data) && res.data[0])
                    ? res.data[0]
                    : {
                        sku: comparisonCdOnlySku,
                        parent: comparisonPageParams.get('parent') || '',
                        clink: '',
                        has_sheet_data: false,
                    };
                comparisonBulkEditSkus = null;
                openComparisonModal(row);
            })
            .catch(function () {
                comparisonBulkEditSkus = null;
                openComparisonModal({
                    sku: comparisonCdOnlySku,
                    parent: comparisonPageParams.get('parent') || '',
                    clink: '',
                    has_sheet_data: false,
                });
            });

            return;
        }

        initComparisonTable();
    });

    function initComparisonTable() {
    table = new Tabulator('#comparison-table', {
        ajaxURL: dataUrl,
        ajaxConfig: 'GET',
        ajaxURLGenerator: function (url, config, params) {
            const query = new URLSearchParams({
                page: String(params.page || 1),
                size: String(params.size || 50),
            });
            const skuTerm = (document.getElementById('comparison-search-sku')?.value || '').trim();
            const parentTerm = (document.getElementById('comparison-search-parent')?.value || '').trim();
            if (comparisonPlaybackActive && comparisonPlaybackParent) {
                query.set('parent_exact', comparisonPlaybackParent);
            } else {
                if (skuTerm) {
                    query.set('sku', skuTerm);
                }
                if (parentTerm) {
                    query.set('parent', parentTerm);
                }
            }
            // Remote sort: send the active sort so the server sorts ALL rows before
            // paginating (otherwise only the current page would be reordered).
            if (Array.isArray(params.sort) && params.sort.length > 0) {
                query.set('sort_field', String(params.sort[0].field || ''));
                query.set('sort_dir', String(params.sort[0].dir || 'asc'));
            }
            return `${url}?${query.toString()}`;
        },
        ajaxResponse: function (url, params, response) {
            if (!response.success) {
                throw new Error(response.message || 'Failed to load comparison data.');
            }
            return {
                data: response.data || [],
                last_page: response.last_page || 1,
            };
        },
        pagination: true,
        paginationMode: 'remote',
        filterMode: 'remote',
        sortMode: 'remote',
        paginationSize: 50,
        paginationSizeSelector: [25, 50, 100, 200],
        paginationInitialPage: 1,
        layout: 'fitColumns',
        movableColumns: true,
        resizableColumns: true,
        height: 'calc(100vh - 200px)',
        placeholder: 'No comparison data found',
        selectableRows: true,
        selectableRowsPersistence: false,
        columns: [
            {
                formatter: 'rowSelection',
                titleFormatter: 'rowSelection',
                hozAlign: 'center',
                headerHozAlign: 'center',
                headerSort: false,
                width: 44,
            },
            {
                title: 'Image',
                field: 'image',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 55,
                headerSort: false,
                formatter: imageFormatter,
            },
            {
                title: 'Parent',
                field: 'parent',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 110,
            },
            {
                title: 'SKU',
                field: 'sku',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 130,
            },
            {
                title: 'Link Sku Purchase',
                field: 'linked_skus',
                hozAlign: 'left',
                headerHozAlign: 'center',
                width: 280,
                headerSort: false,
                cssClass: 'linked-sku-col',
                formatter: linkedSkuFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.comparison-linked-sku-remove')) {
                        e.preventDefault();
                        e.stopPropagation();
                        removeLinkedSkuFromRow(
                            cell.getRow().getData(),
                            e.target.closest('.comparison-linked-sku-remove').dataset.linkedSku || ''
                        );
                    }
                },
            },
            {
                title: '+',
                field: 'linked_sku_add',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 52,
                headerSort: false,
                cssClass: 'linked-sku-add-col',
                formatter: linkedSkuAddFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.comparison-linked-sku-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        bulkLinkSelectedSkus(
                            cell.getRow().getData(),
                            e.target.closest('.comparison-linked-sku-add-btn')
                        );
                    }
                },
            },
            {
                title: 'Mfr Category',
                field: 'category',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 130,
                headerSort: true,
                cssClass: 'comparison-category-col',
                formatter: categoryFormatter,
                cellClick: function (e, cell) {
                    e.stopPropagation();
                    const removeBtn = e.target.closest('.comparison-category-remove');
                    if (removeBtn) {
                        e.preventDefault();
                        removeCategoryFromRow(cell.getRow(), removeBtn.dataset.categoryId || '');
                        return;
                    }
                    openCategoryDropdown(cell);
                },
            },
            {
                title: '+',
                field: 'category_add',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 52,
                headerSort: false,
                headerTooltip: 'Pick a category from the full list',
                cssClass: 'category-add-col',
                formatter: categoryAddFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.comparison-category-add-btn')) {
                        e.preventDefault();
                        e.stopPropagation();
                        openCategoryPickerModal(cell.getRow());
                    }
                },
            },
            {
                title: 'Suppliers',
                field: 'suppliers',
                hozAlign: 'left',
                headerHozAlign: 'center',
                width: 120,
                minWidth: 90,
                maxWidth: 160,
                widthGrow: 0,
                headerSort: false,
                headerTooltip: 'Suppliers for the Mfr Category (from supplier.list) — first name shown',
                cssClass: 'comparison-suppliers-col',
                formatter: suppliersColumnFormatter,
            },
            {
                title: 'C link',
                field: 'clink',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 60,
                headerSort: false,
                headerTooltip: 'Comparison link',
                formatter: clinkFormatter,
                editor: 'input',
                cellEdited: function (cell) {
                    saveClinkUpdate(cell, cell.getValue());
                },
            },
            {
                title: 'LMP',
                field: 'lmp_price',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 75,
                headerSort: false,
                headerTooltip: 'Lowest market price and competition product links',
                formatter: lmpFormatter,
                cellClick: function (e) {
                    const viewLink = e.target.closest('.comparison-view-lmp-competitors');
                    if (viewLink) {
                        e.preventDefault();
                        const sku = viewLink.dataset.sku || '';
                        if (sku) {
                            loadComparisonLmpModal(sku);
                        }
                    }
                },
            },
            {
                title: 'CD',
                field: 'cd_view',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 70,
                headerSort: false,
                headerTooltip: 'Comparison Data',
                formatter: cdFormatter,
                cellMouseEnter: function (e, cell) {
                    showCdHover(e, cell.getRow().getData());
                },
                cellMouseMove: function (e) {
                    positionCdHover(e);
                },
                cellMouseLeave: function () {
                    hideCdHover();
                },
                cellClick: function (e, cell) {
                    e.preventDefault();
                    e.stopPropagation();
                    // Open the comparison sheet on its own full page (same tab) instead of a modal.
                    const sku = String(cell.getRow().getData().sku || '').trim();
                    if (sku) {
                        window.location.href = comparisonSheetPageUrl + '?sku=' + encodeURIComponent(sku);
                    }
                },
            },
            {
                title: 'History',
                field: 'history_view',
                hozAlign: 'center',
                headerHozAlign: 'center',
                width: 80,
                headerSort: false,
                headerTooltip: 'Change history',
                formatter: historyFormatter,
                cellClick: function (e, cell) {
                    if (e.target.closest('.comparison-history-btn')) {
                        openHistoryModal(cell.getRow().getData());
                    }
                },
            },
        ],
    });

    table.on('pageLoaded', function () {
        table.deselectRow();
    });

    function updateComparisonSelectedBadge() {
        const badge = document.getElementById('comparison-selected-badge');
        const countEl = document.getElementById('comparison-selected-count');
        if (!badge || !countEl) {
            return;
        }
        const count = table ? table.getSelectedRows().length : 0;
        countEl.textContent = count;
        badge.classList.toggle('d-none', count === 0);
    }

    table.on('rowSelectionChanged', updateComparisonSelectedBadge);

    document.getElementById('comparison-category-picker-search')?.addEventListener('input', function () {
        renderCategoryPickerList(this.value);
    });
    document.getElementById('comparison-category-picker-list')?.addEventListener('click', function (e) {
        const item = e.target.closest('.comparison-category-picker-item');
        if (!item) {
            return;
        }
        applyCategoryFromPicker(item.dataset.category || '');
    });

    document.getElementById('comparison-linked-sku-save-btn')?.addEventListener('click', saveLinkedSkuFromModal);
    document.getElementById('comparison-linked-sku-input')?.addEventListener('input', function () {
        renderLinkedSkuSuggestions(this.value);
    });
    document.getElementById('comparison-linked-sku-input')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            saveLinkedSkuFromModal();
        }
    });
    document.getElementById('comparison-linked-sku-suggestions')?.addEventListener('click', function (e) {
        const item = e.target.closest('.comparison-linked-sku-suggestion');
        if (!item) {
            return;
        }
        const input = document.getElementById('comparison-linked-sku-input');
        if (input) {
            input.value = item.dataset.sku || '';
        }
        renderLinkedSkuSuggestions('');
    });

    cdModalEl?.addEventListener('hidden.bs.modal', function () {
        comparisonBulkEditSkus = null;
    });

    function applyComparisonTableSearch() {
        if (!table) {
            return;
        }
        clearComparisonRowSelection();
        table.setPage(1);
    }

    let comparisonSearchTimer = null;
    function scheduleComparisonTableSearch() {
        clearTimeout(comparisonSearchTimer);
        comparisonSearchTimer = setTimeout(applyComparisonTableSearch, 300);
    }

    document.getElementById('comparison-search-sku')?.addEventListener('input', scheduleComparisonTableSearch);
    document.getElementById('comparison-search-parent')?.addEventListener('input', scheduleComparisonTableSearch);

    initComparisonPlaybackControls();
    }

    function initComparisonPlaybackControls() {
        document.getElementById('comparison-play-auto')?.addEventListener('click', comparisonPlaybackStart);
        document.getElementById('comparison-play-pause')?.addEventListener('click', comparisonPlaybackStop);
        document.getElementById('comparison-play-forward')?.addEventListener('click', comparisonPlaybackNext);
        document.getElementById('comparison-play-backward')?.addEventListener('click', comparisonPlaybackPrev);
        updateComparisonPlaybackButtons();

        // Preload the distinct parents list so navigation is instant.
        fetch(comparisonParentsUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            comparisonParents = (res && res.success && Array.isArray(res.parents)) ? res.parents : [];
            updateComparisonPlaybackButtons();
        })
        .catch(function () { comparisonParents = []; });
    }

    function comparisonPlaybackStart() {
        if (!table) {
            return;
        }
        if (!comparisonParents.length) {
            showComparisonToast('info', 'No parents available to navigate.');
            return;
        }
        comparisonPlaybackActive = true;
        comparisonPlaybackIndex = 0;
        document.getElementById('comparison-play-auto')?.style.setProperty('display', 'none');
        document.getElementById('comparison-play-pause')?.style.setProperty('display', 'inline-flex');
        comparisonShowCurrentParent();
    }

    function comparisonPlaybackStop() {
        comparisonPlaybackActive = false;
        comparisonPlaybackParent = '';
        comparisonPlaybackIndex = -1;
        document.getElementById('comparison-play-pause')?.style.setProperty('display', 'none');
        document.getElementById('comparison-play-auto')?.style.setProperty('display', 'inline-flex');
        const label = document.getElementById('comparison-playback-label');
        if (label) {
            label.classList.add('d-none');
            label.textContent = '';
        }
        updateComparisonPlaybackButtons();
        if (table) {
            clearComparisonRowSelection();
            table.setPage(1);
        }
    }

    function comparisonPlaybackNext() {
        if (!comparisonPlaybackActive || comparisonPlaybackIndex >= comparisonParents.length - 1) {
            return;
        }
        comparisonPlaybackIndex++;
        comparisonShowCurrentParent();
    }

    function comparisonPlaybackPrev() {
        if (!comparisonPlaybackActive || comparisonPlaybackIndex <= 0) {
            return;
        }
        comparisonPlaybackIndex--;
        comparisonShowCurrentParent();
    }

    function comparisonShowCurrentParent() {
        if (!comparisonPlaybackActive || comparisonPlaybackIndex < 0 || !table) {
            return;
        }
        comparisonPlaybackParent = String(comparisonParents[comparisonPlaybackIndex] || '');
        const label = document.getElementById('comparison-playback-label');
        if (label) {
            label.classList.remove('d-none');
            label.textContent = `Parent ${comparisonPlaybackIndex + 1} / ${comparisonParents.length}: ${comparisonPlaybackParent}`;
        }
        clearComparisonRowSelection();
        table.setPage(1);
        updateComparisonPlaybackButtons();
    }

    function updateComparisonPlaybackButtons() {
        const backBtn = document.getElementById('comparison-play-backward');
        const fwdBtn = document.getElementById('comparison-play-forward');
        const autoBtn = document.getElementById('comparison-play-auto');
        if (backBtn) {
            backBtn.disabled = !comparisonPlaybackActive || comparisonPlaybackIndex <= 0;
        }
        if (fwdBtn) {
            fwdBtn.disabled = !comparisonPlaybackActive || comparisonPlaybackIndex >= comparisonParents.length - 1;
        }
        if (autoBtn) {
            autoBtn.disabled = comparisonParents.length === 0;
        }
    }

    document.getElementById('comparison-cd-import-btn')?.addEventListener('click', importComparisonGoogleSheet);
    document.getElementById('comparison-cd-image-refresh-btn')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!currentCdRow) return;
        setComparisonHeaderSkuImage(currentCdRow, { bustCache: true });
        showComparisonToast('success', 'SKU image refreshed.');
    });
    function bindComparisonSkuImageHover(img) {
        if (!img) return;
        img.addEventListener('mouseenter', function (e) {
            const url = this.dataset.fullSrc || this.getAttribute('src') || '';
            showComparisonImageHover(url, e.clientX, e.clientY);
        });
        img.addEventListener('mousemove', function (e) {
            const url = this.dataset.fullSrc || this.getAttribute('src') || '';
            if (!url) return;
            showComparisonImageHover(url, e.clientX, e.clientY);
        });
        img.addEventListener('mouseleave', hideComparisonImageHover);
    }
    bindComparisonSkuImageHover(document.getElementById('comparison-cd-modal-image'));
    bindComparisonSkuImageHover(document.getElementById('comparison-roi-modal-image'));
    document.addEventListener('mouseover', function (e) {
        const img = e.target.closest?.('.comparison-table-sku-image');
        if (!img) return;
        const url = img.dataset.fullSrc || img.getAttribute('src') || '';
        showComparisonImageHover(url, e.clientX, e.clientY);
    });
    document.addEventListener('mousemove', function (e) {
        const img = e.target.closest?.('.comparison-table-sku-image');
        if (!img) return;
        const url = img.dataset.fullSrc || img.getAttribute('src') || '';
        if (!url) return;
        showComparisonImageHover(url, e.clientX, e.clientY);
    });
    document.addEventListener('mouseout', function (e) {
        const img = e.target.closest?.('.comparison-table-sku-image');
        if (!img) return;
        const related = e.relatedTarget;
        if (related && typeof related.closest === 'function' && related.closest('.comparison-table-sku-image')) {
            return;
        }
        hideComparisonImageHover();
    });
    document.getElementById('comparisonCdModal')?.addEventListener('hidden.bs.modal', hideComparisonImageHover);
    document.getElementById('comparison-cd-autopopulate-suppliers-btn')?.addEventListener('click', autopopulateSupplierNamesFromList);
    document.getElementById('comparison-cd-roi-btn')?.addEventListener('click', openRoiModal);
    document.getElementById('comparison-roi-apply-proposed-prc')?.addEventListener('click', applyProposedPrcFromLmp);
    document.getElementById('comparison-roi-amz-reviews-slot')?.addEventListener('click', function (e) {
        const dot = e.target.closest('[data-reviews-action]');
        if (!dot || dot.classList.contains('is-disabled')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        handleReviewsBadgeAction(dot.getAttribute('data-reviews-action'));
    });
    document.getElementById('comparison-roi-price-chart-range')?.addEventListener('change', function () {
        const days = parseInt(this.value, 10);
        comparisonRoiPriceChartDays = Number.isFinite(days) ? days : 30;
        const ctx = comparisonRoiPriceChartContext;
        if (!ctx) {
            return;
        }
        const metricLabel = comparisonRoiMetricLabel(ctx.metric || 'price');
        const titleEl = document.getElementById('comparisonRoiPriceChartModalLabel');
        if (titleEl) {
            titleEl.innerHTML = `<i class="fas fa-chart-line me-1"></i> ${escapeHtml(ctx.platformLabel)} ${escapeHtml(metricLabel)} — ${escapeHtml(ctx.sku)} · Rolling L${comparisonRoiPriceChartDays}`;
        }
        loadComparisonRoiPriceHistoryChart();
    });
    document.getElementById('comparison-cd-copy-specs-btn')?.addEventListener('click', copySpecsToMemory);
    document.getElementById('comparison-cd-qc-issues-btn')?.addEventListener('click', openComparisonQcIssuesModal);

    document.getElementById('comparison-cd-siblings-sync')?.addEventListener('change', function () {
        setSiblingsSyncEnabled(!!this.checked, { persist: true, triggerSave: !!this.checked });
    });

    // Keep initial badge state in sync with stored preference.
    updateSiblingsBadge(currentSiblingsData);

    document.getElementById('comparison-cd-reviews-btn')?.addEventListener('click', function (e) {
        const dot = e.target.closest('[data-reviews-action]');
        if (!dot || dot.classList.contains('is-disabled')) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        handleReviewsBadgeAction(dot.getAttribute('data-reviews-action'));
    });

    document.getElementById('comparison-reviews-chart-range')?.addEventListener('change', function () {
        const days = parseInt(this.value, 10);
        comparisonReviewsChartDays = Number.isFinite(days) ? days : 0;
        const data = currentReviewsData || {};
        const parent = String(data.parent || currentCdRow?.parent || '').trim();
        const sku = String(data.sku || currentCdRow?.sku || '').trim();
        const label = parent || sku;
        const rangeLabel = comparisonReviewsChartDays <= 0
            ? 'Lifetime'
            : ('Rolling L' + comparisonReviewsChartDays);
        const titleEl = document.getElementById('comparisonReviewsChartModalLabel');
        if (titleEl) {
            titleEl.innerHTML = `<i class="fas fa-chart-line me-1"></i> Rating status — ${escapeHtml(label)}${parent ? ' (Parent)' : ''} · ${rangeLabel}`;
        }
        loadComparisonReviewsChart();
    });

    document.getElementById('comparison-qc-issues-tbody')?.addEventListener('click', function (e) {
        const searchIcon = e.target.closest('.cd-qc-search-icon');
        if (searchIcon) {
            const title = searchIcon.dataset.qcTitle || 'Details';
            const text = searchIcon.dataset.qcText || '';
            const labelEl = document.getElementById('comparisonQcIssueTextModalLabel');
            const bodyEl = document.getElementById('comparison-qc-issue-text-body');
            if (labelEl) labelEl.textContent = title;
            if (bodyEl) {
                bodyEl.textContent = text.trim() ? text : 'No data recorded.';
            }
            comparisonQcIssueTextModal?.show();
            return;
        }

        const thumb = e.target.closest('.cd-qc-issue-thumb');
        if (thumb?.dataset.qcImage) {
            window.open(thumb.dataset.qcImage, '_blank', 'noopener,noreferrer');
        }
    });

    // Deep-link: /purchase-master/comparison?cd_sku=SKU filters to that SKU and
    // auto-opens its CD modal. Used by the Forecast Analysis CD column iframe.
    (function () {
        try {
            const params = new URLSearchParams(window.location.search);
            const cdSku = (params.get('cd_sku') || '').trim();
            if (!cdSku) return;
            const skuInput = document.getElementById('comparison-search-sku');
            if (skuInput) skuInput.value = cdSku;
            let opened = false;
            const openMatch = function () {
                if (opened || typeof table === 'undefined' || !table) return;
                const match = table.getRows().find(function (r) {
                    return String((r.getData() || {}).sku || '').trim().toUpperCase() === cdSku.toUpperCase();
                });
                if (match && typeof openComparisonModal === 'function') {
                    opened = true;
                    openComparisonModal(match.getData());
                }
            };
            const start = function () {
                if (typeof table === 'undefined' || !table) { setTimeout(start, 100); return; }
                table.on('dataLoaded', openMatch);
                try { table.replaceData(); } catch (e) {}
            };
            start();
        } catch (e) {}
    })();
    document.getElementById('comparison-cd-replace-specs-btn')?.addEventListener('click', replaceSpecsFromMemory);
    function updateCdGoogleUrlDotUI() {
        const input = document.getElementById('comparison-cd-google-url');
        const link = document.getElementById('comparison-cd-google-url-link');
        if (!input || !link) {
            return;
        }

        const url = input.value.trim();
        if (url) {
            link.href = url;
            link.classList.remove('comparison-clink-dot-empty');
            link.title = url;
            link.setAttribute('aria-label', 'Open Google Sheet');
        } else {
            link.href = '#';
            link.classList.add('comparison-clink-dot-empty');
            link.title = 'Click to set C link Sheet URL';
            link.setAttribute('aria-label', 'Set C link Sheet URL');
        }

        const dot = document.getElementById('comparison-cd-google-url-dot');
        if (dot) {
            dot.classList.toggle('comparison-clink-dot-muted', !url);
        }
    }

    function setCdGoogleUrlEditing(editing) {
        const wrap = document.getElementById('comparison-cd-google-url-wrap');
        const input = document.getElementById('comparison-cd-google-url');
        if (!wrap || !input) {
            return;
        }

        wrap.classList.toggle('is-editing', editing);
        if (editing) {
            input.focus();
            input.select();
        }
    }

    document.getElementById('comparison-cd-google-url-wrap')?.addEventListener('click', function (e) {
        const editBtn = e.target.closest('#comparison-cd-google-url-edit-btn');
        if (editBtn) {
            e.preventDefault();
            setCdGoogleUrlEditing(true);
            return;
        }

        const link = e.target.closest('#comparison-cd-google-url-link');
        if (!link) {
            return;
        }

        const input = document.getElementById('comparison-cd-google-url');
        const url = input?.value.trim() || '';
        if (!url) {
            e.preventDefault();
            setCdGoogleUrlEditing(true);
        }
    });

    document.getElementById('comparison-cd-google-url-link')?.addEventListener('dblclick', function (e) {
        e.preventDefault();
        setCdGoogleUrlEditing(true);
    });

    document.getElementById('comparison-cd-google-url')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            this.blur();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            setCdGoogleUrlEditing(false);
            updateCdGoogleUrlDotUI();
        }
    });

    document.getElementById('comparison-cd-google-url')?.addEventListener('blur', function () {
        window.setTimeout(function () {
            const wrap = document.getElementById('comparison-cd-google-url-wrap');
            if (wrap?.contains(document.activeElement)) {
                return;
            }
            setCdGoogleUrlEditing(false);
            updateCdGoogleUrlDotUI();
        }, 120);
    });

    document.getElementById('comparison-cd-google-url')?.addEventListener('input', updateCdGoogleUrlDotUI);
    // URL/tab metadata only — quiet save, never pull/push Google Sheet here.
    document.getElementById('comparison-cd-google-url')?.addEventListener('change', () =>
        scheduleAutoSaveComparisonSheet(800, { rerender: false, refreshTable: false })
    );
    document.getElementById('comparison-cd-google-tab')?.addEventListener('change', () =>
        scheduleAutoSaveComparisonSheet(800, { rerender: false, refreshTable: false })
    );
    document.getElementById('comparison-cd-move-row-up-btn')?.addEventListener('click', () => moveSheetRow('up'));
    document.getElementById('comparison-cd-move-row-down-btn')?.addEventListener('click', () => moveSheetRow('down'));
    document.getElementById('comparison-cd-insert-row-btn')?.addEventListener('click', insertSheetRow);
    document.getElementById('comparison-cd-delete-row-btn')?.addEventListener('click', deleteSheetRow);
    document.getElementById('comparison-cd-move-col-left-btn')?.addEventListener('click', () => moveSheetColumn('left'));
    document.getElementById('comparison-cd-move-col-right-btn')?.addEventListener('click', () => moveSheetColumn('right'));
    document.getElementById('comparison-cd-insert-col-btn')?.addEventListener('click', insertSheetColumn);
    document.getElementById('comparison-cd-delete-col-btn')?.addEventListener('click', deleteSheetColumn);

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('paste', function (e) {
        const cell = resolveSheetPasteCell(e.target);
        if (!cell || !canPasteSheetImageIntoCell(cell)) {
            const editable = e.target.closest('.cd-sheet-cell[contenteditable="true"]');
            if (!editable) return;
            const colIndex = parseInt(editable.dataset.col, 10);
            if (isSheetSpecColumn(colIndex)) return;
            if (isCompanyNameRow(parseInt(editable.dataset.row, 10), currentSheetCells)) return;
            const pasted = (e.clipboardData || window.clipboardData)?.getData('text')?.replace(/\s+/g, ' ').trim();
            if (!pasted || !isSheetLinkUrl(pasted)) return;
            e.preventDefault();
            convertSheetCellValue(editable, pasted, false);
            scheduleAutoSaveComparisonSheet(1000, { rerender: false, refreshTable: false });
            return;
        }

        const clipboard = e.clipboardData || window.clipboardData;
        const imageFile = getClipboardImageFile(clipboard);
        if (imageFile) {
            e.preventDefault();
            applyPastedImageFileToSheetCell(cell, imageFile);
            return;
        }

        const htmlSrc = getClipboardHtmlImageSrc(clipboard);
        if (htmlSrc && (htmlSrc.startsWith('data:image/') || isSheetImageUrl(htmlSrc))) {
            e.preventDefault();
            applyPastedImageSrcToSheetCell(cell, htmlSrc);
            return;
        }

        const pasted = clipboard?.getData('text')?.replace(/\s+/g, ' ').trim();
        if (pasted && isSheetLinkUrl(pasted)) {
            e.preventDefault();
            convertSheetCellValue(cell, pasted, false);
            scheduleAutoSaveComparisonSheet(1000, { rerender: false, refreshTable: false });
            return;
        }

        // Browser-default paste (screenshot / rich HTML): shrink any raw <img> so it stays in-cell.
        window.setTimeout(function () {
            const liveCell = (cell && cell.isConnected) ? cell : resolveSheetPasteCell(cell);
            if (!liveCell || !liveCell.querySelectorAll) {
                return;
            }
            const imgs = liveCell.querySelectorAll('img');
            if (!imgs.length) {
                return;
            }
            imgs.forEach(fitSheetCellImageEl);
            const src = imgs[0].getAttribute('src') || imgs[0].src || '';
            if (src.startsWith('data:image/') || isSheetImageUrl(src)) {
                applyPastedImageSrcToSheetCell(liveCell, src);
            }
        }, 0);
    }, true);

    // Do not autosave on every keystroke — that re-read/rebuild loop hangs the page.

    document.getElementById('comparison-cd-critical-filters')?.addEventListener('change', function (e) {
        if (!e.target.classList.contains('cd-priority-filter-check')) {
            return;
        }
        applyPriorityRowFilters();
    });

    document.getElementById('comparison-cd-qc-filters')?.addEventListener('change', function (e) {
        if (!e.target.classList.contains('cd-priority-filter-check')) {
            return;
        }
        applyPriorityRowFilters();
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'cd-sheet-select-all-rows') {
            const checked = !!e.target.checked;
            for (let r = 1; r < (currentSheetCells || []).length; r++) {
                setSheetMultiRowSelected(r, checked);
            }
            applySheetSelectionHighlight();
            return;
        }

        const rowCheck = e.target?.closest?.('.cd-sheet-row-select');
        if (rowCheck) {
            const rowIndex = parseInt(rowCheck.dataset.row, 10);
            setSheetMultiRowSelected(rowIndex, !!rowCheck.checked);
            applySheetSelectionHighlight();
        }
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('click', function (e) {
        const rowEditBtn = e.target.closest('.cd-sheet-row-edit-btn');
        if (rowEditBtn) {
            e.preventDefault();
            e.stopPropagation();
            const rowIndex = parseInt(rowEditBtn.dataset.row, 10);
            if (!Number.isNaN(rowIndex)) {
                selectedSheetRow = rowIndex;
                applySheetSelectionHighlight();
                openPriorityBulkEditModal(rowIndex);
            }
            return;
        }

        const colEditBtn = e.target.closest('.cd-sheet-col-edit-btn');
        if (colEditBtn) {
            e.preventDefault();
            e.stopPropagation();
            const colIndex = parseInt(colEditBtn.dataset.col, 10);
            if (!Number.isNaN(colIndex)) {
                openColumnEditModal(colIndex);
            }
            return;
        }

        if (e.target.closest('.cd-row-select-col, .cd-sheet-row-select, #cd-sheet-select-all-rows')) {
            e.stopPropagation();
        }
    }, true);

    document.addEventListener('click', function (e) {
        const copyFieldBtn = e.target.closest('.cd-field-copy-btn');
        if (copyFieldBtn) {
            e.preventDefault();
            const field = resolveClipboardField(copyFieldBtn);
            copyFieldValueToClipboard(field)
                .then(() => setSheetStatus('Copied to clipboard.', false))
                .catch(() => setSheetStatus('Could not copy value.', true));
            return;
        }
        const cutFieldBtn = e.target.closest('.cd-field-cut-btn');
        if (cutFieldBtn) {
            e.preventDefault();
            const field = resolveClipboardField(cutFieldBtn);
            cutFieldValueToClipboard(field)
                .then(() => setSheetStatus('Cut to clipboard.', false))
                .catch(() => setSheetStatus('Could not cut value.', true));
            return;
        }
        const pasteFieldBtn = e.target.closest('.cd-field-paste-btn');
        if (pasteFieldBtn) {
            e.preventDefault();
            const field = resolveClipboardField(pasteFieldBtn);
            pasteFieldValueFromClipboard(field)
                .then(() => setSheetStatus('Pasted from clipboard.', false))
                .catch((err) => setSheetStatus(err?.message || 'Could not paste value.', true));
            return;
        }

        if (e.target.closest('#comparison-priority-bulk-save-btn')) {
            e.preventDefault();
            savePriorityBulkEditModal();
            return;
        }
        if (e.target.closest('#comparison-priority-bulk-delete-row-btn')) {
            e.preventDefault();
            deleteRowsFromPriorityBulkEditModal();
            return;
        }
        if (e.target.closest('#comparison-priority-bulk-add-row-btn')) {
            e.preventDefault();
            addRowBelowFromPriorityBulkEditModal();
            return;
        }
        if (e.target.closest('#comparison-column-edit-save-btn')) {
            e.preventDefault();
            saveColumnEditModal();
            return;
        }
        if (e.target.closest('#comparison-column-edit-add-col-btn-footer')) {
            e.preventDefault();
            addBlankColumnFromColumnEditModal();
            return;
        }
        if (e.target.closest('#comparison-column-edit-delete-col-btn-footer')) {
            e.preventDefault();
            deleteCurrentColumnFromColumnEditModal();
            return;
        }
        if (e.target.closest('#comparison-column-edit-bulk-apply-btn')) {
            e.preventDefault();
            applyBulkValueToSelectedColumnEditRows();
        }
    });

    document.getElementById('comparisonColumnEditModal')?.addEventListener('change', function (e) {
        const selectAll = e.target.closest('#comparison-column-edit-select-all');
        if (selectAll) {
            const checked = !!selectAll.checked;
            document.querySelectorAll('#comparison-column-edit-tbody .cd-col-edit-row-check').forEach((check) => {
                check.checked = checked;
            });
            syncColumnEditSelectedRowsFromDom();
            return;
        }
        if (e.target.closest('.cd-col-edit-row-check')) {
            syncColumnEditSelectedRowsFromDom();
        }
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('blur', function (e) {
        const cell = e.target.closest('.cd-sheet-cell[contenteditable="true"]');
        if (!cell) return;

        // Finishing a cell edit: quiet local save only (no full rebuild / Google push).
        if (maybeConvertSheetCellToLink(cell)) {
            scheduleAutoSaveComparisonSheet(600, { rerender: false, refreshTable: false });
            return;
        }

        if (maybeRefreshCompanyNameCell(cell)) {
            scheduleAutoSaveComparisonSheet(600, { rerender: false, refreshTable: false });
            return;
        }

        readCellsFromEditor({ expandImages: false });
        const rowIndex = parseInt(cell.dataset.row, 10);
        const specCol = detectSpecColumnIndex(currentSheetCells);
        if (isSupplierNameRow(currentSheetCells, rowIndex, specCol)) {
            syncCommRowOnSheet();
            // Supplier-name change needs a light rebuild for Comm dots — still no list reload.
            renderSheetEditor(currentSheetCells, { migrateDimWt: false, sortByPrice: false });
        }

        scheduleAutoSaveComparisonSheet(800, { rerender: false, refreshTable: false });
    }, true);

    // ---- Drag & drop to reorder rows (drag the row number) and columns (drag the header) ----
    (function initCdSheetDragAndDrop() {
        const wrap = document.getElementById('comparison-cd-sheet-wrap');
        if (!wrap) return;

        let dragType = null;   // 'row' | 'col'
        let dragIndex = null;

        function clearDndTargets() {
            wrap.querySelectorAll('.cd-dnd-target').forEach(el => el.classList.remove('cd-dnd-target'));
        }

        function endDrag() {
            clearDndTargets();
            wrap.classList.remove('cd-dnd-active');
            dragType = null;
            dragIndex = null;
        }

        wrap.addEventListener('dragstart', function (e) {
            if (e.target.closest('.cd-sheet-col-edit-btn, .cd-sheet-row-edit-btn')) {
                e.preventDefault();
                return;
            }
            const rowHandle = e.target.closest('.cd-select-row');
            const colHandle = e.target.closest('.cd-col-header');
            if (rowHandle) {
                dragType = 'row';
                dragIndex = parseInt(rowHandle.dataset.row, 10);
            } else if (colHandle) {
                dragType = 'col';
                dragIndex = parseInt(colHandle.dataset.col, 10);
            } else {
                return;
            }
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', dragType + ':' + dragIndex); } catch (err) {}
            }
            wrap.classList.add('cd-dnd-active');
        });

        wrap.addEventListener('dragover', function (e) {
            if (dragType === null) return;
            const target = dragType === 'row'
                ? e.target.closest('.cd-select-row')
                : e.target.closest('.cd-col-header');
            if (!target) return;
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            clearDndTargets();
            target.classList.add('cd-dnd-target');
        });

        wrap.addEventListener('drop', function (e) {
            if (dragType === null) return;
            e.preventDefault();
            const target = dragType === 'row'
                ? e.target.closest('.cd-select-row')
                : e.target.closest('.cd-col-header');
            if (target) {
                const toIndex = parseInt(dragType === 'row' ? target.dataset.row : target.dataset.col, 10);
                if (!isNaN(toIndex) && !isNaN(dragIndex) && toIndex !== dragIndex) {
                    if (dragType === 'row') moveSheetRowTo(dragIndex, toIndex);
                    else moveSheetColumnTo(dragIndex, toIndex);
                }
            }
            endDrag();
        });

        wrap.addEventListener('dragend', endDrag);
    })();

    let activeCompanyTooltipCell = null;
    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('mouseover', function (e) {
        const cell = e.target.closest('.cd-sheet-cell-company');
        if (!cell || cell === activeCompanyTooltipCell) return;
        activeCompanyTooltipCell = cell;
        showSheetCellTooltip(e, cell.dataset.value || '');
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('mousemove', function (e) {
        if (activeCompanyTooltipCell && e.target.closest('.cd-sheet-cell-company') === activeCompanyTooltipCell) {
            positionCdHover(e);
        }
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('mouseout', function (e) {
        const cell = e.target.closest('.cd-sheet-cell-company');
        if (!cell || cell !== activeCompanyTooltipCell) return;
        const related = e.relatedTarget;
        if (related && cell.contains(related)) return;
        activeCompanyTooltipCell = null;
        hideCdHover();
    }, true);

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('click', function (e) {
        const commBtn = e.target.closest('.cd-sheet-comm-btn');
        if (commBtn) {
            e.preventDefault();
            e.stopPropagation();
            const supplierName = commBtn.dataset.supplierName || getSupplierNameForColumn(parseInt(commBtn.dataset.col, 10));
            openComparisonCommModal(supplierName);
            return;
        }

        if (e.target.closest('.cd-sheet-link-btn')) {
            e.stopPropagation();
            return;
        }

        if (e.target.closest('.cd-sheet-row-edit-btn, .cd-row-select-col, .cd-sheet-cell-priority')) {
            e.stopPropagation();
            const cellTarget = e.target.closest('.cd-sheet-cell');
            if (cellTarget) {
                const rowIndex = parseInt(cellTarget.dataset.row, 10);
                const colIndex = parseInt(cellTarget.dataset.col, 10);
                if (!Number.isNaN(rowIndex) && !Number.isNaN(colIndex)) {
                    selectedSheetRow = rowIndex;
                    selectedSheetCol = colIndex;
                    selectedSheetCell = { row: rowIndex, col: colIndex };
                    applySheetSelectionHighlight();
                }
            }
            return;
        }

        const rowTarget = e.target.closest('.cd-select-row');
        if (rowTarget) {
            e.preventDefault();
            selectedSheetRow = parseInt(rowTarget.dataset.row, 10);
            if (Number.isNaN(selectedSheetRow)) {
                selectedSheetRow = null;
            }
            selectedSheetCell = null;
            applySheetSelectionHighlight();
            return;
        }

        const colTarget = e.target.closest('.cd-select-col');
        if (colTarget) {
            e.preventDefault();
            selectedSheetCol = parseInt(colTarget.dataset.col, 10);
            if (Number.isNaN(selectedSheetCol)) {
                selectedSheetCol = null;
            }
            selectedSheetCell = null;
            applySheetSelectionHighlight();
            return;
        }

        const cellTarget = e.target.closest('.cd-sheet-cell');
        if (cellTarget) {
            const rowIndex = parseInt(cellTarget.dataset.row, 10);
            const colIndex = parseInt(cellTarget.dataset.col, 10);
            if (!Number.isNaN(rowIndex) && !Number.isNaN(colIndex)) {
                selectedSheetRow = rowIndex;
                selectedSheetCol = colIndex;
                selectedSheetCell = { row: rowIndex, col: colIndex };
                applySheetSelectionHighlight();
            }
        }
    });

    document.getElementById('comparison-cd-sheet-wrap')?.addEventListener('dblclick', function (e) {
        const dotCell = e.target.closest('.cd-sheet-cell-link, .cd-sheet-cell-company');
        if (!dotCell) return;
        e.preventDefault();
        hideCdHover();
        activeCompanyTooltipCell = null;
        const value = dotCell.dataset.value || '';
        const rowIndex = parseInt(dotCell.dataset.row, 10);
        const colIndex = parseInt(dotCell.dataset.col, 10);
        dotCell.outerHTML = sheetCellEditorHtml(value, rowIndex, colIndex, true);
        const editable = document.querySelector(`.cd-sheet-cell[data-row="${rowIndex}"][data-col="${colIndex}"]`);
        editable?.focus();
    });
    document.getElementById('cd-lmp-tab-btn')?.addEventListener('shown.bs.tab', function () {
        if (currentCdRow) {
            loadLmpTab(currentCdRow);
        }
    });

    document.getElementById('comparison-cd-lmp-add-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        submitAmazonLmpAddForm('comparison-cd', 'comparison-cd-lmp-add-form');
    });

    document.getElementById('comparison-lmp-add-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        submitComparisonLmpAddForm();
    });

    document.addEventListener('click', function (e) {
        const deleteBtn = e.target.closest('.comparison-delete-lmp-btn');
        if (!deleteBtn) {
            return;
        }
        e.preventDefault();
        deleteAmazonLmpCompetitor(deleteBtn);
    });
});
</script>
@endsection

@section('modal')
<div class="modal fade" id="comparisonPriorityBulkEditModal" tabindex="-1" aria-labelledby="comparisonPriorityBulkEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="comparisonPriorityBulkEditModalLabel">
                    <i class="mdi mdi-pencil-outline me-1"></i> Edit Critical / QC
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    Applying to <strong id="comparison-priority-bulk-count">0</strong> selected row(s):
                    <span id="comparison-priority-bulk-rows" class="fw-semibold"></span>
                </p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1" for="comparison-priority-bulk-critical">Critical</label>
                    <div class="cd-field-clip-wrap">
                        <select id="comparison-priority-bulk-critical" class="form-select form-select-sm">
                            <option value="">— Keep current —</option>
                            <option value="Normal">Normal</option>
                            <option value="Important">Important</option>
                            <option value="Critical">Critical</option>
                        </select>
                        <span class="cd-field-clip-btns">
                            <button type="button" class="cd-field-clip-btn cd-field-copy-btn" data-field-id="comparison-priority-bulk-critical" title="Copy" aria-label="Copy Critical">
                                <i class="mdi mdi-content-copy" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="cd-field-clip-btn cd-field-cut-btn" data-field-id="comparison-priority-bulk-critical" title="Cut" aria-label="Cut Critical">
                                <i class="mdi mdi-content-cut" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="cd-field-clip-btn cd-field-paste-btn" data-field-id="comparison-priority-bulk-critical" title="Paste" aria-label="Paste Critical">
                                <i class="mdi mdi-content-paste" aria-hidden="true"></i>
                            </button>
                        </span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold mb-1" for="comparison-priority-bulk-qc">QC</label>
                    <div class="cd-field-clip-wrap">
                        <select id="comparison-priority-bulk-qc" class="form-select form-select-sm">
                            <option value="">— Keep current —</option>
                            <option value="Normal">Normal</option>
                            <option value="Important">Important</option>
                            <option value="Critical">Critical</option>
                        </select>
                        <span class="cd-field-clip-btns">
                            <button type="button" class="cd-field-clip-btn cd-field-copy-btn" data-field-id="comparison-priority-bulk-qc" title="Copy" aria-label="Copy QC">
                                <i class="mdi mdi-content-copy" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="cd-field-clip-btn cd-field-cut-btn" data-field-id="comparison-priority-bulk-qc" title="Cut" aria-label="Cut QC">
                                <i class="mdi mdi-content-cut" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="cd-field-clip-btn cd-field-paste-btn" data-field-id="comparison-priority-bulk-qc" title="Paste" aria-label="Paste QC">
                                <i class="mdi mdi-content-paste" aria-hidden="true"></i>
                            </button>
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="comparison-priority-bulk-delete-row-btn" title="Delete the selected row(s)">
                        <i class="mdi mdi-delete-outline me-1"></i> Delete current row
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="comparison-priority-bulk-add-row-btn" title="Insert a blank row below the selected row">
                        <i class="mdi mdi-plus me-1"></i> Add Additional Row Below
                    </button>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="comparison-priority-bulk-save-btn">
                    <i class="mdi mdi-content-save-outline me-1"></i> Save to selected
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonColumnEditModal" tabindex="-1" aria-labelledby="comparisonColumnEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="comparisonColumnEditModalLabel">
                    <i class="mdi mdi-pencil-outline me-1"></i> Edit column
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="cd-col-edit-bulk-bar" id="comparison-column-edit-bulk-bar">
                    <label class="cd-col-edit-bulk-label" for="comparison-column-edit-bulk-value">Apply to selected</label>
                    <span id="comparison-column-edit-bulk-value-wrap">
                        <select id="comparison-column-edit-bulk-value" class="form-select form-select-sm cd-col-edit-bulk-value">
                            <option value="Normal">Normal</option>
                            <option value="Important">Important</option>
                            <option value="Critical" selected>Critical</option>
                        </select>
                    </span>
                    <button type="button" class="btn btn-sm btn-info text-white" id="comparison-column-edit-bulk-apply-btn" disabled title="Set this value on all checked rows">
                        <i class="mdi mdi-checkbox-multiple-marked-outline me-1"></i> Apply
                    </button>
                    <span class="cd-col-edit-bulk-count" id="comparison-column-edit-bulk-count">Select rows to apply</span>
                </div>
                <div class="table-responsive" style="max-height: min(65vh, 560px); overflow-x: auto;">
                    <table class="table table-sm table-bordered align-middle cd-col-edit-table">
                        <colgroup>
                            <col style="width: 42px;">
                            <col style="width: 28%;">
                            <col>
                        </colgroup>
                        <thead>
                            <tr>
                                <th scope="col" class="cd-col-edit-check">
                                    <input type="checkbox" class="form-check-input" id="comparison-column-edit-select-all"
                                        title="Select all editable rows" aria-label="Select all editable rows">
                                </th>
                                <th scope="col">Spec / Row</th>
                                <th scope="col">Value</th>
                            </tr>
                        </thead>
                        <tbody id="comparison-column-edit-tbody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-danger" id="comparison-column-edit-delete-col-btn-footer" title="Delete this column">
                    <i class="mdi mdi-delete-outline me-1"></i> Delete column
                </button>
                <button type="button" class="btn btn-sm btn-outline-success me-auto" id="comparison-column-edit-add-col-btn-footer" title="Insert a blank column after this one">
                    <i class="mdi mdi-plus me-1"></i> Add blank column
                </button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="comparison-column-edit-save-btn">
                    <i class="mdi mdi-content-save-outline me-1"></i> Save column
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonReviewsChartModal" tabindex="-1" aria-labelledby="comparisonReviewsChartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="comparisonReviewsChartModalLabel">
                    <i class="fas fa-chart-line me-1"></i> Rating status
                </h6>
                <div class="d-flex align-items-center gap-2 ms-auto me-2">
                    <select id="comparison-reviews-chart-range" class="form-select form-select-sm" style="width: 110px;">
                        <option value="0" selected>Lifetime</option>
                        <option value="90">L90</option>
                        <option value="60">L60</option>
                        <option value="30">L30</option>
                        <option value="7">L7</option>
                    </select>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3">
                <div id="comparison-reviews-chart-loading" class="text-center py-4 text-muted" style="display:none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading rating history…
                </div>
                <div id="comparison-reviews-chart-nodata" class="text-center py-4 text-muted" style="display:none;">
                    No rating snapshot history for this SKU/parent yet.
                </div>
                <div id="comparison-reviews-chart-container" style="display:none;">
                    <div id="comparison-reviews-chart" style="min-height: 260px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonRoiPriceChartModal" tabindex="-1" aria-labelledby="comparisonRoiPriceChartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-bottom comparison-roi-price-chart-bottom-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="comparisonRoiPriceChartModalLabel">
                    <i class="fas fa-chart-line me-1"></i> Price history
                </h6>
                <div class="d-flex align-items-center gap-2 ms-auto me-2">
                    <select id="comparison-roi-price-chart-range" class="form-select form-select-sm" style="width: 110px;" title="Rolling window">
                        <option value="90">L90</option>
                        <option value="60">L60</option>
                        <option value="30" selected>L30</option>
                        <option value="7">L7</option>
                    </select>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body p-3">
                <div id="comparison-roi-price-chart-loading" class="text-center py-4 text-muted" style="display:none;">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading price history…
                </div>
                <div id="comparison-roi-price-chart-nodata" class="text-center py-4 text-muted" style="display:none;">
                    No price history for this channel / SKU yet.
                </div>
                <div id="comparison-roi-price-chart-container" style="display:none;">
                    <div id="comparison-roi-price-chart" style="min-height: 300px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonQcIssuesModal" tabindex="-1" aria-labelledby="comparisonQcIssuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header py-2 bg-info text-white">
                <h6 class="modal-title mb-0" id="comparisonQcIssuesModalLabel">
                    <i class="fas fa-search me-1"></i> QC Issues —
                    <span id="comparison-qc-issues-sku-label"></span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle mb-0 cd-qc-issues-table">
                        <thead>
                            <tr>
                                <th>Problem / Issue</th>
                                <th>Suggestion / Improve</th>
                                <th>Image</th>
                                <th>Video</th>
                            </tr>
                        </thead>
                        <tbody id="comparison-qc-issues-tbody">
                            <tr>
                                <td colspan="4" class="text-muted text-center py-3">No QC issue data loaded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mt-2" id="comparison-qc-issues-history"></div>
                <div class="mt-2">
                    <a href="{{ route('qc.masters') }}" target="_blank" rel="noopener noreferrer" class="small">
                        Open QC Masters page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonQcIssueTextModal" tabindex="-1" aria-labelledby="comparisonQcIssueTextModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="comparisonQcIssueTextModalLabel">Details</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="comparison-qc-issue-text-body" class="small" style="white-space: pre-wrap;"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="comparisonRoiModal" tabindex="-1" aria-labelledby="comparisonRoiModalLabel" aria-hidden="true">
    <div class="modal-dialog comparison-roi-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title mb-0 d-flex align-items-center flex-wrap" id="comparisonRoiModalLabel">
                    <span class="comparison-roi-header-image-wrap d-none" id="comparison-roi-modal-image-wrap">
                        <img id="comparison-roi-modal-image" class="comparison-roi-header-image" src="" alt="SKU image" title="Hover to enlarge">
                    </span>
                    <span>
                        <i class="mdi mdi-percent"></i> Profit Calculator
                        <span class="ms-2 fw-semibold" id="comparison-roi-modal-sku"></span>
                    </span>
                </h5>
                <div id="comparison-roi-amz-reviews-slot" aria-label="Amazon reviews from Jungle Scout"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="comparison-roi-apply-proposed-prc"
                        title="Set Proposed PRC = LMP × 90% for each platform that has LMP">
                        Proposed Prc Apply
                    </button>
                    <div class="small text-end ms-auto" id="comparison-roi-save-status"></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered comparison-roi-table mb-0">
                        <thead>
                            <tr>
                                <th>profit calculator</th>
                                <th>CP</th>
                                <th>CBM</th>
                                <th>Freight</th>
                                <th>GW LB</th>
                                <th>Shipping</th>
                                <th>Proposed PRC</th>
                                <th>LMP</th>
                                <th class="comparison-roi-proposed-metrics-th" title="Gross $ = Proposed PRC × margin − CP − Shipping">Profit</th>
                                <th class="comparison-roi-proposed-metrics-th" title="PGROI% (Proposed PRC) = (Proposed PRC × margin − CP − Shipping) ÷ CP × 100 · Amz 80% / Ebay 83% / Temu 100% / Shopify 95%">PGROI%</th>
                                <th class="comparison-roi-proposed-metrics-th" title="PGPFT% (Proposed PRC) = (Proposed PRC × margin − CP − Shipping) ÷ Proposed PRC × 100">PGPFT%</th>
                                <th class="comparison-roi-proposed-metrics-th" title="PNROI% (Proposed PRC) = (Gross $ − Proposed PRC × Ads%/100) ÷ CP × 100 (Temu: PGROI% − Ads%)">PNROI%</th>
                                <th class="comparison-roi-proposed-metrics-th" title="PNPFT% (Proposed PRC) = PGPFT% − Ads% (Ads% from Pricing Master / OV L30)">PNPFT%</th>
                                <th class="comparison-roi-c-price-th" title="Current listing price from each marketplace">C Price</th>
                                <th class="comparison-roi-site-metrics-th" title="GROI% (C Price) = (C Price × margin − CP − Shipping) ÷ CP × 100 · Amz 80% / Ebay 83% / Temu 100% / Shopify 95%">GROI%</th>
                                <th class="comparison-roi-site-metrics-th" title="GPFT% (C Price) = (C Price × margin − CP − Shipping) ÷ C Price × 100">GPFT%</th>
                                <th class="comparison-roi-site-metrics-th" title="NROI% (C Price) = (Gross $ − C Price × Ads%/100) ÷ CP × 100 (Temu: GROI% − Ads%)">NROI%</th>
                                <th class="comparison-roi-site-metrics-th" title="NPFT% (C Price) = GPFT% − Ads% (Ads% from Pricing Master / OV L30)">NPFT%</th>
                            </tr>
                        </thead>
                        <tbody id="comparison-roi-tbody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
