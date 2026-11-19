@extends('layouts.vertical', ['title' => 'Categories', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('content')
    @include('layouts.shared.page-title', ['page_title' => 'Categories', 'sub_title' => 'Categories'])

    {{-- ✅ Flash Message --}}
    @if (Session::has('flash_message'))
        <div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert"
            style="background-color: #03a744 !important; color: #fff !important;">
            {{ Session::get('flash_message') }}
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <style>
        .pagination-wrapper {
            width: auto;
            overflow-x: auto;
        }

        .pagination-wrapper .pagination {
            margin: 0;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            display: flex;
            flex-wrap: nowrap;
            gap: 4px;
        }

        .pagination-wrapper .page-item .page-link {
            padding: 0.5rem 1rem;
            min-width: 40px;
            text-align: center;
            color: #464646;
            border: 1px solid #f1f1f1;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 6px;
        }

        .pagination-wrapper .page-item.active .page-link {
            background: linear-gradient(135deg, #727cf5, #6366f1);
            border: none;
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(114, 124, 245, 0.2);
        }

        .pagination-wrapper .page-item .page-link:hover:not(.active) {
            background-color: #f8f9fa;
            color: #727cf5;
            border-color: #e9ecef;
        }

        /* Hide the "Showing x to y of z results" text */
        .pagination-wrapper p.small,
        .pagination-wrapper div.flex.items-center.justify-between {
            display: none !important;
        }

        @media (max-width: 576px) {
            .pagination-wrapper .page-item .page-link {
                padding: 0.4rem 0.8rem;
                min-width: 35px;
                font-size: 0.875rem;
            }
        }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    {{-- Page header --}}
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <h4 class="card-title mb-0 me-2">Categories</h4>
                            @php
                                // Counts for what is listed right now (they follow the Parent / SKU / MFR filters).
                                $badgeParents = [];
                                $badgeSkus = [];
                                $badgeRows = [];
                                foreach ($categories as $badgeCategory) {
                                    foreach ($badgeCategory->cp_master_skus ?? [] as $badgeItem) {
                                        $badgeRows[] = $badgeItem;
                                    }
                                }
                                foreach (($uncategorizedSkus ?? []) as $badgeItem) {
                                    $badgeRows[] = $badgeItem;
                                }
                                foreach ($badgeRows as $badgeItem) {
                                    if (($badgeItem['parent'] ?? '') !== '') {
                                        $badgeParents[mb_strtolower($badgeItem['parent'])] = true;
                                    }
                                    if (empty($badgeItem['is_parent'])) {
                                        $badgeSkus[mb_strtolower($badgeItem['sku'])] = true;
                                    }
                                }
                            @endphp
                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 fs-6"
                                title="Parents listed on this page">
                                <i class="mdi mdi-sitemap-outline me-1"></i> Parents: <span id="badge-parents">{{ count($badgeParents) }}</span>
                            </span>
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-3 py-2 fs-6"
                                title="SKUs listed on this page (PARENT rows not counted)">
                                <i class="mdi mdi-barcode me-1"></i> SKUs: <span id="badge-skus">{{ count($badgeSkus) }}</span>
                            </span>
                            <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 fs-6"
                                title="Categories listed on this page">
                                <i class="mdi mdi-shape-outline me-1"></i> Categories: <span id="badge-categories">{{ count($categories) }}</span>
                            </span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'categories'])
                        </div>
                    </div>

                    {{-- 🔍 Search Bar --}}
                    {{-- Parent / SKU / MFR category filter (searches CP Master across all categories, not just this page) --}}
                    <form method="GET" action="{{ route('category.list') }}" class="row g-2 align-items-end mb-3"
                        id="cp-filter-form">
                        <div class="col-md-3">
                            <label for="filter-parent" class="form-label fw-semibold">Parent</label>
                            <input type="text" id="filter-parent" name="parent" class="form-control"
                                value="{{ $parentFilter ?? '' }}" placeholder="Filter by parent..." autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="filter-sku" class="form-label fw-semibold">SKU</label>
                            <input type="text" id="filter-sku" name="sku" class="form-control"
                                value="{{ $skuFilter ?? '' }}" placeholder="Filter by SKU..." autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label for="filter-mfr" class="form-label fw-semibold">MFR category</label>
                            <input type="text" id="filter-mfr" name="mfr" class="form-control"
                                value="{{ $mfrFilter ?? '' }}" placeholder="Filter by MFR category..." autocomplete="off">
                        </div>
                        <div class="col-md-auto d-flex gap-2">
                            @if (($parentFilter ?? '') !== '' || ($skuFilter ?? '') !== '' || ($mfrFilter ?? '') !== '')
                                <a href="{{ route('category.list') }}" class="btn btn-light border">
                                    <i class="mdi mdi-close me-1"></i> Clear
                                </a>
                            @endif
                        </div>
                    </form>

    <style>
        /* Frozen columns: SKU sticks to the left edge, Action to the right edge, while the rest scrolls. */
        #category-table-body th.sticky-col,
        #category-table-body td.sticky-col {
            position: sticky;
            background-color: var(--bs-body-bg, #fff);
            z-index: 2;
        }
        #category-table-body th.sticky-col {
            background-color: #dfe9f8;
            z-index: 3;
        }
        #category-table-body .sticky-left { left: 0; box-shadow: 2px 0 4px -2px rgba(0, 0, 0, .25); }
        #category-table-body .sticky-right { right: 0; box-shadow: -2px 0 4px -2px rgba(0, 0, 0, .25); }
        #category-table-body td.sticky-col.bg-warning-subtle { background-color: var(--bs-warning-bg-subtle, #fff3cd) !important; }
    </style>
                    <div class="table-responsive">
                        <table class="table table-centered table-hover mb-0 border" id="category-table-body" style="min-width: 1300px;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center align-middle" style="width: 5%">#</th>
                                    <th class="text-center align-middle sortable-th" style="width: 12%; cursor: pointer; user-select: none; white-space: nowrap;"
                                        data-sort-key="parent" data-sort-type="text" title="Click to sort">Parent <i class="mdi mdi-unfold-more-horizontal text-muted sort-icon"></i></th>
                                    <th class="text-center align-middle" style="width: 6%">Image</th>
                                    <th class="text-center align-middle sortable-th sticky-col sticky-left" style="width: 14%; cursor: pointer; user-select: none; white-space: nowrap;"
                                        data-sort-key="sku" data-sort-type="text" title="Click to sort">SKU <i class="mdi mdi-unfold-more-horizontal text-muted sort-icon"></i></th>
                                    <th class="text-center align-middle sortable-th" style="width: 18%; cursor: pointer; user-select: none; white-space: nowrap;"
                                        data-sort-key="mfr" data-sort-type="text" title="Click to sort">MFR category <i class="mdi mdi-unfold-more-horizontal text-muted sort-icon"></i></th>
                                    <th class="text-center align-middle sortable-th" style="width: 15%; cursor: pointer; user-select: none; white-space: nowrap;"
                                        data-sort-key="suppliers" data-sort-type="num" title="Click to sort">Suppliers <i class="mdi mdi-unfold-more-horizontal text-muted sort-icon"></i></th>
                                    <th class="text-center align-middle sortable-th" style="width: 12%; cursor: pointer; user-select: none; white-space: nowrap;"
                                        data-sort-key="product" data-sort-type="num" title="Click to sort">Product <i class="mdi mdi-unfold-more-horizontal text-muted sort-icon"></i></th>
                                    <th class="text-center align-middle sticky-col sticky-right" style="width: 18%">Action</th>
                                </tr>
                            </thead>
                            @if (!empty($uncategorizedSkus))
                                {{-- Search results (Parent / SKU search) that are not in any category --}}
                                <tbody class="uncategorized-group">
                                    <tr>
                                        <td colspan="8" class="bg-light text-muted small fw-semibold py-1">
                                            {{ count($uncategorizedSkus) }} {{ \Illuminate\Support\Str::plural('SKU', count($uncategorizedSkus)) }} not in any category
                                        </td>
                                    </tr>
                                    @foreach ($uncategorizedSkus as $uSku)
                                        <tr>
                                            <td class="text-center align-middle {{ $uSku['is_parent'] ? 'bg-warning-subtle' : '' }}"></td>
                                            <td class="text-center parent-cell {{ $uSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                                @if ($uSku['parent'] !== '')
                                                    {{ $uSku['parent'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center align-middle image-cell {{ $uSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                @if (!empty($uSku['image']))
                                                    <a href="{{ $uSku['image'] }}" target="_blank" rel="noopener noreferrer">
                                                        <img src="{{ $uSku['image'] }}" alt="" loading="lazy"
                                                            style="height:40px;width:40px;object-fit:cover;border-radius:4px;border:1px solid #ccc;"
                                                            onerror="this.replaceWith(document.createTextNode('-'))">
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center sku-cell sticky-col sticky-left {{ $uSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                                {{ $uSku['sku'] }}
                                            </td>
                                            <td class="text-center text-muted {{ $uSku['is_parent'] ? 'bg-warning-subtle' : '' }}">No category</td>
                                            <td class="text-center text-muted {{ $uSku['is_parent'] ? 'bg-warning-subtle' : '' }}">-</td>
                                            <td class="text-center text-muted {{ $uSku['is_parent'] ? 'bg-warning-subtle' : '' }}">-</td>
                                            <td class="text-center align-middle sticky-col sticky-right {{ $uSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                <div class="d-flex justify-content-center align-items-center gap-1">
                                                    <button type="button" class="btn btn-soft-primary btn-sm" disabled style="opacity:.45"
                                                        title="Not in a category yet - add it to one first">
                                                        <i class="mdi mdi-square-edit-outline"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-soft-success btn-sm assign-category-btn"
                                                        data-sku="{{ $uSku['sku'] }}" title="Add this SKU to a category">
                                                        <i class="mdi mdi-plus-box-outline"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-soft-danger btn-sm" disabled style="opacity:.45"
                                                        title="Not in a category yet - nothing to remove">
                                                        <i class="mdi mdi-delete"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @endif
                            @forelse($categories as $key => $category)
                                @php
                                    $cpSkus = $category->cp_master_skus ?? [];
                                    $rowspan = max(1, count($cpSkus));
                                    $firstSku = $cpSkus[0] ?? null;
                                    $sortValues = [
                                        'parent' => $firstSku['parent'] ?? '',
                                        'sku' => $firstSku['sku'] ?? '',
                                        'mfr' => $category->name,
                                        'suppliers' => (int) ($category->supplier_count ?? 0),
                                        'product' => count($category->parents_list ?? []),
                                        'status' => $category->status,
                                    ];
                                @endphp
                                {{-- One <tbody> per category: one row per CP Master SKU (parent rows included);
                                     the category-level cells span all of its SKU rows. --}}
                                <tbody class="category-group" data-orig-index="{{ $loop->index }}"
                                    @foreach ($sortValues as $sortKey => $sortValue) data-sort-{{ $sortKey }}="{{ $sortValue }}" @endforeach>
                                    <tr>
                                        @if ($firstSku)
                                        <td class="text-center align-middle {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                            <div class="form-check d-flex justify-content-center align-items-center">
                                                <input type="checkbox" class="form-check-input row-checkbox"
                                                    data-category-id="{{ $category->id }}" data-sku="{{ $firstSku['sku'] }}"
                                                    style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                                            </div>
                                        </td>
                                        @else
                                        <td class="text-center align-middle">
                                            <div class="form-check d-flex justify-content-center align-items-center">
                                                <input type="checkbox" class="form-check-input row-checkbox"
                                                    data-category-id="{{ $category->id }}" data-sku=""
                                                    style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                                            </div>
                                        </td>
                                        @endif
                                        <td class="text-center parent-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                            @if ($firstSku && $firstSku['parent'] !== '')
                                                {{ $firstSku['parent'] }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle image-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                            @if (!empty($firstSku['image']))
                                                <a href="{{ $firstSku['image'] }}" target="_blank" rel="noopener noreferrer">
                                                    <img src="{{ $firstSku['image'] }}" alt="" loading="lazy"
                                                        style="height:40px;width:40px;object-fit:cover;border-radius:4px;border:1px solid #ccc;"
                                                        onerror="this.replaceWith(document.createTextNode('-'))">
                                                </a>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center sku-cell sticky-col sticky-left {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                            @if ($firstSku)
                                                {{ $firstSku['sku'] }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle mfr-category-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <div>
                                                    <h5 class="mb-0 fw-semibold">{{ $category->name }}</h5>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle supplier-cell {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                            <button type="button" class="btn btn-sm supplier-count-btn d-inline-flex align-items-center px-3 py-2 rounded-pill bg-info-subtle shadow-sm border-0"
                                                data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                title="View / add / edit suppliers">
                                                <i class="mdi mdi-account-group text-info me-2"></i>
                                                <span class="fw-semibold text-info supplier-count" data-category-id="{{ $category->id }}">{{ $category->supplier_count }}</span>
                                                <span class="ms-1 text-muted fw-bold">Suppliers</span>
                                            </button>
                                        </td>
                                        <td class="text-center align-middle" rowspan="{{ $rowspan }}">
                                            @php $parents = $category->parents_list ?? []; @endphp
                                            @if(count($parents) > 0)
                                                <div class="dropdown d-inline-block">
                                                    @php $parentsAuto = (bool) ($category->parents_autofilled ?? false); @endphp
                                                    <button class="btn btn-sm {{ $parentsAuto ? 'btn-outline-secondary' : 'btn-light' }} dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                                        @if ($parentsAuto) title="Auto-filled from CP Master (same MFR category)" @endif>
                                                        P ({{ count($parents) }})@if ($parentsAuto) <i class="mdi mdi-auto-fix"></i>@endif
                                                    </button>
                                                    <ul class="dropdown-menu" style="max-height: 240px; overflow-y: auto;">
                                                        @if ($parentsAuto)
                                                            <li><h6 class="dropdown-header">Auto-filled from CP Master (same MFR category)</h6></li>
                                                        @endif
                                                        @foreach ($parents as $parent)
                                                            <li><span class="dropdown-item">{{ $parent }}</span></li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        @if ($firstSku)
                                        <td class="text-center align-middle sticky-col sticky-right {{ $firstSku && $firstSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                            <div class="d-flex justify-content-center align-items-center gap-1">
                                                <a href="#" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal"
                                                    data-bs-target="#editCategoryModal{{ $category->id }}" title="Edit category">
                                                    <i class="mdi mdi-square-edit-outline"></i>
                                                </a>
                                                <button type="button" class="btn btn-soft-success btn-sm add-products-btn"
                                                    data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                    title="Add products (SKUs) to this category">
                                                    <i class="mdi mdi-plus-box-outline"></i>
                                                </button>
                                            <button type="button" class="btn btn-soft-danger btn-sm delete-row-btn"
                                                data-category-id="{{ $category->id }}" data-sku="{{ $firstSku['sku'] }}"
                                                title="Delete this row (removes the SKU from this MFR category)">
                                                <i class="mdi mdi-delete"></i>
                                            </button>
                                            </div>
                                        </td>
                                        @else
                                        <td class="text-center align-middle sticky-col sticky-right ">
                                            <div class="d-flex justify-content-center align-items-center gap-1">
                                                <a href="#" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal"
                                                    data-bs-target="#editCategoryModal{{ $category->id }}" title="Edit category">
                                                    <i class="mdi mdi-square-edit-outline"></i>
                                                </a>
                                                <button type="button" class="btn btn-soft-success btn-sm add-products-btn"
                                                    data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                    title="Add products (SKUs) to this category">
                                                    <i class="mdi mdi-plus-box-outline"></i>
                                                </button>
                                                <button type="button" class="btn btn-soft-danger btn-sm delete-category-btn"
                                                    data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                    title="Delete this row (it has no SKUs, so this deletes the category)">
                                                    <i class="mdi mdi-delete"></i>
                                                </button>
                                            </div>
                                        </td>
                                        @endif
                                    </tr>
                                    @foreach (array_slice($cpSkus, 1) as $cpSku)
                                        <tr>
                                            <td class="text-center align-middle {{ $cpSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                <div class="form-check d-flex justify-content-center align-items-center">
                                                    <input type="checkbox" class="form-check-input row-checkbox"
                                                        data-category-id="{{ $category->id }}" data-sku="{{ $cpSku['sku'] }}"
                                                        style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                                                </div>
                                            </td>
                                            <td class="text-center parent-cell {{ $cpSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                                @if ($cpSku['parent'] !== '')
                                                    {{ $cpSku['parent'] }}
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center align-middle image-cell {{ $cpSku && $cpSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                @if (!empty($cpSku['image']))
                                                    <a href="{{ $cpSku['image'] }}" target="_blank" rel="noopener noreferrer">
                                                        <img src="{{ $cpSku['image'] }}" alt="" loading="lazy"
                                                            style="height:40px;width:40px;object-fit:cover;border-radius:4px;border:1px solid #ccc;"
                                                            onerror="this.replaceWith(document.createTextNode('-'))">
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td class="text-center sku-cell sticky-col sticky-left {{ $cpSku['is_parent'] ? 'bg-warning-subtle fw-bold' : '' }}">
                                                {{ $cpSku['sku'] }}
                                            </td>
                                            <td class="text-center align-middle mfr-category-cell {{ $cpSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                <h5 class="mb-0 fw-semibold">{{ $category->name }}</h5>
                                            </td>
                                            <td class="text-center align-middle supplier-cell {{ $cpSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                <button type="button" class="btn btn-sm supplier-count-btn d-inline-flex align-items-center px-3 py-2 rounded-pill bg-info-subtle shadow-sm border-0"
                                                    data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                    title="View / add / edit suppliers">
                                                    <i class="mdi mdi-account-group text-info me-2"></i>
                                                    <span class="fw-semibold text-info supplier-count" data-category-id="{{ $category->id }}">{{ $category->supplier_count }}</span>
                                                    <span class="ms-1 text-muted fw-bold">Suppliers</span>
                                                </button>
                                            </td>
                                            <td class="text-center align-middle sticky-col sticky-right {{ $cpSku['is_parent'] ? 'bg-warning-subtle' : '' }}">
                                                <div class="d-flex justify-content-center align-items-center gap-1">
                                                    <a href="#" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal"
                                                        data-bs-target="#editCategoryModal{{ $category->id }}" title="Edit category">
                                                        <i class="mdi mdi-square-edit-outline"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-soft-success btn-sm add-products-btn"
                                                        data-category-id="{{ $category->id }}" data-category-name="{{ $category->name }}"
                                                        title="Add products (SKUs) to this category">
                                                        <i class="mdi mdi-plus-box-outline"></i>
                                                    </button>
                                                <button type="button" class="btn btn-soft-danger btn-sm delete-row-btn"
                                                    data-category-id="{{ $category->id }}" data-sku="{{ $cpSku['sku'] }}"
                                                    title="Delete this row (removes the SKU from this MFR category)">
                                                    <i class="mdi mdi-delete"></i>
                                                </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            @empty
                                @if (empty($uncategorizedSkus))
                                    <tbody>
                                        <tr>
                                            <td colspan="8" class="text-center">
                                                @if (($parentFilter ?? '') !== '' || ($skuFilter ?? '') !== '')
                                                    No SKUs found
                                                @else
                                                    No categories found
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                @endif
                            @endforelse
                        </table>
                    </div>
                    {{-- Suppliers modal (opened from the Suppliers count on any SKU / parent row) --}}
                    <div class="modal fade" id="categorySuppliersModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered shadow-none" style="max-width: 98vw; width: 98vw;">
                            <div class="modal-content border-0">
                                <div class="modal-header bg-primary text-white">
                                    <h5 class="modal-title">
                                        <i class="mdi mdi-account-group me-1"></i>
                                        Suppliers - <span id="csm-category-name"></span>
                                        <span class="badge bg-light text-dark ms-2" id="csm-count">0</span>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info py-2 small mb-3">
                                        Suppliers belong to the category, so any change here applies to <strong>all SKUs and parents</strong>
                                        of this category. <strong>Remove</strong> only unlinks the supplier from this category; the supplier stays in Supplier List.
                                    </div>
                                    <div id="csm-message" class="small mb-2"></div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="min-width:160px">Name</th>
                                                    <th style="min-width:140px">Company</th>
                                                    <th style="min-width:120px">Phone</th>
                                                    <th style="min-width:160px">Email</th>
                                                    <th style="min-width:120px">WhatsApp</th>
                                                    <th style="min-width:120px">WeChat</th>
                                                    <th style="min-width:110px">City</th>
                                                    <th style="width:150px" class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="csm-body"></tbody>
                                            <tfoot>
                                                <tr class="table-light">
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-name" placeholder="New supplier name *"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-company" placeholder="Company"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-phone" placeholder="Phone"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-email" placeholder="Email"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-whatsapp" placeholder="WhatsApp"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-wechat" placeholder="WeChat"></td>
                                                    <td><input type="text" class="form-control form-control-sm" id="csm-new-city" placeholder="City"></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-success btn-sm" id="csm-add-new">
                                                            <i class="mdi mdi-plus"></i> Add
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center mt-3">
                                        <label for="csm-existing" class="form-label fw-semibold mb-0">Or link an existing supplier:</label>
                                        <select id="csm-existing" class="form-select form-select-sm" style="max-width:320px">
                                            <option value="">Select supplier...</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="csm-link-existing">
                                            <i class="mdi mdi-link-variant"></i> Link
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @foreach ($categories as $category)
                                    <!-- Edit Category Modal -->
                                    <div id="editCategoryModal{{ $category->id }}" class="modal fade" tabindex="-1"
                                        aria-labelledby="editCategoryModal" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
                                            <div class="modal-content border-0">
                                                <div class="modal-header bg-primary text-white">
                                                    <h5 class="modal-title">
                                                        <i class="mdi mdi-pencil me-1"></i> Edit Category
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white"
                                                        data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="edit-category-container"
                                                    id="edit-container-{{ $category->id }}"
                                                    data-category-id="{{ $category->id }}">
                                                    <div class="modal-body p-4">
                                                        <!-- Category Name: search the list and pick, or add a new one -->
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-12">
                                                                <label for="category_name_{{ $category->id }}"
                                                                    class="form-label fw-semibold">
                                                                    Category Name <span class="text-danger">*</span>
                                                                </label>
                                                                @include('purchase-master.category.partials.category-name-combobox', ['inputId' => 'category_name_'.$category->id, 'value' => $category->name, 'currentId' => $category->id])
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-primary"
                                                            id="submit-edit-{{ $category->id }}">
                                                            <i class="mdi mdi-content-save me-1"></i> Update Category
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                    @endforeach
                    {{-- Add products (SKUs) to a category: opened from a row, or in bulk for the selected rows --}}
                    <div class="modal fade" id="addProductsModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered modal-lg shadow-none">
                            <div class="modal-content border-0 rounded-3">
                                <div class="modal-header bg-primary text-white rounded-top">
                                    <h5 class="modal-title">
                                        <i class="mdi mdi-plus-box-outline me-1"></i>
                                        Add products to <span id="apm-kind">category</span> <span id="apm-target"></span>
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="small text-muted mb-2" id="apm-note"></div>
                                    <label class="form-label fw-semibold" for="apm-search">SKU (from CP Master)</label>
                                    <input type="text" class="form-control" id="apm-search" autocomplete="off"
                                        placeholder="Quick search by SKU or parent...">

                                    <div class="d-flex justify-content-between align-items-center border rounded-top bg-light px-3 py-2 mt-2">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" id="apm-select-all">
                                            <label class="form-check-label fw-semibold" for="apm-select-all">Select all</label>
                                        </div>
                                        <div class="small text-muted">
                                            <span id="apm-shown">0</span> shown &middot;
                                            <span class="fw-semibold text-primary"><span id="apm-selected">0</span> selected</span>
                                            <a href="#" id="apm-clear" class="ms-2">Clear</a>
                                        </div>
                                    </div>
                                    <div id="apm-list" class="list-group list-group-flush border border-top-0 rounded-bottom"
                                        style="max-height:320px; overflow-y:auto;"></div>
                                    <div class="form-text">Selections are kept while you search. SKUs already in the category are greyed out.</div>
                                    <div class="text-danger small mt-2" id="apm-error" style="display:none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="apm-submit" disabled>
                                        <i class="mdi mdi-plus me-1"></i> Add <span id="apm-submit-count">0</span> SKUs
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Add a SKU that is in no category to one (opened from the Action column of uncategorised rows) --}}
                    <div class="modal fade" id="assignCategoryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered shadow-none">
                            <div class="modal-content border-0 rounded-3">
                                <div class="modal-header bg-primary text-white rounded-top">
                                    <h5 class="modal-title"><i class="mdi mdi-plus-box-outline me-1"></i> Add SKU to a category</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">SKU: <span class="fw-semibold" id="acm-sku"></span></div>
                                    <label class="form-label fw-semibold" for="acm-category">Category</label>
                                    <select id="acm-category" class="form-select">
                                        <option value="">Select a category...</option>
                                        @foreach (($allCategories ?? []) as $optCategory)
                                            <option value="{{ $optCategory->id }}">{{ $optCategory->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="text-danger small mt-2" id="acm-error" style="display:none;"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="acm-submit" disabled>
                                        <i class="mdi mdi-plus me-1"></i> Add to category
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3 text-muted small">
                        Showing all {{ count($categories) }} {{ \Illuminate\Support\Str::plural('category', count($categories)) }}@if (!empty($uncategorizedSkus)) and {{ count($uncategorizedSkus) }} {{ \Illuminate\Support\Str::plural('SKU', count($uncategorizedSkus)) }} not in any category @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            // Handle edit submission
            $('body').on('click', '[id^="submit-edit-"]', function(e) {
                e.preventDefault();
                const categoryId = $(this).attr('id').replace('submit-edit-', '');
                const container = $('#edit-container-' + categoryId);
                const categoryIdAttr = container.data('category-id');

                // Validation
                const categoryName = $('#category_name_' + categoryId).val().trim();
                if (!categoryName) {
                    alert('Please enter a category name.');
                    $('#category_name_' + categoryId).focus();
                    return;
                }

                // Collect data
                const data = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    category_id: categoryIdAttr,
                    category_name: categoryName
                };

                // Disable button to prevent double submission
                const submitBtn = $('#submit-edit-' + categoryId);
                submitBtn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Updating...');

                // AJAX submission
                $.ajax({
                    url: '{{ route('category.create') }}',
                    method: 'POST',
                    data: data,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#editCategoryModal' + categoryId).modal('hide');
                            location.reload();
                        } else {
                            alert(response.message || 'Failed to update category.');
                            submitBtn.prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Update Category');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                        let errorMessage = 'An error occurred while updating the category.';
                        
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.errors) {
                                const errors = Object.values(xhr.responseJSON.errors).flat();
                                errorMessage = errors.join('\n');
                            } else if (xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }
                        }
                        
                        alert(errorMessage);
                        submitBtn.prop('disabled', false).html('<i class="mdi mdi-content-save me-1"></i> Update Category');
                    }
                });
            });
        });

        $(document).ready(function() {
            // Rows (SKU / PARENT lines): delete one or several. Deleting a row removes that SKU from this
            // MFR category (the SKU stays in CP Master). The category itself is never deleted from here.
            const rowDeleteUrl = (categoryId) => '{{ url('/category') }}/' + categoryId + '/rows/delete';

            function deleteRows(selection, confirmText) {
                // selection: { categoryId: [sku, ...] }
                const categoryIds = Object.keys(selection);
                if (!categoryIds.length) return;
                if (!confirm(confirmText)) return;

                const requests = categoryIds.map(function(categoryId) {
                    return $.ajax({
                        url: rowDeleteUrl(categoryId),
                        method: 'POST',
                        dataType: 'json',
                        data: { _token: $('meta[name="csrf-token"]').attr('content'), skus: selection[categoryId] }
                    });
                });

                $.when.apply($, requests).done(function() {
                    location.reload();
                }).fail(function(xhr) {
                    const msg = xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.';
                    alert('Error: ' + msg);
                    location.reload();
                });
            }

            // Delete a row that has no SKU: the row is just the (empty) category, so this deletes the category.
            $(document).on('click', '.delete-category-btn', function() {
                const id = $(this).data('category-id');
                const name = String($(this).data('category-name'));
                if (!confirm('This row has no SKUs, so deleting it deletes the category "' + name + '". Continue?')) return;
                $.ajax({
                    url: '{{ url('/category/delete') }}/' + id,
                    method: 'POST',
                    data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') }
                }).always(function() {
                    location.reload();
                });
            });

            // Delete a single row
            $(document).on('click', '.delete-row-btn', function() {
                const categoryId = $(this).data('category-id');
                const sku = String($(this).data('sku'));
                const selection = {};
                selection[categoryId] = [sku];
                deleteRows(selection, 'Delete the row "' + sku + '"? It will be removed from this MFR category (the SKU stays in CP Master).');
            });
        });

        });
    </script>
    <script>
        (function() {
            const baseUrl = '{{ url('/category') }}';
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const fields = ['name', 'company', 'phone', 'email', 'whatsapp', 'wechat', 'city'];
            let currentCategoryId = null;

            const esc = (v) => $('<div>').text(v == null ? '' : v).html();
            const url = (suffix) => baseUrl + '/' + currentCategoryId + '/suppliers' + (suffix || '');

            function api(method, endpoint, data) {
                return $.ajax({
                    url: endpoint,
                    method: method,
                    data: data || {},
                    dataType: 'json',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                });
            }

            function errText(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.message) return xhr.responseJSON.message;
                return 'Something went wrong. Please try again.';
            }

            function showMessage(text, ok) {
                $('#csm-message').html(text ? '<span class="' + (ok ? 'text-success' : 'text-danger') + '">' + esc(text) + '</span>' : '');
            }

            function setCount(n) {
                $('#csm-count').text(n);
                $('.supplier-count[data-category-id="' + currentCategoryId + '"]').text(n);
            }

            function rowHtml(s) {
                let h = '<tr data-supplier-id="' + s.id + '">';
                fields.forEach(function(f) {
                    h += '<td><input type="text" class="form-control form-control-sm csm-field" data-field="' + f + '" value="' + esc(s[f]) + '"></td>';
                });
                h += '<td class="text-center text-nowrap">' +
                    '<button type="button" class="btn btn-primary btn-sm csm-save" title="Save"><i class="mdi mdi-content-save"></i></button> ' +
                    '<button type="button" class="btn btn-outline-danger btn-sm csm-remove" title="Remove from this category"><i class="mdi mdi-delete"></i></button>' +
                    '</td></tr>';
                return h;
            }

            function render(data) {
                const rows = (data.suppliers || []).map(rowHtml).join('');
                $('#csm-body').html(rows || '<tr class="csm-empty"><td colspan="8" class="text-center text-muted py-3">No suppliers for this category yet.</td></tr>');
                const opts = ['<option value="">Select supplier...</option>'].concat((data.available || []).map(function(a) {
                    return '<option value="' + a.id + '">' + esc(a.name) + '</option>';
                }));
                $('#csm-existing').html(opts.join(''));
                setCount((data.suppliers || []).length);
            }

            function load() {
                $('#csm-body').html('<tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>');
                api('GET', url()).done(render).fail(function(xhr) {
                    $('#csm-body').html('');
                    showMessage(errText(xhr), false);
                });
            }

            $(document).on('click', '.supplier-count-btn', function() {
                currentCategoryId = $(this).data('category-id');
                $('#csm-category-name').text($(this).data('category-name'));
                showMessage('', true);
                $('#categorySuppliersModal input').val('');
                new bootstrap.Modal(document.getElementById('categorySuppliersModal')).show();
                load();
            });

            $(document).on('click', '#csm-body .csm-save', function() {
                const tr = $(this).closest('tr');
                const data = {};
                tr.find('.csm-field').each(function() { data[$(this).data('field')] = $(this).val(); });
                if (!$.trim(data.name)) { showMessage('Supplier name is required.', false); return; }
                const btn = $(this).prop('disabled', true);
                api('PUT', url('/' + tr.data('supplier-id')), data)
                    .done(function(res) { showMessage(res.message + ' Applies to all SKUs of this category.', true); })
                    .fail(function(xhr) { showMessage(errText(xhr), false); })
                    .always(function() { btn.prop('disabled', false); });
            });

            $(document).on('click', '#csm-body .csm-remove', function() {
                const tr = $(this).closest('tr');
                const name = tr.find('[data-field="name"]').val();
                if (!confirm('Remove "' + name + '" from this category? It will disappear for all SKUs and parents of this category. The supplier stays in Supplier List.')) return;
                api('DELETE', url('/' + tr.data('supplier-id')))
                    .done(function(res) { showMessage(res.message, true); load(); })
                    .fail(function(xhr) { showMessage(errText(xhr), false); });
            });

            $('#csm-add-new').on('click', function() {
                const data = {};
                fields.forEach(function(f) { data[f] = $('#csm-new-' + f).val(); });
                if (!$.trim(data.name)) { showMessage('Enter a supplier name to add.', false); return; }
                const btn = $(this).prop('disabled', true);
                api('POST', url(), data)
                    .done(function(res) {
                        showMessage(res.message + ' It now applies to all SKUs of this category.', true);
                        fields.forEach(function(f) { $('#csm-new-' + f).val(''); });
                        load();
                    })
                    .fail(function(xhr) { showMessage(errText(xhr), false); })
                    .always(function() { btn.prop('disabled', false); });
            });

            $('#csm-link-existing').on('click', function() {
                const supplierId = $('#csm-existing').val();
                if (!supplierId) { showMessage('Select a supplier to link.', false); return; }
                api('POST', url(), { supplier_id: supplierId })
                    .done(function(res) { showMessage(res.message, true); load(); })
                    .fail(function(xhr) { showMessage(errText(xhr), false); });
            });
        })();
    </script>
    <script>
        // Click a column header to sort the category groups by it: ascending, descending, then original order.
        (function() {
            const table = document.getElementById('category-table-body');
            if (!table) return;
            const headers = table.querySelectorAll('th.sortable-th');
            const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });
            let activeKey = null;
            let direction = 0; // 1 asc, -1 desc, 0 original

            function groups() { return Array.from(table.querySelectorAll('tbody.category-group')); }

            function sortGroups(key, type, dir) {
                const list = groups();
                list.sort(function(a, b) {
                    if (dir === 0) return (+a.dataset.origIndex) - (+b.dataset.origIndex);
                    const av = a.dataset['sort' + key.charAt(0).toUpperCase() + key.slice(1)] || '';
                    const bv = b.dataset['sort' + key.charAt(0).toUpperCase() + key.slice(1)] || '';
                    // Empty values always go last, whichever direction is used.
                    if (av === '' && bv !== '') return 1;
                    if (bv === '' && av !== '') return -1;
                    const cmp = type === 'num' ? (parseFloat(av) || 0) - (parseFloat(bv) || 0) : collator.compare(av, bv);
                    return cmp !== 0 ? dir * cmp : (+a.dataset.origIndex) - (+b.dataset.origIndex);
                });
                list.forEach(function(g) { table.appendChild(g); });
            }

            function updateIcons() {
                headers.forEach(function(h) {
                    const icon = h.querySelector('.sort-icon');
                    const on = h.dataset.sortKey === activeKey && direction !== 0;
                    icon.className = 'mdi sort-icon ' + (!on ? 'mdi-unfold-more-horizontal text-muted'
                        : (direction === 1 ? 'mdi-arrow-up text-primary' : 'mdi-arrow-down text-primary'));
                });
            }

            headers.forEach(function(h) {
                h.addEventListener('click', function() {
                    const key = h.dataset.sortKey;
                    direction = (activeKey === key) ? (direction === 1 ? -1 : (direction === -1 ? 0 : 1)) : 1;
                    activeKey = direction === 0 ? null : key;
                    sortGroups(key, h.dataset.sortType, direction);
                    updateIcons();
                });
            });
        })();
    </script>
    <script>
        // Searchable Category Name dropdown: filter the known categories as you type and pick one; if nothing
        // matches, "+ Add new category" keeps what you typed as a new category name.
        (function() {
            const mfrNames = @json($mfrCategoryNames ?? []);
            const used = @json($usedCategoryNames ?? (object) []); // lower-case name => category id

            // useFilter=false (on focus / when opened) lists every existing category; the list is only
            // narrowed once the user types.
            function render($combo, useFilter) {
                const $input = $combo.find('.cat-combo-input');
                const $menu = $combo.find('.cat-combo-menu').empty();
                const $hint = $combo.find('.cat-combo-hint');
                const typed = $.trim($input.val());
                const typedLower = typed.toLowerCase();
                const filterLower = useFilter ? typedLower : '';
                const currentId = parseInt($combo.data('current-id'), 10) || 0;
                const ownerOf = (nameLower) => (used[nameLower] !== undefined ? used[nameLower] : null);
                let exactInList = false;
                let shown = 0;

                mfrNames.forEach(function(name) {
                    const nameLower = name.toLowerCase();
                    if (filterLower && nameLower.indexOf(filterLower) === -1) return;
                    if (shown >= 100) return;
                    if (nameLower === typedLower) exactInList = true;
                    const owner = ownerOf(nameLower);
                    const taken = owner !== null && owner !== currentId;
                    const $item = $('<button type="button" class="list-group-item list-group-item-action py-1 cat-combo-item"></button>')
                        .text(name).attr('data-name', name);
                    if (taken) {
                        $item.addClass('disabled text-muted').attr('data-taken', '1')
                            .append($('<span class="badge bg-secondary-subtle text-dark ms-2"></span>').text('already added'));
                    }
                    $menu.append($item);
                    shown++;
                });

                const ownerOfTyped = ownerOf(typedLower);
                const typedTaken = typed !== '' && ownerOfTyped !== null && ownerOfTyped !== currentId;
                // The "new category" option is always the first row of the dropdown.
                if (typed !== '' && useFilter && !exactInList && !typedTaken) {
                    $menu.prepend($('<button type="button" class="list-group-item list-group-item-action py-1 text-primary fw-semibold cat-combo-new"></button>')
                        .text('+ Add new category "' + typed + '"').attr('data-name', typed));
                } else {
                    $menu.prepend($('<button type="button" class="list-group-item list-group-item-action py-1 text-primary fw-semibold cat-combo-new" data-clear="1"></button>')
                        .text('+ Add new category...'));
                }
                if (!shown) {
                    $menu.append('<div class="list-group-item text-muted py-1">No existing category matches.</div>');
                }

                const isOwn = ownerOfTyped !== null && ownerOfTyped === currentId;
                if (typed === '') $hint.text('');
                else if (typedTaken) $hint.addClass('text-danger').text('A category with this name already exists.');
                else if (isOwn || exactInList) $hint.removeClass('text-danger').text('');
                else $hint.removeClass('text-danger').text('New category (not in the CP Master MFR category list).');
                return $menu;
            }

            function open($combo, useFilter) { render($combo, !!useFilter).show(); }
            function close($combo) { $combo.find('.cat-combo-menu').hide(); }

            $(document).on('focus click', '.cat-combo-input', function() { open($(this).closest('.cat-combo'), false); });
            $(document).on('input', '.cat-combo-input', function() { open($(this).closest('.cat-combo'), true); });

            $(document).on('mousedown', '.cat-combo-item, .cat-combo-new', function(e) {
                e.preventDefault(); // keep focus; avoids closing before the click is handled
                const $item = $(this);
                if ($item.attr('data-taken')) return;
                const $combo = $item.closest('.cat-combo');
                const $input = $combo.find('.cat-combo-input');
                if ($item.attr('data-clear')) {
                    // "+ Add new category...": empty the field so a new name can be typed
                    $input.val('').trigger('focus');
                    open($combo, true);
                    return;
                }
                $input.val($item.attr('data-name'));
                render($combo, true);
                close($combo);
            });

            $(document).on('click', function(e) {
                $('.cat-combo').each(function() {
                    if (!$.contains(this, e.target)) close($(this));
                });
            });
            $(document).on('keydown', '.cat-combo-input', function(e) {
                if (e.key === 'Escape') close($(this).closest('.cat-combo'));
            });
        })();
    </script>
    <script>
        (function() {
            const searchUrl = '{{ url('/category/sku-search') }}';
            const addUrl = '{{ url('/category/rows/add') }}';
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const $modal = $('#addProductsModal');
            let targets = [];           // [{id, name}]
            let selected = new Map();   // sku -> true (kept across searches)
            let shown = [];             // items currently listed
            let timer = null;
            let reqSeq = 0;

            function esc(v) { return $('<div>').text(v == null ? '' : v).html(); }

            function selectableShown() { return shown.filter(function(i) { return !i.already; }); }

            function refreshCounts() {
                const n = selected.size;
                const sel = selectableShown();
                const selShown = sel.filter(function(i) { return selected.has(i.sku); }).length;
                $('#apm-shown').text(shown.length);
                $('#apm-selected').text(n);
                $('#apm-submit-count').text(n);
                $('#apm-submit').prop('disabled', n === 0);
                const all = $('#apm-select-all');
                all.prop('disabled', sel.length === 0);
                all.prop('checked', sel.length > 0 && selShown === sel.length);
                all.prop('indeterminate', selShown > 0 && selShown < sel.length);
            }

            function render() {
                const $list = $('#apm-list').empty();
                if (!shown.length) {
                    $list.append('<div class="list-group-item text-muted small">No SKUs found.</div>');
                }
                shown.forEach(function(item, idx) {
                    const id = 'apm-item-' + idx;
                    const $row = $('<label class="list-group-item d-flex align-items-center gap-2 mb-0"></label>')
                        .attr('for', id).css('cursor', item.already ? 'not-allowed' : 'pointer');
                    const $cb = $('<input type="checkbox" class="form-check-input apm-item mt-0">')
                        .attr('id', id).attr('data-sku', item.sku)
                        .prop('checked', selected.has(item.sku) || false).prop('disabled', !!item.already);
                    const $txt = $('<span class="flex-grow-1"></span>').html(
                        '<span class="fw-semibold">' + esc(item.sku) + '</span>' +
                        (item.parent ? ' <span class="text-muted small">&middot; ' + esc(item.parent) + '</span>' : '') +
                        (item.already ? ' <span class="badge bg-secondary-subtle text-secondary ms-1">already in category</span>' : ''));
                    if (item.already) $row.addClass('text-muted bg-light');
                    $row.append($cb, $txt);
                    $list.append($row);
                });
                refreshCounts();
            }

            function search() {
                const seq = ++reqSeq;
                const params = { q: $('#apm-search').val() };
                if (targets.length === 1) params.category_id = targets[0].id;
                $('#apm-list').html('<div class="list-group-item text-muted small">Searching...</div>');
                $.getJSON(searchUrl, params).done(function(res) {
                    if (seq !== reqSeq) return;
                    shown = res.items || [];
                    render();
                }).fail(function() {
                    if (seq !== reqSeq) return;
                    shown = [];
                    $('#apm-list').html('<div class="list-group-item text-danger small">Could not load SKUs.</div>');
                    refreshCounts();
                });
            }

            function openModal(list) {
                targets = list;
                selected = new Map();
                shown = [];
                $('#apm-search').val('');
                $('#apm-error').hide();
                if (list.length === 1) {
                    $('#apm-kind').text('category');
                    $('#apm-target').text(list[0].name);
                    $('#apm-note').text('The selected SKUs will be linked to this category.');
                } else {
                    $('#apm-kind').text('');
                    $('#apm-target').text(list.length + ' categories');
                    $('#apm-note').text('The selected SKUs will be added to: ' + list.map(function(c) { return c.name; }).join(', '));
                }
                refreshCounts();
                bootstrap.Modal.getOrCreateInstance($modal[0]).show();
                search();
                setTimeout(function() { $('#apm-search').trigger('focus'); }, 300);
            }

            // Per-row button
            $(document).on('click', '.add-products-btn', function() {
                openModal([{ id: $(this).data('category-id'), name: String($(this).data('category-name')) }]);
            });

            $('#apm-search').on('input', function() {
                clearTimeout(timer);
                timer = setTimeout(search, 250);
            });

            $(document).on('change', '.apm-item', function() {
                const sku = String($(this).data('sku'));
                if (this.checked) selected.set(sku, true); else selected.delete(sku);
                refreshCounts();
            });

            $('#apm-select-all').on('change', function() {
                const on = this.checked;
                selectableShown().forEach(function(i) { if (on) selected.set(i.sku, true); else selected.delete(i.sku); });
                $('.apm-item:not(:disabled)').prop('checked', on);
                refreshCounts();
            });

            $('#apm-clear').on('click', function(e) {
                e.preventDefault();
                selected.clear();
                $('.apm-item').prop('checked', false);
                refreshCounts();
            });

            $('#apm-submit').on('click', function() {
                if (!selected.size) return;
                const $btn = $(this).prop('disabled', true);
                $('#apm-error').hide();
                $.ajax({
                    url: addUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        _token: csrf,
                        category_ids: targets.map(function(c) { return c.id; }),
                        skus: Array.from(selected.keys())
                    }
                }).done(function() {
                    location.reload();
                }).fail(function(xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.';
                    $('#apm-error').text(msg).show();
                    $btn.prop('disabled', false);
                });
            });
        })();
    </script>
    <script>
        // Parent / SKU / MFR category filters apply by themselves: after you stop typing (or press Enter)
        // the page reloads with the filter, and the cursor goes back to the field you were typing in.
        (function() {
            const form = document.getElementById('cp-filter-form');
            if (!form) return;
            const inputs = form.querySelectorAll('input[type="text"]');
            let timer = null;
            const submit = function() { clearTimeout(timer); form.submit(); };

            inputs.forEach(function(input) {
                let last = input.value;
                input.addEventListener('input', function() {
                    if (input.value === last) return;
                    last = input.value;
                    try { sessionStorage.setItem('categoryFilterFocus', input.id); } catch (e) {}
                    clearTimeout(timer);
                    timer = setTimeout(submit, 600);
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    try { sessionStorage.setItem('categoryFilterFocus', input.id); } catch (err) {}
                    submit();
                });
            });

            try {
                const id = sessionStorage.getItem('categoryFilterFocus');
                sessionStorage.removeItem('categoryFilterFocus');
                const el = id ? document.getElementById(id) : null;
                if (el) {
                    el.focus();
                    el.setSelectionRange(el.value.length, el.value.length);
                }
            } catch (e) {}
        })();
    </script>
    <script>
        // Uncategorised rows (Parent / SKU search): add the SKU to a category picked from the list.
        (function() {
            const modalEl = document.getElementById('assignCategoryModal');
            if (!modalEl) return;
            let sku = '';
            $(document).on('click', '.assign-category-btn', function() {
                sku = String($(this).data('sku'));
                $('#acm-sku').text(sku);
                $('#acm-category').val('');
                $('#acm-error').hide();
                $('#acm-submit').prop('disabled', true);
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
            $('#acm-category').on('change', function() {
                $('#acm-submit').prop('disabled', !$(this).val());
            });
            $('#acm-submit').on('click', function() {
                const categoryId = $('#acm-category').val();
                if (!categoryId || !sku) return;
                const $btn = $(this).prop('disabled', true);
                $.ajax({
                    url: '{{ url('/category/rows/add') }}',
                    method: 'POST',
                    dataType: 'json',
                    data: { _token: $('meta[name="csrf-token"]').attr('content'), category_ids: [categoryId], skus: [sku] }
                }).done(function() {
                    location.reload();
                }).fail(function(xhr) {
                    $('#acm-error').text(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Server error occurred.').show();
                    $btn.prop('disabled', false);
                });
            });
        })();
    </script>
@endsection
