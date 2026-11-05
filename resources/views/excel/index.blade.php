@extends('layouts.vertical', ['title' => 'eBay LQS'])

@section('css')
<style>
    .excel-manager-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 18px;
    }
    .excel-manager-head h3 {
        margin: 0;
        font-weight: 650;
        letter-spacing: -0.02em;
    }
    .excel-manager-head p {
        margin: 4px 0 0;
        color: #6c757d;
    }
    .excel-dropzone {
        border: 1.5px dashed #b7c3d0;
        border-radius: 12px;
        background: #f8fafc;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 28px 16px;
        transition: border-color .15s ease, background .15s ease;
        cursor: pointer;
    }
    .excel-dropzone.is-dragover {
        border-color: #727cf5;
        background: #f3f4ff;
    }
    .excel-dropzone i {
        font-size: 42px;
        color: #188038;
        line-height: 1;
    }
    .excel-stat-label {
        font-size: 11px;
        letter-spacing: .04em;
    }
    .excel-stat-value {
        font-size: 18px;
        font-weight: 650;
        margin-top: 4px;
    }
    .excel-scroll {
        overflow: auto;
        max-height: calc(100vh - 390px);
        min-height: 320px;
        border: 1px solid #dadce0;
        border-radius: 8px;
        background: #fff;
    }
    .excel-grid {
        width: max-content;
        min-width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }
    .excel-grid th,
    .excel-grid td {
        padding: 7px 12px;
        border-right: 1px solid #e6e8eb;
        border-bottom: 1px solid #e6e8eb;
        white-space: nowrap;
        max-width: 420px;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
        background: #fff;
    }
    .excel-grid th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #727cf5;
        color: #fff;
        font-weight: 650;
        cursor: pointer;
        user-select: none;
        border-right-color: rgba(255, 255, 255, .25);
        border-bottom-color: #5f6ae0;
        box-shadow: inset 0 -1px 0 #5f6ae0;
    }
    .excel-grid th:hover {
        background: #6570e8;
        color: #fff;
    }
    .excel-grid tbody tr:hover td {
        background: #f1f3f4;
    }
    .excel-grid th.is-sorted {
        background: #4e5bd6;
        color: #fff;
    }
    .excel-grid td.is-blank {
        color: transparent;
    }
    .excel-sort-mark {
        display: inline-block;
        width: 1em;
        color: rgba(255, 255, 255, .9);
        font-size: 11px;
    }
    .excel-pagebar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 12px;
    }
    .excel-loading {
        position: absolute;
        inset: 0;
        background: rgba(255, 255, 255, .72);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 3;
    }
    .excel-table-wrap {
        position: relative;
    }
</style>
@endsection

@section('content')
<div class="excel-manager py-2" id="excel-manager">
    <div class="excel-manager-head">
        <div>
            <h3>eBay LQS</h3>
            <p>Upload a workbook, switch worksheets, then search, sort, page, and export.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-light" id="excel-upload-another" hidden>
                <i class="ri-upload-2-line me-1"></i> Upload another file
            </button>
            <button type="button" class="btn btn-success" id="excel-export" disabled>
                <i class="ri-file-excel-2-line me-1"></i> Export Excel
            </button>
        </div>
    </div>

    <div id="excel-alert" class="alert alert-danger alert-dismissible fade show" role="alert" hidden>
        <span id="excel-alert-text"></span>
        <button type="button" class="btn-close" id="excel-alert-close" aria-label="Close"></button>
    </div>

    <div class="card border-0 shadow-sm mb-3" id="excel-upload-card">
        <div class="card-body">
            <div id="excel-dropzone" class="excel-dropzone" role="button" tabindex="0" aria-label="Upload an Excel workbook">
                <i class="ri-file-excel-2-line"></i>
                <div class="fw-semibold mt-2">Drag and drop an Excel workbook here</div>
                <div class="text-muted small mb-3">.xlsx or .xls, up to 50 MB</div>
                <button type="button" class="btn btn-primary btn-sm" id="excel-browse">Browse files</button>
            </div>
            <input type="file" id="excel-file" accept=".xlsx,.xls,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden>
            <div class="progress mt-3" id="excel-progress-wrap" hidden>
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="excel-progress" style="width: 0%">Uploading…</div>
            </div>
        </div>
    </div>

    <div id="excel-workspace" hidden>
        <div class="row g-2 mb-3">
            <x-excel.stat label="Uploaded file" id="excel-filename" />
            <x-excel.stat label="Worksheets" id="excel-sheet-count" />
            <x-excel.stat label="Records in worksheet" id="excel-record-count" />
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-lg-5">
                        <label for="excel-worksheet" class="form-label mb-1">Worksheet</label>
                        <select id="excel-worksheet" class="form-select"></select>
                    </div>
                    <div class="col-lg-5">
                        <label for="excel-search" class="form-label mb-1">Search</label>
                        <input id="excel-search" type="search" class="form-control" placeholder="Search this worksheet" autocomplete="off">
                    </div>
                    <div class="col-lg-2">
                        <label for="excel-per-page" class="form-label mb-1">Rows</label>
                        <select id="excel-per-page" class="form-select">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="excel-table-wrap">
                    <div id="excel-loading" class="excel-loading" hidden>
                        <div class="spinner-border text-primary" role="status" aria-label="Loading"></div>
                    </div>
                    <div id="excel-empty" class="text-center text-muted py-5" hidden>This worksheet has no records.</div>
                    <div class="excel-scroll" id="excel-scroll">
                        <table class="excel-grid">
                            <thead id="excel-head"></thead>
                            <tbody id="excel-body"></tbody>
                        </table>
                    </div>
                </div>

                <div class="excel-pagebar">
                    <div class="text-muted small" id="excel-page-info">No records</div>
                    <div class="btn-group" id="excel-pager" role="group" aria-label="Pagination"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $excelManagerConfig = [
        'importUrl' => route('excel.import'),
        'worksheetUrl' => url('/excel/worksheets/__ID__'),
        'dataUrl' => url('/excel/worksheets/__ID__/data'),
        'exportUrl' => url('/excel/worksheets/__ID__/export'),
        'import' => $importPayload,
    ];
@endphp
<script type="application/json" id="excel-manager-config">@json($excelManagerConfig)</script>
@endsection

@section('script')
<script src="{{ asset('js/excel-data-manager.js') }}?v=20261105"></script>
@endsection
