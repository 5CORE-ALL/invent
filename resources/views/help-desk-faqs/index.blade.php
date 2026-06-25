@extends('layouts.vertical', ['title' => 'Help Desk FAQs, FFP'])

@section('css')
    <style>
        /*
         * Search highlight: when the page is rendered with `?q=…`, the JS
         * walker wraps every case-insensitive match of the query inside the
         * visible row cells with <mark.faq-search-highlight>. The colour
         * matches the browser default <mark> for familiarity but with a bit
         * more saturation so it survives the table's striped row backgrounds.
         */
        mark.faq-search-highlight {
            background-color: #fff3a3;
            color: inherit;
            padding: 0 1px;
            border-radius: 2px;
            box-shadow: 0 0 0 1px rgba(180, 150, 0, 0.25);
        }

        /*
         * Match-context block: rendered under the FAQ text whenever the
         * search query matched in a column that the table doesn't display
         * directly (Messages / Action / CA / What / + Action / Variant) or
         * past the 80-char Answer truncation. Keeps the row visually
         * "explainable" so the user understands why it surfaced.
         */
        .faq-match-context {
            font-size: 0.78rem;
            line-height: 1.3;
            color: #6c757d;
            border-left: 2px solid #fcd34d;
            padding-left: 6px;
            text-align: left !important;
        }
        .faq-match-context-item + .faq-match-context-item {
            margin-top: 2px;
        }
        .faq-match-context-label {
            font-weight: 600;
            color: #475569;
            margin-right: 4px;
        }
        .faq-match-context-snippet {
            color: #334155;
        }

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

        /* Compact layout so all columns fit on screen */
        #faqTable {
            width: 100%;
            font-size: 0.75rem;
        }

        #faqTable th,
        #faqTable td {
            padding: 0.3rem 0.35rem !important;
        }

        /* Cap image sizes inside the table so columns stay narrow */
        #faqTable td img,
        #faqTable th img {
            max-height: 26px !important;
            height: auto !important;
            width: auto !important;
        }

        #faqTable td img.faq-msg-icon,
        #faqTable th img.faq-msg-icon {
            max-height: 39px !important;
        }

        /* Allow the hover-zoom images to still enlarge */
        #faqTable .faq-hover-zoom:hover {
            max-height: 80px !important;
            transform: scale(1);
        }

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

        #faqTable thead th.faq-sortable {
            cursor: pointer;
            user-select: none;
        }

        #faqTable thead th.faq-sortable:hover {
            background-color: #b9d4ff !important;
        }

        .faq-sort-ind {
            font-size: 0.7rem;
            color: #405189;
        }

        .faq-hover-zoom {
            transition: transform 0.2s ease;
            cursor: pointer;
        }

        .faq-hover-zoom:hover {
            transform: scale(2);
        }
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
            <div class="d-flex flex-nowrap align-items-center gap-2 mb-3 w-100">
                {{-- Search.
                     `value` is pre-filled from the server-side `q` param so a
                     refresh/back-navigation keeps the user's query visible.
                     When the user types, a debounced JS handler navigates to
                     `?q=…`, which makes the controller skip pagination and
                     return every matching row on a single page (so the local
                     filter then matches across the whole dataset, not just
                     the current paginated slice). --}}
                <div class="input-group flex-grow-1" style="min-width: 120px;">
                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                    <input type="text" id="faqQuickSearch" class="form-control"
                        placeholder="Search..."
                        value="{{ $searchQuery ?? '' }}"
                        data-server-query="{{ $searchQuery ?? '' }}">
                    <button type="button" id="faqSearchClear" class="btn btn-light border" title="Clear">&times;</button>
                </div>
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
                <span id="faqCountBadge" class="btn btn-soft-primary btn-sm faq-top-btn" style="cursor:default; flex-shrink:0;"></span>
                <span id="faqSelectedBadge" class="btn btn-primary btn-sm faq-top-btn" style="display:none; cursor:default; flex-shrink:0;">0 selected</span>
                <small class="text-muted d-none" id="faqSearchCount"></small>
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
                <button type="button" class="btn btn-soft-dark btn-sm faq-top-btn flex-fill" data-bs-toggle="modal" data-bs-target="#faqGuruModal" title="Guru" data-bs-toggle="tooltip">
                    <i class="ri-user-star-line"></i>
                </button>
                <a href="{{ route('help-desk-faqs.archived') }}" class="btn btn-soft-secondary btn-sm faq-top-btn d-flex align-items-center justify-content-center" title="Archived FAQs" style="flex-shrink:0;">
                    <i class="ri-archive-line"></i>
                </a>
            </div>
            {{-- Per-page selector --}}
            <div class="d-flex align-items-center gap-2 mb-2">
                <form method="GET" action="{{ route('help-desk-faqs.index') }}" class="d-flex align-items-center gap-2 mb-0">
                    <label class="form-label mb-0 text-muted" style="white-space:nowrap; font-size:0.8rem;">Rows per page:</label>
                    <select name="per_page" class="form-select form-select-sm" style="width:80px;" onchange="this.form.submit()">
                        @foreach ([25, 50, 100, 200, 500] as $n)
                            <option value="{{ $n }}" {{ $perPage == $n ? 'selected' : '' }}>{{ $n }}</option>
                        @endforeach
                    </select>
                </form>
                @if ($faqs instanceof \Illuminate\Pagination\LengthAwarePaginator)
                    <span class="text-muted" style="font-size:0.8rem;">
                        Showing {{ $faqs->firstItem() }}–{{ $faqs->lastItem() }} of {{ $faqs->total() }} total
                    </span>
                @elseif (!empty($searchQuery))
                    {{-- Search-active branch: server returned every match across all
                         pages, so per-page / pagination controls would be misleading.
                         Show the absolute hit count so the user knows the whole
                         dataset was scanned, not just the current paginated slice. --}}
                    <span class="text-muted" style="font-size:0.8rem;">
                        Showing all {{ $faqs->count() }} match{{ $faqs->count() === 1 ? '' : 'es' }} for
                        <strong>&ldquo;{{ \Illuminate\Support\Str::limit($searchQuery, 60) }}&rdquo;</strong>
                        across every page
                    </span>
                @endif
            </div>

            <div class="table-responsive">
                <table id="faqTable" class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;" class="text-center">
                                <input type="checkbox" id="faqSelectAll" class="form-check-input" title="Select all">
                            </th>
                            <th style="min-width: 90px;" class="faq-sortable" data-sort-key="group">Group <span class="faq-sort-ind"></span></th>
                            <th style="min-width: 150px;" class="faq-sortable" data-sort-key="faq" title="Frequently Asked Questions / Frequently Faced Problems / Issues">FAQ/FFP/Issues <span class="faq-sort-ind"></span></th>
                            <th style="min-width: 150px;" class="faq-sortable" data-sort-key="answers">Answer/Solutions!!! <span class="faq-sort-ind"></span></th>
                            <th class="text-center" title="Overview"><img src="{{ asset('images/magnifier-icon.png') }}" alt="Overview" style="height: 32px; width: auto; object-fit: contain;"></th>
                            <th style="min-width: 110px;" class="faq-sortable" data-sort-key="dept-label">Dept <span class="faq-sort-ind"></span></th>
                            <th class="text-center faq-sortable" data-sort-key="type-variant" title="Varient/Types"><img src="{{ asset('images/variant-icon.png') }}" alt="Varient/Types" style="height: 30px; width: auto; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="what" title="What"><img src="{{ asset('images/what-icon.png') }}" alt="What" class="faq-hover-zoom" style="height: 28px; width: auto; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="link" title="Link 1"><img src="{{ asset('images/link-icon.png') }}" alt="Link 1" style="height: 22px; width: 22px; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="link2" title="Link 2"><img src="{{ asset('images/link-icon.png') }}" alt="Link 2" style="height: 22px; width: 22px; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="sop">
                                <img src="{{ asset('assets/images/task-sop-icon.png') }}" alt="SOP" title="SOP" style="height: 24px; width: 24px; object-fit: contain;">
                            </th>
                            <th class="text-center faq-sortable" data-sort-key="video" title="Video"><img src="{{ asset('assets/images/task-video-icon.png') }}" alt="Video" style="height: 24px; width: 24px; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="actiontext" title="Action"><img src="{{ asset('images/action-comic.png') }}" alt="Action" style="height: 28px; width: auto; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="plus-action" title="+ Action"><img src="{{ asset('images/action-comic.png') }}" alt="+ Action" style="height: 28px; width: auto; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="ca" title="Corrective Action"><img src="{{ asset('images/action-icon.png') }}" alt="Corrective Action" style="height: 26px; width: 26px; object-fit: contain;"></th>
                            <th class="text-center faq-sortable" data-sort-key="messages" title="Messages"><img src="{{ asset('images/message-icon.png') }}" alt="Messages" class="faq-msg-icon" style="height: 104px; width: 104px; object-fit: contain;"></th>
                            <th class="text-end" style="min-width: 90px;">CTRL</th>
                            <th class="text-center" title="Add/Edit history">Guru</th>
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
                            <tr class="faq-row" data-search="{{ \Illuminate\Support\Str::lower($faq->group_name . ' ' . $faq->faq . ' ' . $faq->answers . ' ' . $faq->type_variant . ' ' . $faq->what . ' ' . $faq->action . ' ' . $faq->ca . ' ' . $faq->plus_action . ' ' . $faq->messages) }}" data-dept='@json(array_map("strval", $faqDept))'>
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input faq-select" value="{{ $faq->id }}">
                                </td>
                                <td>@if (trim((string) $faq->group_name) !== ''){{ $faq->group_name }}@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td>
                                    {{ $faq->faq }}
                                    @php($_snips = $faq->getAttribute('match_snippets'))
                                    @if (!empty($_snips) && is_array($_snips))
                                        {{-- "Match in …" sub-line: explains why the row was returned
                                             when the matched text lives in an off-screen column
                                             (Messages / Action / CA / What / Variant / + Action) or
                                             past the 80-char Answer truncation. The JS highlighter
                                             will mark the query inside each snippet too. --}}
                                        <div class="faq-match-context mt-1">
                                            @foreach ($_snips as $snip)
                                                <div class="faq-match-context-item">
                                                    <span class="faq-match-context-label">Match in {{ $snip['label'] }}:</span>
                                                    <span class="faq-match-context-snippet">{{ $snip['snippet'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>{{ \Illuminate\Support\Str::limit($faq->answers, 80) }}</td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-link p-0 text-dark faq-view-btn"
                                        title="View"
                                        data-group="{{ e($faq->group_name) }}"
                                        data-faq="{{ e($faq->faq) }}"
                                        data-answers="{{ e($faq->answers) }}"
                                        data-dept-label="{{ e($deptLabel) }}"
                                        data-type-variant="{{ e($faq->type_variant) }}"
                                        data-what="{{ e($faq->what) }}"
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
                                <td class="text-center">@if (trim((string) $faq->type_variant) !== '')<span title="{{ $faq->type_variant }}"><img src="{{ asset('images/variant-icon.png') }}" alt="Variant/Type" style="height: 34px; width: auto; object-fit: contain;"></span>@endif</td>
                                <td class="text-center">@if (trim((string) $faq->what) !== '')<img src="{{ asset('images/what-icon.png') }}" alt="What" class="faq-hover-zoom" title="{{ $faq->what }}" style="height: 32px; width: auto; object-fit: contain;">@endif</td>
                                <td class="text-center">@if ($faq->link)<a href="{{ $faq->link }}" target="_blank" rel="noopener" title="Open link 1"><img src="{{ asset('images/link-icon.png') }}" alt="Link 1" style="height: 26px; width: 26px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->link2)<a href="{{ $faq->link2 }}" target="_blank" rel="noopener" title="Open link 2"><img src="{{ asset('images/link-icon.png') }}" alt="Link 2" style="height: 26px; width: 26px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->sop)<a href="{{ $faq->sop }}" target="_blank" rel="noopener" title="Open SOP"><img src="{{ asset('assets/images/task-sop-icon.png') }}" alt="SOP" style="height: 32px; width: 32px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->video)<a href="{{ $faq->video }}" target="_blank" rel="noopener" title="Open video"><img src="{{ asset('assets/images/task-video-icon.png') }}" alt="Video" style="height: 32px; width: 32px; object-fit: contain;"></a>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->action)<button type="button" class="btn btn-link p-0 faq-actiontext-btn" data-actiontext="{{ e($faq->action) }}" title="{{ $faq->action }}"><img src="{{ asset('images/action-comic.png') }}" alt="Action" style="height: 34px; width: auto; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->plus_action)<button type="button" class="btn btn-link p-0 faq-action-btn" data-action="{{ e($faq->plus_action) }}" title="{{ $faq->plus_action }}"><img src="{{ asset('images/action-comic.png') }}" alt="+ Action" style="height: 34px; width: auto; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->ca)<button type="button" class="btn btn-link p-0 faq-ca-btn" data-ca="{{ e($faq->ca) }}" title="Corrective Action"><img src="{{ asset('images/action-icon.png') }}" alt="Corrective Action" style="height: 30px; width: 30px; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td class="text-center">@if ($faq->messages)<button type="button" class="btn btn-link p-0 faq-msg-btn" data-message="{{ e($faq->messages) }}" title="View message"><img src="{{ asset('images/message-icon.png') }}" alt="Message" class="faq-msg-icon" style="height: 104px; width: 104px; object-fit: contain;"></button>@else<span class="text-muted">&mdash;</span>@endif</td>
                                <td>
                                    <div class="d-flex justify-content-center align-items-center gap-2 flex-nowrap">
                                    @if ($canEditFaq)
                                    <button type="button" class="btn btn-sm btn-link p-0 text-secondary faq-edit-btn"
                                        data-id="{{ $faq->id }}"
                                        data-group="{{ e($faq->group_name) }}"
                                        data-faq="{{ e($faq->faq) }}"
                                        data-answers="{{ e($faq->answers) }}"
                                        data-type-variant="{{ e($faq->type_variant) }}"
                                        data-what="{{ e($faq->what) }}"
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
                                    @if ($canDeleteFaq)
                                    <form action="{{ route('help-desk-faqs.destroy', $faq->id) }}" method="POST" class="d-inline m-0"
                                        onsubmit="return confirm('Archive this FAQ? You can restore it later from Archived.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-link p-0 text-danger" title="Archive"><i class="ri-delete-bin-line fs-5"></i></button>
                                    </form>
                                    @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    @php($hist = is_array($faq->edit_history) ? $faq->edit_history : [])
                                    @if (!empty($hist) || $faq->created_by_email)
                                        <button type="button" class="btn btn-sm btn-link p-0 faq-guru-btn"
                                            title="View history"
                                            data-history='@json($hist)'
                                            data-created="{{ e($faq->created_by_email) }}"
                                            data-updated="{{ e($faq->updated_by_email) }}">
                                            <i class="ri-history-line fs-5"></i>
                                        </button>
                                    @else
                                        <span class="text-muted">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="18" class="text-center text-muted py-4">No FAQs yet. Click "Add FAQ" to create one.</td>
                            </tr>
                        @endforelse
                        <tr id="faqNoResults" style="display: none;">
                            <td colspan="18" class="text-center text-muted py-4">No FAQs match your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination links.
                 Explicitly request the Bootstrap-5 template (project convention,
                 see e.g. purchase-master/supplier, product-master/tobedc). Calling
                 plain ->links() falls back to Laravel's Tailwind template, which
                 ships *two* sub-layouts ("mobile" with Previous/Next + showing
                 N-of-N + an SVG chevron, and "desktop" with 1 2 3 4 5) that are
                 normally toggled by Tailwind responsive classes. This app uses
                 Bootstrap, not Tailwind, so neither section gets hidden — the
                 result is duplicated paginators and a huge unsized chevron in
                 the middle of the page. --}}
            @if ($faqs instanceof \Illuminate\Pagination\LengthAwarePaginator && $faqs->hasPages())
                <div class="d-flex justify-content-center mt-3">
                    {{ $faqs->onEachSide(2)->links('pagination::bootstrap-5') }}
                </div>
            @endif
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
                    <h5 class="modal-title"><i class="ri-search-line"></i> FAQ Details</h5>
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
    <script>
        (function() {
            var editModalEl = document.getElementById('faqEditModal');
            var editForm = document.getElementById('faqEditForm');
            var baseUpdateUrl = '{{ url('help-desk-faqs') }}';

            document.querySelectorAll('.faq-edit-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = this.getAttribute('data-id');
                    editForm.setAttribute('action', baseUpdateUrl + '/' + id);

                    editForm.querySelector('[name="group_name"]').value = this.getAttribute('data-group') || '';
                    editForm.querySelector('[name="faq"]').value = this.getAttribute('data-faq') || '';
                    editForm.querySelector('[name="answers"]').value = this.getAttribute('data-answers') || '';
                    editForm.querySelector('[name="type_variant"]').value = this.getAttribute('data-type-variant') || '';
                    editForm.querySelector('[name="what"]').value = this.getAttribute('data-what') || '';
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

            // ── Cross-page search (full dataset scan) ─────────────────────
            // The client-side `applyFilters()` only hides/shows DOM rows that
            // exist on the current paginated page, which means a user looking
            // for "abc" on page 1 would miss "abc" rows that live on page 5.
            // To make search behave like "show every match in one page" we
            // debounce-navigate to `?q=<text>` after typing pauses; the
            // controller then bypasses pagination and renders every match.
            // We still call `applyFilters()` on every keystroke so the visible
            // page is filtered instantly while the user is mid-type — the
            // server reload only kicks in once typing settles, and only when
            // the value actually differs from what the server already loaded.
            //
            // Note: page navigation is intentionally a real reload (not AJAX)
            // because every row carries server-rendered data-* attributes and
            // event handlers that would otherwise need to be re-bound.
            function buildSearchUrl(value) {
                var url = new URL(window.location.href);
                var trimmed = (value || '').trim();
                if (trimmed === '') {
                    url.searchParams.delete('q');
                } else {
                    url.searchParams.set('q', trimmed);
                }
                // When a search is active the controller skips pagination
                // entirely, so a stale `page` query param would 404-ish on
                // the paginator side; strip it so a refresh stays sane.
                url.searchParams.delete('page');
                return url.toString();
            }

            var serverQuery = (searchInput && searchInput.getAttribute('data-server-query')) || '';
            var searchReloadTimer = null;
            var searchReloadDelayMs = 400;

            // After the auto-reload triggered by typing, put focus back into
            // the search box (cursor at end) so the user can keep typing
            // without clicking again. Only do this when the URL actually
            // carries `?q=…`, so initial page loads don't steal focus.
            if (searchInput && serverQuery !== '') {
                searchInput.focus();
                try {
                    var caret = searchInput.value.length;
                    searchInput.setSelectionRange(caret, caret);
                } catch (_) { /* IE/older Safari may not support range */ }
            }

            // ── Yellow background highlight of search matches in row cells ──
            // When the page was rendered with `?q=…`, walk every visible row
            // cell, find each case-insensitive occurrence of the query in
            // text-only nodes, and wrap it in <mark class="faq-search-highlight">
            // so the matched word(s) jump out against the table background.
            //
            // Implementation notes:
            //   - We only touch text nodes (TreeWalker SHOW_TEXT), so existing
            //     buttons / forms / event handlers stay intact.
            //   - SCRIPT/STYLE/MARK/INPUT/TEXTAREA parents are skipped — we
            //     never want to mutate code, already-highlighted text, or
            //     editable form values.
            //   - The query is RegExp-escaped before being compiled, so an
            //     input like "a+b" doesn't get interpreted as the regex
            //     metacharacter.
            //   - Highlighting runs once per page load, so it imposes ~zero
            //     overhead when no search is active.
            function escapeRegExpForHighlight(s) {
                return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            function highlightMatchesIn(root, query) {
                if (!root || !query) return;
                var skipParents = { SCRIPT: 1, STYLE: 1, MARK: 1, INPUT: 1, TEXTAREA: 1, SELECT: 1, OPTION: 1 };
                var pattern = new RegExp(escapeRegExpForHighlight(query), 'gi');
                var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
                    acceptNode: function(node) {
                        if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                        var p = node.parentNode;
                        while (p && p !== root) {
                            if (skipParents[p.nodeName]) return NodeFilter.FILTER_REJECT;
                            p = p.parentNode;
                        }
                        // Reset the regex's lastIndex (sticky from /g) before each test.
                        pattern.lastIndex = 0;
                        return pattern.test(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
                    }
                });

                var matchedNodes = [];
                while (walker.nextNode()) matchedNodes.push(walker.currentNode);

                matchedNodes.forEach(function(textNode) {
                    var text = textNode.nodeValue;
                    var parent = textNode.parentNode;
                    if (!parent) return;
                    pattern.lastIndex = 0;
                    var frag = document.createDocumentFragment();
                    var lastIdx = 0;
                    var m;
                    while ((m = pattern.exec(text)) !== null) {
                        if (m.index > lastIdx) {
                            frag.appendChild(document.createTextNode(text.substring(lastIdx, m.index)));
                        }
                        var mark = document.createElement('mark');
                        mark.className = 'faq-search-highlight';
                        mark.textContent = m[0];
                        frag.appendChild(mark);
                        lastIdx = m.index + m[0].length;
                        // Guard against pathological zero-length match (shouldn't
                        // happen with the escaped query, but cheap insurance).
                        if (m[0].length === 0) pattern.lastIndex++;
                    }
                    if (lastIdx < text.length) {
                        frag.appendChild(document.createTextNode(text.substring(lastIdx)));
                    }
                    parent.replaceChild(frag, textNode);
                });
            }

            if (serverQuery !== '') {
                Array.prototype.forEach.call(document.querySelectorAll('tr.faq-row'), function(row) {
                    highlightMatchesIn(row, serverQuery);
                });
            }

            function scheduleServerSearch(value) {
                if (searchReloadTimer) clearTimeout(searchReloadTimer);
                searchReloadTimer = setTimeout(function() {
                    var trimmed = (value || '').trim();
                    if (trimmed === serverQuery) return; // nothing to do
                    window.location.href = buildSearchUrl(trimmed);
                }, searchReloadDelayMs);
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    applyFilters();
                    scheduleServerSearch(searchInput.value);
                });
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (searchReloadTimer) clearTimeout(searchReloadTimer);
                        var trimmed = (searchInput.value || '').trim();
                        if (trimmed !== serverQuery) {
                            window.location.href = buildSearchUrl(trimmed);
                        }
                    }
                });
            }
            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    if (searchReloadTimer) clearTimeout(searchReloadTimer);
                    searchInput.value = '';
                    applyFilters();
                    // Only navigate if the server is currently holding a
                    // search result set — otherwise we'd needlessly reload
                    // the default paginated view.
                    if (serverQuery !== '') {
                        window.location.href = buildSearchUrl('');
                        return;
                    }
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

            // ---- Column sorting (click header), blanks always on top ----
            var faqTbody = document.querySelector('#faqTable tbody');
            var faqNoResultsRow = document.getElementById('faqNoResults');
            var sortState = { key: null, dir: 'asc' };

            function rowSortValue(row, key) {
                var btn = row.querySelector('.faq-view-btn');
                var val = btn ? (btn.getAttribute('data-' + key) || '') : '';
                return val.trim().toLowerCase();
            }

            function sortRowsBy(key, dir) {
                var sorted = rows.slice().sort(function(a, b) {
                    var av = rowSortValue(a, key);
                    var bv = rowSortValue(b, key);
                    var ae = av === '';
                    var be = bv === '';
                    if (ae && !be) return -1; // blanks on top
                    if (!ae && be) return 1;
                    if (ae && be) return 0;
                    var cmp = av.localeCompare(bv, undefined, { numeric: true, sensitivity: 'base' });
                    return dir === 'asc' ? cmp : -cmp;
                });
                sorted.forEach(function(r) {
                    if (faqNoResultsRow) {
                        faqTbody.insertBefore(r, faqNoResultsRow);
                    } else {
                        faqTbody.appendChild(r);
                    }
                });
            }

            document.querySelectorAll('#faqTable thead th.faq-sortable').forEach(function(th) {
                th.addEventListener('click', function() {
                    var key = this.getAttribute('data-sort-key');
                    if (!key) return;
                    if (sortState.key === key) {
                        sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortState.key = key;
                        sortState.dir = 'asc';
                    }
                    document.querySelectorAll('#faqTable thead th.faq-sortable .faq-sort-ind').forEach(function(s) {
                        s.textContent = '';
                    });
                    var ind = this.querySelector('.faq-sort-ind');
                    if (ind) ind.textContent = sortState.dir === 'asc' ? '\u25B2' : '\u25BC';
                    sortRowsBy(key, sortState.dir);
                });
            });

            // Default sort: rows with a blank Answer column show on top.
            (function() {
                var ansTh = document.querySelector('#faqTable thead th.faq-sortable[data-sort-key="answers"]');
                sortState.key = 'answers';
                sortState.dir = 'asc';
                if (ansTh) {
                    var ind = ansTh.querySelector('.faq-sort-ind');
                    if (ind) ind.textContent = '\u25B2';
                }
                sortRowsBy('answers', 'asc');
            })();

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
                    setViewText('view_group', this.getAttribute('data-group'));
                    setViewText('view_faq', this.getAttribute('data-faq'));
                    setViewText('view_answers', this.getAttribute('data-answers'));
                    setViewText('view_dept', this.getAttribute('data-dept-label'));
                    setViewText('view_type_variant', this.getAttribute('data-type-variant'));
                    setViewText('view_what', this.getAttribute('data-what'));
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

            // ---- Guru history icon: show add/edit history ----
            var guruHistoryModalEl = document.getElementById('faqGuruHistoryModal');
            var guruHistoryBody = document.getElementById('faqGuruHistoryBody');
            function escapeHtmlText(s) {
                var d = document.createElement('div');
                d.textContent = s == null ? '' : String(s);
                return d.innerHTML;
            }
            document.querySelectorAll('.faq-guru-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var hist = [];
                    try { hist = JSON.parse(this.getAttribute('data-history') || '[]'); } catch (e) {}
                    if (!guruHistoryBody) return;
                    if (!hist.length) {
                        guruHistoryBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No history recorded.</td></tr>';
                    } else {
                        guruHistoryBody.innerHTML = hist.map(function(h) {
                            return '<tr><td>' + escapeHtmlText(h.email || '\u2014') + '</td><td>' + escapeHtmlText(h.action || '') + '</td><td>' + escapeHtmlText(h.at || '') + '</td></tr>';
                        }).join('');
                    }
                    if (guruHistoryModalEl) new bootstrap.Modal(guruHistoryModalEl).show();
                });
            });

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

            function bulkApplyCheckboxFor(field) {
                return bulkEditForm.querySelector('.bulk-apply[value="' + field + '"]');
            }

            // Fields are always editable. Typing/changing a field auto-ticks its
            // "apply" box. You can also tick a box with an empty field to clear it.
            bulkEditForm.querySelectorAll('.bulk-field').forEach(function(el) {
                el.disabled = false;
                var field = el.getAttribute('data-field');
                var evt = (el.tagName === 'SELECT') ? 'change' : 'input';
                el.addEventListener(evt, function() {
                    var ap = bulkApplyCheckboxFor(field);
                    if (ap) ap.checked = true;
                });
            });

            // Dept block: keep editable, auto-tick dept apply, "All" clears the others.
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
                    var ids = selectedIds();
                    if (ids.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one row first.');
                        return;
                    }
                    // (Re)populate the selected ids so the server knows which rows to update.
                    bulkIdsWrap.innerHTML = '';
                    ids.forEach(function(id) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'ids[]';
                        input.value = id;
                        bulkIdsWrap.appendChild(input);
                    });
                    var checkedApply = bulkEditForm.querySelectorAll('.bulk-apply:checked');
                    if (checkedApply.length === 0) {
                        e.preventDefault();
                        alert('Edit at least one field to update.');
                        return;
                    }
                    // Ensure applied fields submit even if they were left disabled.
                    bulkEditForm.querySelectorAll('.bulk-field, .bulk-field-wrap input').forEach(function(el) {
                        el.disabled = false;
                    });
                });
            }
        })();
    </script>
@endsection
