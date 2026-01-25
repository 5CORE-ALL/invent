<div class="modal fade" id="aiTitleModal" tabindex="-1" aria-labelledby="aiTitleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiTitleModalLabel">
                    <i class="fas fa-magic"></i>
                    AI Title Generator - <span id="modalMarketplace"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="fas fa-box"></i>
                        Product SKU
                    </label>
                    <input type="text" class="form-control" id="productSku" readonly>
                </div>

                <div class="row">
                    <!-- LEFT SECTION - Original Title -->
                    <div class="col-md-6">
                        <div class="title-section">
                            <h6 class="fw-bold mb-3">
                                <i class="fas fa-file-alt text-primary"></i>
                                Original Marketplace Title
                            </h6>
                            
                            <div class="title-display">
                                <textarea id="originalTitle" class="form-control border-0" rows="5" readonly style="resize: none;"></textarea>
                            </div>

                            <div class="char-info">
                                <span>
                                    <i class="fas fa-text-width"></i>
                                    Characters: <strong id="originalChars">0 / 0</strong>
                                </span>
                                <span>
                                    Remaining: <strong id="originalRemaining" class="text-success">0</strong>
                                </span>
                            </div>

                            <div class="mt-3">
                                <a href="#" id="originalLink" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-external-link-alt"></i>
                                    View on Marketplace
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT SECTION - AI Generated Title -->
                    <div class="col-md-6">
                        <div class="title-section bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0">
                                    <i class="fas fa-robot text-purple"></i>
                                    AI Generated Title
                                </h6>
                                <div class="improvement-counter">
                                    <i class="fas fa-sync-alt"></i>
                                    Improvements: <span id="improvementCounter">0 / 3</span>
                                </div>
                            </div>
                            
                            <div class="title-display" style="background: #fff;">
                                <textarea id="aiTitle" class="form-control border-0" rows="5" readonly style="resize: none;"></textarea>
                            </div>

                            <div class="char-info">
                                <span>
                                    <i class="fas fa-text-width"></i>
                                    Characters: <strong id="aiChars">0 / 0</strong>
                                </span>
                                <span>
                                    Remaining: <strong id="aiRemaining" class="text-success">0</strong>
                                </span>
                            </div>

                            <div class="mt-3 text-center">
                                <div class="mb-3">
                                    <span class="score-badge score-low" id="scoreValue">0</span>
                                    <div class="mt-2 text-muted small">Title Quality Score</div>
                                </div>

                                <div class="d-flex flex-wrap justify-content-center gap-2">
                                    <button class="btn btn-primary btn-action" id="btnGenerate" onclick="generateTitle()">
                                        <i class="fas fa-magic"></i>
                                        Generate
                                    </button>
                                    
                                    <button class="btn btn-warning btn-action" id="btnApprove" onclick="approveTitle()" disabled>
                                        <i class="fas fa-check-circle"></i>
                                        Approve
                                    </button>
                                    
                                    <button class="btn btn-info btn-action" id="btnImprove" onclick="improveTitle()" disabled>
                                        <i class="fas fa-rocket"></i>
                                        Improve
                                    </button>
                                    
                                    <button class="btn btn-secondary btn-action" id="btnCopy" onclick="copyTitle()" disabled>
                                        <i class="fas fa-copy"></i>
                                        Copy
                                    </button>
                                    
                                    <button class="btn btn-success btn-action" id="btnPush" onclick="pushToMarketplace()" disabled>
                                        <i class="fas fa-upload"></i>
                                        Push to Marketplace
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 alert alert-info">
                    <h6 class="fw-bold mb-2">
                        <i class="fas fa-lightbulb"></i>
                        How to Use:
                    </h6>
                    <ol class="mb-0 small">
                        <li><strong>Generate:</strong> Create an AI-powered title based on current title</li>
                        <li><strong>Approve:</strong> Approve the generated title to enable improvements</li>
                        <li><strong>Improve:</strong> Enhance the title (up to 3 times after approval)</li>
                        <li><strong>Copy:</strong> Copy the AI title to clipboard</li>
                        <li><strong>Push:</strong> Save the AI title to the marketplace database</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
