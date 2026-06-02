@extends('layouts.vertical', ['title' => 'Help Desk FAQs, FFP'])

@section('css')
    <style>
        #faqTable th,
        #faqTable td {
            text-align: center !important;
            vertical-align: middle;
            height: auto;
            white-space: normal;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        #faqTable thead th {
            background-color: #cfe2ff !important;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Help Desk FAQs, FFP', 'sub_title' => 'Manage FAQs shown in the 5Core Help Desk'])

    @php
        $faqEditorTokens = ['president', 'innet', 'jasmine', 'hritikhsha'];
        $authUser = auth()->user();
        $authName = strtolower($authUser->name ?? '');
        $authEmail = strtolower($authUser->email ?? '');
        $canEditFaq = false;
        foreach ($faqEditorTokens as $token) {
            if (($authName !== '' && str_contains($authName, $token)) || ($authEmail !== '' && str_contains($authEmail, $token))) {
                $canEditFaq = true;
                break;
            }
        }
    @endphp

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
        <div class="card-header d-flex justify-content-end align-items-center">
            <div class="d-flex gap-2">
                <button type="button" id="faqBulkEditBtn" class="btn btn-soft-warning btn-sm text-dark" disabled>
                    <i class="bx bx-edit-alt"></i> Bulk Edit <span id="faqBulkEditCount"></span>
                </button>
                <button type="button" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal" data-bs-target="#faqBulkModal">
                    <i class="bx bx-upload"></i> Bulk Import
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#faqCreateModal">
                    <i class="bx bx-plus"></i> Add FAQ
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row mb-3 g-2">
                <div class="col-md-5 col-lg-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" id="faqQuickSearch" class="form-control" placeholder="Quick search FAQ or answer...">
                        <button type="button" id="faqSearchClear" class="btn btn-light border" title="Clear">&times;</button>
                    </div>
                </div>
                <div class="col-md-4 col-lg-3">
                    <div class="dropdown">
                        <button class="btn btn-light border dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center"
                            type="button" id="faqDeptFilterBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                            <span><i class="bx bx-filter-alt"></i> <span id="faqDeptFilterLabel">All</span></span>
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
                </div>
                <div class="col-12 d-flex align-items-center gap-2">
                    <span id="faqCountBadge" class="badge bg-info text-dark"></span>
                    <span id="faqSelectedBadge" class="badge bg-primary" style="display: none;">0 selected</span>
                    <small class="text-muted" id="faqSearchCount"></small>
                </div>
            </div>
            <div class="table-responsive">
                <table id="faqTable" class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;" class="text-center">
                                <input type="checkbox" id="faqSelectAll" class="form-check-input" title="Select all">
                            </th>
                            <th style="min-width: 220px; cursor: help;" title="Frequently Asked Questions / Frequently Faced Problems">FAQ/FFP</th>
                            <th style="min-width: 220px;">Answer/Solutions!!!</th>
                            <th class="text-center" title="Overview"><img src="{{ asset('images/magnifier-icon.png') }}" alt="Overview" style="height: 32px; width: auto; object-fit: contain;"></th>
                            <th style="min-width: 160px;">Dept</th>
                            <th class="text-center" title="Link 1"><img src="{{ asset('images/link-icon.png') }}" alt="Link 1" style="height: 22px; width: 22px; object-fit: contain;"></th>
                            <th class="text-center" title="Link 2"><img src="{{ asset('images/link-icon.png') }}" alt="Link 2" style="height: 22px; width: 22px; object-fit: contain;"></th>
                            <th class="text-center">
                                <img src="{{ asset('assets/images/task-sop-icon.png') }}" alt="SOP" title="SOP" style="height: 24px; width: 24px; object-fit: contain;">
                            </th>
                            <th class="text-center" title="Video"><img src="{{ asset('assets/images/task-video-icon.png') }}" alt="Video" style="height: 24px; width: 24px; object-fit: contain;"></th>
                            <th class="text-center" title="Action"><img src="{{ asset('images/action-comic.png') }}" alt="Action" style="height: 28px; width: auto; object-fit: contain;"></th>
                            <th class="text-center" title="+ Action"><img src="{{ asset('images/action-comic.png') }}" alt="+ Action" style="height: 28px; width: auto; object-fit: contain;"></th>
                            <th class="text-center" title="Corrective Action"><img src="{{ asset('images/action-icon.png') }}" alt="Corrective Action" style="height: 26px; width: 26px; object-fit: contain;"></th>
                            <th class="text-end" style="min-width: 120px;">Control</th>
                            <th class="text-center" title="Messages"><img src="{{ asset('images/message-icon.png') }}" alt="Messages" style="height: 104px; width: 104px; object-fit: contain;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($faqs as $faq)
                            @php
                                $faqDept = is_array($faq->dept) ? $faq->dept : [];
                                if (in_array('all', array_map('strval', $faqDept), true)) {
                                    $deptLabel = 'All';
                                } elseif (empty($faqDept)) {
                                    $deptLabel = '—';
                                } else {
                                    $deptLabel = implode(', ', array_map(fn($d) => $deptNames[$d] ?? $d, $faqDept));
                                }
                            @endphp
                            <tr class="faq-row" data-search="{{ \Illuminate\Support\Str::lower($faq->faq . ' ' . $faq->answers . ' ' . $faq->action . ' ' . $faq->ca . ' ' . $faq->plus_action . ' ' . $faq->messages) }}" data-dept='@json(array_map("strval", $faqDept))'>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input faq-select" value="{{ $faq->id }}">
                                </td>
                                <td>{{ $faq->faq }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($faq->answers, 80) }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-link p-0 text-dark faq-view-btn"
                                        title="View"
                                        data-faq="{{ e($faq->faq) }}"
                                        data-answers="{{ e($faq->answers) }}"
                                        data-dept-label="{{ e($deptLabel) }}"
                                        data-link="{{ e($faq->link) }}"
                                        data-link2="{{ e($faq->link2) }}"
                                        data-sop="{{ e($faq->sop) }}"
                                        data-video="{{ e($faq->video) }}"
                                        data-actiontext="{{ e($faq->action) }}"
                                        data-ca="{{ e($faq->ca) }}"
                                        data-plus-action="{{ e($faq->plus_action) }}"
                                        data-messages="{{ e($faq->messages) }}">
                                        <img src="{{ asset('images/magnifier-icon.png') }}" alt="View" style="height: 34px; width: auto; object-fit: contain;">
                                    </button>
                                </td>
                                <td>
                                    @if (in_array('all', array_map('strval', $faqDept), true))
                                        <span class="badge bg-success">All</span>
                                    @elseif (empty($faqDept))
                                        <span class="text-muted">&mdash;</span>
                                    @else
                                        @foreach ($faqDept as $d)
                                            <span class="badge bg-soft-primary text-primary">{{ $deptNames[$d] ?? $d }}</span>
                                        @endforeach
                                    @endif
                                </td>
                                <td class="text-center">@if ($faq->link)<a href="{{ $faq->link }}" target="_blank" rel="noopener" title="Open link 1"><img src="{{ asset('images/link-icon.png') }}" alt="Link 1" style="height: 26px; width: 26px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->link2)<a href="{{ $faq->link2 }}" target="_blank" rel="noopener" title="Open link 2"><img src="{{ asset('images/link-icon.png') }}" alt="Link 2" style="height: 26px; width: 26px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->sop)<a href="{{ $faq->sop }}" target="_blank" rel="noopener" title="Open SOP"><img src="{{ asset('assets/images/task-sop-icon.png') }}" alt="SOP" style="height: 32px; width: 32px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->video)<a href="{{ $faq->video }}" target="_blank" rel="noopener" title="Open video"><img src="{{ asset('assets/images/task-video-icon.png') }}" alt="Video" style="height: 32px; width: 32px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->action)<button type="button" class="btn btn-link p-0 faq-actiontext-btn" data-actiontext="{{ e($faq->action) }}" title="{{ $faq->action }}"><img src="{{ asset('images/action-comic.png') }}" alt="Action" style="height: 34px; width: auto; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->plus_action)<button type="button" class="btn btn-link p-0 faq-action-btn" data-action="{{ e($faq->plus_action) }}" title="{{ $faq->plus_action }}"><img src="{{ asset('images/action-comic.png') }}" alt="+ Action" style="height: 34px; width: auto; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->ca)<button type="button" class="btn btn-link p-0 faq-ca-btn" data-ca="{{ e($faq->ca) }}" title="Corrective Action"><img src="{{ asset('images/action-icon.png') }}" alt="Corrective Action" style="height: 30px; width: 30px; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td>
                                    <div class="d-flex justify-content-center align-items-center gap-2 flex-nowrap">
                                    @if ($canEditFaq)
                                    <button type="button" class="btn btn-sm btn-link p-0 text-secondary faq-edit-btn"
                                        data-id="{{ $faq->id }}"
                                        data-faq="{{ e($faq->faq) }}"
                                        data-answers="{{ e($faq->answers) }}"
                                        data-dept='@json($faqDept)'
                                        data-link="{{ e($faq->link) }}"
                                        data-link2="{{ e($faq->link2) }}"
                                        data-sop="{{ e($faq->sop) }}"
                                        data-video="{{ e($faq->video) }}"
                                        data-actiontext="{{ e($faq->action) }}"
                                        data-ca="{{ e($faq->ca) }}"
                                        data-plus-action="{{ e($faq->plus_action) }}"
                                        data-messages="{{ e($faq->messages) }}" title="Edit">
                                        <i class="ri-edit-line fs-5"></i>
                                    </button>
                                    @endif
                                    <form action="{{ route('help-desk-faqs.destroy', $faq->id) }}" method="POST" class="d-inline m-0"
                                        onsubmit="return confirm('Delete this FAQ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link p-0 text-danger" title="Delete"><i class="ri-delete-bin-line fs-5"></i></button>
                                    </form>
                                    </div>
                                </td>
                                <td class="text-center">@if ($faq->messages)<button type="button" class="btn btn-link p-0 faq-msg-btn" data-message="{{ e($faq->messages) }}" title="View message"><img src="{{ asset('images/message-icon.png') }}" alt="Message" style="height: 104px; width: 104px; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14" class="text-center text-muted py-4">No FAQs yet. Click "Add FAQ" to create one.</td>
                            </tr>
                        @endforelse
                        <tr id="faqNoResults" style="display: none;">
                            <td colspan="14" class="text-center text-muted py-4">No FAQs match your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Create Modal --}}
    <div class="modal fade" id="faqCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
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

    {{-- Edit Modal --}}
    <div class="modal fade" id="faqEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
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
                        <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                    </div>
                    <div class="alert alert-info mb-3">
                        <p class="mb-1"><strong>Expected columns:</strong></p>
                        <code>faq, dept, answers, link, link2, sop, video</code>
                        <hr class="my-2">
                        <ul class="mb-0 ps-3">
                            <li><strong>faq</strong> is required; rows with an empty faq are skipped.</li>
                            <li><strong>dept</strong> accepts department names or slugs separated by <code>,</code> or <code>|</code> (e.g. <code>HR|Management</code>), or the word <code>all</code> for everyone. Leave blank to show to everyone.</li>
                            <li>Other columns are optional.</li>
                        </ul>
                    </div>
                    <a href="{{ route('help-desk-faqs.sample') }}" class="btn btn-sm btn-soft-secondary">
                        <i class="bx bx-download"></i> Download sample CSV
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
        <div class="modal-dialog modal-lg">
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
                            <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="answers" id="bulk_apply_answers">
                            <label class="form-check-label fw-semibold" for="bulk_apply_answers">Answers</label>
                        </div>
                        <textarea name="answers" class="form-control bulk-field" data-field="answers" rows="2" placeholder="New answer" disabled></textarea>
                    </div>

                    <div class="border rounded p-2 mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="dept" id="bulk_apply_dept">
                            <label class="form-check-label fw-semibold" for="bulk_apply_dept">DEPT</label>
                        </div>
                        <div class="bulk-field-wrap" data-field="dept">
                            <div class="form-check">
                                <input class="form-check-input dept-all" type="checkbox" name="dept[]" value="all" id="dept_bulk_all" disabled>
                                <label class="form-check-label fw-semibold" for="dept_bulk_all">All</label>
                            </div>
                            <hr class="my-2">
                            <div class="row g-1">
                                @foreach ($departments as $dept)
                                    <div class="col-6 col-md-4">
                                        <div class="form-check">
                                            <input class="form-check-input dept-one" type="checkbox" name="dept[]" value="{{ $dept->id }}" id="dept_bulk_{{ $dept->id }}" disabled>
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
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="link" id="bulk_apply_link">
                                <label class="form-check-label fw-semibold" for="bulk_apply_link">Link</label>
                            </div>
                            <input type="text" name="link" class="form-control bulk-field" data-field="link" placeholder="https://..." disabled>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="link2" id="bulk_apply_link2">
                                <label class="form-check-label fw-semibold" for="bulk_apply_link2">Link 2</label>
                            </div>
                            <input type="text" name="link2" class="form-control bulk-field" data-field="link2" placeholder="https://..." disabled>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="sop" id="bulk_apply_sop">
                                <label class="form-check-label fw-semibold" for="bulk_apply_sop">SOP</label>
                            </div>
                            <input type="text" name="sop" class="form-control bulk-field" data-field="sop" placeholder="https://..." disabled>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="video" id="bulk_apply_video">
                                <label class="form-check-label fw-semibold" for="bulk_apply_video">Video</label>
                            </div>
                            <input type="text" name="video" class="form-control bulk-field" data-field="video" placeholder="https://..." disabled>
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="ca" id="bulk_apply_ca">
                                <label class="form-check-label fw-semibold" for="bulk_apply_ca">CA (Corrective Action)</label>
                            </div>
                            <textarea name="ca" class="form-control bulk-field" data-field="ca" rows="2" placeholder="New corrective action" disabled></textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check mb-1">
                                <input class="form-check-input bulk-apply" type="checkbox" name="apply[]" value="plus_action" id="bulk_apply_plus_action">
                                <label class="form-check-label fw-semibold" for="bulk_apply_plus_action">+ Action</label>
                            </div>
                            <textarea name="plus_action" class="form-control bulk-field" data-field="plus_action" rows="2" placeholder="New additional action" disabled></textarea>
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
                    <h5 class="modal-title"><i class="ri-search-line"></i> FAQ Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
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
    <script>
        (function() {
            var editModalEl = document.getElementById('faqEditModal');
            var editForm = document.getElementById('faqEditForm');
            var baseUpdateUrl = '{{ url('help-desk-faqs') }}';

            document.querySelectorAll('.faq-edit-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = this.getAttribute('data-id');
                    editForm.setAttribute('action', baseUpdateUrl + '/' + id);

                    editForm.querySelector('[name="faq"]').value = this.getAttribute('data-faq') || '';
                    editForm.querySelector('[name="answers"]').value = this.getAttribute('data-answers') || '';
                    editForm.querySelector('[name="link"]').value = this.getAttribute('data-link') || '';
                    editForm.querySelector('[name="link2"]').value = this.getAttribute('data-link2') || '';
                    editForm.querySelector('[name="sop"]').value = this.getAttribute('data-sop') || '';
                    editForm.querySelector('[name="video"]').value = this.getAttribute('data-video') || '';
                    editForm.querySelector('[name="action"]').value = this.getAttribute('data-actiontext') || '';
                    editForm.querySelector('[name="ca"]').value = this.getAttribute('data-ca') || '';
                    editForm.querySelector('[name="plus_action"]').value = this.getAttribute('data-plus-action') || '';
                    editForm.querySelector('[name="messages"]').value = this.getAttribute('data-messages') || '';

                    var dept = [];
                    try { dept = JSON.parse(this.getAttribute('data-dept') || '[]'); } catch (e) {}
                    dept = (dept || []).map(String);
                    editForm.querySelectorAll('input[name="dept[]"]').forEach(function(cb) {
                        cb.checked = dept.indexOf(String(cb.value)) !== -1;
                    });
                    syncDeptAll(editForm);

                    new bootstrap.Modal(editModalEl).show();
                });
            });

            // "All Departments" checkbox: when ticked, clear & disable the individual ones.
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
                if (allCb) {
                    allCb.addEventListener('change', function() { syncDeptAll(scope); });
                }
                syncDeptAll(scope);
            });

            // Quick search + DEPT filter: applied together (text AND department).
            var searchInput = document.getElementById('faqQuickSearch');
            var clearBtn = document.getElementById('faqSearchClear');
            var noResults = document.getElementById('faqNoResults');
            var countEl = document.getElementById('faqSearchCount');
            var rows = Array.prototype.slice.call(document.querySelectorAll('tr.faq-row'));

            var deptFilterAll = document.getElementById('faqDeptFilterAll');
            var deptFilterBoxes = Array.prototype.slice.call(document.querySelectorAll('.faq-dept-filter'));
            var deptFilterLabel = document.getElementById('faqDeptFilterLabel');
            var countBadge = document.getElementById('faqCountBadge');

            function selectedDepts() {
                return deptFilterBoxes.filter(function(cb) { return cb.checked; }).map(function(cb) { return cb.value; });
            }

            function rowMatchesDept(row, sel) {
                if (sel.length === 0) return true; // "All Departments"
                var depts = [];
                try { depts = JSON.parse(row.getAttribute('data-dept') || '[]'); } catch (e) {}
                depts = (depts || []).map(String);
                // FAQs visible to everyone (no dept / "all") always match.
                if (depts.length === 0 || depts.indexOf('all') !== -1) return true;
                for (var i = 0; i < sel.length; i++) {
                    if (depts.indexOf(sel[i]) !== -1) return true;
                }
                return false;
            }

            function applyFilters() {
                var q = (searchInput.value || '').trim().toLowerCase();
                var sel = selectedDepts();
                var visible = 0;
                rows.forEach(function(row) {
                    var hay = row.getAttribute('data-search') || '';
                    var matchText = q === '' || hay.indexOf(q) !== -1;
                    var matchDept = rowMatchesDept(row, sel);
                    var match = matchText && matchDept;
                    row.style.display = match ? '' : 'none';
                    if (match) visible++;
                });
                if (noResults) {
                    noResults.style.display = (rows.length > 0 && visible === 0) ? '' : 'none';
                }
                if (deptFilterLabel) {
                    deptFilterLabel.textContent = sel.length === 0 ? 'All' : (sel.length + ' department' + (sel.length > 1 ? 's' : ''));
                }
                if (countBadge) {
                    var isFiltered = (q !== '' || sel.length !== 0);
                    countBadge.textContent = isFiltered
                        ? ('Count: ' + visible + ' / ' + rows.length)
                        : ('Count: ' + rows.length);
                }
                if (countEl) {
                    countEl.textContent = (q === '' && sel.length === 0) ? '' : (visible + ' of ' + rows.length + ' shown');
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', applyFilters);
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    applyFilters();
                    searchInput.focus();
                });
            }

            // "All Departments" clears individual selections.
            if (deptFilterAll) {
                deptFilterAll.addEventListener('change', function() {
                    if (this.checked) {
                        deptFilterBoxes.forEach(function(cb) { cb.checked = false; });
                    }
                    applyFilters();
                });
            }
            // Selecting any department unchecks "All Departments".
            deptFilterBoxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    if (deptFilterAll) {
                        deptFilterAll.checked = selectedDepts().length === 0;
                    }
                    applyFilters();
                });
            });

            applyFilters();

            // ---- View (magnifying glass): show read-only FAQ details ----
            var viewModalEl = document.getElementById('faqViewModal');
            function setViewText(id, value) {
                var el = document.getElementById(id);
                if (!el) return;
                if (value && value.trim() !== '') {
                    el.textContent = value;
                    el.classList.remove('text-muted');
                } else {
                    el.textContent = '—';
                    el.classList.add('text-muted');
                }
            }
            function setViewLink(id, value, label) {
                var el = document.getElementById(id);
                if (!el) return;
                if (value && value.trim() !== '') {
                    el.innerHTML = '';
                    var a = document.createElement('a');
                    a.href = value;
                    a.target = '_blank';
                    a.rel = 'noopener';
                    a.textContent = label || value;
                    el.appendChild(a);
                    el.classList.remove('text-muted');
                } else {
                    el.textContent = '—';
                    el.classList.add('text-muted');
                }
            }
            document.querySelectorAll('.faq-view-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    setViewText('view_faq', this.getAttribute('data-faq'));
                    setViewText('view_answers', this.getAttribute('data-answers'));
                    setViewText('view_dept', this.getAttribute('data-dept-label'));
                    setViewLink('view_link', this.getAttribute('data-link'), 'Open link 1');
                    setViewLink('view_link2', this.getAttribute('data-link2'), 'Open link 2');
                    setViewLink('view_sop', this.getAttribute('data-sop'), 'Open SOP');
                    setViewLink('view_video', this.getAttribute('data-video'), 'Open video');
                    setViewText('view_action', this.getAttribute('data-actiontext'));
                    setViewText('view_ca', this.getAttribute('data-ca'));
                    setViewText('view_plus_action', this.getAttribute('data-plus-action'));
                    setViewText('view_messages', this.getAttribute('data-messages'));
                    var msgVal = this.getAttribute('data-messages') || '';
                    var copyBtn = document.getElementById('view_messages_copy');
                    if (copyBtn) {
                        if (msgVal.trim() !== '') {
                            copyBtn.style.display = '';
                            copyBtn.setAttribute('data-copy', msgVal);
                        } else {
                            copyBtn.style.display = 'none';
                            copyBtn.removeAttribute('data-copy');
                        }
                    }
                    if (viewModalEl) new bootstrap.Modal(viewModalEl).show();
                });
            });

            var msgCopyBtn = document.getElementById('view_messages_copy');
            if (msgCopyBtn) {
                msgCopyBtn.addEventListener('click', function() {
                    var text = this.getAttribute('data-copy') || '';
                    var icon = this.querySelector('i');
                    var done = function() {
                        if (icon) {
                            icon.classList.remove('ri-file-copy-line');
                            icon.classList.add('ri-check-line', 'text-success');
                            setTimeout(function() {
                                icon.classList.remove('ri-check-line', 'text-success');
                                icon.classList.add('ri-file-copy-line');
                            }, 1500);
                        }
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

            // ---- Message icon: show message text in a modal ----
            var messageModalEl = document.getElementById('faqMessageModal');
            var messageTextEl = document.getElementById('faqMessageText');
            document.querySelectorAll('.faq-msg-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (messageTextEl) messageTextEl.textContent = this.getAttribute('data-message') || '';
                    if (messageModalEl) new bootstrap.Modal(messageModalEl).show();
                });
            });

            // ---- CA icon: show Corrective Action text in a modal ----
            var caModalEl = document.getElementById('faqCaModal');
            var caBodyEl = document.getElementById('faqCaBody');
            document.querySelectorAll('.faq-ca-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (caBodyEl) caBodyEl.textContent = this.getAttribute('data-ca') || '';
                    if (caModalEl) new bootstrap.Modal(caModalEl).show();
                });
            });

            // ---- Action icon: show Action text in a modal ----
            var actionTextModalEl = document.getElementById('faqActionTextModal');
            var actionTextBodyEl = document.getElementById('faqActionTextBody');
            document.querySelectorAll('.faq-actiontext-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (actionTextBodyEl) actionTextBodyEl.textContent = this.getAttribute('data-actiontext') || '';
                    if (actionTextModalEl) new bootstrap.Modal(actionTextModalEl).show();
                });
            });

            // ---- Corrective Action icon: show + Action text in a modal ----
            var actionModalEl = document.getElementById('faqActionModal');
            var actionTextEl = document.getElementById('faqActionText');
            document.querySelectorAll('.faq-action-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    if (actionTextEl) actionTextEl.textContent = this.getAttribute('data-action') || '';
                    if (actionModalEl) new bootstrap.Modal(actionModalEl).show();
                });
            });

            // ---- Row selection + Bulk Edit ----
            var selectAll = document.getElementById('faqSelectAll');
            var bulkEditBtn = document.getElementById('faqBulkEditBtn');
            var bulkEditCount = document.getElementById('faqBulkEditCount');
            var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.faq-select'));
            var selectedBadge = document.getElementById('faqSelectedBadge');

            function selectedIds() {
                return rowChecks.filter(function(cb) { return cb.checked; }).map(function(cb) { return cb.value; });
            }

            function refreshSelection() {
                var ids = selectedIds();
                if (bulkEditBtn) bulkEditBtn.disabled = ids.length === 0;
                if (bulkEditCount) bulkEditCount.textContent = ids.length > 0 ? '(' + ids.length + ')' : '';
                if (selectedBadge) {
                    if (ids.length > 0) {
                        selectedBadge.textContent = ids.length + ' selected';
                        selectedBadge.style.display = '';
                    } else {
                        selectedBadge.style.display = 'none';
                    }
                }
                if (selectAll) {
                    var visible = rowChecks.filter(function(cb) {
                        var row = cb.closest('tr');
                        return row && row.style.display !== 'none';
                    });
                    var checkedVisible = visible.filter(function(cb) { return cb.checked; });
                    selectAll.checked = visible.length > 0 && checkedVisible.length === visible.length;
                    selectAll.indeterminate = checkedVisible.length > 0 && checkedVisible.length < visible.length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    var on = this.checked;
                    rowChecks.forEach(function(cb) {
                        var row = cb.closest('tr');
                        if (row && row.style.display !== 'none') cb.checked = on;
                    });
                    refreshSelection();
                });
            }
            rowChecks.forEach(function(cb) {
                cb.addEventListener('change', refreshSelection);
            });
            refreshSelection();

            // Bulk Edit modal: per-field "apply" toggles enable/disable inputs.
            var bulkEditModalEl = document.getElementById('faqBulkEditModal');
            var bulkEditForm = document.getElementById('faqBulkEditForm');
            var bulkIdsWrap = document.getElementById('faqBulkEditIds');
            var bulkModalCount = document.getElementById('faqBulkEditModalCount');

            function setBulkFieldEnabled(field, enabled) {
                bulkEditForm.querySelectorAll('.bulk-field[data-field="' + field + '"]').forEach(function(el) {
                    el.disabled = !enabled;
                });
                var wrap = bulkEditForm.querySelector('.bulk-field-wrap[data-field="' + field + '"]');
                if (wrap) {
                    wrap.querySelectorAll('input').forEach(function(el) { el.disabled = !enabled; });
                }
            }

            bulkEditForm.querySelectorAll('.bulk-apply').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    setBulkFieldEnabled(this.value, this.checked);
                });
            });

            // "All Departments" in bulk dept clears the individual ones.
            var bulkDeptWrap = bulkEditForm.querySelector('.bulk-field-wrap[data-field="dept"]');
            if (bulkDeptWrap) {
                var bulkAllCb = bulkDeptWrap.querySelector('.dept-all');
                var bulkOnes = bulkDeptWrap.querySelectorAll('.dept-one');
                if (bulkAllCb) {
                    bulkAllCb.addEventListener('change', function() {
                        if (this.checked) {
                            bulkOnes.forEach(function(o) { o.checked = false; });
                        }
                    });
                }
            }

            if (bulkEditBtn) {
                bulkEditBtn.addEventListener('click', function() {
                    var ids = selectedIds();
                    if (ids.length === 0) return;
                    bulkIdsWrap.innerHTML = '';
                    ids.forEach(function(id) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        bulkIdsWrap.appendChild(input);
                    });
                    if (bulkModalCount) bulkModalCount.textContent = '(' + ids.length + ' selected)';
                    new bootstrap.Modal(bulkEditModalEl).show();
                });
            }

            if (bulkEditForm) {
                bulkEditForm.addEventListener('submit', function(e) {
                    var anyApply = bulkEditForm.querySelectorAll('.bulk-apply:checked').length > 0;
                    if (!anyApply) {
                        e.preventDefault();
                        alert('Tick at least one field to update.');
                    }
                });
            }
        })();
    </script>
@endsection
