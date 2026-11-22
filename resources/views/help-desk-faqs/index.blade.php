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

        #faqColumnsMenu .form-check { white-space: nowrap; }
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
            {{-- Three separate column search filters (one row) --}}
            <div class="d-flex flex-nowrap align-items-center gap-2 mb-2 w-100">
                <input type="text" id="faqFilterGroup" class="form-control form-control-sm faq-col-filter" style="flex: 1 1 0; min-width: 0;" data-field="group_name" placeholder="Search Group...">
                <input type="text" id="faqFilterFaq" class="form-control form-control-sm faq-col-filter" style="flex: 1 1 0; min-width: 0;" data-field="faq" placeholder="Search FAQ/FFP/Issues...">
                <input type="text" id="faqFilterAnswers" class="form-control form-control-sm faq-col-filter" style="flex: 1 1 0; min-width: 0;" data-field="answers" placeholder="Search Answer/Solutions...">
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3 w-100">
                {{-- Dept dropdown --}}
                <div class="dropdown" style="width: 100px; flex-shrink: 0;">
                    <button class="btn btn-light border dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center faq-top-btn"
                        type="button" id="faqDeptFilterBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <span><span id="faqDeptFilterLabel">All</span></span>
                    </button>
                    <div class="dropdown-menu p-2" style="max-height: 320px; overflow-y: auto; min-width: 240px;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="faqDeptFilterAll" checked>
                            <label class="form-check-label fw-semibold" for="faqDeptFilterAll">All</label>
                        </div>
                        <hr class="my-1">
                        @foreach ($departments as $dept)
                            <div class="form-check">
                                <input class="form-check-input faq-dept-filter" type="checkbox" value="{{ $dept->id }}" id="faqDeptFilter_{{ $dept->id }}">
                                <label class="form-check-label" for="faqDeptFilter_{{ $dept->id }}">{{ $dept->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
                {{-- Count badge --}}
                <span id="faqCountBadge" class="btn btn-soft-primary btn-sm faq-top-btn" style="cursor:default; flex-shrink:0;">Count: 0</span>
                <span id="faqSelectedBadge" class="btn btn-primary btn-sm faq-top-btn" style="display:none; cursor:default; flex-shrink:0;">0 selected</span>
                {{-- Column visibility --}}
                <div class="dropdown" style="flex-shrink:0;">
                    <button class="btn btn-soft-secondary btn-sm faq-top-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Show / hide columns">
                        <i class="ri-layout-column-line"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" id="faqColumnsMenu" style="max-height: 360px; overflow-y: auto; min-width: 200px;"></div>
                </div>
                <button type="button" id="faqExportBtn" class="btn btn-soft-success btn-sm faq-top-btn" style="flex-shrink:0;" title="Export CSV (current filter)">
                    <i class="ri-file-download-line"></i>
                </button>
                {{-- Action buttons --}}
                <button type="button" class="btn btn-success btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqSubmitModal" title="Submit a new FAQ/FFP/Issue">
                    <i class="ri-send-plane-line"></i>
                </button>
                @if ($canEditFaq)
                    <button type="button" id="faqBulkEditBtn" class="btn btn-soft-warning btn-sm text-dark faq-top-btn flex-fill" disabled title="Bulk Edit">
                        <i class="ri-quill-pen-line"></i><span id="faqBulkEditCount"></span>
                    </button>
                    <button type="button" class="btn btn-soft-primary btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqBulkModal" title="Bulk Import">
                        <i class="ri-upload-2-line"></i>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqCreateModal" title="Add FAQ">
                        <i class="ri-add-line"></i>
                    </button>
                @endif
                <button type="button" class="btn btn-soft-dark btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqGuruModal" title="Guru">
                    <i class="ri-user-star-line"></i>
                </button>
                <a href="{{ route('help-desk-faqs.archived') }}" class="btn btn-soft-secondary btn-sm faq-top-btn d-flex align-items-center justify-content-center" title="Archived FAQs" style="flex-shrink:0;">
                    <i class="ri-archive-line"></i>
                </a>
            </div>

            <div id="faqTable"></div>
        </div>
    </div>

    {{-- Create Modal --}}
    <div class="modal fade" id="faqCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <form action="{{ route('help-desk-faqs.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('help-desk-faqs.partials.form', ['departments' => $departments])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save FAQ</button>
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
    <div class="modal fade" id="faqEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <form action="{{ route('help-desk-faqs.index') }}" method="POST" class="modal-content" id="faqEditForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @include('help-desk-faqs.partials.form', ['departments' => $departments, 'edit' => true])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update FAQ</button>
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

    {{-- Guru Users Modal --}}
    <div class="modal fade" id="faqGuruModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-user-star-line"></i> Guru Users</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Guru users can <strong>add and edit</strong> all data in this sheet (no delete). Managed by <code>president@5core.com</code>, <code>software5@5core.com</code> and <code>mgr-advertisement@5core.com</code>.</p>

                    @if ($isGuruManager)
                        <form action="{{ route('help-desk-faqs.gurus.store') }}" method="POST" class="row g-2 align-items-end mb-3">
                            @csrf
                            <div class="col-md-5">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control" placeholder="User name">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="user@5core.com" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Add</button>
                            </div>
                        </form>
                    @else
                        <div class="alert alert-info">You can view the Guru list. Only managers can add or remove Guru users.</div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    @if ($isGuruManager)<th class="text-end" style="width: 90px;">Action</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($gurus as $guru)
                                    <tr>
                                        <td>{{ $guru->name ?: '—' }}</td>
                                        <td>{{ $guru->email }}</td>
                                        @if ($isGuruManager)
                                            <td class="text-end">
                                                <form action="{{ route('help-desk-faqs.gurus.destroy', $guru->id) }}" method="POST"
                                                    onsubmit="return confirm('Remove this Guru user?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isGuruManager ? 3 : 2 }}" class="text-center text-muted py-3">No Guru users yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
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
    <div class="modal fade" id="faqViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><img src="{{ asset('images/magnifier-icon.png') }}" alt="" style="height: 24px; width: auto; object-fit: contain; vertical-align: text-bottom;"> FAQ Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <span class="fw-bold text-uppercase small text-muted">Group:</span>
                        <span id="view_group"></span>
                    </div>
                    <div class="p-3 mb-3 rounded border" style="background-color: #fff3cd; width: 100%;">
                        <div class="fw-bold text-uppercase small text-muted mb-1">FAQ</div>
                        <div id="view_faq" class="fw-semibold"></div>
                    </div>
                    <div class="p-3 mb-3 rounded border" style="background-color: #d1e7dd; width: 100%;">
                        <div class="fw-bold text-uppercase small text-muted mb-1">Answers</div>
                        <div id="view_answers"></div>
                    </div>
                    @php($viewIcon = fn($src, $h = 20) => '<img src="' . asset($src) . '" alt="" style="height:' . $h . 'px;width:auto;object-fit:contain;vertical-align:text-bottom;" class="me-2">')
                    <dl class="row mb-0">
                        <dt class="col-sm-3"><i class="ri-building-line me-2"></i>Dept</dt>
                        <dd class="col-sm-9" id="view_dept"></dd>

                        <dt class="col-sm-3">Type/Variant</dt>
                        <dd class="col-sm-9" id="view_type_variant"></dd>

                        <dt class="col-sm-3">What</dt>
                        <dd class="col-sm-9" id="view_what"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('images/link-icon.png') !!}Link</dt>
                        <dd class="col-sm-9" id="view_link"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('images/link-icon.png') !!}Link 2</dt>
                        <dd class="col-sm-9" id="view_link2"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('assets/images/task-sop-icon.png') !!}SOP</dt>
                        <dd class="col-sm-9" id="view_sop"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('assets/images/task-video-icon.png') !!}Video</dt>
                        <dd class="col-sm-9" id="view_video"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('images/action-comic.png', 22) !!}Action</dt>
                        <dd class="col-sm-9" id="view_action"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('images/action-icon.png') !!}CA (Corrective Action)</dt>
                        <dd class="col-sm-9" id="view_ca"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('images/action-comic.png', 22) !!}+ Action</dt>
                        <dd class="col-sm-9" id="view_plus_action"></dd>

                        <dt class="col-sm-3">{!! $viewIcon('images/message-icon.png') !!}Messages</dt>
                        <dd class="col-sm-9">
                            <span id="view_messages"></span>
                            <button type="button" id="view_messages_copy" class="btn btn-sm btn-link p-0 ms-2 align-baseline" title="Copy message" style="display:none;">
                                <i class="ri-file-copy-line"></i>
                            </button>
                        </dd>
                    </dl>
                </div>
                <div class="modal-footer">
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
    <div class="modal fade" id="faqGuruHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-history-line"></i> Add / Edit History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Date &amp; Time</th>
                            </tr>
                        </thead>
                        <tbody id="faqGuruHistoryBody"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
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
            var colQueries = { group_name: '', faq: '', answers: '' }; // per-column header filters

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
                } else {
                    el.textContent = '\u2014';
                    el.classList.add('text-muted');
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
                    if (hasText(d.messages)) { copyBtn.style.display = ''; copyBtn.setAttribute('data-copy', d.messages); }
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
                var body = document.getElementById('faqGuruHistoryBody');
                var hist = d.history || [];
                if (!body) return;
                if (!hist.length) {
                    body.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No history recorded.</td></tr>';
                } else {
                    body.innerHTML = hist.map(function(h) {
                        return '<tr><td>' + esc(h.email || '\u2014') + '</td><td>' + esc(h.action || '') + '</td><td>' + esc(h.at || '') + '</td></tr>';
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
                    { headerSort: false, download: false,
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
                    headerSort: false, download: false,
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
                    title: 'Guru', label: 'Guru', field: '_guru', width: 64, minWidth: 54,
                    headerTooltip: 'Add/Edit history', headerSort: false, download: false,
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
                        r._hay = [r.group_name, r.faq, r.answers, r.type_variant, r.what, r.action, r.ca, r.plus_action, r.messages]
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
                persistence: { columns: true },
                persistenceID: 'helpDeskFaqsGrid',
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
            function rowFilter(d) {
                var q = searchQuery.toLowerCase();
                if (q !== '' && (d._hay || '').indexOf(q) === -1) return false;
                for (var f in colQueries) {
                    if (colQueries[f] && !containsFilter(colQueries[f], d[f])) return false;
                }
                return rowMatchesDept(d, selectedDepts());
            }

            function updateCount() {
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
                var sel = selectedDepts();
                if (deptFilterLabel) {
                    deptFilterLabel.textContent = sel.length === 0 ? 'All' : (sel.length + ' department' + (sel.length > 1 ? 's' : ''));
                }
                if (!tableReady) return;
                table.setFilter(rowFilter);
                if (changed) reformatRows();
                updateCount();
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

            if (deptFilterAll) {
                deptFilterAll.addEventListener('change', function() {
                    if (this.checked) deptFilterBoxes.forEach(function(cb) { cb.checked = false; });
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
                buildColumnsMenu();
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

            // ───────────────────────── column visibility + export ─────────────────────────
            function buildColumnsMenu() {
                var menu = document.getElementById('faqColumnsMenu');
                if (!menu) return;
                menu.innerHTML = '';
                table.getColumns().forEach(function(c) {
                    var def = c.getDefinition();
                    if (!def.label || def.field === '_select') return;
                    var id = 'faqColToggle_' + def.field;
                    var wrap = document.createElement('div');
                    wrap.className = 'form-check';
                    var cb = document.createElement('input');
                    cb.type = 'checkbox'; cb.className = 'form-check-input'; cb.id = id;
                    cb.checked = c.isVisible();
                    cb.addEventListener('change', function() {
                        if (cb.checked) c.show(); else c.hide();
                        table.redraw(true);
                    });
                    var lb = document.createElement('label');
                    lb.className = 'form-check-label'; lb.setAttribute('for', id); lb.textContent = def.label;
                    wrap.appendChild(cb); wrap.appendChild(lb);
                    menu.appendChild(wrap);
                });
            }

            var exportBtn = document.getElementById('faqExportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    table.download('csv', 'help-desk-faqs.csv', { delimiter: ',' }, 'active');
                });
            }
        })();
    </script>
@endsection
