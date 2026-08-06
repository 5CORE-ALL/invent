{{-- Shared Add Product modal (same as Product Master page) --}}
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content"
             style="border: none; border-radius: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header"
                 style="background: linear-gradient(135deg, #6B73FF 0%, #000DFF 100%); border-bottom: 4px solid #4D55E6; padding: 1.5rem; border-radius: 0;">
                <h5 class="modal-title" id="addProductModalLabel"
                    style="color: white; font-weight: 800; font-size: 1.8rem; letter-spacing: 0.5px;">
                    <i class="fas fa-plus-circle me-2"></i>ADD NEW PRODUCT LISTING
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body" style="background-color: #F8FAFF; padding: 2rem;">
                <div id="form-errors" class="mb-3"></div>
                <form id="addProductForm">
                    <div class="row mb-5">
                        <div class="col-md">
                            <div class="form-group">
                                <label for="sku" class="form-label fw-bold" style="color: #4A5568;">SKU <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sku" placeholder="Enter SKU"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="form-group">
                                <label for="parent" class="form-label fw-bold" style="color: #4A5568;">Parent</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="parent"
                                           placeholder="Enter or select parent"
                                           style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;"
                                           list="parentOptions">
                                    <datalist id="parentOptions"></datalist>
                                </div>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="form-group">
                                <label for="status" class="form-label fw-bold" style="color: #4A5568;">Status</label>
                                <select class="form-control" id="status" name="status"
                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                    <option value="">Select Status</option>
                                    <option value="active">🟢 Active</option>
                                    <option value="inactive">🔴 Inactive</option>
                                    <option value="DC">🔴 DC</option>
                                    <option value="upcoming">🟡 Coming</option>
                                    <option value="2BDC">🔵 2BDC</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md">
                            <div class="form-group">
                                <label for="unit" class="form-label fw-bold" style="color: #4A5568;">Unit <span class="text-danger">*</span></label>
                                <select class="form-control" id="unit" name="unit" required
                                        style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                    <option value="">Select Unit</option>
                                    <option value="Pieces">PCs</option>
                                    <option value="Pair">Pair</option>
                                    <option value="Set">Set</option>
                                </select>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="cp" class="form-label fw-bold" style="color: #4A5568;">CP</label>
                                <input type="text" class="form-control" id="cp" placeholder="Enter cp"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: #EDF2F7;"
                                       readonly>
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="lp" class="form-label fw-bold" style="color: #4A5568;">LP</label>
                                <input type="text" class="form-control" id="lp" placeholder="Enter LP"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: #EDF2F7;"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-3" hidden>
                            <div class="form-group">
                                <label for="lps" class="form-label fw-bold" style="color: #4A5568;">LPS</label>
                                <input type="text" class="form-control" id="lps" placeholder="Enter LPS"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: #EDF2F7;"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="wtAct" class="form-label fw-bold" style="color: #4A5568;">WT ACT</label>
                                <input type="text" class="form-control" id="wtAct" placeholder="Enter WT ACT"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="upc" class="form-label fw-bold" style="color: #4A5568;">UPC</label>
                                <input type="text" class="form-control" id="upc" placeholder="Enter UPC"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="b" class="form-label fw-bold" style="color: #4A5568;">B</label>
                                <input type="text" class="form-control" id="b" placeholder="Enter b"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="h1" class="form-label fw-bold" style="color: #4A5568;">H1</label>
                                <input type="text" class="form-control" id="h1" placeholder="Enter h1"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="l2Url" class="form-label fw-bold" style="color: #4A5568;">Url</label>
                                <input type="text" class="form-control" id="l2Url" placeholder="Enter Url"
                                       style="border: 2px solid #E2E8F0; border-radius: 6px; padding: 0.75rem; background-color: white;">
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="dc" class="form-label fw-bold" style="color: #4A5568;">DC</label>
                                <input type="text" class="form-control" id="dc" placeholder="DC" disabled>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="weight" class="form-label fw-bold" style="color: #4A5568;">Weight</label>
                                <input type="text" class="form-control" id="weight" placeholder="Weight" disabled>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="msrp" class="form-label fw-bold" style="color: #4A5568;">MSRP</label>
                                <input type="text" class="form-control" id="msrp" placeholder="MSRP" disabled>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="map" class="form-label fw-bold" style="color: #4A5568;">MAP</label>
                                <input type="text" class="form-control" id="map" placeholder="MAP" disabled>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="productImage" class="form-label fw-bold" style="color: #4A5568;">Product Image</label>
                                <input type="file" class="form-control" id="productImage" name="image" accept="image/*">
                                <div id="imagePreview" class="mt-2"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="modal-footer"
                 style="background: linear-gradient(135deg, #F8FAFF 0%, #E6F0FF 100%); border-top: 4px solid #E2E8F0; padding: 1.5rem; border-radius: 0;">
                <button type="button" class="btn btn-lg" data-bs-dismiss="modal"
                        style="background: linear-gradient(135deg, #FF6B6B 0%, #FF0000 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-lg" id="saveProductBtn"
                        style="background: linear-gradient(135deg, #4ADE80 0%, #22C55E 100%); color: white; border: none; border-radius: 6px; padding: 0.75rem 2rem; font-weight: 700; letter-spacing: 0.5px;">
                    <i class="fas fa-save me-2"></i>Save Product
                </button>
            </div>
        </div>
    </div>
</div>
