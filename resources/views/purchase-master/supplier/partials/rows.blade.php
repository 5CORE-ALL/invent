@foreach($suppliers as $index => $supplier)
    @php
    $categoryIds = explode(',', $supplier->category_id ?? '');
    $supplierCategoryNames = $categories->whereIn('id', $categoryIds)->pluck('name')->toArray();
@endphp


<tr>
    <td>
        <span class="badge bg-primary fw-bold">{{ $supplier->type ?? '-' }}</span>
    </td>

    <td>
        <div class="dropdown d-inline-block">
            @if(!empty(array_filter($categoryIds)))
            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                Categories ({{ count(array_filter($categoryIds)) }})
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


    <td>{{ $supplier->name ?? '-' }}</td>
    <td>
        @if(!empty($supplier->company))
            <div class="d-flex align-items-center">
                <span title="{{ $supplier->company }}">{{ \Illuminate\Support\Str::limit($supplier->company, 15, '...') }}</span>
                @if(!empty($supplier->website))
                    <a href="{{ $supplier->website }}" target="_blank" class="text-decoration-none" title="Visit Website">
                        <i class="mdi mdi-link-variant ms-1 text-primary" style="font-size: 18px;"></i>
                    </a>
                @elseif(!empty($supplier->alibaba))
                    <a href="{{ $supplier->alibaba }}" target="_blank" class="text-decoration-none" title="Visit Alibaba">
                        <i class="mdi mdi-link-variant ms-1 text-warning" style="font-size: 18px;"></i>
                    </a>
                @endif
            </div>
        @else
            <span class="text-muted">-</span>
        @endif
    </td>
    <td style="position: relative;">
        @php
            $parents = !empty($supplier->parent) ? array_filter(explode(',', $supplier->parent)) : [];
        @endphp

        <div class="dropdown d-inline-block">
            @if(count($parents) > 0)
                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Parent ({{ count($parents) }})
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

            if ($avg !== null) {
                if ($avg >= 90) {
                    $label = 'Excellent';
                    $class = 'success';
                    $icon = 'star-circle';
                } elseif ($avg >= 75) {
                    $label = 'Good';
                    $class = 'primary';
                    $icon = 'star-half-full';
                } elseif ($avg >= 60) {
                    $label = 'Average';
                    $class = 'warning';
                    $icon = 'star-outline';
                } else {
                    $label = 'Poor';
                    $class = 'danger';
                    $icon = 'star-off';
                }
            }
        @endphp

        @if ($avg === null)
            <button class="btn btn-outline-primary btn-sm rate-btn"
                data-supplier-id="{{ $supplier->id }}"
                data-supplier-name="{{ $supplier->name }}"
                data-bs-toggle="modal"
                data-bs-target="#ratingModal">
                <i class="mdi mdi-star-outline me-1"></i> Rate Supplier
            </button>
        @else
            <div class="d-flex align-items-center">
                <div class="rating-badge bg-{{ $class }} bg-opacity-10 rounded-pill px-2 py-1" 
                    style="border: 1.2px solid var(--bs-{{ $class }}); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <i class="mdi mdi-{{ $icon }} text-{{ $class }}" 
                    style="font-size: 1.2rem; vertical-align: -2px;"></i>
                    <span class="ms-1 text-{{ $class }} fw-bold" 
                        style="font-size: 0.9rem;">{{ $label }}</span>
                </div>
            </div>
        @endif
    </td>
    <td>
        @if(!empty($supplier->email))
            <a href="mailto:{{ $supplier->email }}"
            class="d-flex justify-content-center align-items-center text-decoration-none"
            title="Send Email">
                <span style="background: #e3f0ff; border-radius: 50%; width: 36px; height: 36px; display: flex; justify-content: center; align-items: center;">
                    <i class="mdi mdi-email-outline text-primary" style="font-size: 1.4rem;"></i>
                </span>
            </a>
        @else
            <span class="text-muted">-</span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->whatsapp))
            @php
                $number = preg_replace('/\D/', '', $supplier->whatsapp);
            @endphp

            <a href="#" onclick="openWhatsApp('{{ $number }}')" target="_blank"
            class="d-flex justify-content-center align-items-center text-decoration-none"
            title="Chat on WhatsApp">
                <span style="background: #00d757; border-radius: 50%; width: 36px; height: 36px; display: flex; justify-content: center; align-items: center;">
                    <i class="mdi mdi-whatsapp" style="font-size: 1.4rem; color: #fff;"></i>
                </span>
            </a>
        @else
            <span class="text-muted">-</span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->wechat))
            <a href="javascript:void(0);" 
            class="d-flex justify-content-center align-items-center text-decoration-none"
            title="WeChat ID: {{ $supplier->wechat }}">
                <span style="background: #09b83e; border-radius: 50%; width: 36px; height: 36px; display: flex; justify-content: center; align-items: center;">
                    <i class="mdi mdi-wechat" style="font-size: 1.4rem; color: #fff;"></i>
                </span>
            </a>
        @else
            <span class="text-muted">-</span>
        @endif
    </td>

    <td>
        @if(!empty($supplier->alibaba))
            <a href="{{ $supplier->alibaba }}" target="_blank"
            class="d-flex justify-content-center align-items-center text-decoration-none"
            title="View Alibaba Profile">
                <span style="background: #ffecb3; border-radius: 50%; width: 36px; height: 36px; display: flex; justify-content: center; align-items: center;">
                    <i class="mdi mdi-shopping" style="font-size: 1.4rem; color: #ff9800;"></i>
                </span>
            </a>
        @else
            <span class="text-muted">-</span>
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
        <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
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
                        <div class="container-fluid">
                            <div class="row g-3">

                                <!-- Type -->
                                <div class="col-md-6">
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

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                    <select name="category_id[]" class="form-select select2" data-placeholder="Select Category" multiple required id="categorySelect{{ $supplier->id }}">
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ in_array((int) $category->id, $selected) ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Supplier Name *</label>
                                    <input type="text" name="name" class="form-control" placeholder="Supplier Name" value="{{ $supplier->name }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Company Name</label>
                                    <input type="text" name="company" class="form-control" placeholder="Company Name" value="{{ $supplier->company }}">
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Parents</label>
                                    <input type="text" name="parent" class="form-control" placeholder="Use commas to separate multiple Parents (e.g., TV-BOX, CAMERA)" value="{{ $supplier->parent }}">
                                    <small class="text-muted">Separate multiple parents with commas</small>
                                </div>

                                <div class="col-md-6">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label fw-semibold">Country Code</label>
                                            <input type="text" name="country_code" class="form-control" placeholder="+86" value="{{ $supplier->country_code }}">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label fw-semibold">Phone</label>
                                            <input type="text" name="phone" class="form-control" placeholder="Phone Number" value="{{ $supplier->phone }}">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">City</label>
                                    <input type="text" name="city" class="form-control" placeholder="City" value="{{ $supplier->city }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Email Address</label>
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" value="{{ $supplier->email }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">WhatsApp Number</label>
                                    <input type="text" name="whatsapp" class="form-control" placeholder="WhatsApp Number" value="{{ $supplier->whatsapp }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">WeChat ID</label>
                                    <input type="text" name="wechat" class="form-control" placeholder="WeChat ID" value="{{ $supplier->wechat }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Alibaba Profile</label>
                                    <input type="text" name="alibaba" class="form-control" placeholder="Alibaba Profile" value="{{ $supplier->alibaba }}">
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
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary">
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
        <div class="modal-dialog modal-lg modal-dialog-centered shadow-none">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold d-flex align-items-center m-0" id="viewSupplierModal{{ $supplier->id }}Label">
                        <i class="mdi mdi-eye me-2 fs-5"></i> Supplier Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="container-fluid">
                        <div class="row g-3">
                            <div class="col-md-4 text-center mb-3">
                                <div class="rounded-circle mx-auto mb-2 shadow" style="width: 80px; height: 80px; background: #e3f0ff; display: flex; align-items: center; justify-content: center;">
                                    <i class="mdi mdi-account-circle text-primary" style="font-size: 3.5rem;"></i>
                                </div>
                                <h5 class="mb-0 fw-bold text-dark">{{ $supplier->name ?? '-' }}</h5>
                                <span class="badge bg-primary">{{ $supplier->type ?? '-' }}</span>
                            </div>
                            <div class="col-md-8">
                                <div class="row g-2">
                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">Category:</span>
                                        <div class="dropdown">
                                            @php
                                                $categoryIds = explode(',', $supplier->category_id ?? '');
                                                $count = count(array_filter($categoryIds));
                                            @endphp
                                            
                                            @if($count > 0)
                                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Categories ({{ $count }})
                                                </button>
                                                <ul class="dropdown-menu">
                                                    @foreach($categories as $category)
                                                        @if(in_array($category->id, $categoryIds))
                                                            <li><span class="dropdown-item">{{ $category->name }}</span></li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">Company:</span>
                                        <div>{{ $supplier->company ?? '-' }}</div>
                                    </div>

                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">Parent:</span>
                                        <div class="dropdown">
                                            @php
                                                $parentList = explode(',', $supplier->parent ?? '');
                                                $parentList = array_filter($parentList);
                                                $parentCount = count($parentList);
                                            @endphp

                                            @if($parentCount > 0)
                                                <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Parents ({{ $parentCount }})
                                                </button>
                                                <ul class="dropdown-menu">
                                                    @foreach($parentList as $p)
                                                        <li><span class="dropdown-item">{{ $p }}</span></li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">Phone:</span>
                                        <div>
                                            @if(!empty($supplier->phone))
                                                <a href="tel:{{ $supplier->country_code }}{{ $supplier->phone }}" class="text-decoration-none text-dark">
                                                    <i class="mdi mdi-phone text-success"></i> {{ $supplier->country_code }} {{ $supplier->phone }}
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">City:</span>
                                        <div>{{ $supplier->city ?? '-' }}</div>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">Email:</span>
                                        <div>
                                            @if(!empty($supplier->email))
                                                <a href="mailto:{{ $supplier->email }}" class="text-decoration-none text-primary">
                                                    <i class="mdi mdi-email-outline"></i> {{ $supplier->email }}
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">WhatsApp:</span>
                                        <div>
                                            @if(!empty($supplier->whatsapp))
                                                <a href="https://wa.me/{{ preg_replace('/\D/', '', $supplier->whatsapp) }}" target="_blank" class="text-success text-decoration-none">
                                                    <i class="mdi mdi-whatsapp"></i> {{ $supplier->whatsapp }}
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">WeChat:</span>
                                        <div>
                                            @if(!empty($supplier->wechat))
                                                <span class="text-success"><i class="mdi mdi-wechat"></i> {{ $supplier->wechat }}</span>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">Alibaba:</span>
                                        <div>
                                            @if(!empty($supplier->alibaba))
                                                <a href="{{ $supplier->alibaba }}" target="_blank" class="text-warning text-decoration-none">
                                                    <i class="mdi mdi-shopping"></i> Profile
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <span class="fw-semibold text-muted">Others:</span>
                                        <div>{{ $supplier->others ?? '-' }}</div>
                                    </div>
                                    <div class="col-sm-12">
                                        <span class="fw-semibold text-muted">Address:</span>
                                        <div>{{ $supplier->address ?? '-' }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 mt-3 d-flex align-items-center">
                                <span class="fw-semibold text-muted me-2" style="white-space: nowrap;">
                                    Bank Details:
                                </span>

                                @if(!empty($supplier->bank_details))
                                    <div class="border rounded p-2 bg-light shadow-sm text-dark flex-grow-1" style="background: #f8fafc; font-size: 0.95rem; line-height: 1.4;">
                                        @php
                                            $lines = preg_split("/\r\n|\n|\r/", trim($supplier->bank_details));
                                        @endphp
                                        @foreach($lines as $line)
                                            <div class="mb-1">{{ $line }}</div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="border rounded p-2 bg-light shadow-sm text-muted flex-grow-1 d-flex align-items-center" style="min-height: 48px;">
                                        <i class="mdi mdi-bank text-primary me-1"></i> -
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</tr>
@endforeach