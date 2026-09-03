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
                                    <div id="listing-publish-aliexpress-category" class="listing-publish-category" hidden>
                                        <label for="listing-publish-category-id">AliExpress category ID</label>
                                        <input type="text" id="listing-publish-category-id" class="form-control form-control-sm" placeholder="e.g. 200000345" inputmode="numeric" autocomplete="off">
                                        <small>Required if AliExpress cannot guess the category. Find it on the seller category picker, then publish again.</small>
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
