@forelse($categories as $key => $category)
<tr>
    <td class="text-center">
        <div class="form-check d-flex justify-content-center align-items-center">
            <input type="checkbox" 
                   class="form-check-input category-checkbox" 
                   id="category-{{ $category->id }}"
                   value="{{ $category->id }}"
                   style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex justify-content-center align-items-center">
            <div>
                <h5 class="mb-0 fw-semibold">{{ $category->name }}</h5>
            </div>
        </div>
    </td>
    <td class="text-center">
        <div class="d-flex justify-content-center align-items-center">
            <div class="d-inline-flex align-items-center px-3 py-2 rounded-pill bg-info-subtle shadow-sm">
                <i class="mdi mdi-account-group text-info me-2"></i>
                <span class="fw-semibold text-info">{{ $category->supplier_count }}</span>
                <span class="ms-1 text-muted medium fw-bold">Suppliers</span>
            </div>
        </div>
    </td>
    <td class="text-center">
        <span
            class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} px-3 py-2 rounded-pill">
            {{ ucfirst($category->status) }}
        </span>
    </td>
    <td class="text-center">
    <div class="d-flex justify-content-center align-items-center gap-1">
        <a href="#" class="btn btn-soft-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editCategoryModal{{ $category->id }}" title="Edit">
            <i class="mdi mdi-square-edit-outline" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"></i>
        </a>

        <form action="{{ route('category.delete', $category->id) }}" method="POST" class="delete-category-form"
            onsubmit="return confirm('Are you sure you want to delete this category?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-soft-danger btn-sm" data-bs-toggle="tooltip" title="Delete">
                <i class="mdi mdi-delete"></i>
            </button>
        </form>
    </div>
</td>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal{{$category->id}}" class="modal fade" tabindex="-1" aria-labelledby="editCategoryModal"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered shadow-none">
            <div class="modal-content border-0">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="mdi mdi-plus-circle me-1"></i> <span id="modalTitle">Edit Category</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form action="{{ route('category.create') }}" method="POST" id="addCategoryForm">
                    @csrf
                    <input type="hidden" name="category_id" value="{{ $category->id }}">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="category_name_{{ $category->id }}"
                                class="form-label fw-semibold text-start d-block">Category Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="category_name_{{ $category->id }}"
                                name="category_name" placeholder="Enter category name" value="{{ $category->name }}">
                        </div>
                        <div class="mb-3">
                            <label for="category_status_{{ $category->id }}"
                                class="form-label fw-semibold text-start d-block">Status </label>
                            <select class="form-select" id="category_status_{{ $category->id }}" name="status">
                                <option value="" disabled>Select Status</option>
                                <option value="active" @if($category->status=='active') selected @endif>Active</option>
                                <option value="inactive" @if($category->status=='inactive') selected @endif>Inactive
                                </option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-content-save me-1"></i> Update
                                Category
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</tr>
@empty
<tr>
    <td colspan="4" class="text-center">No categories found</td>
</tr>
@endforelse