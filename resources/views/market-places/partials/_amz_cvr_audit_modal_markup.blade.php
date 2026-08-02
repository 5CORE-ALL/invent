    {{-- Audit Modal --}}
    <div class="modal fade" id="amzCvrAuditModal" tabindex="-1" aria-labelledby="amzCvrAuditModalLabel" aria-hidden="true">
        <div class="modal-dialog amz-cvr-audit-modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title mb-0" id="amzCvrAuditModalLabel">
                        <i class="fas fa-clipboard-check me-1"></i>
                        Audit — <span id="amzCvrAuditSku" class="fw-normal opacity-75"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3 small">
                        <div class="col-md-4">
                            <div class="text-muted">Parent</div>
                            <div class="fw-semibold" id="amzCvrAuditParent">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">INV</div>
                            <div class="fw-semibold" id="amzCvrAuditInv">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">Views</div>
                            <div class="fw-semibold" id="amzCvrAuditViews">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">CVR L30</div>
                            <div class="fw-semibold" id="amzCvrAuditCvr">—</div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-muted">Price</div>
                            <div class="fw-semibold" id="amzCvrAuditPrice">—</div>
                        </div>
                    </div>
                    <input type="hidden" id="amzCvrAuditSkuInput" value="">
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                            <div class="form-label fw-semibold mb-0">Issue found</div>
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" id="amzCvrAddIssueBtn"
                                title="Add issue type with assignee for future tasks">
                                <i class="fas fa-plus"></i> Issue
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-center" id="amzCvrAuditIssueOptions">
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="pricing" id="amzCvrIssuePricing">
                                <label class="form-check-label" for="amzCvrIssuePricing">Pricing Issue</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="compliance" id="amzCvrIssueCompliance">
                                <label class="form-check-label" for="amzCvrIssueCompliance">Compliance Issue</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="missing_listing" id="amzCvrIssueMissingListing">
                                <label class="form-check-label" for="amzCvrIssueMissingListing">Missing listing Issue</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="advertisement" id="amzCvrIssueAdvertisement">
                                <label class="form-check-label" for="amzCvrIssueAdvertisement">Advertisement Issue</label>
                            </div>
                            <span id="amzCvrCustomIssueOptions" class="d-flex flex-wrap gap-3"></span>
                            <div class="form-check mb-0">
                                <input class="form-check-input amz-cvr-issue-opt" type="checkbox" value="other" id="amzCvrIssueOther">
                                <label class="form-check-label" for="amzCvrIssueOther">Other Issue</label>
                            </div>
                        </div>
                        <div id="amzCvrAuditIssueOtherWrap" class="mt-2 d-none">
                            <label for="amzCvrAuditIssueOtherText" class="form-label small text-muted mb-1">Additional issue</label>
                            <textarea id="amzCvrAuditIssueOtherText" class="form-control" rows="2"
                                placeholder="Describe the additional issue..." maxlength="1000"></textarea>
                        </div>
                    </div>
                    <div id="amzCvrAuditBulkNote" class="small text-muted mb-2 d-none"></div>
                    <div id="amzCvrAuditTaskRows" class="d-flex flex-column gap-2 mb-2"></div>
                    <div class="mb-0">
                        <button type="button" class="btn btn-success btn-sm" id="amzCvrAuditSubmitTaskBtn">
                            <i class="fas fa-paper-plane me-1"></i> <span id="amzCvrAuditSubmitLabel">Submit</span>
                        </button>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="amzCvrAuditSaveBtn">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Add custom Issue type modal --}}
    <div class="modal fade" id="amzCvrAddIssueModal" tabindex="-1" aria-labelledby="amzCvrAddIssueModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="amzCvrAddIssueModalLabel">
                        <i class="fas fa-plus me-1"></i> Add Issue Type
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        New issues are saved for future task allotment with the selected assignee.
                    </p>
                    <div class="mb-2">
                        <label for="amzCvrNewIssueLabel" class="form-label fw-semibold mb-1">Issue name</label>
                        <input type="text" id="amzCvrNewIssueLabel" class="form-control"
                            placeholder="e.g. Inventory" maxlength="200" autocomplete="off">
                        <small class="text-muted">“Issue” is added automatically if missing.</small>
                    </div>
                    <div class="mb-2">
                        <label for="amzCvrNewIssueAssigneeSearch" class="form-label fw-semibold mb-1">Assignee</label>
                        <div class="position-relative" id="amzCvrNewIssueAssigneeWrap">
                            <input type="text" id="amzCvrNewIssueAssigneeSearch" class="form-control"
                                placeholder="Quick Search assignee..." autocomplete="off">
                            <input type="hidden" id="amzCvrNewIssueAssigneeId" value="">
                            <div id="amzCvrNewIssueAssigneeDropdown"
                                class="list-group position-absolute w-100 shadow-sm d-none"
                                style="z-index: 1080; max-height: 220px; overflow-y: auto; top: 100%; left: 0;">
                            </div>
                        </div>
                    </div>
                    <div id="amzCvrCustomIssueList" class="mt-3"></div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary btn-sm" id="amzCvrSaveNewIssueBtn">
                        <i class="fas fa-save me-1"></i> Save Issue
                    </button>
                </div>
            </div>
        </div>
    </div>
