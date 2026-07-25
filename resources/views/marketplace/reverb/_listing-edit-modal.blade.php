{{-- Reverb View Listing editor modal --}}
<style>
    #reverbListingEditModal .modal-dialog { max-width: min(1140px, 96vw); margin: 1rem auto; }
    #reverbListingEditModal .modal-content { border: 0; border-radius: 8px; overflow: hidden; }
    #reverbListingEditModal .rv-modal-header {
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        padding: 0.85rem 1.25rem;
    }
    #reverbListingEditModal .rv-modal-header h5 {
        font-weight: 700;
        margin: 0;
        letter-spacing: -0.01em;
    }
    #reverbListingEditModal .rv-accent {
        height: 3px;
        background: #0d9488;
    }
    #reverbListingEditModal .rv-actions {
        background: #f8fafb;
        border-bottom: 1px solid #e5e7eb;
        padding: 0.65rem 1.25rem;
        gap: 0.5rem;
    }
    #reverbListingEditModal .btn-rv-teal {
        background: #0d9488;
        border-color: #0d9488;
        color: #fff;
    }
    #reverbListingEditModal .btn-rv-teal:hover { background: #0f766e; border-color: #0f766e; color: #fff; }
    #reverbListingEditModal .rv-nav-tabs .nav-link {
        color: #374151;
        font-weight: 600;
        border: 0;
        border-bottom: 3px solid transparent;
        border-radius: 0;
        padding: 0.75rem 1rem;
    }
    #reverbListingEditModal .rv-nav-tabs .nav-link.active {
        color: #0f766e;
        background: #fff;
        border-bottom-color: #0d9488;
    }
    #reverbListingEditModal .rv-section-alert,
    #reverbListingEditModal .rv-field-alert,
    #reverbListingEditModal .rv-header-alert-icon {
        color: #dc2626 !important;
        font-size: 1.05rem;
        font-style: normal;
        font-family: Arial, Helvetica, sans-serif;
        font-weight: 700;
        line-height: 1;
        vertical-align: middle;
        display: none !important;
    }
    #reverbListingEditModal .rv-section-alert::before,
    #reverbListingEditModal .rv-field-alert::before,
    #reverbListingEditModal .rv-header-alert-icon::before {
        content: none !important;
    }
    #reverbListingEditModal .rv-section-alert.rv-alert-on,
    #reverbListingEditModal .rv-field-alert.rv-alert-on,
    #reverbListingEditModal .rv-header-alert-icon.rv-alert-on {
        display: inline-block !important;
    }
    #reverbListingEditModal .rv-header-issues {
        display: none;
        align-items: flex-start;
        gap: 0.5rem;
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        border-radius: 6px;
        padding: 0.55rem 0.75rem;
        font-size: 0.85rem;
        margin-bottom: 0.65rem;
    }
    #reverbListingEditModal .rv-header-issues.rv-alert-on {
        display: flex !important;
    }
    #reverbListingEditModal .rv-header-issues ul {
        margin: 0.15rem 0 0;
        padding-left: 1.1rem;
    }
    #reverbListingEditModal .form-label .rv-field-alert {
        margin-left: 0.35rem;
    }
    #reverbListingEditModal .is-rv-invalid {
        border-color: #dc2626 !important;
        box-shadow: 0 0 0 0.15rem rgba(220, 38, 38, 0.15);
    }
    #reverbListingEditModal .rv-photo-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
        gap: 0.5rem;
    }
    #reverbListingEditModal .rv-photo-card {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        background: #fff;
        padding: 0.35rem;
        position: relative;
    }
    #reverbListingEditModal .rv-photo-card img {
        width: 100%;
        height: 72px;
        object-fit: contain;
        background: #f3f4f6;
        border-radius: 4px;
    }
    #reverbListingEditModal .rv-photo-card .btn-remove {
        position: absolute;
        top: 2px;
        right: 2px;
        padding: 0 0.3rem;
        line-height: 1.2;
        font-size: 0.7rem;
    }
    #reverbListingEditModal .rv-field-error {
        color: #dc2626;
        font-size: 0.75rem;
        display: none;
        margin-top: 0.25rem;
    }
    #reverbListingEditModal .rv-field-error.rv-alert-on { display: block !important; }
    #reverbListingEditModal .form-label { font-weight: 600; font-size: 0.8rem; color: #374151; }
    #reverbListingEditModal .rv-status-bar { font-size: 0.85rem; }
    #reverbListingEditModal .modal-body { max-height: calc(100vh - 210px); overflow-y: auto; background: #f3f4f6; }
    #reverbListingEditModal .rv-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 1rem 1.15rem;
    }
