                    <div class="modal fade" id="listingPublishModal" tabindex="-1" aria-labelledby="listingPublishModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="listingPublishModalLabel">Publish listings</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="listing-publish-modal-note" id="listing-publish-modal-note">
                                        Only SKUs you check are published. Nothing else is added automatically.
                                    </p>
                                    <div id="listing-publish-mode-box" class="listing-publish-mode" role="radiogroup" aria-label="How do you want to list?">
                                        <label class="listing-publish-mode-card">
                                            <input type="radio" name="listing-publish-mode" value="single" checked>
                                            <span>
                                                <strong>Single listing</strong>
                                                <em>Each checked SKU becomes its own listing. Use this when sizes/colors should not be grouped.</em>
                                            </span>
                                        </label>
                                        <label class="listing-publish-mode-card">
                                            <input type="radio" name="listing-publish-mode" value="variation">
                                            <span>
                                                <strong>Variation listing</strong>
                                                <em>One listing with the SKUs you check. Suggested siblings start unchecked — you pick the group.</em>
                                            </span>
                                        </label>
                                    </div>
                                    <div id="listing-publish-wayfair-category" class="listing-publish-category" @if(($publishChannel ?? '') !== 'wayfair') hidden @endif>
                                        <label>Suggested Wayfair class</label>
                                        <div id="listing-publish-wayfair-category-path" class="listing-publish-category-path">Looking up the Wayfair class…</div>
                                        <label for="listing-publish-wayfair-class-id">Wayfair class ID</label>
                                        <input type="number" id="listing-publish-wayfair-class-id" class="form-control form-control-sm" placeholder="e.g. 510" min="1" step="1" required>
                                        <small>Required. This fills from a listed sibling or Wayfair class list when the API allows it. Otherwise type the class ID from Partner Home.</small>
                                    </div>
                                    <div id="listing-publish-aliexpress-category" class="listing-publish-category" hidden>
                                        <label>Suggested AliExpress category</label>
                                        <div id="listing-publish-aliexpress-category-path" class="listing-publish-category-path">Matching from the product type…</div>
                                        <input type="hidden" id="listing-publish-category-id" value="">
                                        <label for="listing-publish-category-name">Or type a category name</label>
                                        <input type="text" id="listing-publish-category-name" class="form-control form-control-sm" placeholder="e.g. Guitar Capos" autocomplete="off">
                                        <small>Use the category name, like other marketplaces. You do not need a category ID.</small>
                                        <div id="listing-publish-aliexpress-weight" class="listing-publish-weight-row">
                                            <label for="listing-publish-weight-lb">Package weight (lb)</label>
                                            <input type="number" id="listing-publish-weight-lb" class="form-control form-control-sm" placeholder="e.g. 3.35" min="0.01" step="0.01">
                                            <small id="listing-publish-weight-note">Looking up Dim/Wt Master…</small>
                                        </div>
                                    </div>
                                    <div id="listing-publish-reverb-category" class="listing-publish-category" hidden>
                                        <label>Suggested Reverb category</label>
                                        <div id="listing-publish-reverb-category-path" class="listing-publish-category-path">Matching from the product type…</div>
                                        <input type="hidden" id="listing-publish-category-uuid" value="">
                                        <label for="listing-publish-reverb-category-name">Category</label>
                                        <div class="listing-publish-cat-wrap">
                                            <input type="text" id="listing-publish-reverb-category-name" class="form-control form-control-sm" placeholder="e.g. Stands" autocomplete="off">
                                            <div id="listing-publish-reverb-category-results" class="listing-publish-cat-results"></div>
                                        </div>
                                        <small>Type to search Reverb categories. Click one from the list to use it.</small>
                                    </div>
                                    <div id="listing-publish-groups"></div>
                                    <div id="listing-publish-progress"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="listing-publish-confirm">
                                        <i class="fas fa-cloud-upload-alt"></i> Publish listing(s)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="listing-publish-status" class="listing-publish-status-overlay" hidden>
                        <div class="listing-publish-status-card" role="alertdialog" aria-modal="true" aria-labelledby="listing-publish-status-title">
                            <div id="listing-publish-status-icon" class="listing-publish-status-icon"></div>
                            <h3 id="listing-publish-status-title">Publishing…</h3>
                            <p id="listing-publish-status-message" class="listing-publish-status-message"></p>
                            <button type="button" class="btn btn-primary" id="listing-publish-status-close" hidden>Close</button>
                        </div>
                    </div>
