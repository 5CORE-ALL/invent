<!-- Global Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-tags me-2"></i> 
                    <span id="modalCategoryTitle">Category</span>
                </h5>
                <button id="toggleModalFullscreen" type="button" class="btn btn-sm btn-outline-secondary me-2" title="Toggle Fullscreen">
                    <i class="fas fa-expand"></i>
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <!-- Stats -->
                <div class="row g-3 mb-4 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light rounded shadow-sm">
                            <h5 id="categoryTotalProducts">0</h5>
                            <small>Total Products</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light rounded shadow-sm">
                            <h5 id="categoryTotalValue">$0.00</h5>
                            <small>Total Value</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light rounded shadow-sm">
                            <h5 id="categoryAvgPrice">$0.00</h5>
                            <small>Avg Price</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-3 bg-light rounded shadow-sm">
                            <h5 id="categoryLiveProducts">0</h5>
                            <small>Live Products</small>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="table-responsive">
                    <table class="table table-bordered align-middle text-center">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>SKU</th>
                                <th>Title</th>
                                <th>Platform</th>
                                <th>Status</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Value</th>
                                <th>Live</th>
                            </tr>
                        </thead>
                        <tbody id="categoryProductsTableBody"></tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="5" class="text-end">TOTALS:</th>
                                <th id="categoryTotalPrice">$0.00</th>
                                <th id="categoryTotalQuantity">0</th>
                                <th id="categoryTotalValueFooter">$0.00</th>
                                <th>-</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Platform Distribution -->
                <div class="mt-4">
                    <h6>Platform Distribution</h6>
                    <div id="categoryPlatformStats" class="ps-2"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