</style>

<div class="modal fade" id="reverbListingEditModal" tabindex="-1" aria-labelledby="reverbListingEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="rv-accent"></div>
            <div class="rv-modal-header d-flex justify-content-between align-items-start gap-2">
                <div class="flex-grow-1">
                    <h5 id="reverbListingEditModalLabel">
                        View Listing
                        <span class="rv-header-alert-icon" id="rvHeaderAlertIcon" title="Listing has missing or invalid fields" aria-hidden="true">▲</span>
                    </h5>
                    <div class="text-muted small mt-1">
                        SKU: <code id="rvEditorSku">—</code>
                        <span class="mx-1">·</span>
                        Reverb ID: <span id="rvEditorListingId">—</span>
                        <span class="mx-1">·</span>
                        <span id="rvEditorState" class="badge bg-light text-muted">—</span>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="rv-actions d-flex flex-wrap align-items-center">
                <button type="button" class="btn btn-sm btn-outline-primary" id="rvBtnPull">
                    <i class="ri-download-2-line"></i> Pull from Reverb
                </button>
                <button type="button" class="btn btn-sm btn-rv-teal" id="rvBtnPush">
                    <i class="ri-upload-2-line"></i> Push to Reverb
                </button>
                <button type="button" class="btn btn-sm btn-warning" id="rvBtnAutopopulateMissing" title="Fill only blank fields from Product Master pages">
                    <i class="ri-magic-line"></i> Autopopulate Missing Data
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="rvBtnPullPm" data-section="full">
                        <i class="ri-database-2-line"></i> PULL From Product Master
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Product Master pages</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item rv-pm-section" href="#" data-section="full">Full Listing</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rv-pm-section" href="#" data-section="title">Title (Title Master)</a></li>
                        <li><a class="dropdown-item rv-pm-section" href="#" data-section="images">Images (Image Master)</a></li>
                        <li><a class="dropdown-item rv-pm-section" href="#" data-section="bullets">Bullet Points (/bullet-points)</a></li>
                        <li><a class="dropdown-item rv-pm-section" href="#" data-section="description">Description (Description Master)</a></li>
                        <li><a class="dropdown-item rv-pm-section" href="#" data-section="videos">Videos (Video Master)</a></li>
                        <li><a class="dropdown-item rv-pm-section" href="#" data-section="details">Details (Reverb Listing Master)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('reverb.listing.master') }}" target="_blank" rel="noopener">Open Reverb Listing Master ↗</a></li>
                    </ul>
                </div>
                <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-link text-decoration-none ms-auto" id="rvEditorListingLink" style="display:none;">
                    Open on Reverb <i class="ri-external-link-line"></i>
                </a>
            </div>

            <div class="px-3 pt-2 pb-0 bg-white border-bottom">
                <div id="rvEditorStatus" class="rv-status-bar text-muted mb-2">Select a linked listing to edit.</div>
                <div id="rvHeaderIssues" class="rv-header-issues" role="alert">
                    <span class="rv-header-alert-icon rv-alert-on" aria-hidden="true">▲</span>
                    <div>
                        <strong id="rvHeaderIssuesTitle">Missing or invalid Reverb fields</strong>
                        <ul id="rvHeaderIssuesList"></ul>
                    </div>
                </div>
                <ul class="nav rv-nav-tabs" id="rvEditorTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="rv-tab-media" data-bs-toggle="tab" data-bs-target="#rv-pane-media" type="button" role="tab">
                            Photos &amp; Videos <span class="rv-section-alert" data-section="media" title="Issues in Photos &amp; Videos" aria-label="Issues in Photos &amp; Videos">▲</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rv-tab-details" data-bs-toggle="tab" data-bs-target="#rv-pane-details" type="button" role="tab">
                            Details <span class="rv-section-alert" data-section="details" title="Issues in Details" aria-label="Issues in Details">▲</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rv-tab-pricing" data-bs-toggle="tab" data-bs-target="#rv-pane-pricing" type="button" role="tab">
                            Pricing &amp; Inventory <span class="rv-section-alert" data-section="pricing" title="Issues in Pricing &amp; Inventory" aria-label="Issues in Pricing &amp; Inventory">▲</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rv-tab-description" data-bs-toggle="tab" data-bs-target="#rv-pane-description" type="button" role="tab">
                            Description <span class="rv-section-alert" data-section="description" title="Issues in Description" aria-label="Issues in Description">▲</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rv-tab-shipping" data-bs-toggle="tab" data-bs-target="#rv-pane-shipping" type="button" role="tab">
                            Shipping <span class="rv-section-alert" data-section="shipping" title="Issues in Shipping" aria-label="Issues in Shipping">▲</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="modal-body">
                <form id="rvListingEditorForm" autocomplete="off" onsubmit="return false;">
                    <input type="hidden" id="rv_shopify_sku_id" value="">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="rv-pane-media" role="tabpanel">
                            <div class="rv-panel mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Photos <span class="text-muted fw-normal">(min 11, max 25)</span>
                                        <span class="rv-field-alert" data-field="photos" title="Need at least 11 images" aria-label="Need at least 11 images">▲</span>
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="rvAddPhoto">+ Add photo URL</button>
                                </div>
                                <div id="rvPhotoGrid" class="rv-photo-grid mb-2"></div>
                                <div class="rv-field-error" data-field="photos"></div>
                                <div id="rvPhotoInputs" class="d-flex flex-column gap-1"></div>
                            </div>
                            <div class="rv-panel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0">Videos <span class="text-muted fw-normal">(min 1, max 3)</span>
                                        <span class="rv-field-alert" data-field="videos" title="Need at least 1 video" aria-label="Need at least 1 video">▲</span>
                                    </label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="rvAddVideo">+ Add video URL</button>
                                </div>
                                <div id="rvVideoInputs" class="d-flex flex-column gap-1"></div>
                                <div class="rv-field-error" data-field="videos"></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="rv-pane-details" role="tabpanel">
                            <div class="rv-panel">
                                <div class="mb-3">
                                    <label class="form-label" for="rv_title">Title
                                        <span class="rv-field-alert" data-field="title" title="Title required" aria-label="Title required">▲</span>
                                    </label>
                                    <input type="text" class="form-control" id="rv_title" name="title" data-rv-field="title">
                                    <div class="rv-field-error" data-field="title"></div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label" for="rv_make">Make
                                            <span class="rv-field-alert" data-field="make" title="Make required" aria-label="Make required">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_make" name="make" data-rv-field="make">
                                        <div class="rv-field-error" data-field="make"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="rv_model">Model
                                            <span class="rv-field-alert" data-field="model" title="Model required" aria-label="Model required">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_model" name="model" data-rv-field="model">
                                        <div class="rv-field-error" data-field="model"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="rv_finish">Finish
                                            <span class="rv-field-alert" data-field="finish" title="Finish is blank" aria-label="Finish is blank">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_finish" name="finish" data-rv-field="finish">
                                        <div class="rv-field-error" data-field="finish"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="rv_year">Year
                                            <span class="rv-field-alert" data-field="year" title="Year is blank" aria-label="Year is blank">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_year" name="year" placeholder="e.g. 2020 or 1980s" data-rv-field="year">
                                        <div class="rv-field-error" data-field="year"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="rv_condition_name">Condition
                                            <span class="rv-field-alert" data-field="condition" title="Condition required" aria-label="Condition required">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_condition_name" name="condition_name" placeholder="Brand New, Excellent…" data-rv-field="condition">
                                        <input type="hidden" id="rv_condition_uuid" name="condition_uuid">
                                        <div class="rv-field-error" data-field="condition"></div>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label" for="rv_category_name">Category
                                            <span class="rv-field-alert" data-field="category" title="Category is blank" aria-label="Category is blank">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_category_name" name="category_name" data-rv-field="category">
                                        <input type="hidden" id="rv_category_uuid" name="category_uuid">
                                        <div class="rv-field-error" data-field="category"></div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="rv_sku_field">SKU
                                            <span class="rv-field-alert" data-field="sku" title="SKU is blank" aria-label="SKU is blank">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_sku_field" name="sku" data-rv-field="sku">
                                        <div class="rv-field-error" data-field="sku"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="rv_upc">UPC / EAN
                                            <span class="rv-field-alert" data-field="upc" title="UPC is blank" aria-label="UPC is blank">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_upc" name="upc" data-rv-field="upc">
                                        <div class="rv-field-error" data-field="upc"></div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end gap-3 pb-1 flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rv_upc_does_not_apply">
                                            <label class="form-check-label" for="rv_upc_does_not_apply">UPC does not apply</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rv_handmade">
                                            <label class="form-check-label" for="rv_handmade">Handmade</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rv_offers_enabled" checked>
                                            <label class="form-check-label" for="rv_offers_enabled">Offers enabled</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="rv-pane-pricing" role="tabpanel">
                            <div class="rv-panel">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label" for="rv_price_amount">Price
                                            <span class="rv-field-alert" data-field="price" title="Price required" aria-label="Price required">▲</span>
                                        </label>
                                        <input type="number" step="0.01" min="0" class="form-control" id="rv_price_amount" name="price_amount" data-rv-field="price">
                                        <div class="rv-field-error" data-field="price"></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="rv_price_currency">Currency
                                            <span class="rv-field-alert" data-field="currency" title="Currency is blank" aria-label="Currency is blank">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_price_currency" name="price_currency" value="USD" data-rv-field="currency">
                                        <div class="rv-field-error" data-field="currency"></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="rv_inventory">Inventory
                                            <span class="rv-field-alert" data-field="inventory" title="Inventory issue" aria-label="Inventory issue">▲</span>
                                        </label>
                                        <input type="number" step="1" min="0" class="form-control" id="rv_inventory" name="inventory" data-rv-field="inventory">
                                        <div class="rv-field-error" data-field="inventory"></div>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end pb-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rv_has_inventory" checked>
                                            <label class="form-check-label" for="rv_has_inventory">Has inventory</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="rv-pane-description" role="tabpanel">
                            <div class="rv-panel mb-3">
                                <label class="form-label" for="rv_description">Description (HTML allowed)
                                    <span class="rv-field-alert" data-field="description" title="Description required" aria-label="Description required">▲</span>
                                </label>
                                <textarea class="form-control font-monospace" id="rv_description" name="description" rows="12" data-rv-field="description"></textarea>
                                <div class="rv-field-error" data-field="description"></div>
                            </div>
                            <div class="rv-panel">
                                <label class="form-label" for="rv_bullets">Highlighted features / bullets
                                    <span class="text-muted fw-normal">(from <a href="{{ url('/bullet-points') }}" target="_blank" rel="noopener">Bullet Points</a> · one per line)</span>
                                    <span class="rv-field-alert" data-field="bullets" title="Bullets are blank — fill on /bullet-points" aria-label="Bullets are blank">▲</span>
                                </label>
                                <textarea class="form-control" id="rv_bullets" name="bullets" rows="5" placeholder="Loaded from Bullet Points Master (/bullet-points)" data-rv-field="bullets"></textarea>
                                <div class="rv-field-error" data-field="bullets"></div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="rv-pane-shipping" role="tabpanel">
                            <div class="rv-panel">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label" for="rv_shipping_profile_id">Shipping profile ID
                                            <span class="rv-field-alert" data-field="shipping" title="Shipping is blank" aria-label="Shipping is blank">▲</span>
                                        </label>
                                        <input type="text" class="form-control" id="rv_shipping_profile_id" name="shipping_profile_id" data-rv-field="shipping">
                                        <div class="rv-field-error" data-field="shipping"></div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end pb-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rv_local_pickup_only">
                                            <label class="form-check-label" for="rv_local_pickup_only">Local pickup only</label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="rv_shipping_rates_json">Shipping rates (JSON)
                                            <span class="rv-field-alert" data-field="shipping" title="Shipping is blank" aria-label="Shipping is blank">▲</span>
                                        </label>
                                        <textarea class="form-control font-monospace" id="rv_shipping_rates_json" rows="6" placeholder='[{"region_code":"US_CON","amount":"10.00","currency":"USD"}]' data-rv-field="shipping"></textarea>
                                        <div class="form-text">Provide profile ID and/or rates, or enable local pickup only.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
