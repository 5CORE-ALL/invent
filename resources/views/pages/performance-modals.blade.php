<!-- Add/Edit Designation Modal -->
<div class="modal fade" id="designationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="designationModalTitle">Add Designation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="designation-form">
                <div class="modal-body">
                    <input type="hidden" id="designation-id">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="designation-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="designation-description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="designation-is-active" checked>
                            <label class="form-check-label" for="designation-is-active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="category-form">
                <div class="modal-body">
                    <input type="hidden" id="category-id">
                    <input type="hidden" id="category-designation-id">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="category-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea id="category-description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order</label>
                        <input type="number" id="category-order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add/Edit Checklist Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">Add Checklist Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="item-form">
                <div class="modal-body">
                    <input type="hidden" id="item-id">
                    <input type="hidden" id="item-category-id">
                    <div class="mb-3">
                        <label class="form-label">Question <span class="text-danger">*</span></label>
                        <textarea id="item-question" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Weight <span class="text-danger">*</span></label>
                        <input type="number" id="item-weight" class="form-control" value="1.00" step="0.1" min="0.1" max="10" required>
                        <small class="text-muted">Weight for scoring calculation (default: 1.00)</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order</label>
                        <input type="number" id="item-order" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Review Modal -->
<div class="modal fade" id="viewReviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Performance Review Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="review-details-content">
                <p class="text-muted">Loading...</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Import CSV Modal -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Checklist Items from CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="import-csv-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="ri-information-line me-2"></i>
                        <strong>CSV Format:</strong> The CSV file should have the following columns:
                        <ul class="mb-0 mt-2">
                            <li><strong>Category Name</strong> (required)</li>
                            <li><strong>Question</strong> (required)</li>
                            <li><strong>Weight</strong> (optional, default: 1.00)</li>
                            <li><strong>Order</strong> (optional, default: 0)</li>
                            <li><strong>Category Description</strong> (optional)</li>
                        </ul>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" id="csv-file-input" class="form-control" accept=".csv" required>
                        <small class="text-muted">Only CSV files are allowed</small>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="csv-update-existing" checked>
                            <label class="form-check-label" for="csv-update-existing">
                                Update existing categories/items if they exist
                            </label>
                        </div>
                    </div>
                    <div id="import-progress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%">Importing...</div>
                        </div>
                    </div>
                    <div id="import-results" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-upload-line me-1"></i>Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
