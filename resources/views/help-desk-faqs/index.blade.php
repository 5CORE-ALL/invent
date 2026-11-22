@extends('layouts.vertical', ['title' => 'Help Desk FAQs, FFP'])

@section('css')
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        /* Search highlight (wraps every case-insensitive match of the query). */
        mark.faq-search-highlight {
            background-color: #fff3a3;
            color: inherit;
            padding: 0 1px;
            border-radius: 2px;
            box-shadow: 0 0 0 1px rgba(180, 150, 0, 0.25);
        }

        /*
         * Match-context block: rendered under the FAQ text whenever the search
         * query matched in a column the grid doesn't show directly
         * (Messages / Action / CA / What / + Action / Variant) or past the
         * 80-char Answer truncation.
         */
        .faq-match-context {
            font-size: 0.78rem;
            line-height: 1.3;
            color: #6c757d;
            border-left: 2px solid #fcd34d;
            padding-left: 6px;
            margin-top: 4px;
            text-align: left !important;
        }
        .faq-match-context-item + .faq-match-context-item { margin-top: 2px; }
        .faq-match-context-label { font-weight: 600; color: #475569; margin-right: 4px; }
        .faq-match-context-snippet { color: #334155; }

        .faq-top-btn {
            min-width: 36px;
            height: 31px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            padding: 0 8px;
            font-size: 1rem;
        }

        /* ── Tabulator skin ─────────────────────────────────────────── */
        #faqTable {
            font-size: 0.75rem;
            border: 1px solid #dee2e6;
        }
        #faqTable .tabulator-header,
        #faqTable .tabulator-header .tabulator-col {
            background-color: #cfe2ff;
        }
        #faqTable .tabulator-header .tabulator-col.tabulator-sortable:hover {
            background-color: #b9d4ff;
        }
        #faqTable .tabulator-header .tabulator-col .tabulator-col-content {
            padding: 4px 6px;
        }
        #faqTable .tabulator-header .tabulator-col .tabulator-col-title {
            white-space: normal;
            text-align: center;
        }
        #faqTable .tabulator-header .tabulator-col .tabulator-header-filter {
            margin-top: 4px;
        }
        #faqTable .tabulator-header .tabulator-col .tabulator-header-filter input {
            font-size: 0.75rem;
            padding: 3px 6px;
            border: 1px solid #adb5bd;
            border-radius: 4px;
            background: #fff;
        }
        #faqTable .tabulator-header .tabulator-col .tabulator-col-title img {
            max-height: 28px;
            width: auto;
            vertical-align: middle;
        }
        #faqTable .tabulator-cell {
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
            padding: 4px 6px;
        }
        #faqTable .tabulator-cell img {
            max-height: 30px;
            width: auto;
            object-fit: contain;
            vertical-align: middle;
        }
        #faqTable .tabulator-cell .btn-link { line-height: 1; }
        #faqTable .tabulator-row.tabulator-selected { background-color: #e7f1ff; }
        #faqTable .tabulator-row .tabulator-cell.faq-what-cell { overflow: visible; }
        #faqTable .tabulator-row:hover { z-index: 1; }
        #faqTable .tabulator-footer { font-size: 0.8rem; }

        .faq-hover-zoom { transition: transform 0.2s ease; cursor: pointer; }
        .faq-hover-zoom:hover { transform: scale(2); }

        /* ── FAQ Details modal (next-gen) ───────────────────────────── */
        .faq-detail-modal .modal-content {
            border: 0;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.28);
            background: #f8fafc;
        }
        .faq-detail-modal .modal-body { padding: 18px 22px 8px; }
        .faq-detail-modal .min-w-0 { min-width: 0; }
        .fd-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            color: #fff;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 55%, #0ea5e9 100%);
        }
        .fd-header-icon {
            width: 46px; height: 46px; border-radius: 14px;
            background: rgba(255, 255, 255, 0.95);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.18);
            flex-shrink: 0;
        }
        .fd-header-icon img { height: 30px; width: auto; object-fit: contain; }
        .fd-eyebrow { font-size: 0.7rem; letter-spacing: 0.12em; text-transform: uppercase; opacity: 0.85; font-weight: 600; }
        .fd-chip {
            display: inline-flex; align-items: center; gap: 6px; max-width: 100%;
            margin-top: 4px; padding: 3px 12px; border-radius: 999px;
            background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(6px);
            font-weight: 700; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.02em;
        }
        .fd-chip .text-muted { color: rgba(255, 255, 255, 0.8) !important; }
        .fd-close {
            border: 0; width: 34px; height: 34px; border-radius: 50%;
            background: rgba(255, 255, 255, 0.18); color: #fff; font-size: 1.3rem;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s ease, transform .15s ease;
        }
        .fd-close:hover { background: rgba(255, 255, 255, 0.32); transform: rotate(90deg); }

        .fd-card, .fd-meta, .fd-pill {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .fd-card { padding: 14px 16px; margin-bottom: 14px; position: relative; }
        .fd-card::before {
            content: ''; position: absolute; left: 0; top: 12px; bottom: 12px; width: 4px;
            border-radius: 0 4px 4px 0; background: #cbd5e1;
        }
        .fd-question { background: linear-gradient(180deg, #fffbeb, #fff); }
        .fd-question::before { background: #f59e0b; }
        .fd-answer { background: linear-gradient(180deg, #ecfdf5, #fff); }
        .fd-answer::before { background: #10b981; }
        .fd-action::before { background: #ef4444; }
        .fd-ca::before { background: #ec4899; }
        .fd-plus::before { background: #f97316; }
        .fd-messages { background: linear-gradient(180deg, #eff6ff, #fff); }
        .fd-messages::before { background: #3b82f6; }

        .fd-label {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
            color: #64748b; margin-bottom: 6px;
        }
        .fd-label i { font-size: 0.95rem; }
        .fd-label img { height: 18px; width: auto; object-fit: contain; }
        .fd-text { color: #0f172a; font-size: 0.92rem; line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
        .fd-text-lg { font-size: 1.05rem; font-weight: 600; }
        .fd-text.text-muted { color: #94a3b8 !important; }

        .fd-grid { display: grid; gap: 12px; margin-bottom: 14px; }
        .fd-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .fd-grid-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .fd-grid .fd-card { margin-bottom: 0; }
        .fd-meta { padding: 12px 14px; }
        .fd-section-title {
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
            color: #94a3b8; margin: 4px 2px 8px;
        }
        .fd-pill { display: flex; align-items: center; gap: 10px; padding: 10px 12px; transition: transform .15s ease, box-shadow .15s ease; }
        .fd-pill img { height: 28px; width: 28px; object-fit: contain; flex-shrink: 0; }
        .fd-pill-body { min-width: 0; }
        .fd-pill-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        .fd-pill-value { font-size: 0.82rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .fd-pill-value a { color: #4f46e5; text-decoration: none; }
        .fd-pill-value a:hover { text-decoration: underline; }
        .fd-pill:not(.fd-empty):hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(79, 70, 229, 0.15); }
        .fd-pill.fd-empty { opacity: 0.5; background: #f1f5f9; box-shadow: none; }
        .fd-meta.fd-empty .fd-text { color: #94a3b8; }
        .fd-item.fd-hide-empty.fd-empty { display: none; }
        .fd-actions:not(:has(.fd-item:not(.fd-empty))) { display: none; }

        .fd-copy {
            border: 0; border-radius: 999px; padding: 4px 12px; font-size: 0.78rem; font-weight: 600;
            background: #dbeafe; color: #1d4ed8; display: inline-flex; align-items: center; gap: 4px;
            transition: background .15s ease;
        }
        .fd-copy:hover { background: #bfdbfe; }
        .fd-footer {
            display: flex; justify-content: flex-end; padding: 12px 22px 16px; background: #f8fafc;
        }
        .fd-footer .btn { border-radius: 10px; padding: 6px 22px; font-weight: 600; }

        @media (max-width: 767px) {
            .fd-grid-3, .fd-grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        /* FAQ Details: full-height drawer docked to the right (same width as before) */
        #faqViewModal .modal-dialog {
            margin: 0 0 0 auto;
            max-width: 800px;
            width: 100%;
            height: 100%;
            min-height: 100%;
            align-items: stretch;
        }
        #faqViewModal .modal-content {
            height: 100%;
            max-height: 100%;
            border-radius: 18px 0 0 18px;
        }
        #faqViewModal.fade .modal-dialog { transform: translateX(100%); transition: transform .3s ease-out; }
        #faqViewModal.show .modal-dialog { transform: none; }
        #faqViewModal .modal-body { flex: 1 1 auto; overflow-y: auto; }
        @media (max-width: 800px) { #faqViewModal .modal-content { border-radius: 0; } }

        /* ── History timeline (inside .faq-detail-modal) ───────────── */
        .fh-title { font-size: 1.15rem; font-weight: 700; line-height: 1.2; }
        .fh-count { font-size: 0.72rem; font-weight: 600; background: rgba(255,255,255,0.22); padding: 2px 10px; border-radius: 999px; margin-left: 6px; vertical-align: middle; }
        .fh-timeline { position: relative; padding: 4px 0 4px 6px; }
        .fh-timeline::before { content: ''; position: absolute; left: 21px; top: 10px; bottom: 10px; width: 2px; background: linear-gradient(#c7d2fe, #e5e7eb); border-radius: 2px; }
        .fh-item { position: relative; display: flex; gap: 14px; margin-bottom: 14px; }
        .fh-item:last-child { margin-bottom: 0; }
        .fh-dot {
            position: relative; z-index: 1; flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1rem;
            box-shadow: 0 0 0 4px #f8fafc; background: #6366f1;
        }
        .fh-added .fh-dot { background: linear-gradient(135deg, #10b981, #059669); }
        .fh-edited .fh-dot { background: linear-gradient(135deg, #f59e0b, #f97316); }
        .fh-card {
            flex: 1 1 auto; min-width: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px;
            padding: 10px 14px; box-shadow: 0 1px 2px rgba(15,23,42,0.04); transition: transform .15s ease, box-shadow .15s ease;
        }
        .fh-card:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(79,70,229,0.12); }
        .fh-row { display: flex; align-items: center; gap: 12px; }
        .fh-avatar {
            flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 1.05rem; color: #4338ca; background: #e0e7ff; overflow: hidden;
            box-shadow: 0 0 0 2px #fff, 0 0 0 3px #c7d2fe;
        }
        .fh-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .fh-who { flex: 1 1 auto; min-width: 0; }
        .fh-name { font-size: 0.92rem; font-weight: 700; color: #0f172a; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-transform: capitalize; }
        .fh-email { font-size: 0.74rem; font-weight: 500; color: #64748b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fh-badge { display: inline-block; margin-top: 2px; padding: 1px 10px; border-radius: 999px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; }
        .fh-added .fh-badge { background: #d1fae5; color: #047857; }
        .fh-edited .fh-badge { background: #fef3c7; color: #b45309; }
        .fh-when { text-align: right; flex-shrink: 0; }
        .fh-date { font-size: 0.82rem; font-weight: 700; color: #0f172a; letter-spacing: 0.02em; }
        .fh-time { font-size: 0.74rem; color: #64748b; margin-top: 2px; }
        .fh-time small { font-weight: 700; }
        .fh-empty { text-align: center; color: #94a3b8; padding: 28px 0; }
        .fh-empty i { font-size: 2.2rem; display: block; margin-bottom: 4px; }

        /* ── Add / Edit FAQ form (next-gen, one-screen layout) ──────── */
        .faq-edit-modal .modal-body { padding: 14px 18px 6px; }
        .faq-edit-modal .modal-dialog { max-width: min(1140px, calc(100vw - 24px)); }
        .fe-section {
            background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 10px 14px 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .fe-section-title {
            display: flex; align-items: center; gap: 6px; margin-bottom: 8px;
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #6366f1;
        }
        .fe-section-title i { font-size: 1rem; }
        .fe-label { display: block; margin-bottom: 2px; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: #64748b; }
        .fe-form .form-control { border-radius: 9px; border-color: #dbe0ea; font-size: 0.82rem; }
        .fe-form .form-control:focus { border-color: #818cf8; box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15); }
        .fe-form textarea.form-control { resize: vertical; min-height: 0; }

        .fe-chip-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 5px; margin-top: 6px; }
        .fe-chip {
            position: relative; display: flex; align-items: center; justify-content: center; margin: 0; padding: 4px 6px; cursor: pointer;
            border: 1px solid #dbe0ea; border-radius: 999px; background: #f8fafc; color: #475569;
            font-size: 0.74rem; font-weight: 600; text-align: center; user-select: none;
            transition: background .12s ease, border-color .12s ease, color .12s ease, transform .12s ease;
        }
        .fe-chip:hover { border-color: #a5b4fc; background: #eef2ff; }
        .fe-chip input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .fe-chip span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fe-chip:has(input:checked) {
            background: linear-gradient(135deg, #6366f1, #8b5cf6); border-color: transparent; color: #fff;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }
        .fe-chip:has(input:disabled) { opacity: 0.45; cursor: not-allowed; }
        .fe-chip-all { width: 100%; }
        .fe-chip-all:has(input:checked) { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }
        .fe-save { border-radius: 10px; padding: 6px 22px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        @media (max-width: 575px) { .fe-chip-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }

        /* ── Dept filter panel ──────────────────────────────────────── */
        .faq-dept-panel {
            width: min(760px, calc(100vw - 48px));
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
        }
        .faq-dept-toolbar { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; }
        .faq-dept-toolbar input { flex: 1 1 auto; }
        .faq-dept-hint { font-size: 0.72rem; margin-bottom: 8px; }
        .faq-dept-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 8px; }
        .faq-dept-card-title {
            display: flex; align-items: center; gap: 8px; margin: 0 0 6px; padding: 2px 4px 6px;
            border-bottom: 1px solid #dee2e6; cursor: pointer; user-select: none;
            font-size: 0.74rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: #495057;
        }
        .faq-dept-list {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 2px 10px; max-height: 340px; overflow-y: auto;
        }
        .faq-dept-item {
            display: flex; align-items: center; gap: 8px; margin: 0; padding: 4px 6px;
            border-radius: 6px; cursor: pointer; font-size: 0.82rem; white-space: nowrap;
        }
        .faq-dept-item:hover { background: #eef5ff; }
        .faq-dept-item input, .faq-dept-card-title input { margin: 0; flex-shrink: 0; }
        /* Standard badge grid (same look as the summary badges on the marketplace pages) */
        .faq-dept-strip { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 0.4rem; margin-bottom: 10px; }
        .faq-dept-chip {
            display: inline-flex !important; align-items: center; justify-content: center; text-align: center; gap: 0.3rem;
            min-height: 42px; box-sizing: border-box; padding: 0.4rem 0.45rem !important; border-radius: 0.375rem;
            font-size: 0.85rem !important; font-weight: 700; line-height: 1.2; color: #fff; cursor: pointer;
            background-color: #0d6efd; border: 0; transition: filter .12s ease, transform .12s ease;
        }
        .faq-dept-chip:hover { filter: brightness(1.1); }
        .faq-dept-chip::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%; background: #9ca3af; flex-shrink: 0;
        }
        .faq-dept-chip b { font-weight: 700; margin: 0; padding: 0; background: none; color: inherit; }
        .faq-dept-chip.is-zero { background-color: #6c757d; }
        .faq-dept-chip.is-active { background-color: #6610f2; box-shadow: 0 0 0 2px rgba(102, 16, 242, .25); }
        .faq-dept-chip.is-active::before { background: #22c55e; }
        .faq-dept-chip-all, .faq-dept-chip-all.is-zero { background-color: #4c1d95; }
        .faq-dept-item .faq-dept-name { flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
        .faq-dept-count {
            flex-shrink: 0; min-width: 24px; padding: 0 7px; border-radius: 999px; text-align: center;
            font-size: 0.68rem; font-weight: 700; line-height: 18px; background: #e0e7ff; color: #4338ca;
        }
        .faq-dept-count.is-zero { background: #f1f5f9; color: #94a3b8; }
        .faq-dept-count-all { background: #dbeafe; color: #1d4ed8; }
        .faq-dept-item.is-checked .faq-dept-count { background: #4f46e5; color: #fff; }
        .faq-dept-item.is-checked { background: #dbe7ff; box-shadow: inset 0 0 0 1px #6ea8fe; }
        .faq-dept-none { grid-column: 1 / -1; padding: 6px; font-size: 0.78rem; }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Help Desk FAQs, FFP', 'sub_title' => 'Manage FAQs shown in the 5Core Help Desk'])

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            {{-- Search across all data + three column filters, all in one row --}}
            <div class="d-flex flex-nowrap align-items-center gap-2 mb-2 w-100">
                <div class="input-group input-group-sm" style="flex: 1.6 1 0; min-width: 0;">
                    <span class="input-group-text px-2"><img src="{{ asset('images/magnifier-icon.png') }}" alt="Search" style="height: 20px; width: auto; object-fit: contain;"></span>
                    <input type="text" id="faqFilterAll" class="form-control faq-col-filter" data-field="_all" value="{{ $searchQuery ?? '' }}"
                        placeholder="Search all data..." title="Searches every column: group, FAQ, answer, dept, variant, what, links, action, CA, messages" autocomplete="off">
                    <button type="button" id="faqFilterAllClear" class="btn btn-light border px-2" title="Clear search">&times;</button>
                </div>
                <input type="text" id="faqFilterGroup" class="form-control form-control-sm faq-col-filter" style="flex: 1 1 0; min-width: 0;" data-field="group_name" placeholder="Search Group...">
                <input type="text" id="faqFilterFaq" class="form-control form-control-sm faq-col-filter" style="flex: 1 1 0; min-width: 0;" data-field="faq" placeholder="Search FAQ/FFP/Issues...">
                <input type="text" id="faqFilterAnswers" class="form-control form-control-sm faq-col-filter" style="flex: 1 1 0; min-width: 0;" data-field="answers" placeholder="Search Answer/Solutions...">
            </div>
            {{-- Department counts as real .badge pills (Auto Task Events reads these as its "Badges") --}}
            <div class="faq-dept-strip" id="faqDeptStrip" title="FAQs per department (click to filter)">
                <span class="badge fs-6 p-2 faq-dept-chip faq-dept-chip-all" id="faqDeptBadgeAll" role="button">All FAQs <b id="faqDeptStripTotal">0</b></span>
                @foreach ($departments as $dept)
                    <span class="badge fs-6 p-2 faq-dept-chip" id="faqDeptBadge_{{ $dept->id }}" data-dept-id="{{ $dept->id }}" role="button">{{ $dept->name }} <b data-dept-count="{{ $dept->id }}">0</b></span>
                @endforeach
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 w-100">
                {{-- Dept filter: wide panel with quick search + multi-column list --}}
                <div class="dropdown" style="flex-shrink: 0;">
                    <button class="btn btn-light border dropdown-toggle text-start d-flex justify-content-between align-items-center faq-top-btn"
                        type="button" id="faqDeptFilterBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Filter by department">
                        <span>Dept: <span id="faqDeptFilterLabel">All</span></span>
                    </button>
                    <div class="dropdown-menu faq-dept-panel p-3" id="faqDeptPanel">
                        <div class="faq-dept-toolbar">
                            <input type="search" id="faqDeptSearch" class="form-control form-control-sm" placeholder="Quick search departments..." autocomplete="off">
                            <button type="button" id="faqDeptClear" class="btn btn-sm btn-outline-secondary">Clear</button>
                            <button type="button" id="faqDeptClose" class="btn btn-sm btn-success">Save &amp; Close</button>
                        </div>
                        <div class="faq-dept-hint text-muted"><span id="faqDeptSummary">Showing all departments.</span> Tick one or more departments to filter the table.</div>
                        <div class="faq-dept-card">
                            <label class="faq-dept-card-title">
                                <input class="form-check-input" type="checkbox" id="faqDeptFilterAll" checked>
                                <span>All departments</span>
                                <small id="faqDeptCountSmall" class="ms-auto text-muted"></small>
                            </label>
                            <div class="faq-dept-list">
                        @foreach ($departments as $dept)
                                    <label class="faq-dept-item" data-name="{{ strtolower($dept->name) }}" for="faqDeptFilter_{{ $dept->id }}">
                                <input class="form-check-input faq-dept-filter" type="checkbox" value="{{ $dept->id }}" id="faqDeptFilter_{{ $dept->id }}">
                                        <span class="faq-dept-name">{{ $dept->name }}</span>
                                    </label>
                        @endforeach
                                <div class="faq-dept-none text-muted" style="display:none;">No department found</div>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Count badge --}}
                <span id="faqCountBadge" class="btn btn-soft-primary btn-sm faq-top-btn" style="cursor:default; flex-shrink:0;">Count: 0</span>
                <span id="faqSelectedBadge" class="btn btn-primary btn-sm faq-top-btn" style="display:none; cursor:default; flex-shrink:0;">0 selected</span>
                {{-- Column manager (shared Tabulator "Columns" panel) --}}
                <div class="dropdown" style="flex-shrink:0;">
                    <button class="btn btn-soft-secondary btn-sm faq-top-btn" type="button" id="columnVisibilityDropdown"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Columns">
                        <i class="ri-layout-column-line"></i>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu">
                        <!-- Populated by partials/tabulator-column-manager -->
                    </ul>
                </div>
                <button type="button" id="faqExportBtn" class="btn btn-soft-success btn-sm faq-top-btn" style="flex-shrink:0;" title="Export CSV (current filter)">
                    <i class="ri-file-download-line"></i>
                </button>
                {{-- Action buttons --}}
                <button type="button" class="btn btn-success btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqSubmitModal" title="Submit a new FAQ/FFP/Issue">
                    <i class="ri-send-plane-line"></i>
                </button>
                @if ($canEditFaq)
                    <button type="button" class="btn btn-soft-primary btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqBulkModal" title="Bulk Import">
                        <i class="ri-upload-2-line"></i>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqCreateModal" title="Add FAQ">
                        <i class="ri-add-line"></i>
                    </button>
                @endif
                <a href="{{ route('help-desk-faqs.archived') }}" class="btn btn-soft-secondary btn-sm faq-top-btn d-flex align-items-center justify-content-center" title="Archived FAQs" style="flex-shrink:0;">
                    <i class="ri-archive-line"></i>
                </a>
            </div>

            <div id="faqTable"></div>
        </div>
    </div>

    {{-- Create Modal --}}
    <div class="modal fade faq-detail-modal faq-edit-modal" id="faqCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <form action="{{ route('help-desk-faqs.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="fd-header">
                    <div class="fd-header-icon"><i class="ri-add-circle-line" style="font-size:1.6rem;color:#4f46e5;"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fd-eyebrow">New entry</div>
                        <div class="fh-title">Add FAQ</div>
                    </div>
                    <button type="button" class="fd-close" data-bs-dismiss="modal" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    @include('help-desk-faqs.partials.form', ['departments' => $departments])
                </div>
                <div class="fd-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fe-save"><i class="ri-save-line"></i> Save FAQ</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Submit (simple) Modal --}}
    <div class="modal fade" id="faqSubmitModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <form action="{{ route('help-desk-faqs.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Submit New FAQ/FFP/Issue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Group</label>
                            <input type="text" name="group_name" class="form-control" placeholder="Enter the group">
                        </div>
                        <div class="col-12">
                            <label class="form-label">FAQ/FFP/Issues <span class="text-danger">*</span></label>
                            <textarea name="faq" class="form-control" rows="3" placeholder="Enter the question / problem / issue" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label d-block">Department</label>
                            <div class="border rounded p-2 dept-checkboxes">
                                <div class="form-check mb-1">
                                    <input class="form-check-input dept-all" type="checkbox" name="dept[]" value="all" id="dept_submit_all">
                                    <label class="form-check-label fw-semibold" for="dept_submit_all">All</label>
                                </div>
                                <hr class="my-2">
                                <div class="row g-1">
                                    @foreach ($departments as $dept)
                                        <div class="col-6 col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input dept-one" type="checkbox" name="dept[]" value="{{ $dept->id }}" id="dept_submit_{{ $dept->id }}">
                                                <label class="form-check-label" for="dept_submit_{{ $dept->id }}">{{ $dept->name }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <small class="text-muted">Tick one or more departments. Choose "All" to show to everyone.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Submit</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div class="modal fade faq-detail-modal faq-edit-modal" id="faqEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <form action="{{ route('help-desk-faqs.index') }}" method="POST" class="modal-content" id="faqEditForm">
                @csrf
                @method('PUT')
                <div class="fd-header">
                    <div class="fd-header-icon"><i class="ri-edit-2-line" style="font-size:1.6rem;color:#4f46e5;"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fd-eyebrow">Editing</div>
                        <div class="fh-title">Edit FAQ</div>
                    </div>
                    <button type="button" class="fd-close" data-bs-dismiss="modal" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    @include('help-desk-faqs.partials.form', ['departments' => $departments, 'edit' => true])
                </div>
                <div class="fd-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fe-save"><i class="ri-save-line"></i> Update FAQ</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Bulk Import Modal --}}
    <div class="modal fade" id="faqBulkModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('help-desk-faqs.bulk') }}" method="POST" class="modal-content" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Import FAQs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="file" class="form-control" accept=".csv,.xlsx,.xls,text/csv" required>
                    </div>
                    <div class="alert alert-info mb-3">
                        <p class="mb-1"><strong>Available department options:</strong></p>
                        <div class="d-flex flex-wrap gap-1">
                            <span class="badge bg-success">all</span>
                            @foreach ($departments as $dept)
                                <span class="badge bg-soft-primary text-primary">{{ $dept->name }}</span>
                            @endforeach
                        </div>
                    </div>
                    <a href="{{ route('help-desk-faqs.sample') }}" class="btn btn-sm btn-soft-secondary">
                        <i class="ri-download-2-line"></i> Download template (Excel, with dropdowns)
                    </a>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Bulk Edit Modal --}}
    <div class="modal fade" id="faqBulkEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <form action="{{ route('help-desk-faqs.bulk-update') }}" method="POST" class="modal-content" id="faqBulkEditForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Edit <span id="faqBulkEditModalCount" class="text-muted"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="faqBulkEditIds"></div>
                    <p class="text-muted">Tick a field to update it for all selected rows. Unticked fields are left unchanged.</p>

                    <div class="border rounded p-2 mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="group_name" id="bulk_apply_group_name">
                            <label class="form-check-label fw-semibold" for="bulk_apply_group_name">Group</label>
                        </div>
                        <input type="text" name="group_name" class="form-control bulk-field" data-field="group_name" placeholder="New group">
                    </div>

                    <div class="border rounded p-2 mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="answers" id="bulk_apply_answers">
                            <label class="form-check-label fw-semibold" for="bulk_apply_answers">Answers</label>
                        </div>
                        <textarea name="answers" class="form-control bulk-field" data-field="answers" rows="2" placeholder="New answer"></textarea>
                    </div>

                    <div class="border rounded p-2 mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="dept" id="bulk_apply_dept">
                            <label class="form-check-label fw-semibold" for="bulk_apply_dept">DEPT</label>
                        </div>
                        <div class="bulk-field-wrap" data-field="dept">
                            <div class="form-check">
                                <input class="form-check-input dept-all" type="checkbox" name="dept[]" value="all" id="dept_bulk_all">
                                <label class="form-check-label fw-semibold" for="dept_bulk_all">All</label>
                            </div>
                            <hr class="my-2">
                            <div class="row g-1">
                                @foreach ($departments as $dept)
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input dept-one" type="checkbox" name="dept[]" value="{{ $dept->id }}" id="dept_bulk_{{ $dept->id }}">
                                            <label class="form-check-label" for="dept_bulk_{{ $dept->id }}">{{ $dept->name }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="type_variant" id="bulk_apply_type_variant">
                                <label class="form-check-label fw-semibold" for="bulk_apply_type_variant">Type/Variant</label>
                            </div>
                            <textarea name="type_variant" class="form-control bulk-field" data-field="type_variant" rows="2" placeholder="New type / variant"></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="what" id="bulk_apply_what">
                                <label class="form-check-label fw-semibold" for="bulk_apply_what">What</label>
                            </div>
                            <textarea name="what" class="form-control bulk-field" data-field="what" rows="2" placeholder="New what"></textarea>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="link" id="bulk_apply_link">
                                <label class="form-check-label fw-semibold" for="bulk_apply_link">Link</label>
                            </div>
                            <input type="text" name="link" class="form-control bulk-field" data-field="link" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="link2" id="bulk_apply_link2">
                                <label class="form-check-label fw-semibold" for="bulk_apply_link2">Link 2</label>
                            </div>
                            <input type="text" name="link2" class="form-control bulk-field" data-field="link2" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="sop" id="bulk_apply_sop">
                                <label class="form-check-label fw-semibold" for="bulk_apply_sop">SOP</label>
                            </div>
                            <input type="text" name="sop" class="form-control bulk-field" data-field="sop" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="video" id="bulk_apply_video">
                                <label class="form-check-label fw-semibold" for="bulk_apply_video">Video</label>
                            </div>
                            <input type="text" name="video" class="form-control bulk-field" data-field="video" placeholder="https://...">
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="action" id="bulk_apply_action">
                                <label class="form-check-label fw-semibold" for="bulk_apply_action">Action</label>
                            </div>
                            <textarea name="action" class="form-control bulk-field" data-field="action" rows="2" placeholder="New action"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="ca" id="bulk_apply_ca">
                                <label class="form-check-label fw-semibold" for="bulk_apply_ca">CA (Corrective Action)</label>
                            </div>
                            <textarea name="ca" class="form-control bulk-field" data-field="ca" rows="2" placeholder="New corrective action"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="plus_action" id="bulk_apply_plus_action">
                                <label class="form-check-label fw-semibold" for="bulk_apply_plus_action">+ Action</label>
                            </div>
                            <textarea name="plus_action" class="form-control bulk-field" data-field="plus_action" rows="2" placeholder="New additional action"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="messages" id="bulk_apply_messages">
                                <label class="form-check-label fw-semibold" for="bulk_apply_messages">Messages</label>
                            </div>
                            <textarea name="messages" class="form-control bulk-field" data-field="messages" rows="2" placeholder="New message"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply to selected</button>
                </div>
            </form>
        </div>
    </div>

    {{-- FAQ View (read-only) Modal --}}
    <div class="modal fade faq-detail-modal" id="faqViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="fd-header">
                    <div class="fd-header-icon"><img src="{{ asset('images/magnifier-icon.png') }}" alt=""></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fd-eyebrow">FAQ Details</div>
                        <span class="fd-chip"><i class="ri-folder-3-line"></i><span id="view_group"></span></span>
                </div>
                    <button type="button" class="fd-close" data-bs-dismiss="modal" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>

                <div class="modal-body">
                    {{-- Question --}}
                    <section class="fd-card fd-question">
                        <div class="fd-label"><i class="ri-question-line"></i> Question</div>
                        <div id="view_faq" class="fd-text fd-text-lg"></div>
                    </section>

                    {{-- Answer --}}
                    <section class="fd-card fd-answer">
                        <div class="fd-label"><i class="ri-checkbox-circle-line"></i> Answer</div>
                        <div id="view_answers" class="fd-text"></div>
                    </section>

                    {{-- Meta --}}
                    <div class="fd-grid fd-grid-3">
                        <div class="fd-meta fd-item">
                            <div class="fd-label"><i class="ri-building-line"></i> Dept</div>
                            <div id="view_dept" class="fd-text"></div>
                    </div>
                        <div class="fd-meta fd-item">
                            <div class="fd-label"><img src="{{ asset('images/variant-icon.png') }}" alt=""> Type / Variant</div>
                            <div id="view_type_variant" class="fd-text"></div>
                    </div>
                        <div class="fd-meta fd-item">
                            <div class="fd-label"><img src="{{ asset('images/what-icon.png') }}" alt=""> What</div>
                            <div id="view_what" class="fd-text"></div>
                    </div>
                    </div>

                    {{-- Resources --}}
                    <div class="fd-section-title">Resources</div>
                    <div class="fd-grid fd-grid-4">
                        <div class="fd-pill fd-item">
                            <img src="{{ asset('images/link-icon.png') }}" alt="">
                            <div class="fd-pill-body"><div class="fd-pill-label">Link</div><div id="view_link" class="fd-pill-value"></div></div>
                        </div>
                        <div class="fd-pill fd-item">
                            <img src="{{ asset('images/link-icon.png') }}" alt="">
                            <div class="fd-pill-body"><div class="fd-pill-label">Link 2</div><div id="view_link2" class="fd-pill-value"></div></div>
                        </div>
                        <div class="fd-pill fd-item">
                            <img src="{{ asset('assets/images/task-sop-icon.png') }}" alt="">
                            <div class="fd-pill-body"><div class="fd-pill-label">SOP</div><div id="view_sop" class="fd-pill-value"></div></div>
                        </div>
                        <div class="fd-pill fd-item">
                            <img src="{{ asset('assets/images/task-video-icon.png') }}" alt="">
                            <div class="fd-pill-body"><div class="fd-pill-label">Video</div><div id="view_video" class="fd-pill-value"></div></div>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="fd-grid fd-grid-3 fd-actions">
                        <section class="fd-card fd-action fd-item fd-hide-empty">
                            <div class="fd-label"><img src="{{ asset('images/action-comic.png') }}" alt=""> Action</div>
                            <div id="view_action" class="fd-text"></div>
                        </section>
                        <section class="fd-card fd-ca fd-item fd-hide-empty">
                            <div class="fd-label"><img src="{{ asset('images/action-icon.png') }}" alt=""> Corrective Action</div>
                            <div id="view_ca" class="fd-text"></div>
                        </section>
                        <section class="fd-card fd-plus fd-item fd-hide-empty">
                            <div class="fd-label"><img src="{{ asset('images/action-comic.png') }}" alt=""> + Action</div>
                            <div id="view_plus_action" class="fd-text"></div>
                        </section>
                    </div>

                    {{-- Messages --}}
                    <section class="fd-card fd-messages fd-item fd-hide-empty">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fd-label mb-0"><img src="{{ asset('images/message-icon.png') }}" alt=""> Message</div>
                            <button type="button" id="view_messages_copy" class="fd-copy" title="Copy message" style="display:none;">
                                <i class="ri-file-copy-line"></i> Copy
                            </button>
                </div>
                        <div id="view_messages" class="fd-text"></div>
                    </section>
                </div>

                <div class="fd-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- CA (Corrective Action) View Modal --}}
    <div class="modal fade" id="faqCaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><img src="{{ asset('images/action-icon.png') }}" alt="" style="height: 24px; width: 24px; object-fit: contain; vertical-align: text-bottom;"> CA (Corrective Action)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="faqCaBody" class="mb-0" style="white-space: pre-wrap; word-break: break-word;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Action (text) View Modal --}}
    <div class="modal fade" id="faqActionTextModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><img src="{{ asset('images/action-comic.png') }}" alt="" style="height: 26px; width: auto; object-fit: contain; vertical-align: text-bottom;"> Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="faqActionTextBody" class="mb-0" style="white-space: pre-wrap; word-break: break-word;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Corrective Action View Modal --}}
    <div class="modal fade" id="faqActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><img src="{{ asset('images/action-comic.png') }}" alt="" style="height: 24px; width: auto; object-fit: contain; vertical-align: text-bottom;"> + Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="faqActionText" class="mb-0" style="white-space: pre-wrap; word-break: break-word;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Guru History Modal --}}
    <div class="modal fade faq-detail-modal" id="faqGuruHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="fd-header">
                    <div class="fd-header-icon"><i class="ri-history-line" style="font-size:1.6rem;color:#4f46e5;"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fd-eyebrow">Activity</div>
                        <div class="fh-title">Add / Edit History <span class="fh-count" id="faqGuruHistoryCount"></span></div>
                    </div>
                    <button type="button" class="fd-close" data-bs-dismiss="modal" aria-label="Close"><i class="ri-close-line"></i></button>
                </div>
                <div class="modal-body">
                    <div id="faqGuruHistoryList" class="fh-timeline"></div>
                </div>
                <div class="fd-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Message View Modal --}}
    <div class="modal fade" id="faqMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><img src="{{ asset('images/message-icon.png') }}" alt="" style="height: 22px; width: 22px; object-fit: contain; vertical-align: text-bottom;"> Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p id="faqMessageText" class="mb-0" style="white-space: pre-wrap; word-break: break-word;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        (function() {
            // ───────────────────────── config ─────────────────────────
            var CAN_EDIT = @json((bool) $canEditFaq);
            var CAN_DELETE = @json((bool) $canDeleteFaq);
            var CSRF = @json(csrf_token());
            var BASE_URL = @json(url('help-desk-faqs'));
            var DATA_URL = @json(route('help-desk-faqs.data'));
            var IMG = {
                magnifier: @json(asset('images/magnifier-icon.png')),
                variant: @json(asset('images/variant-icon.png')),
                what: @json(asset('images/what-icon.png')),
                link: @json(asset('images/link-icon.png')),
                sop: @json(asset('assets/images/task-sop-icon.png')),
                video: @json(asset('assets/images/task-video-icon.png')),
                action: @json(asset('images/action-comic.png')),
                ca: @json(asset('images/action-icon.png')),
                message: @json(asset('images/message-icon.png'))
            };
            var DASH = '<span class="text-muted">&mdash;</span>';

            // ───────────────────────── helpers ─────────────────────────
            var searchQuery = '';
            var colQueries = { _all: '', group_name: '', faq: '', answers: '' }; // per-column header filters

            function esc(s) {
                var d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML.replace(/"/g, '&quot;');
            }
            function escRe(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
            function truncate(s, n) {
                s = s == null ? '' : String(s);
                return s.length > n ? s.substring(0, n - 3) + '...' : s;
            }
            function hasText(v) { return v != null && String(v).trim() !== ''; }

            // Escape `text` and wrap each match of the active search query in <mark>.
            function hl(text, field) {
                text = text == null ? '' : String(text);
                var terms = [];
                if (searchQuery) terms.push(searchQuery);
                if (field && colQueries[field]) terms.push(colQueries[field]);
                if (!terms.length) return esc(text);
                terms.sort(function(a, b) { return b.length - a.length; });
                var re = new RegExp(terms.map(escRe).join('|'), 'gi');
                var out = '', last = 0, m;
                while ((m = re.exec(text)) !== null) {
                    out += esc(text.slice(last, m.index)) + '<mark class="faq-search-highlight">' + esc(m[0]) + '</mark>';
                    last = m.index + m[0].length;
                    if (m[0].length === 0) re.lastIndex++;
                }
                return out + esc(text.slice(last));
            }

            var SNIPPET_LABELS = {
                type_variant: 'Variant/Type', what: 'What', action: 'Action', ca: 'Corrective Action',
                plus_action: '+ Action', messages: 'Messages', answers: 'Answer'
            };
            // "Match in …" snippets for matches living in columns the grid doesn't show.
            function matchSnippets(d) {
                if (!searchQuery) return [];
                var q = searchQuery.toLowerCase();
                var cols = ['type_variant', 'what', 'action', 'ca', 'plus_action', 'messages'];
                var ans = d.answers || '';
                if (ans.toLowerCase().indexOf(q) !== -1 && truncate(ans, 80).toLowerCase().indexOf(q) === -1) {
                    cols.push('answers');
                }
                var out = [];
                cols.forEach(function(col) {
                    var v = d[col] || '';
                    if (!v) return;
                    var pos = v.toLowerCase().indexOf(q);
                    if (pos === -1) return;
                    var win = 40;
                    var start = Math.max(0, pos - win);
                    var end = Math.min(v.length, pos + q.length + win);
                    var snip = v.substring(start, end);
                    if (start > 0) snip = '\u2026' + snip.replace(/^\s+/, '');
                    if (end < v.length) snip = snip.replace(/\s+$/, '') + '\u2026';
                    out.push({ label: SNIPPET_LABELS[col] || col, snippet: snip });
                });
                return out;
            }

            function iconBtn(cls, src, alt, title, h) {
                return '<button type="button" class="btn btn-link p-0 ' + cls + '" title="' + esc(title) + '">' +
                    '<img src="' + src + '" alt="' + esc(alt) + '" style="height:' + h + 'px;width:auto;object-fit:contain;"></button>';
            }
            function iconLink(href, src, alt, title, h) {
                return '<a href="' + esc(href) + '" target="_blank" rel="noopener" title="' + esc(title) + '">' +
                    '<img src="' + src + '" alt="' + esc(alt) + '" style="height:' + h + 'px;width:' + h + 'px;object-fit:contain;"></a>';
            }
            function headerImg(src, alt, h) {
                return '<img src="' + src + '" alt="' + esc(alt) + '" style="height:' + h + 'px;width:auto;object-fit:contain;">';
            }

            // ───────────────────────── modals (create / edit / dept) ─────────────────────────
            var editModalEl = document.getElementById('faqEditModal');
            var editForm = document.getElementById('faqEditForm');

            function syncDeptAll(scope) {
                var box = scope.querySelector('.dept-checkboxes');
                if (!box) return;
                var allCb = box.querySelector('.dept-all');
                var ones = box.querySelectorAll('.dept-one');
                if (allCb && allCb.checked) {
                    ones.forEach(function(cb) { cb.checked = false; cb.disabled = true; });
                } else {
                    ones.forEach(function(cb) { cb.disabled = false; });
                }
            }
            document.querySelectorAll('.dept-checkboxes').forEach(function(box) {
                var scope = box.closest('form');
                var allCb = box.querySelector('.dept-all');
                if (allCb) allCb.addEventListener('change', function() { syncDeptAll(scope); });
                syncDeptAll(scope);
            });

            function openEdit(d) {
                editForm.setAttribute('action', BASE_URL + '/' + d.id);
                editForm.querySelector('[name="group_name"]').value = d.group_name || '';
                editForm.querySelector('[name="faq"]').value = d.faq || '';
                editForm.querySelector('[name="answers"]').value = d.answers || '';
                editForm.querySelector('[name="type_variant"]').value = d.type_variant || '';
                editForm.querySelector('[name="what"]').value = d.what || '';
                editForm.querySelector('[name="link"]').value = d.link || '';
                editForm.querySelector('[name="link2"]').value = d.link2 || '';
                editForm.querySelector('[name="sop"]').value = d.sop || '';
                editForm.querySelector('[name="video"]').value = d.video || '';
                editForm.querySelector('[name="action"]').value = d.action || '';
                editForm.querySelector('[name="ca"]').value = d.ca || '';
                editForm.querySelector('[name="plus_action"]').value = d.plus_action || '';
                editForm.querySelector('[name="messages"]').value = d.messages || '';
                var dept = (d.dept || []).map(String);
                editForm.querySelectorAll('input[name="dept[]"]').forEach(function(cb) {
                    cb.checked = dept.indexOf(String(cb.value)) !== -1;
                });
                syncDeptAll(editForm);
                new bootstrap.Modal(editModalEl).show();
            }

            function archiveFaq(d) {
                if (!confirm('Archive this FAQ? You can restore it later from Archived.')) return;
                var f = document.createElement('form');
                f.method = 'POST';
                f.action = BASE_URL + '/' + d.id;
                f.style.display = 'none';
                [['_token', CSRF], ['_method', 'DELETE']].forEach(function(p) {
                    var i = document.createElement('input');
                    i.type = 'hidden'; i.name = p[0]; i.value = p[1];
                    f.appendChild(i);
                });
                document.body.appendChild(f);
                f.submit();
            }

            // ───────────────────────── view modal ─────────────────────────
            var viewModalEl = document.getElementById('faqViewModal');
            function setViewText(id, value) {
                var el = document.getElementById(id);
                if (!el) return;
                if (hasText(value)) { el.textContent = value; el.classList.remove('text-muted'); }
                else { el.textContent = '\u2014'; el.classList.add('text-muted'); }
                markEmpty(el, !hasText(value));
            }
            // Toggle the empty state on the surrounding detail card / pill.
            function markEmpty(el, empty) {
                var item = el.closest('.fd-item');
                if (item) item.classList.toggle('fd-empty', empty);
            }
            function setViewLink(id, value, label) {
                var el = document.getElementById(id);
                if (!el) return;
                if (hasText(value)) {
                    el.innerHTML = '';
                    var a = document.createElement('a');
                    a.href = value; a.target = '_blank'; a.rel = 'noopener'; a.textContent = label || value;
                    el.appendChild(a);
                    el.classList.remove('text-muted');
                    markEmpty(el, false);
                } else {
                    el.textContent = '\u2014';
                    el.classList.add('text-muted');
                    markEmpty(el, true);
                }
            }
            function deptLabelFor(d) { return d.dept_label || '\u2014'; }

            function openView(d) {
                setViewText('view_group', d.group_name);
                setViewText('view_faq', d.faq);
                setViewText('view_answers', d.answers);
                setViewText('view_dept', deptLabelFor(d));
                setViewText('view_type_variant', d.type_variant);
                setViewText('view_what', d.what);
                setViewLink('view_link', d.link, 'Open link 1');
                setViewLink('view_link2', d.link2, 'Open link 2');
                setViewLink('view_sop', d.sop, 'Open SOP');
                setViewLink('view_video', d.video, 'Open video');
                setViewText('view_action', d.action);
                setViewText('view_ca', d.ca);
                setViewText('view_plus_action', d.plus_action);
                setViewText('view_messages', d.messages);
                    var copyBtn = document.getElementById('view_messages_copy');
                    if (copyBtn) {
                    if (hasText(d.messages)) { copyBtn.style.display = 'inline-flex'; copyBtn.setAttribute('data-copy', d.messages); }
                    else { copyBtn.style.display = 'none'; copyBtn.removeAttribute('data-copy'); }
                }
                new bootstrap.Modal(viewModalEl).show();
            }

            var msgCopyBtn = document.getElementById('view_messages_copy');
            if (msgCopyBtn) {
                msgCopyBtn.addEventListener('click', function() {
                    var text = this.getAttribute('data-copy') || '';
                    var icon = this.querySelector('i');
                    var done = function() {
                        if (!icon) return;
                            icon.classList.remove('ri-file-copy-line');
                            icon.classList.add('ri-check-line', 'text-success');
                            setTimeout(function() {
                                icon.classList.remove('ri-check-line', 'text-success');
                                icon.classList.add('ri-file-copy-line');
                            }, 1500);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(done).catch(function() {});
                    } else {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); done(); } catch (e) {}
                        document.body.removeChild(ta);
                    }
                });
            }

            function showText(modalId, bodyId, text) {
                var body = document.getElementById(bodyId);
                if (body) body.textContent = text || '';
                new bootstrap.Modal(document.getElementById(modalId)).show();
            }

            function openHistory(d) {
                var list = document.getElementById('faqGuruHistoryList');
                var hist = (d.history || []).slice().reverse(); // newest first
                if (!list) return;
                var countEl = document.getElementById('faqGuruHistoryCount');
                if (countEl) countEl.textContent = hist.length ? hist.length + (hist.length === 1 ? ' entry' : ' entries') : '';
                    if (!hist.length) {
                    list.innerHTML = '<div class="fh-empty"><i class="ri-inbox-line"></i><div>No history recorded.</div></div>';
                    } else {
                    list.innerHTML = hist.map(function(h) {
                        var email = h.email || '\u2014';
                        var name = h.name || (h.email ? String(h.email).split('@')[0] : '\u2014');
                        var action = String(h.action || '');
                        var kind = /^added/i.test(action) ? 'added' : 'edited';
                        var when = String(h.at_display || h.at || '');
                        return '<div class="fh-item fh-' + kind + '">' +
                            '<div class="fh-dot"><i class="' + (kind === 'added' ? 'ri-add-line' : 'ri-pencil-line') + '"></i></div>' +
                            '<div class="fh-card">' +
                                '<div class="fh-row">' +
                                    '<span class="fh-avatar">' + (h.avatar
                                        ? '<img src="' + esc(h.avatar) + '" alt="" style="object-position:' + (h.avatar_x || 50) + '% ' + (h.avatar_y || 50) + '%;transform:scale(' + ((h.avatar_zoom || 100) / 100) + ');" onerror="this.parentNode.textContent=\'' + esc(name.charAt(0).toUpperCase()) + '\';">'
                                        : esc(name.charAt(0).toUpperCase())) + '</span>' +
                                    '<div class="fh-who"><div class="fh-name">' + esc(name) + '</div><div class="fh-email">' + esc(email) + '</div>' +
                                    '<span class="fh-badge">' + esc(action || '\u2014') + '</span></div>' +
                                    '<div class="fh-when"><div class="fh-date">' + esc(when) + '</div></div>' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                        }).join('');
                    }
                new bootstrap.Modal(document.getElementById('faqGuruHistoryModal')).show();
            }

            // ───────────────────────── Tabulator ─────────────────────────
            var rowData = function(cell) { return cell.getRow().getData(); };
            var centerCol = { hozAlign: 'center', vertAlign: 'middle', headerHozAlign: 'center' };
            function col(def) { return Object.assign({}, centerCol, def); }

            function iconColumn(field, label, headerHtml, formatter, extra) {
                return col(Object.assign({
                    title: label, label: label, field: field,
                    titleFormatter: function() { return headerHtml; },
                    headerTooltip: label,
                    width: 64, minWidth: 54,
                    formatter: formatter
                }, extra || {}));
            }

            // Case-insensitive "contains" match used by the three header search boxes.
            function containsFilter(headerValue, rowValue) {
                var q = String(headerValue || '').trim().toLowerCase();
                if (q === '') return true;
                return String(rowValue == null ? '' : rowValue).toLowerCase().indexOf(q) !== -1;
            }

            // Sorters for the icon-only columns (they have no text value of their own).
            var DETAIL_FIELDS = ['type_variant', 'what', 'link', 'link2', 'sop', 'video', 'action', 'ca', 'plus_action', 'messages'];
            function overviewSorter(a, b, aRow, bRow) { // how many detail fields are filled in
                var count = function(r) {
                    var d = r.getData();
                    return DETAIL_FIELDS.reduce(function(n, f) { return n + (hasText(d[f]) ? 1 : 0); }, 0);
                };
                return count(aRow) - count(bRow);
            }
            function ctrlSorter(a, b, aRow, bRow) { // record order (id)
                return (aRow.getData().id || 0) - (bRow.getData().id || 0);
            }
            function guruSorter(a, b, aRow, bRow) { // number of history entries
                var n = function(r) { return (r.getData().history || []).length; };
                return n(aRow) - n(bRow);
            }

            var columns = [
                {
                    title: '', field: '_select', label: 'Select', width: 40, minWidth: 40, frozen: true,
                    hozAlign: 'center', vertAlign: 'middle', headerHozAlign: 'center',
                    headerSort: false, resizable: false, download: false,
                    formatter: 'rowSelection', titleFormatter: 'rowSelection'
                },
                col({
                    title: 'Group', label: 'Group', field: 'group_name', minWidth: 110, widthGrow: 1,
                    formatter: function(cell) {
                        var v = cell.getValue();
                        return hasText(v) ? hl(v, 'group_name') : DASH;
                    }
                }),
                col({
                    title: 'FAQ/FFP/Issues', label: 'FAQ/FFP/Issues', field: 'faq', minWidth: 160, widthGrow: 3,
                    headerTooltip: 'Frequently Asked Questions / Frequently Faced Problems / Issues',
                    formatter: function(cell) {
                        var d = rowData(cell);
                        var html = hl(d.faq, 'faq');
                        var snips = matchSnippets(d);
                        if (snips.length) {
                            html += '<div class="faq-match-context">' + snips.map(function(s) {
                                return '<div class="faq-match-context-item"><span class="faq-match-context-label">Match in ' + esc(s.label) +
                                    ':</span><span class="faq-match-context-snippet">' + hl(s.snippet) + '</span></div>';
                            }).join('') + '</div>';
                        }
                        return html;
                    }
                }),
                col({
                    title: 'Answer/Solutions!!!', label: 'Answer/Solutions', field: 'answers', minWidth: 160, widthGrow: 3,
                    formatter: function(cell) { return hl(truncate(cell.getValue(), 80), 'answers'); }
                }),
                iconColumn('_view', 'Overview', headerImg(IMG.magnifier, 'Overview', 30),
                    function() { return iconBtn('faq-view-btn text-dark', IMG.magnifier, 'View', 'View', 32); },
                    { download: false, sorter: overviewSorter,
                      cellClick: function(e, cell) { openView(rowData(cell)); } }),
                col({
                    title: 'Dept', label: 'Dept', field: 'dept_label', minWidth: 100, width: 120,
                    formatter: function(cell) {
                        var d = rowData(cell);
                        var dept = d.dept || [];
                        if (dept.indexOf('all') !== -1) return '<span class="badge bg-success">All</span>';
                        if (!dept.length) return DASH;
                        return String(d.dept_label || '').split(', ').map(function(n) {
                            return '<span class="badge bg-soft-primary text-primary">' + esc(n) + '</span>';
                        }).join(' ');
                    }
                }),
                iconColumn('type_variant', 'Variant/Types', headerImg(IMG.variant, 'Varient/Types', 28),
                    function(cell) {
                        var v = cell.getValue();
                        return hasText(v) ? '<span title="' + esc(v) + '"><img src="' + IMG.variant + '" alt="Variant/Type" style="height:32px;width:auto;object-fit:contain;"></span>' : '';
                    }),
                iconColumn('what', 'What', headerImg(IMG.what, 'What', 26),
                    function(cell) {
                        var v = cell.getValue();
                        return hasText(v) ? '<img src="' + IMG.what + '" alt="What" class="faq-hover-zoom" title="' + esc(v) + '" style="height:30px;width:auto;object-fit:contain;">' : '';
                    }, { cssClass: 'faq-what-cell' }),
                iconColumn('link', 'Link 1', headerImg(IMG.link, 'Link 1', 22),
                    function(cell) { var v = cell.getValue(); return hasText(v) ? iconLink(v, IMG.link, 'Link 1', 'Open link 1', 26) : DASH; }),
                iconColumn('link2', 'Link 2', headerImg(IMG.link, 'Link 2', 22),
                    function(cell) { var v = cell.getValue(); return hasText(v) ? iconLink(v, IMG.link, 'Link 2', 'Open link 2', 26) : DASH; }),
                iconColumn('sop', 'SOP', headerImg(IMG.sop, 'SOP', 24),
                    function(cell) { var v = cell.getValue(); return hasText(v) ? iconLink(v, IMG.sop, 'SOP', 'Open SOP', 30) : DASH; }),
                iconColumn('video', 'Video', headerImg(IMG.video, 'Video', 24),
                    function(cell) { var v = cell.getValue(); return hasText(v) ? iconLink(v, IMG.video, 'Video', 'Open video', 30) : DASH; }),
                iconColumn('action', 'Action', headerImg(IMG.action, 'Action', 26),
                    function(cell) {
                        var v = cell.getValue();
                        return hasText(v) ? iconBtn('faq-actiontext-btn', IMG.action, 'Action', v, 32) : DASH;
                    },
                    { cellClick: function(e, cell) {
                        if (hasText(cell.getValue()) && e.target.closest('button')) showText('faqActionTextModal', 'faqActionTextBody', cell.getValue());
                    } }),
                iconColumn('plus_action', '+ Action', headerImg(IMG.action, '+ Action', 26),
                    function(cell) {
                        var v = cell.getValue();
                        return hasText(v) ? iconBtn('faq-action-btn', IMG.action, '+ Action', v, 32) : DASH;
                    },
                    { cellClick: function(e, cell) {
                        if (hasText(cell.getValue()) && e.target.closest('button')) showText('faqActionModal', 'faqActionText', cell.getValue());
                    } }),
                iconColumn('ca', 'Corrective Action', headerImg(IMG.ca, 'Corrective Action', 26),
                    function(cell) {
                        var v = cell.getValue();
                        return hasText(v) ? iconBtn('faq-ca-btn', IMG.ca, 'Corrective Action', 'Corrective Action', 30) : DASH;
                    },
                    { cellClick: function(e, cell) {
                        if (hasText(cell.getValue()) && e.target.closest('button')) showText('faqCaModal', 'faqCaBody', cell.getValue());
                    } }),
                iconColumn('messages', 'Messages', headerImg(IMG.message, 'Messages', 36),
                    function(cell) {
                        var v = cell.getValue();
                        return hasText(v) ? iconBtn('faq-msg-btn', IMG.message, 'Message', 'View message', 36) : DASH;
                    },
                    { cellClick: function(e, cell) {
                        if (hasText(cell.getValue()) && e.target.closest('button')) showText('faqMessageModal', 'faqMessageText', cell.getValue());
                    } }),
                col({
                    title: 'CTRL', label: 'CTRL', field: '_ctrl', width: 90, minWidth: 80,
                    sorter: ctrlSorter, download: false,
                    formatter: function() {
                        var html = '<div class="d-flex justify-content-center align-items-center gap-2 flex-nowrap">';
                        if (CAN_EDIT) html += '<button type="button" class="btn btn-sm btn-link p-0 text-secondary faq-edit-btn" title="Edit"><i class="ri-edit-line fs-5"></i></button>';
                        if (CAN_DELETE) html += '<button type="button" class="btn btn-sm btn-link p-0 text-danger faq-archive-btn" title="Archive"><i class="ri-delete-bin-line fs-5"></i></button>';
                        return html + '</div>';
                    },
                    cellClick: function(e, cell) {
                        var t = e.target;
                        if (t.closest('.faq-edit-btn')) openEdit(rowData(cell));
                        else if (t.closest('.faq-archive-btn')) archiveFaq(rowData(cell));
                    }
                }),
                col({
                    title: 'User', label: 'User', field: '_guru', width: 72, minWidth: 60,
                    headerTooltip: 'Add/Edit history', sorter: guruSorter, download: false,
                    formatter: function(cell) {
                        var d = rowData(cell);
                        if ((d.history && d.history.length) || d.created_by_email) {
                            return '<button type="button" class="btn btn-sm btn-link p-0 faq-guru-btn" title="View history"><i class="ri-history-line fs-5"></i></button>';
                        }
                        return DASH;
                    },
                    cellClick: function(e, cell) {
                        if (e.target.closest('.faq-guru-btn')) openHistory(rowData(cell));
                    }
                })
            ];

            var table = new Tabulator('#faqTable', {
                ajaxURL: DATA_URL,
                ajaxResponse: function(url, params, response) {
                    (response || []).forEach(function(r) {
                        r._hay = [r.group_name, r.faq, r.answers, r.dept_label, r.type_variant, r.what, r.link, r.link2, r.sop, r.video, r.action, r.ca, r.plus_action, r.messages]
                            .join(' ').toLowerCase();
                    });
                    return response || [];
                },
                index: 'id',
                layout: 'fitColumns',
                height: '72vh',
                columns: columns,
                columnDefaults: { resizable: true, headerSortTristate: false },
                initialSort: [{ column: 'answers', dir: 'asc' }], // blanks on top by default
                pagination: true,
                paginationMode: 'local',
                paginationSize: 50,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                paginationCounter: 'rows',
                paginationButtonCount: 5,
                selectableRows: 'highlight',
                selectableRowsPersistence: true,
                movableColumns: true,
                placeholder: 'No FAQs match your search.'
            });

            // ───────────────────────── search + dept filter ─────────────────────────
            var deptFilterAll = document.getElementById('faqDeptFilterAll');
            var deptFilterBoxes = Array.prototype.slice.call(document.querySelectorAll('.faq-dept-filter'));
            var deptFilterLabel = document.getElementById('faqDeptFilterLabel');
            var countBadge = document.getElementById('faqCountBadge');

            function selectedDepts() {
                return deptFilterBoxes.filter(function(cb) { return cb.checked; }).map(function(cb) { return cb.value; });
            }
            function rowMatchesDept(d, sel) {
                if (sel.length === 0) return true;
                var depts = (d.dept || []).map(String);
                // FAQs visible to everyone (no dept / "all") always match.
                if (depts.length === 0 || depts.indexOf('all') !== -1) return true;
                for (var i = 0; i < sel.length; i++) {
                    if (depts.indexOf(sel[i]) !== -1) return true;
                }
                return false;
            }
            // Everything except the Dept filter (used for the live per-department counts).
            function passesTextFilters(d) {
                var q = searchQuery.toLowerCase();
                if (q !== '' && (d._hay || '').indexOf(q) === -1) return false;
                for (var f in colQueries) {
                    if (f === '_all') continue; // handled above via searchQuery
                    if (colQueries[f] && !containsFilter(colQueries[f], d[f])) return false;
                }
                return true;
            }
            function rowFilter(d) {
                return passesTextFilters(d) && rowMatchesDept(d, selectedDepts());
            }

            // Per-department counts: FAQs tagged with each department, for the current search / column filters.
            function updateDeptCounts() {
                var counts = {}, total = 0;
                table.getData().forEach(function(d) {
                    if (!passesTextFilters(d)) return;
                    total++;
                    (d.dept || []).forEach(function(id) { counts[id] = (counts[id] || 0) + 1; });
                });
                document.querySelectorAll('[data-dept-count]').forEach(function(el) {
                    var n = counts[el.getAttribute('data-dept-count')] || 0;
                    el.textContent = n;
                    el.classList.toggle('is-zero', n === 0);
                    var chip = el.closest('.faq-dept-chip');
                    if (chip) chip.classList.toggle('is-zero', n === 0);
                });
                var stripTotal = document.getElementById('faqDeptStripTotal');
                if (stripTotal) stripTotal.textContent = total;
                var all = document.getElementById('faqDeptCountAll');
                if (all) all.textContent = total;
            }


            function updateCount() {
                updateDeptCounts();
                if (!countBadge) return;
                var total = table.getDataCount();
                var active = table.getDataCount('active');
                var filtered = active !== total;
                countBadge.textContent = filtered ? ('Count: ' + active + ' / ' + total) : ('Count: ' + total);
            }

            // Re-render cells so highlights / match snippets follow the query.
            var reformatTimer = null;
            function reformatRows() {
                clearTimeout(reformatTimer);
                reformatTimer = setTimeout(function() {
                    table.getRows().forEach(function(r) { r.reformat(); });
                }, 0);
            }

            var colFilterInputs = Array.prototype.slice.call(document.querySelectorAll('.faq-col-filter'));
            var tableReady = false;
            function applyFilters() {
                var next = {};
                colFilterInputs.forEach(function(inp) { next[inp.getAttribute('data-field')] = (inp.value || '').trim(); });
                var changed = Object.keys(next).some(function(k) { return next[k] !== colQueries[k]; });
                colQueries = next;
                searchQuery = next._all || '';
                var sel = selectedDepts();
                if (deptFilterLabel) {
                    deptFilterLabel.textContent = sel.length === 0 ? 'All' : (sel.length + ' selected');
                }
                var summary = document.getElementById('faqDeptSummary');
                if (summary) summary.textContent = sel.length === 0 ? 'Showing all departments.' : (sel.length + ' department' + (sel.length > 1 ? 's' : '') + ' selected.');
                var small = document.getElementById('faqDeptCountSmall');
                if (small) small.textContent = sel.length ? (sel.length + ' / ' + deptFilterBoxes.length) : (deptFilterBoxes.length + ' total');
                deptFilterBoxes.forEach(function(cb) {
                    var item = cb.closest('.faq-dept-item');
                    if (item) item.classList.toggle('is-checked', cb.checked);
                    var chip = document.getElementById('faqDeptBadge_' + cb.value);
                    if (chip) chip.classList.toggle('is-active', cb.checked);
                });
                var chipAll = document.getElementById('faqDeptBadgeAll');
                if (chipAll) chipAll.classList.toggle('is-active', sel.length === 0);
                if (!tableReady) return;
                table.setFilter(rowFilter);
                if (changed) reformatRows();
                updateCount();
            }

            var allClear = document.getElementById('faqFilterAllClear');
            if (allClear) {
                allClear.addEventListener('click', function() {
                    var inp = document.getElementById('faqFilterAll');
                    inp.value = '';
                    applyFilters();
                    inp.focus();
                });
            }
            var colFilterTimer = null;
            colFilterInputs.forEach(function(inp) {
                inp.addEventListener('input', function() {
                    clearTimeout(colFilterTimer);
                    colFilterTimer = setTimeout(applyFilters, 200);
                });
                inp.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') { inp.value = ''; applyFilters(); }
                });
            });

            // Dept chips under the search row: click to toggle that department in the filter.
            var deptStrip = document.getElementById('faqDeptStrip');
            if (deptStrip) {
                deptStrip.addEventListener('click', function(e) {
                    var chip = e.target.closest('.faq-dept-chip');
                    if (!chip) return;
                    if (chip.id === 'faqDeptBadgeAll') {
                        deptFilterBoxes.forEach(function(cb) { cb.checked = false; });
                    } else {
                        var cb = document.getElementById('faqDeptFilter_' + chip.getAttribute('data-dept-id'));
                        if (cb) cb.checked = !cb.checked;
                    }
                    if (deptFilterAll) deptFilterAll.checked = selectedDepts().length === 0;
                    applyFilters();
                });
            }

            // Dept panel: quick search, Clear, Save & Close
            var deptSearch = document.getElementById('faqDeptSearch');
            if (deptSearch) {
                deptSearch.addEventListener('input', function() {
                    var q = deptSearch.value.trim().toLowerCase();
                    var shown = 0;
                    document.querySelectorAll('#faqDeptPanel .faq-dept-item').forEach(function(it) {
                        var ok = !q || (it.getAttribute('data-name') || '').indexOf(q) !== -1;
                        it.style.display = ok ? '' : 'none';
                        if (ok) shown++;
                    });
                    var none = document.querySelector('#faqDeptPanel .faq-dept-none');
                    if (none) none.style.display = shown ? 'none' : '';
                });
            }
            var deptClearBtn = document.getElementById('faqDeptClear');
            if (deptClearBtn) {
                deptClearBtn.addEventListener('click', function() {
                    deptFilterBoxes.forEach(function(cb) { cb.checked = false; });
                    if (deptFilterAll) deptFilterAll.checked = true;
                    if (deptSearch) { deptSearch.value = ''; deptSearch.dispatchEvent(new Event('input')); }
                    applyFilters();
                });
            }
            var deptCloseBtn = document.getElementById('faqDeptClose');
            if (deptCloseBtn) {
                deptCloseBtn.addEventListener('click', function() {
                    var tg = document.getElementById('faqDeptFilterBtn');
                    if (tg && window.bootstrap) bootstrap.Dropdown.getOrCreateInstance(tg).hide();
                });
            }

            if (deptFilterAll) {
                deptFilterAll.addEventListener('change', function() {
                    if (this.checked) deptFilterBoxes.forEach(function(cb) { cb.checked = false; });
                    else if (selectedDepts().length === 0) this.checked = true; // nothing else picked: stay on "All"
                    applyFilters();
                });
            }
            deptFilterBoxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    if (deptFilterAll) deptFilterAll.checked = selectedDepts().length === 0;
                    applyFilters();
                });
            });

            // ───────────────────────── selection + bulk edit ─────────────────────────
            var bulkEditBtn = document.getElementById('faqBulkEditBtn');
            var bulkEditCount = document.getElementById('faqBulkEditCount');
            var selectedBadge = document.getElementById('faqSelectedBadge');

            function selectedIds() {
                return table.getSelectedData().map(function(d) { return d.id; });
            }
            function refreshSelection() {
                var n = selectedIds().length;
                if (bulkEditBtn) bulkEditBtn.disabled = n === 0;
                if (bulkEditCount) bulkEditCount.textContent = n > 0 ? '(' + n + ')' : '';
                if (selectedBadge) {
                    if (n > 0) { selectedBadge.textContent = n + ' selected'; selectedBadge.style.display = ''; }
                    else { selectedBadge.style.display = 'none'; }
                }
            }

            table.on('tableBuilt', function() {
                tableReady = true;
                applyFilters();
                applyColumnVisibilityFromServer(); // saved layout + builds the Columns panel
                    refreshSelection();
                });
            table.on('dataLoaded', function() { updateCount(); });
            table.on('dataFiltered', function() { updateCount(); });
            table.on('rowSelectionChanged', function() { refreshSelection(); });

            var bulkEditModalEl = document.getElementById('faqBulkEditModal');
            var bulkEditForm = document.getElementById('faqBulkEditForm');
            var bulkIdsWrap = document.getElementById('faqBulkEditIds');
            var bulkModalCount = document.getElementById('faqBulkEditModalCount');

            function bulkApplyCheckboxFor(field) {
                return bulkEditForm.querySelector('.bulk-apply[value="' + field + '"]');
            }
            function fillBulkIds(ids) {
                bulkIdsWrap.innerHTML = '';
                ids.forEach(function(id) {
                    var input = document.createElement('input');
                    input.type = 'hidden'; input.name = 'ids[]'; input.value = id;
                    bulkIdsWrap.appendChild(input);
                });
            }

            // Typing/changing a field auto-ticks its "apply" box.
            bulkEditForm.querySelectorAll('.bulk-field').forEach(function(el) {
                el.disabled = false;
                var field = el.getAttribute('data-field');
                var evt = (el.tagName === 'SELECT') ? 'change' : 'input';
                el.addEventListener(evt, function() {
                    var ap = bulkApplyCheckboxFor(field);
                    if (ap) ap.checked = true;
                });
            });
            var bulkDeptWrap = bulkEditForm.querySelector('.bulk-field-wrap[data-field="dept"]');
            if (bulkDeptWrap) {
                var bulkDeptApply = bulkApplyCheckboxFor('dept');
                var bulkAllCb = bulkDeptWrap.querySelector('.dept-all');
                var bulkOnes = bulkDeptWrap.querySelectorAll('.dept-one');
                bulkDeptWrap.querySelectorAll('input').forEach(function(el) {
                    el.disabled = false;
                    el.addEventListener('change', function() {
                        if (bulkDeptApply) bulkDeptApply.checked = true;
                    });
                });
                if (bulkAllCb) {
                    bulkAllCb.addEventListener('change', function() {
                        if (this.checked) bulkOnes.forEach(function(o) { o.checked = false; });
                    });
                }
            }

            if (bulkEditBtn) {
                bulkEditBtn.addEventListener('click', function() {
                    var ids = selectedIds();
                    if (ids.length === 0) return;
                    fillBulkIds(ids);
                    if (bulkModalCount) bulkModalCount.textContent = '(' + ids.length + ' selected)';
                    new bootstrap.Modal(bulkEditModalEl).show();
                });
            }
                bulkEditForm.addEventListener('submit', function(e) {
                    var ids = selectedIds();
                    if (ids.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one row first.');
                        return;
                    }
                fillBulkIds(ids);
                if (bulkEditForm.querySelectorAll('.bulk-apply:checked').length === 0) {
                        e.preventDefault();
                        alert('Edit at least one field to update.');
                        return;
                    }
                    bulkEditForm.querySelectorAll('.bulk-field, .bulk-field-wrap input').forEach(function(el) {
                        el.disabled = false;
                    });
            });

            // ───────────────────────── Columns panel (shared with the other Tabulator pages) ─────────────────────────
            var tcmConfig = {
                channel: 'help_desk_faqs',
                groups: [
                    { key: 'basic', label: 'Basic' },
                    { key: 'resources', label: 'Resources' },
                    { key: 'details', label: 'Details' },
                    { key: 'other', label: 'Other' }
                ],
                fallbackGroup: 'other',
                skipFields: ['_select'],
                showAll: true
            };
            var tcmHooks = {
                classify: function(field) {
                    if (['group_name', 'faq', 'answers', '_view', 'dept_label'].indexOf(field) !== -1) return 'basic';
                    if (['link', 'link2', 'sop', 'video'].indexOf(field) !== -1) return 'resources';
                    if (['type_variant', 'what', 'action', 'plus_action', 'ca', 'messages'].indexOf(field) !== -1) return 'details';
                    return 'other';
                }
            };
            @include('partials.tabulator-column-manager')

            var exportBtn = document.getElementById('faqExportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    table.download('csv', 'help-desk-faqs.csv', { delimiter: ',' }, 'active');
                });
            }
        })();
    </script>
@endsection
