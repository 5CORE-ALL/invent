@foreach($suppliers as $index => $supplier)
    @php
    $categoryIds = explode(',', $supplier->category_id ?? '');
    $supplierCategoryNames = $categories->whereIn('id', $categoryIds)->pluck('name')->toArray();
@endphp


<tr data-supplier-id="{{ $supplier->id }}">
    <td class="text-center align-middle supplier-select-col">
        <input type="checkbox" class="form-check-input supplier-row-select" value="{{ $supplier->id }}"
            data-supplier-id="{{ $supplier->id }}"
            aria-label="Select supplier {{ $supplier->name }}">
    </td>
    <td>
        <div class="dropdown d-inline-block">
            @if(!empty(array_filter($categoryIds)))
            <button class="btn btn-sm btn-light dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ count(array_filter($categoryIds)) }}
            </button>
            <ul class="dropdown-menu">
                @foreach ($categories as $category)
                    @if(in_array($category->id, $categoryIds))
                        <li><span class="dropdown-item">{{ $category->name }}</span></li>
                    @endif
                @endforeach
            </ul>
            @endif
        </div>

        @if(empty(array_filter($categoryIds)))
            <span class="text-muted">-</span>
        @endif
    </td>

    <td class="text-center align-middle">
        @php
            $assignedCategories = $categories->filter(function ($category) use ($categoryIds) {
                return in_array($category->id, $categoryIds);
            });
            $firstCatCount = $assignedCategories->first()?->supplier_count ?? 0;
        @endphp
        @if($assignedCategories->isNotEmpty())
            <div class="dropdown d-inline-block">
                <button class="btn btn-sm btn-light dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                    title="Suppliers per category (category-wide totals)">
                    <i class="mdi mdi-account-group text-info me-1"></i>
                    <span class="fw-semibold text-info">{{ $firstCatCount }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    @foreach ($assignedCategories as $category)
                        <li>
                            <span class="dropdown-item small d-flex justify-content-between align-items-center gap-2">
                                <span>{{ $category->name }}</span>
                                <span class="badge bg-info-subtle text-info">{{ $category->supplier_count }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <span class="text-muted">-</span>
        @endif
    </td>


    <td class="supplier-name-col" title="{{ $supplier->name ?? '' }}">{{ $supplier->name ?? '-' }}</td>
    @php
        $approvalRaw = $supplier->approval_status ?? '';
        $approvalEffective = in_array($approvalRaw, ['green', 'yellow'], true) ? $approvalRaw : 'red';
        $approvalHoverLabels = ['red' => 'disqualified', 'yellow' => 'Explore', 'green' => 'Qualified'];
    @endphp
    <td class="text-center align-middle">
        <div class="dropdown supplier-approval-dropdown d-inline-block text-center" data-supplier-id="{{ $supplier->id }}">
            <button class="btn btn-link p-1 text-decoration-none supplier-approval-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                data-current-status="{{ $approvalEffective }}"
                title="{{ $approvalHoverLabels[$approvalEffective] }}">
                <span class="supplier-approval-dot supplier-approval-dot--{{ $approvalEffective }}"></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end supplier-approval-menu py-1">
                <li>
                    <button type="button" class="dropdown-item supplier-approval-pick d-flex align-items-center gap-2 py-2" data-status="red">
                        <span class="supplier-approval-dot supplier-approval-dot--red flex-shrink-0" title="disqualified"></span>
                        <span>disqualified</span>
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item supplier-approval-pick d-flex align-items-center gap-2 py-2" data-status="yellow">
                        <span class="supplier-approval-dot supplier-approval-dot--yellow flex-shrink-0" title="Explore"></span>
                        <span>Explore</span>
                    </button>
                </li>
                <li>
                    <button type="button" class="dropdown-item supplier-approval-pick d-flex align-items-center gap-2 py-2" data-status="green">
                        <span class="supplier-approval-dot supplier-approval-dot--green flex-shrink-0" title="Qualified"></span>
                        <span>Qualified</span>
                    </button>
                </li>
            </ul>
        </div>
    </td>
    <td class="text-center align-middle">
        @if(!empty($supplier->company))
            <button type="button"
                class="btn btn-link p-1 text-decoration-none supplier-company-toggle"
                data-company="{{ $supplier->company }}"
                data-supplier-name="{{ $supplier->name ?? '' }}"
                title="Click to view full company name">
                <span class="supplier-approval-dot supplier-approval-dot--green"></span>
            </button>
        @else
            <span class="text-muted">-</span>
        @endif
    </td>
    <td class="parents-col" style="position: relative;">
        @php
            $parents = !empty($supplier->parent) ? array_filter(explode(',', $supplier->parent)) : [];
        @endphp

        <div class="dropdown d-block w-100">
            @if(count($parents) > 0)
                <button class="btn btn-sm btn-light dropdown-toggle w-75" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    P ({{ count($parents) }})
                </button>
                <ul class="dropdown-menu show-on-top" style="max-height: 200px; overflow-y: auto;">
                    @foreach ($parents as $parent)
                        <li><span class="dropdown-item">{{ trim($parent) }}</span></li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if(count($parents) == 0)
            <span class="text-muted">-</span>
        @endif
    </td>

    <td>
        <span class="badge bg-info">{{ $supplier->zone ?? '-' }}</span>
    </td>

    <td>
        <!-- View Button -->
            <a href="#" class="btn btn-soft-success btn-sm"
                data-bs-toggle="modal" data-bs-target="#viewSupplierModal{{ $supplier->id }}"
                title="View">
                <i class="mdi mdi-eye-outline"></i>
            </a>
    </td>
    <td>
        @php
            $scores = $supplier->ratings->pluck('final_score')->filter();
            $avg = $scores->count() ? round($scores->avg(), 2) : null;
            $ratingPctColor = null;
            if ($avg !== null) {
                if ($avg > 90) {
                    $ratingPctColor = '#c2188b';
                } elseif ($avg >= 75) {
                    $ratingPctColor = '#198754';
                } elseif ($avg >= 50) {
                    $ratingPctColor = '#b58100';
                } else {
                    $ratingPctColor = '#dc3545';
                }
            }
        @endphp

        @if ($avg === null)
            <button type="button" class="btn btn-link btn-sm p-0 rate-btn text-primary"
                data-supplier-id="{{ $supplier->id }}"
                data-supplier-name="{{ $supplier->name }}"
                data-bs-toggle="modal"
                data-bs-target="#ratingModal"
                title="Rate"
                aria-label="Rate">
                <i class="mdi mdi-pencil rate-btn-icon"></i>
            </button>
        @else
            @php
                $latestRating = $supplier->ratings->sortByDesc('id')->first();
                $editPayload = $latestRating ? [
                    'id' => $latestRating->id,
                    'evaluation_date' => $latestRating->evaluation_date
                        ? \Illuminate\Support\Carbon::parse($latestRating->evaluation_date)->format('Y-m-d')
                        : now()->format('Y-m-d'),
                    'criteria' => $latestRating->criteria ?? [],
                ] : null;
            @endphp
            <div class="d-flex align-items-center justify-content-center gap-2">
                <span class="fw-bold" style="color: {{ $ratingPctColor }};" title="Average % (each rating uses only filled criteria)">{{ (int) round($avg) }}%</span>
                @if ($editPayload)
                    <button type="button" class="btn btn-link p-0 rating-edit-dot rate-edit-btn"
                        data-bs-toggle="modal" data-bs-target="#ratingModal"
                        data-supplier-id="{{ $supplier->id }}"
                        data-supplier-name="{{ $supplier->name }}"
                        data-rating-payload='@json($editPayload)'
                        title="Edit rating"
                        aria-label="Edit rating">
                        <span class="rating-edit-dot-inner d-inline-block rounded-circle bg-secondary"></span>
                    </button>
                @endif
            </div>
        @endif
    </td>
    <td>
        @if(!empty($supplier->alibaba))
            <a href="{{ $supplier->alibaba }}" target="_blank" class="text-decoration-none" title="View Alibaba Profile">
                <span class="supplier-data-dot supplier-data-dot--ok"></span>
            </a>
        @else
            <span class="supplier-data-dot supplier-data-dot--missing" title="No Alibaba on file"></span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->link_1688))
            <a href="{{ $supplier->link_1688 }}" target="_blank" class="text-decoration-none" title="View 1688 Profile">
                <span class="supplier-data-dot supplier-data-dot--ok"></span>
            </a>
        @else
            <span class="supplier-data-dot supplier-data-dot--missing" title="No 1688 on file"></span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->qq))
            <span class="supplier-data-dot supplier-data-dot--ok" title="QQ: {{ $supplier->qq }}"></span>
        @else
            <span class="supplier-data-dot supplier-data-dot--missing" title="No QQ on file"></span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->email))
            <a href="mailto:{{ $supplier->email }}" class="text-decoration-none" title="{{ $supplier->email }}">
                <span class="supplier-data-dot supplier-data-dot--ok"></span>
            </a>
        @else
            <span class="supplier-data-dot supplier-data-dot--missing" title="No email on file"></span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->whatsapp))
            @php
                $number = preg_replace('/\D/', '', $supplier->whatsapp);
                if (!empty($supplier->country_code) && strlen($number) < 10) {
                    $countryCode = preg_replace('/\D/', '', $supplier->country_code);
                    $number = $countryCode . $number;
                } elseif (!empty($supplier->country_code) && !empty($supplier->phone)) {
                    $phoneDigits = preg_replace('/\D/', '', $supplier->phone);
                    if ($number === $phoneDigits) {
                        $countryCode = preg_replace('/\D/', '', $supplier->country_code);
                        $number = $countryCode . $number;
                    }
                }
            @endphp
            <a href="#" onclick="openWhatsApp('{{ $number }}'); return false;" class="text-decoration-none" title="Chat on WhatsApp">
                <span class="supplier-data-dot supplier-data-dot--ok"></span>
            </a>
        @else
            <span class="supplier-data-dot supplier-data-dot--missing" title="No WhatsApp on file"></span>
        @endif
    </td>

    <td>
        @php
            $bankCount = (int) ($supplier->bank_accounts_count ?? 0);
        @endphp
        <button type="button"
                class="btn btn-link p-0 border-0 supplier-bank-open-btn"
                data-supplier-id="{{ $supplier->id }}"
                data-supplier-name="{{ e($supplier->name) }}"
                title="{{ $bankCount > 0 ? $bankCount . ' bank account(s)' : 'Add / view bank details' }}">
            <span class="supplier-data-dot {{ $bankCount > 0 ? 'supplier-data-dot--ok' : 'supplier-data-dot--missing' }}"></span>
        </button>
    </td>

    <td class="text-center align-middle">
        @php
            $adv = $supplier->latestAdvance;
            $advPct = $adv && $adv->advance_percent !== null ? (float) $adv->advance_percent : null;
            $advAmt = $adv && $adv->advance_amount !== null ? (float) $adv->advance_amount : null;
            $advCurr = $adv ? strtoupper((string) ($adv->currency ?? 'USD')) : 'USD';
            $advSym = $advCurr === 'RMB' || $advCurr === 'CNY' ? '¥' : '$';
        @endphp
        @if($advPct !== null)
            <span class="fw-semibold text-primary"
                  title="{{ $advAmt !== null ? 'Advance: '.$advSym.number_format($advAmt, 2) : 'Advance %' }}">
                {{ rtrim(rtrim(number_format($advPct, 2, '.', ''), '0'), '.') }}%
            </span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->wechat))
            <span class="supplier-data-dot supplier-data-dot--ok" title="WeChat ID: {{ $supplier->wechat }}"></span>
        @else
            <span class="supplier-data-dot supplier-data-dot--missing" title="No WeChat on file"></span>
        @endif
    </td>

    <td class="text-center">
        <div class="d-flex justify-content-center align-items-center gap-1">
            <!-- Edit Button -->
            <a href="#" class="btn btn-soft-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#editSupplierModal{{ $supplier->id }}"
                title="Edit">
                <i class="mdi mdi-square-edit-outline"></i>
            </a>

            <!-- Delete Button -->
            <form action="{{ route('supplier.delete', $supplier->id) }}" method="POST" class="d-inline"
                onsubmit="return confirm('Are you sure you want to delete this supplier?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-soft-danger btn-sm" data-bs-toggle="tooltip" title="Delete">
                    <i class="mdi mdi-delete-outline"></i>
                </button>
            </form>
        </div>
    </td>


    <!-- Edit Supplier Modal -->
    <div class="modal fade" id="editSupplierModal{{ $supplier->id }}" tabindex="-1" aria-labelledby="editSupplierModal{{ $supplier->id }}Label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('supplier.create') }}" class="needs-validation" novalidate id="editSupplierForm{{ $supplier->id }}">
                    <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                    @csrf
                    <!-- Modal Header -->
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold d-flex align-items-center m-0" id="editSupplierModal{{ $supplier->id }}Label">
                            <i class="mdi mdi-account-edit me-2 fs-5"></i> Edit Supplier
                        </h5>
                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body py-3">
                        <div class="container-fluid px-0">
                            <div class="row g-3">

                                <!-- Type -->
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                                    @php $types = ['Supplier', 'Forwarders', 'Photographer']; @endphp
                                    <select name="type" class="form-select" required>
                                        <option value="">Select Type</option>
                                        @foreach($types as $type)
                                            <option value="{{ $type }}" {{ $supplier->type == $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Category -->
                                @php
                                    $selected = collect(explode(',', $supplier->category_id ?? ''))->filter()->map(fn($id) => (int) trim($id))->filter()->toArray();
                                @endphp

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                    <select name="category_id[]" class="form-select select2" data-placeholder="Select Category" multiple required id="categorySelect{{ $supplier->id }}">
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ in_array((int) $category->id, $selected) ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Supplier Name *</label>
                                    <input type="text" name="name" class="form-control" placeholder="Supplier Name" value="{{ $supplier->name }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Company Name</label>
                                    <input type="text" name="company" class="form-control" placeholder="Company Name" value="{{ $supplier->company }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Alias</label>
                                    <input type="text" name="alias" class="form-control" placeholder="Alias" value="{{ $supplier->alias }}">
                                </div>

                                <div class="col-md-4">
                                    <div class="row g-1">
                                        <div class="col-4">
                                            <label class="form-label fw-semibold">Code</label>
                                            <input type="text" name="country_code" class="form-control" placeholder="+86" value="{{ $supplier->country_code }}">
                                        </div>
                                        <div class="col-8">
                                            <label class="form-label fw-semibold">Phone</label>
                                            <input type="text" name="phone" class="form-control" placeholder="Phone Number" value="{{ $supplier->phone }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">City</label>
                                    <input type="text" name="city" class="form-control" placeholder="City" value="{{ $supplier->city }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Zone</label>
                                    <select name="zone" class="form-select">
                                        <option value="">Select Zone</option>
                                        <option value="GHZ" {{ ($supplier->zone ?? '') == 'GHZ' ? 'selected' : '' }}>GHZ</option>
                                        <option value="Ningbo" {{ ($supplier->zone ?? '') == 'Ningbo' ? 'selected' : '' }}>Ningbo</option>
                                        <option value="Tianjin" {{ ($supplier->zone ?? '') == 'Tianjin' ? 'selected' : '' }}>Tianjin</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Approved</label>
                                    <div class="d-flex align-items-center gap-2 approval-form-dots flex-wrap">
                                        <label class="mb-0 cursor-pointer small text-muted border rounded px-2 py-1" title="Not set">
                                            <input type="radio" name="approval_status" value="" class="d-none" {{ empty($supplier->approval_status) ? 'checked' : '' }}> None
                                        </label>
                                        <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="disqualified">
                                            <input type="radio" name="approval_status" value="red" class="d-none" {{ ($supplier->approval_status ?? '') === 'red' ? 'checked' : '' }}>
                                            <span class="d-inline-block supplier-approval-dot supplier-approval-dot--red border-0" title="disqualified"></span>
                                        </label>
                                        <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Qualified">
                                            <input type="radio" name="approval_status" value="green" class="d-none" {{ ($supplier->approval_status ?? '') === 'green' ? 'checked' : '' }}>
                                            <span class="d-inline-block supplier-approval-dot supplier-approval-dot--green border-0" title="Qualified"></span>
                                        </label>
                                        <label class="mb-0 cursor-pointer d-inline-flex align-items-center" title="Explore">
                                            <input type="radio" name="approval_status" value="yellow" class="d-none" {{ ($supplier->approval_status ?? '') === 'yellow' ? 'checked' : '' }}>
                                            <span class="d-inline-block supplier-approval-dot supplier-approval-dot--yellow border-0" title="Explore"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Email Address</label>
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ $supplier->email }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">WhatsApp Number</label>
                                    <input type="text" name="whatsapp" class="form-control" placeholder="WhatsApp Number" value="{{ $supplier->whatsapp }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">WeChat ID</label>
                                    <input type="text" name="wechat" class="form-control" placeholder="WeChat ID" value="{{ $supplier->wechat }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Alibaba Profile</label>
                                    <input type="text" name="alibaba" class="form-control" placeholder="Alibaba Profile" value="{{ $supplier->alibaba }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">1688</label>
                                    <input type="text" name="link_1688" class="form-control" placeholder="1688 Profile / URL" value="{{ $supplier->link_1688 }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">QQ</label>
                                    <input type="text" name="qq" class="form-control" placeholder="QQ ID" value="{{ $supplier->qq }}">
                                </div>
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Website URL</label>
                                            <input type="text" name="website" class="form-control" placeholder="enter website URL" value="{{ $supplier->website }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Others</label>
                                            <input type="text" name="others" class="form-control" placeholder="Other Details" value="{{ $supplier->others }}">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Address</label>
                                            <input type="text" name="address" class="form-control" placeholder="Full Address" value="{{ $supplier->address }}">
                                        </div>
                                    </div>
                                </div>
                                <!-- Bank Details -->
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Bank Details</label>
                                    <textarea name="bank_details" class="form-control" rows="2" placeholder="Bank Details">{{ $supplier->bank_details }}</textarea>
                                </div>
                            </div>
                        </div>
                        <!-- Submit Button -->
                        <div class="d-flex justify-content-end mt-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="mdi mdi-content-save"></i> Save Supplier
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Supplier Modal -->
    <div class="modal fade" id="viewSupplierModal{{ $supplier->id }}" tabindex="-1" aria-labelledby="viewSupplierModal{{ $supplier->id }}Label" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center m-0" id="viewSupplierModal{{ $supplier->id }}Label">
                        <i class="mdi mdi-eye me-2 fs-5"></i> Supplier Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    @php
                        $viewCategoryIds = explode(',', $supplier->category_id ?? '');
                        $viewCategoryCount = count(array_filter($viewCategoryIds));
                        $viewParents = array_values(array_filter(explode(',', $supplier->parent ?? '')));
                        $viewBankPairs = $supplier->parsedBankDetailPairs();
                        $viewWaNumber = preg_replace('/\D/', '', (string) ($supplier->whatsapp ?? ''));
                        if (!empty($supplier->country_code) && strlen($viewWaNumber) < 10) {
                            $viewWaNumber = preg_replace('/\D/', '', $supplier->country_code) . $viewWaNumber;
                        } elseif (!empty($supplier->country_code) && !empty($supplier->phone)) {
                            $phoneDigits = preg_replace('/\D/', '', $supplier->phone);
                            if ($viewWaNumber === $phoneDigits) {
                                $viewWaNumber = preg_replace('/\D/', '', $supplier->country_code) . $viewWaNumber;
                            }
                        }
                    @endphp
                    <div class="supplier-view-hero">
                        <div class="rounded-circle shadow supplier-view-avatar" style="width: 48px; height: 48px; background: #e3f0ff; display: flex; align-items: center; justify-content: center;">
                            <i class="mdi mdi-account-circle text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold text-dark">{{ $supplier->name ?? '-' }}</h5>
                            <span class="badge bg-primary">{{ $supplier->type ?? '-' }}</span>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Category</div>
                                <div class="sv-value">
                                    @if($viewCategoryCount > 0)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown">
                                                {{ $viewCategoryCount }}
                                            </button>
                                            <ul class="dropdown-menu">
                                                @foreach($categories as $category)
                                                    @if(in_array($category->id, $viewCategoryIds))
                                                        <li><span class="dropdown-item">{{ $category->name }}</span></li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Company</div>
                                <div class="sv-value">{{ $supplier->company ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Alias</div>
                                <div class="sv-value">{{ $supplier->alias ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Parents</div>
                                <div class="sv-value">
                                    @if(count($viewParents) > 0)
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light dropdown-toggle py-0 px-2" type="button" data-bs-toggle="dropdown">
                                                P ({{ count($viewParents) }})
                                            </button>
                                            <ul class="dropdown-menu" style="max-height: 200px; overflow-y: auto;">
                                                @foreach($viewParents as $p)
                                                    <li><span class="dropdown-item">{{ trim($p) }}</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Phone</div>
                                <div class="sv-value">
                                    @if(!empty($supplier->phone))
                                        <a href="tel:{{ $supplier->country_code }}{{ $supplier->phone }}" class="text-decoration-none text-dark">
                                            <i class="mdi mdi-phone text-success"></i> {{ $supplier->country_code }} {{ $supplier->phone }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">City</div>
                                <div class="sv-value">{{ $supplier->city ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Zone</div>
                                <div class="sv-value">
                                    @if(!empty($supplier->zone))
                                        <span class="badge bg-info">{{ $supplier->zone }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Email</div>
                                <div class="sv-value">
                                    @if(!empty($supplier->email))
                                        <a href="mailto:{{ $supplier->email }}" class="text-decoration-none text-primary">
                                            <i class="mdi mdi-email-outline"></i> {{ $supplier->email }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">WhatsApp</div>
                                <div class="sv-value">
                                    @if(!empty($supplier->whatsapp))
                                        <a href="https://wa.me/{{ $viewWaNumber }}" target="_blank" class="text-success text-decoration-none">
                                            <i class="mdi mdi-whatsapp"></i> {{ $supplier->whatsapp }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">WeChat</div>
                                <div class="sv-value">
                                    @if(!empty($supplier->wechat))
                                        <span class="text-success"><i class="mdi mdi-wechat"></i> {{ $supplier->wechat }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Alibaba</div>
                                <div class="sv-value">
                                    @if(!empty($supplier->alibaba))
                                        <a href="{{ $supplier->alibaba }}" target="_blank" class="text-warning text-decoration-none">
                                            <i class="mdi mdi-shopping"></i> Profile
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">1688</div>
                                <div class="sv-value">
                                    @if(!empty($supplier->link_1688))
                                        <a href="{{ $supplier->link_1688 }}" target="_blank" class="text-decoration-none" style="color:#e65100;">
                                            Profile
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">QQ</div>
                                <div class="sv-value">{{ $supplier->qq ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="sv-field">
                                <div class="sv-label">Others</div>
                                <div class="sv-value">{{ $supplier->others ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="sv-field">
                                <div class="sv-label">Address</div>
                                <div class="sv-value">{{ $supplier->address ?: '—' }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="supplier-view-bank">
                        <div class="sv-bank-title"><i class="mdi mdi-bank me-1"></i>Bank Details</div>
                        @if(count($viewBankPairs) > 0)
                            <div class="row g-2">
                                @foreach($viewBankPairs as $bankPair)
                                    @php
                                        $bankLabel = strtolower($bankPair['label']);
                                        $bankCol = in_array($bankLabel, ['bank address', 'address'], true) ? 'col-12' : 'col-md-6';
                                    @endphp
                                    <div class="{{ $bankCol }}">
                                        <div class="sv-field">
                                            @if($bankPair['label'] !== '')
                                                <div class="sv-label">{{ $bankPair['label'] }}</div>
                                            @endif
                                            <div class="sv-value">{{ $bankPair['value'] }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="sv-field text-muted"><i class="mdi mdi-bank-off me-1"></i>No bank details on file</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

</tr>
@endforeach