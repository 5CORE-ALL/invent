@extends('layouts.vertical', ['title' => 'A+ Masters', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .tabulator-col .tabulator-col-sorter {
            display: none !important;
        }
        
        .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            white-space: nowrap;
            transform: rotate(180deg);
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }
        
        .tabulator .tabulator-header .tabulator-col {
            height: 100px !important;
        }

        .tabulator .tabulator-header .tabulator-col.tabulator-sortable .tabulator-col-title {
            padding-right: 0px !important;
        }

        .tabulator-paginator label {
            margin-right: 5px;
        }

        .parent-row {
            background-color: #fffacd !important;
        }

        .copy-sku-btn {
            cursor: pointer;
            padding: 2px 5px;
            margin-left: 5px;
        }

        .audit-dot {
            width: 12px;
            height: 12px;
            background-color: #dc3545;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
            transition: transform 0.2s ease;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }

        .audit-dot:hover {
            transform: scale(1.3);
            box-shadow: 0 3px 6px rgba(220, 53, 69, 0.5);
        }

        /* Voice Recording Animations */
        #voiceNoteStatus {
            animation: pulse 1.5s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        #voiceNoteRecordBtn.btn-danger {
            animation: blink 1s ease-in-out infinite;
        }

        @keyframes blink {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .status-toggle-btn {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .status-toggle-btn.red {
            background-color: #dc3545;
        }

        .status-toggle-btn.green {
            background-color: #28a745;
        }

        .status-toggle-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .status-toggle-btn:active {
            transform: scale(0.95);
        }

        .status-toggle-btn.loading {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .status-toggle-btn.loading::after {
            content: "";
            position: absolute;
            width: 12px;
            height: 12px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'A+ Masters',
        'sub_title' => 'A+ Images Master Data',
    ])
    
    <div class="toast-container"></div>
    
    <div class="row">
        <div class="card shadow-sm">
            <div class="card-body py-3">
                <h4>A+ Images Master</h4>
                
                <!-- Control Bar -->
                <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
                    <!-- LQS Average Badge -->
                    <span class="badge bg-primary p-2" style="font-weight: bold; font-size: 14px;">
                        LQS AVG: <span id="lqsAvg">-</span>
                    </span>

                    <!-- LQS Play Controls -->
                    <div class="btn-group align-items-center" role="group" style="gap: 4px;">
                        <button type="button" id="lqs-play-backward" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Previous (Lower LQS)" disabled>
                            <i class="fas fa-step-backward" style="font-size: 12px;"></i>
                        </button>
                        <button type="button" id="lqs-play-auto" class="btn btn-sm btn-success rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Play LQS (Lowest to Highest)">
                            <i class="fas fa-play" style="font-size: 12px;"></i>
                        </button>
                        <button type="button" id="lqs-play-pause" class="btn btn-sm btn-danger rounded-circle shadow-sm" style="display: none; width: 32px; height: 32px; padding: 0;" title="Pause">
                            <i class="fas fa-pause" style="font-size: 12px;"></i>
                        </button>
                        <button type="button" id="lqs-play-forward" class="btn btn-sm btn-light rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Next (Higher LQS)" disabled>
                            <i class="fas fa-step-forward" style="font-size: 12px;"></i>
                        </button>
                    </div>

                    <span id="lqs-play-status" class="badge bg-info" style="display: none; font-size: 12px;">
                        Playing: <span id="current-lqs-value">-</span>
                    </span>

                    <!-- Column Visibility -->
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" id="columnVisibilityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-eye"></i> Columns
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="columnVisibilityDropdown" id="column-dropdown-menu" style="max-height: 400px; overflow-y: auto;"></ul>
                    </div>
                    
                    <button id="show-all-columns-btn" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-eye"></i> Show All
                    </button>

                    <!-- Audit Filter -->
                    <button type="button" class="btn btn-sm btn-outline-danger" id="auditFilterBtn" title="Show only rows with Audit">
                        <i class="fas fa-filter"></i> Audit
                    </button>

                    <!-- Action Buttons -->
                    <button type="button" class="btn btn-sm btn-primary" id="addAPlusImagesBtn">
                        <i class="fas fa-plus"></i> Add
                    </button>
                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#importAPlusImagesModal">
                        <i class="fas fa-upload"></i> Import
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="export-btn">
                        <i class="fa fa-download"></i> Export
                    </button>
                </div>

                <!-- Summary Stats -->
                <div id="summary-stats" class="mt-2 p-3 bg-light rounded">
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary fs-6 p-2" id="total-items-badge">Total: 0</span>
                        <span class="badge bg-success fs-6 p-2" id="parent-count-badge">Parents: 0</span>
                        <span class="badge bg-info fs-6 p-2" id="sku-count-badge">SKUs: 0</span>
                        <span class="badge bg-warning fs-6 p-2" id="db-missing-badge">DB Missing: 0</span>
                    </div>
                </div>
            </div>
            
            <div class="card-body" style="padding: 0;">
                <div id="aplus-table-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                    <!-- Search Bar -->
                    <div class="p-2 bg-light border-bottom">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <input type="text" id="general-search" class="form-control form-control-sm" placeholder="Search all columns...">
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="sku-search" class="form-control form-control-sm" placeholder="Search SKU...">
                            </div>
                            <div class="col-md-3">
                                <input type="text" id="lqs-search" class="form-control form-control-sm" placeholder="Search LQS...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Table -->
                    <div id="aplus-table" style="flex: 1;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add A+ Images Modal -->
    <div class="modal fade" id="addAPlusImagesModal" tabindex="-1" aria-labelledby="addAPlusImagesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAPlusImagesModalLabel">Add A+ Images Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addAPlusImagesForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="addAPlusImagesSku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <select class="form-control" id="addAPlusImagesSku" name="sku" required>
                                    <option value="">Select SKU</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="addDB" class="form-label">DB</label>
                                <input type="text" class="form-control" id="addDB" name="db" placeholder="Enter DB Link">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveAddAPlusImagesBtn">
                        <i class="fas fa-save me-2"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importAPlusImagesModal" tabindex="-1" aria-labelledby="importAPlusImagesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="importAPlusImagesModalLabel">
                        <i class="fas fa-upload me-2"></i>Import A+ Images Data
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instructions:</strong>
                        <ol class="mb-0 mt-2">
                            <li>Download the sample file below</li>
                            <li>Fill in the A+ images data (DB links)</li>
                            <li>Upload the completed file</li>
                        </ol>
                    </div>
                    
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-primary w-100" id="downloadSampleAPlusImagesBtn">
                            <i class="fas fa-download me-2"></i>Download Sample File
                        </button>
                    </div>

                    <div class="mb-3">
                        <label for="aPlusImagesImportFile" class="form-label fw-bold">Select Excel File</label>
                        <input type="file" class="form-control" id="aPlusImagesImportFile" accept=".xlsx,.xls,.csv">
                        <div class="form-text">Supported formats: .xlsx, .xls, .csv</div>
                        <div id="aPlusImagesFileError" class="text-danger mt-2" style="display: none;"></div>
                    </div>

                    <div id="aPlusImagesImportProgress" class="progress mb-3" style="display: none;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>

                    <div id="aPlusImagesImportResult" class="alert" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="importAPlusImagesBtn" disabled>
                        <i class="fas fa-upload me-2"></i>Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Audit Modal -->
    <div class="modal fade" id="editAuditSuggestionModal" tabindex="-1" aria-labelledby="editAuditSuggestionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="editAuditSuggestionModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Audit
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editAuditSuggestionForm">
                        <input type="hidden" id="editAuditSku" name="sku">
                        
                        <!-- Audit Suggestion with Voice-to-Text -->
                        <div class="mb-3">
                            <label for="editAuditSuggestion" class="form-label fw-bold">
                                Audit Suggestion (Max 500 characters)
                                <button type="button" class="btn btn-sm btn-danger ms-2" id="voiceToTextBtn" title="Voice to Text">
                                    <i class="fas fa-microphone"></i>
                                </button>
                                <span id="voiceStatus" class="badge bg-warning ms-2" style="display: none;">Listening...</span>
                            </label>
                            <textarea class="form-control" id="editAuditSuggestion" name="audit_suggestion" rows="5" maxlength="500" placeholder="Enter audit suggestion or click microphone to speak..."></textarea>
                            <div class="form-text">
                                <span id="charCount">0</span> / 500 characters
                            </div>
                            
                            <!-- A+ Reference Links -->
                            <div class="alert alert-info mt-3">
                                <strong><i class="fas fa-lightbulb me-2"></i>A+ Content Reference Links:</strong>
                                <ul class="mb-0 mt-2" style="font-size: 13px;">
                                    <li><a href="https://advertising.amazon.com/solutions/products/a-plus-content" target="_blank" rel="noopener">Amazon A+ Content Overview - Official Guide</a></li>
                                    <li><a href="https://sellercentral.amazon.com/gp/help/external/G202102950" target="_blank" rel="noopener">A+ Content Manager - Amazon Seller Central Help</a></li>
                                    <li><a href="https://advertising.amazon.com/library/guides/a-plus-content" target="_blank" rel="noopener">A+ Content Best Practices & Examples</a></li>
                                    <li><a href="https://www.junglescout.com/blog/amazon-a-plus-content/" target="_blank" rel="noopener">Complete A+ Content Guide - Jungle Scout</a></li>
                                    <li><a href="https://www.helium10.com/blog/amazon-a-plus-content/" target="_blank" rel="noopener">A+ Content Strategy & Tips - Helium 10</a></li>
                                    <li><a href="https://www.amzscout.net/blog/amazon-a-plus-content/" target="_blank" rel="noopener">A+ Content Tutorial & Examples - AMZScout</a></li>
                                    <li><a href="https://www.shopify.com/partners/blog/amazon-a-plus-content" target="_blank" rel="noopener">How to Create A+ Content - Shopify Guide</a></li>
                                    <li><a href="https://ecomcrew.com/amazon-a-plus-content/" target="_blank" rel="noopener">A+ Content Design Ideas - EcomCrew</a></li>
                                </ul>
                            </div>
                        </div>
                        
                        <!-- Link Data (up to 4 URLs) -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="fas fa-link text-danger me-1"></i>Link Data
                            </label>
                            <input type="url" class="form-control mb-2" id="auditLinkData"  name="link_data[]" placeholder="Enter URL #1...">
                            <input type="url" class="form-control mb-2" id="auditLinkData2" name="link_data[]" placeholder="Enter URL #2...">
                            <input type="url" class="form-control mb-2" id="auditLinkData3" name="link_data[]" placeholder="Enter URL #3...">
                            <input type="url" class="form-control"      id="auditLinkData4" name="link_data[]" placeholder="Enter URL #4...">
                            <div class="form-text">Add up to 4 related links (will appear as red link icons)</div>
                        </div>

                        <!-- Screenshot (Snippet) — multi-select + paste-from-clipboard -->
                        <div class="mb-3">
                            <label for="auditScreenshot" class="form-label fw-bold">
                                <i class="fas fa-camera text-primary me-1"></i>Screenshot (Snippet)
                            </label>
                            <input type="file" class="form-control" id="auditScreenshot" name="screenshot[]" accept="image/*" multiple>
                            <div class="form-text">Upload one or more screenshots / image snippets (max 5MB each)</div>

                            <div class="mt-2 d-flex align-items-center gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="pasteScreenshotBtn" title="Paste image from clipboard (Cmd/Ctrl+V also works anywhere in this dialog)">
                                    <i class="fas fa-paste me-1"></i> Paste from Clipboard
                                </button>
                                <small class="text-muted">Take a screenshot, then click here (or press <kbd>Ctrl/Cmd</kbd>+<kbd>V</kbd>).</small>
                                <span id="pasteStatus" class="badge bg-success" style="display: none;"></span>
                            </div>

                            <div id="screenshotPreview" class="mt-2 d-flex flex-wrap gap-2"></div>
                        </div>
                        
                        <!-- Voice Note -->
                        <div class="mb-3">
                            <label for="auditVoiceNote" class="form-label fw-bold">
                                <i class="fas fa-microphone-alt text-success me-1"></i>Voice Note
                            </label>
                            <div>
                                <button type="button" class="btn btn-sm btn-success" id="voiceNoteRecordBtn" title="Click to record voice note">
                                    <i class="fas fa-circle"></i> Record
                                </button>
                                <span id="voiceNoteStatus" class="badge bg-danger ms-2" style="display: none;">
                                    Recording... <span id="voiceNoteTimer">0:00</span>
                                </span>
                            </div>
                            <div id="voiceNotePreview" class="mt-2"></div>
                            <div class="form-text">Click the button to record an audio note (max 2 minutes)</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="fixedAuditBtn">
                        <i class="fas fa-check-circle me-2"></i>Fixed
                    </button>
                    <button type="button" class="btn btn-primary" id="saveEditAuditBtn">
                        <i class="fas fa-save me-2"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit History Modal -->
    <div class="modal fade" id="auditHistoryModal" tabindex="-1" aria-labelledby="auditHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="auditHistoryModalLabel">Audit History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="auditHistoryContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status History Modal -->
    <div class="modal fade" id="statusHistoryModal" tabindex="-1" aria-labelledby="statusHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusHistoryModalLabel">Status History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="statusHistoryContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Competitors (LMP) Modal -->
    <div class="modal fade" id="competitorsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-search me-2"></i>Competitors for SKU: <span id="competitorsSku"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Add New Competitor Form -->
                    <div class="card mb-3 border-success">
                        <div class="card-header bg-success text-white">
                            <strong><i class="fas fa-plus-circle me-2"></i>Add New Competitor</strong>
                        </div>
                        <div class="card-body">
                            <form id="addCompetitorForm" class="row g-3">
                                <input type="hidden" id="compSku">
                                <div class="col-md-3">
                                    <label class="form-label"><strong>ASIN</strong> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="compAsin" placeholder="B07ABC123" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><strong>Price</strong> <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="compPrice" placeholder="29.99" step="0.01" min="0.01" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><strong>Product Link</strong></label>
                                    <input type="url" class="form-control" id="compLink" placeholder="https://amazon.com/dp/...">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><strong>Marketplace</strong></label>
                                    <select class="form-select" id="compMarketplace">
                                        <option value="Amazon" selected>Amazon</option>
                                        <option value="US">US</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-plus me-2"></i>Add Competitor
                                    </button>
                                    <button type="reset" class="btn btn-secondary">
                                        <i class="fas fa-undo me-2"></i>Clear
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Competitors List -->
                    <div id="competitorsList">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading competitors...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DB Link Modal -->
    <div class="modal fade" id="dbLinkModal" tabindex="-1" aria-labelledby="dbLinkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="dbLinkModalLabel">
                        <i class="fas fa-link me-2"></i><span id="dbModalTitle">Add DB Link</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="dbLinkForm">
                        <input type="hidden" id="dbLinkSku" name="sku">
                        <div class="mb-3">
                            <label for="dbLinkInput" class="form-label fw-bold">
                                <i class="fas fa-database text-primary me-1"></i>DB Link
                            </label>
                            <input type="url" class="form-control" id="dbLinkInput" name="db_link" placeholder="https://example.com/db-link" required>
                            <div class="form-text">Enter the full URL for the DB link</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveDBLinkBtn">
                        <i class="fas fa-save me-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Upload Modal -->
    <div class="modal fade" id="imageUploadModal" tabindex="-1" aria-labelledby="imageUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #2c6ed5 0%, #1a56b7 100%); color: white;">
                    <h5 class="modal-title" id="imageUploadModalLabel">
                        <i class="fas fa-upload me-2"></i>Upload <span id="imageTypeLabel"></span> Image
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="imageUploadForm">
                        <input type="hidden" id="imageUploadSku" name="sku">
                        <input type="hidden" id="imageUploadType" name="image_type">
                        <div class="mb-3">
                            <label for="imageFileInput" class="form-label fw-bold">
                                <i class="fas fa-image text-primary me-1"></i>Select Image File
                            </label>
                            <input type="file" class="form-control" id="imageFileInput" name="image_file" accept="image/*" required>
                            <div class="form-text">Accepted formats: JPG, PNG, GIF, BMP, WEBP, SVG, etc.</div>
                        </div>
                        <div id="imagePreviewContainer" style="display: none;">
                            <label class="form-label fw-bold">Preview:</label>
                            <div class="text-center">
                                <img id="imagePreview" src="" alt="Preview" style="max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveImageBtn">
                        <i class="fas fa-save me-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
<script>
    const COLUMN_VIS_KEY = "aplus_tabulator_column_visibility";
    const CSRF_TOKEN = '{{ csrf_token() }}';
    let table = null;
    let tableData = [];
    let lqsPlayInterval = null;
    let currentLqsIndex = 0;
    let sortedLqsData = [];

    // Safe JSON fetch wrapper.
    // - Always sends Accept: application/json + X-Requested-With so Laravel returns JSON
    //   for validation/auth/CSRF/exception responses instead of HTML.
    // - Detects HTML responses (PHP error pages, 419 CSRF, 413 too large, login redirects)
    //   and surfaces a readable error instead of the cryptic "Unexpected token '<'".
    // - Parses Laravel validation (422) errors into a single readable message.
    async function safeJsonFetch(url, options = {}) {
        const opts = { ...options };
        const headers = new Headers(opts.headers || {});

        if (!headers.has('Accept')) headers.set('Accept', 'application/json');
        if (!headers.has('X-Requested-With')) headers.set('X-Requested-With', 'XMLHttpRequest');
        if (!headers.has('X-CSRF-TOKEN')) headers.set('X-CSRF-TOKEN', CSRF_TOKEN);

        // Do NOT force Content-Type when body is FormData — the browser must set the boundary.
        const isFormData = (typeof FormData !== 'undefined') && (opts.body instanceof FormData);
        if (!isFormData && opts.body && !headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }

        opts.headers = headers;

        let response;
        try {
            response = await fetch(url, opts);
        } catch (networkErr) {
            throw new Error('Network error: ' + (networkErr.message || 'request failed'));
        }

        const contentType = (response.headers.get('content-type') || '').toLowerCase();
        const text = await response.text();

        // HTML / non-JSON response — translate into a useful message.
        if (!contentType.includes('application/json')) {
            if (response.status === 419) {
                throw new Error('Session expired (CSRF). Please refresh the page and try again.');
            }
            if (response.status === 413) {
                throw new Error('File too large. Please upload a smaller file.');
            }
            if (response.status === 401 || response.status === 403) {
                throw new Error('Not authorized. Please log in again.');
            }
            if (response.status >= 500) {
                throw new Error('Server error (' + response.status + '). Check Laravel logs.');
            }
            // Generic fallback (likely an HTML error page from PHP).
            throw new Error('Unexpected server response (' + response.status + '). The server returned HTML instead of JSON — this usually means a PHP error, exceeded upload limit, or expired session.');
        }

        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (parseErr) {
            throw new Error('Invalid JSON response from server.');
        }

        if (!response.ok) {
            // Laravel validation error shape: { message, errors: { field: [msg, ...] } }
            if (response.status === 422 && data && data.errors) {
                const firstField = Object.keys(data.errors)[0];
                const firstMsg = Array.isArray(data.errors[firstField]) ? data.errors[firstField][0] : data.errors[firstField];
                throw new Error(firstMsg || data.message || 'Validation failed');
            }
            throw new Error((data && (data.message || data.error)) || ('Request failed (' + response.status + ')'));
        }

        return data;
    }

    // Toast notification function
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    // Copy to clipboard
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast(`SKU "${text}" copied to clipboard!`, 'success');
        }).catch(err => {
            console.error('Failed to copy:', err);
            showToast('Failed to copy SKU', 'error');
        });
    }

    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });
        
        // Initialize Tabulator
        console.log("Initializing Tabulator...");
        table = new Tabulator("#aplus-table", {
            ajaxURL: "/a-plus-images-master-data-view",
            ajaxConfig: {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': CSRF_TOKEN
                }
            },
            ajaxSorting: false,
            layout: "fitData",
            pagination: true,
            paginationSize: 100,
            paginationSizeSelector: [25, 50, 100, 200, 500],
            paginationCounter: "rows",
            ajaxResponse: function(url, params, response) {
                console.log("AJAX Response received:", response);
                console.log("Response type:", typeof response);
                
                if (response && response.data && Array.isArray(response.data)) {
                    console.log("Data array length:", response.data.length);
                    tableData = response.data;
                    return response.data;
                }
                
                console.error("Invalid response format:", response);
                return [];
            },
            ajaxError: function(error) {
                console.error("AJAX Error:", error);
                showToast("Error loading data: " + (error.message || "Unknown error"), "error");
            },
            dataLoaded: function(data) {
                console.log("Data loaded successfully:", data.length, "rows");
                updateSummary();
            },
            rowFormatter: function(row) {
                const data = row.getData();
                if (data.SKU && data.SKU.toUpperCase().includes('PARENT')) {
                    row.getElement().classList.add('parent-row');
                }
            },
            langs: {
                "default": {
                    "pagination": {
                        "page_size": "Show",
                        "counter": {
                            "showing": "Showing",
                            "of": "of",
                            "rows": "rows"
                        }
                    }
                }
            },
            initialSort: [{
                column: "lqs",
                dir: "asc"
            }],
            columns: [
                {
                    title: "Image",
                    field: "image_path",
                    width: 80,
                    frozen: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '-';
                        return `<img src="${value}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">`;
                    }
                },
                {
                    title: "Parent",
                    field: "Parent",
                    width: 150,
                    frozen: true
                },
                {
                    title: "SKU",
                    field: "SKU",
                    width: 200,
                    frozen: true,
                    formatter: function(cell) {
                        const sku = cell.getValue();
                        if (!sku) return '-';
                        return `
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <span>${sku}</span>
                                <button class="btn btn-sm btn-link p-0 copy-sku-btn" onclick="copyToClipboard('${sku}')" title="Copy SKU">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        `;
                    }
                },
                {
                    title: "LQS",
                    field: "lqs",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '-';
                        
                        const rawValue = parseFloat(value);
                        let score;
                        
                        // Only round off double digit values except 10
                        if (rawValue >= 11 && rawValue <= 99) {
                            // Double digit (except 10): divide by 10 and round
                            score = Math.round(rawValue / 10);
                        } else if (rawValue === 10) {
                            // Keep 10 as it is
                            score = 10;
                        } else if (rawValue >= 100) {
                            // Triple digit: divide by 10 and round
                            score = Math.round(rawValue / 10);
                        } else {
                            // Single digit (0-9): keep as is
                            score = Math.round(rawValue);
                        }
                        
                        let color = '#dc3545'; // Red for low scores
                        if (score >= 8) color = '#28a745'; // Green for 8-10
                        else if (score >= 6) color = '#ffc107'; // Yellow for 6-7
                        
                        return `<span class="badge" style="background-color: ${color}; color: ${score >= 6 && score < 8 ? 'black' : 'white'};">${score}</span>`;
                    }
                },
                {
                    title: "Stat",
                    field: "status",
                    width: 80,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = (cell.getValue() || '').toLowerCase();
                        if (value === 'active') {
                            return '<span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: #28a745;" title="Active"></span>';
                        } else if (value === 'upcoming') {
                            return '<span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: #ffc107;" title="Upcoming"></span>';
                        }
                        return value || '-';
                    }
                },
                {
                    title: "INV",
                    field: "shopify_inv",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (value === 0 || value === "0") return "0";
                        if (value === null || value === undefined || value === "") return "-";
                        return String(value);
                    }
                },
                {
                    title: "Ovl30",
                    field: "ovl30",
                    width: 80,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return (value === null || value === undefined || value === '') ? '0' : String(value);
                    }
                },
                {
                    title: "Dil",
                    field: "dil",
                    width: 50,
                    hozAlign: "center",
                    sorter: "number",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        let dilText = '0%';
                        let dilColor = '#a00211';
                        
                        if (value !== null && value !== undefined && value !== '') {
                            const dilNum = parseFloat(value);
                            dilText = Math.round(dilNum) + '%';
                            
                            if (dilNum < 16.7) dilColor = '#a00211';
                            else if (dilNum >= 16.7 && dilNum < 25) dilColor = '#ffc107';
                            else if (dilNum >= 25 && dilNum < 50) dilColor = '#28a745';
                            else if (dilNum >= 50) dilColor = '#e83e8c';
                        }
                        
                        return `<span style="color: ${dilColor}; font-weight: bold;">${dilText}</span>`;
                    }
                },
                {
                    title: "B/S",
                    field: "buyer_seller",
                    width: 45,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const row = cell.getRow().getData();
                        let html = '<div style="display: flex; justify-content: center; gap: 5px;">';
                        if (row.buyer_link) {
                            html += `<a href="${row.buyer_link}" target="_blank" class="text-decoration-none fw-semibold" style="color: #2c6ed5;" title="Buyer Link">B</a>`;
                        }
                        if (row.seller_link) {
                            html += `<a href="${row.seller_link}" target="_blank" class="text-decoration-none fw-semibold" style="color: #47ad77;" title="Seller Link">S</a>`;
                        }
                        html += '</div>';
                        return (row.buyer_link || row.seller_link) ? html : '-';
                    }
                },
                {
                    title: "DB",
                    field: "db",
                    width: 80,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue() || cell.getRow().getData()['DB'] || '';
                        const sku = cell.getRow().getData().SKU;
                        const escapedSku = sku.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const cleanUrl = value ? value.trim() : '';
                        
                        if (cleanUrl && cleanUrl.match(/^https?:\/\//i)) {
                            return `
                                <div style="display: flex; align-items: center; justify-content: center; gap: 5px;">
                                    <a href="${cleanUrl}" target="_blank" class="text-decoration-none" style="color: #2c6ed5;" title="Open DB Link">
                                        <i class="fas fa-link"></i>
                                    </a>
                                    <button class="btn btn-sm btn-link p-0" onclick="openDBModal('${escapedSku}', '${cleanUrl.replace(/'/g, "\\'")}')" title="Edit DB Link" style="color: #6c757d;">
                                        <i class="fas fa-edit" style="font-size: 12px;"></i>
                                    </button>
                                </div>
                            `;
                        } else {
                            return `<button class="btn btn-sm btn-link p-0" onclick="openDBModal('${escapedSku}', '')" title="Add DB Link" style="color: #28a745;">
                                <i class="fas fa-plus"></i>
                            </button>`;
                        }
                    }
                },
                {
                    title: "Comp",
                    field: "comp",
                    width: 45,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const sku = cell.getRow().getData().SKU;
                        const escapedSku = sku.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        return `<button class="btn btn-sm btn-info" onclick="viewCompetitors('${escapedSku}')" title="View Competitors"><i class="fas fa-search"></i></button>`;
                    }
                },
                {
                    title: "Audit",
                    field: "audit_suggestion",
                    width: 100,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const sku = cell.getRow().getData().SKU;
                        // Properly escape SKU for use in onclick
                        const escapedSku = sku.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const escapedValue = value ? value.replace(/'/g, "\\'").replace(/"/g, '&quot;') : '';
                        
                        if (value && value.trim()) {
                            return `
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <div class="audit-dot" onclick="editAudit('${escapedSku}', '${escapedValue}')" title="${value}"></div>
                                    <button class="btn btn-sm btn-link p-0" onclick="viewAuditHistory('${escapedSku}')" title="View History">
                                        <i class="fas fa-history"></i>
                                    </button>
                                </div>
                            `;
                        }
                        return `<button class="btn btn-sm btn-link p-0" onclick="editAudit('${escapedSku}', '')" title="Add Audit"><i class="fas fa-plus"></i></button>`;
                    }
                },
                {
                    title: "A+(P)",
                    field: "premium_image",
                    width: 100,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const premiumImage = cell.getValue();
                        const sku = cell.getRow().getData().SKU;
                        const escapedSku = sku.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        
                        if (premiumImage && premiumImage.trim()) {
                            return `
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <img src="/storage/${premiumImage}" 
                                         style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;" 
                                         onclick="window.open('/storage/${premiumImage}', '_blank')"
                                         title="Click to view full size">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openImageUploadModal('${escapedSku}', 'premium')" title="Edit Image">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            `;
                        } else {
                            return `<button class="btn btn-sm btn-outline-success" onclick="openImageUploadModal('${escapedSku}', 'premium')" title="Upload Image">
                                <i class="fas fa-upload"></i>
                            </button>`;
                        }
                    }
                },
                {
                    title: "A+(S)",
                    field: "standard_image",
                    width: 100,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const standardImage = cell.getValue();
                        const sku = cell.getRow().getData().SKU;
                        const escapedSku = sku.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        
                        if (standardImage && standardImage.trim()) {
                            return `
                                <div style="display: inline-flex; align-items: center; gap: 5px;">
                                    <img src="/storage/${standardImage}" 
                                         style="width:40px;height:40px;object-fit:cover;border-radius:4px;cursor:pointer;" 
                                         onclick="window.open('/storage/${standardImage}', '_blank')"
                                         title="Click to view full size">
                                    <button class="btn btn-sm btn-outline-primary" onclick="openImageUploadModal('${escapedSku}', 'standard')" title="Edit Image">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            `;
                        } else {
                            return `<button class="btn btn-sm btn-outline-success" onclick="openImageUploadModal('${escapedSku}', 'standard')" title="Upload Image">
                                <i class="fas fa-upload"></i>
                            </button>`;
                        }
                    }
                },
                {
                    title: "Action",
                    field: "status_toggle",
                    width: 45,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const sku = cell.getRow().getData().SKU;
                        const escapedSku = sku.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        const statusToggle = cell.getValue() || 'red';
                        const statusClass = statusToggle === 'green' ? 'green' : 'red';
                        return `<button class="status-toggle-btn ${statusClass}" data-sku="${sku}" onclick="toggleStatus('${escapedSku}', '${statusToggle}')" title="Toggle Status"></button>`;
                    }
                },
                {
                    title: "Push",
                    field: "push",
                    width: 80,
                    hozAlign: "center",
                    formatter: function(cell) {
                        const sku = cell.getRow().getData().SKU;
                        const escapedSku = sku.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        return `<button class="btn btn-sm btn-primary" onclick="pushData('${escapedSku}')" title="Push"><i class="fas fa-paper-plane"></i></button>`;
                    }
                }
            ]
        });

        // General Search
        $('#general-search').on('keyup', function() {
            table.setFilter([
                {field: "SKU", type: "like", value: this.value},
                {field: "Parent", type: "like", value: this.value},
                {field: "status", type: "like", value: this.value}
            ]);
        });

        // SKU Search
        $('#sku-search').on('keyup', function() {
            table.setFilter("SKU", "like", this.value);
        });

        // LQS Search
        $('#lqs-search').on('keyup', function() {
            table.setFilter("lqs", "like", this.value);
        });

        // Update summary
        function updateSummary() {
            const data = table.getData("active");
            const totalItems = data.length;
            const parentCount = data.filter(item => item.Parent).length;
            const skuCount = data.filter(item => item.SKU).length;
            const dbMissingCount = data.filter(item => !item.db && !item.DB).length;
            
            // Calculate LQS average
            const lqsValues = data.filter(item => item.lqs).map(item => parseFloat(item.lqs));
            const lqsAvg = lqsValues.length > 0 ? (lqsValues.reduce((a, b) => a + b, 0) / lqsValues.length).toFixed(1) : '-';
            
            $('#total-items-badge').text('Total: ' + totalItems);
            $('#parent-count-badge').text('Parents: ' + parentCount);
            $('#sku-count-badge').text('SKUs: ' + skuCount);
            $('#db-missing-badge').text('DB Missing: ' + dbMissingCount);
            $('#lqsAvg').text(lqsAvg);
        }

        // Column visibility
        function buildColumnDropdown() {
            const menu = document.getElementById("column-dropdown-menu");
            menu.innerHTML = '';

            const savedVisibility = JSON.parse(localStorage.getItem(COLUMN_VIS_KEY) || '{}');
            
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (!def.field) return;

                const li = document.createElement("li");
                const label = document.createElement("label");
                label.style.display = "block";
                label.style.padding = "5px 10px";
                label.style.cursor = "pointer";

                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.value = def.field;
                checkbox.checked = savedVisibility[def.field] !== false;
                checkbox.style.marginRight = "8px";

                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(def.title));
                li.appendChild(label);
                menu.appendChild(li);
            });
        }

        function saveColumnVisibility() {
            const visibility = {};
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field) {
                    visibility[def.field] = col.isVisible();
                }
            });
            localStorage.setItem(COLUMN_VIS_KEY, JSON.stringify(visibility));
        }

        function applyColumnVisibility() {
            const savedVisibility = JSON.parse(localStorage.getItem(COLUMN_VIS_KEY) || '{}');
            table.getColumns().forEach(col => {
                const def = col.getDefinition();
                if (def.field && savedVisibility[def.field] === false) {
                    col.hide();
                }
            });
        }

        table.on('tableBuilt', function() {
            applyColumnVisibility();
            buildColumnDropdown();
        });

        table.on('dataLoaded', updateSummary);
        table.on('dataProcessed', updateSummary);

        document.getElementById("column-dropdown-menu").addEventListener("change", function(e) {
            if (e.target.type === 'checkbox') {
                const field = e.target.value;
                const col = table.getColumn(field);
                if (e.target.checked) {
                    col.show();
                } else {
                    col.hide();
                }
                saveColumnVisibility();
            }
        });

        document.getElementById("show-all-columns-btn").addEventListener("click", function() {
            table.getColumns().forEach(col => col.show());
            buildColumnDropdown();
            saveColumnVisibility();
        });

        // Audit Filter
        let auditFilterActive = false;
        document.getElementById("auditFilterBtn").addEventListener("click", function() {
            auditFilterActive = !auditFilterActive;
            const btn = this;
            
            if (auditFilterActive) {
                // Filter to show only rows with audit suggestions
                table.setFilter("audit_suggestion", "!=", "");
                table.setFilter("audit_suggestion", "!=", null);
                btn.classList.remove('btn-outline-danger');
                btn.classList.add('btn-danger');
                btn.innerHTML = '<i class="fas fa-filter"></i> Audit (Active)';
            } else {
                // Clear filter
                table.clearFilter();
                btn.classList.remove('btn-danger');
                btn.classList.add('btn-outline-danger');
                btn.innerHTML = '<i class="fas fa-filter"></i> Audit';
            }
        });

        // Export
        $('#export-btn').on('click', function() {
            table.download("xlsx", "aplus_master_data.xlsx", {sheetName: "A+ Masters"});
        });

        // LQS Play Controls
        $('#lqs-play-auto').on('click', function() {
            sortedLqsData = tableData.filter(item => item.lqs).sort((a, b) => parseFloat(a.lqs) - parseFloat(b.lqs));
            if (sortedLqsData.length === 0) {
                showToast('No LQS data available', 'error');
                return;
            }
            
            currentLqsIndex = 0;
            startLqsPlay();
        });

        $('#lqs-play-pause').on('click', stopLqsPlay);
        
        $('#lqs-play-forward').on('click', function() {
            if (sortedLqsData.length === 0) return;
            currentLqsIndex = Math.min(currentLqsIndex + 1, sortedLqsData.length - 1);
            highlightLqsRow();
        });
        
        $('#lqs-play-backward').on('click', function() {
            if (sortedLqsData.length === 0) return;
            currentLqsIndex = Math.max(currentLqsIndex - 1, 0);
            highlightLqsRow();
        });

        function startLqsPlay() {
            $('#lqs-play-auto').hide();
            $('#lqs-play-pause').show();
            $('#lqs-play-status').show();
            $('#lqs-play-forward').prop('disabled', false);
            $('#lqs-play-backward').prop('disabled', false);
            
            lqsPlayInterval = setInterval(() => {
                if (currentLqsIndex >= sortedLqsData.length - 1) {
                    stopLqsPlay();
                    return;
                }
                currentLqsIndex++;
                highlightLqsRow();
            }, 3000);
            
            highlightLqsRow();
        }

        function stopLqsPlay() {
            if (lqsPlayInterval) {
                clearInterval(lqsPlayInterval);
                lqsPlayInterval = null;
            }
            $('#lqs-play-auto').show();
            $('#lqs-play-pause').hide();
            $('#lqs-play-status').hide();
        }

        function highlightLqsRow() {
            if (!sortedLqsData[currentLqsIndex]) return;
            const currentItem = sortedLqsData[currentLqsIndex];
            $('#current-lqs-value').text(currentItem.lqs);
            table.setFilter("SKU", "=", currentItem.SKU);
            table.scrollToRow(currentItem.SKU, "center", true);
        }

        // Add functionality
        $('#addAPlusImagesBtn').on('click', function() {
            $('#addAPlusImagesModal').modal('show');
        });

        $('#saveAddAPlusImagesBtn').on('click', async function() {
            const sku = $('#addAPlusImagesSku').val();
            const db = $('#addDB').val();

            if (!sku) {
                showToast('Please select a SKU', 'error');
                return;
            }

            try {
                const result = await safeJsonFetch('/a-plus-images-master/store', {
                    method: 'POST',
                    body: JSON.stringify({ sku, db })
                });

                if (result.success) {
                    showToast('Data added successfully', 'success');
                    $('#addAPlusImagesModal').modal('hide');
                    table.setData('/a-plus-images-master-data-view');
                } else {
                    showToast(result.message || 'Failed to add data', 'error');
                }
            } catch (error) {
                showToast(error.message || 'Failed to add data', 'error');
            }
        });

        // Import functionality
        $('#aPlusImagesImportFile').on('change', function() {
            $('#importAPlusImagesBtn').prop('disabled', !this.files.length);
        });

        $('#downloadSampleAPlusImagesBtn').on('click', function() {
            const data = [['SKU', 'DB']];
            const ws = XLSX.utils.aoa_to_sheet(data);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Sample");
            XLSX.writeFile(wb, "aplus_import_sample.xlsx");
        });

        $('#importAPlusImagesBtn').on('click', async function() {
            const file = $('#aPlusImagesImportFile')[0].files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('file', file);

            $('#aPlusImagesImportProgress').show().find('.progress-bar').css('width', '50%');

            try {
                const result = await safeJsonFetch('/a-plus-images-master/import', {
                    method: 'POST',
                    body: formData
                });

                $('#aPlusImagesImportProgress').find('.progress-bar').css('width', '100%');

                if (result.success) {
                    $('#aPlusImagesImportResult')
                        .removeClass('alert-danger')
                        .addClass('alert-success')
                        .html(`<i class="fa fa-check-circle me-2"></i>${result.message}`)
                        .show();
                    showToast('Import completed successfully', 'success');
                    setTimeout(() => {
                        $('#importAPlusImagesModal').modal('hide');
                        table.setData('/a-plus-images-master-data-view');
                    }, 2000);
                } else {
                    $('#aPlusImagesImportResult')
                        .removeClass('alert-success')
                        .addClass('alert-danger')
                        .html(`<i class="fa fa-exclamation-circle me-2"></i>${result.message}`)
                        .show();
                    showToast('Import failed', 'error');
                }
            } catch (error) {
                $('#aPlusImagesImportProgress').hide();
                $('#aPlusImagesImportResult')
                    .removeClass('alert-success')
                    .addClass('alert-danger')
                    .html(`<i class="fa fa-exclamation-circle me-2"></i>${error.message}`)
                    .show();
                showToast(error.message || 'Import failed', 'error');
            }
        });
    });

    // Global functions for button actions
    function openDBModal(sku, currentValue) {
        $('#dbLinkSku').val(sku);
        $('#dbLinkInput').val(currentValue);
        $('#dbModalTitle').text(currentValue ? 'Edit DB Link' : 'Add DB Link');
        $('#dbLinkModal').modal('show');
    }

    // Normalize a value that may be a JSON-encoded string, a plain string, or an array.
    function toArray(value) {
        if (value == null || value === '') return [];
        if (Array.isArray(value)) return value.filter(v => v !== null && v !== '');
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed.startsWith('[')) {
                try {
                    const parsed = JSON.parse(trimmed);
                    if (Array.isArray(parsed)) return parsed.filter(v => v !== null && v !== '');
                } catch (e) { /* fall through */ }
            }
            return [trimmed];
        }
        return [];
    }

    function editAudit(sku, currentValue) {
        // Start from a clean slate every time.
        $('#editAuditSku').val(sku);
        $('#editAuditSuggestion').val(currentValue || '');
        $('#charCount').text((currentValue || '').length);
        $('#auditLinkData, #auditLinkData2, #auditLinkData3, #auditLinkData4').val('');
        $('#auditScreenshot').val('');
        $('#screenshotPreview').empty();
        $('#voiceNotePreview').empty();
        window.audioBlob = null;

        // Look up the row in the cached tabulator data so we can restore the
        // previously-saved links, screenshots and voice note for editing.
        const rowData = (tableData || []).find(r => r && r.SKU === sku) || {};

        // 4 link inputs — preload up to 4 URLs.
        const links = toArray(rowData.audit_link_data);
        const linkInputs = ['#auditLinkData', '#auditLinkData2', '#auditLinkData3', '#auditLinkData4'];
        links.slice(0, 4).forEach((url, i) => {
            $(linkInputs[i]).val(url || '');
        });

        // Existing screenshots — show as thumbnails with a "Saved" badge so the
        // user can distinguish them from newly-added files.
        const screenshots = toArray(rowData.audit_screenshot);
        if (screenshots.length) {
            const $preview = $('#screenshotPreview');
            screenshots.forEach((path) => {
                if (!path) return;
                const url = path.startsWith('http') ? path : ('/storage/' + path.replace(/^\/+/, ''));
                $preview.append(`
                    <div class="position-relative saved-thumb" title="${path}">
                        <a href="${url}" target="_blank">
                            <img src="${url}" class="img-thumbnail" style="width: 110px; height: 110px; object-fit: cover;">
                        </a>
                        <span class="badge bg-info position-absolute top-0 start-0" style="font-size: 9px;">Saved</span>
                    </div>
                `);
            });
        }

        // Existing voice note.
        const voiceNote = rowData.audit_voice_note;
        if (voiceNote) {
            const vurl = String(voiceNote).startsWith('http') ? voiceNote : ('/storage/' + String(voiceNote).replace(/^\/+/, ''));
            $('#voiceNotePreview').html(`
                <audio controls class="w-100 mt-2">
                    <source src="${vurl}">
                </audio>
                <small class="text-muted d-block">Previously saved voice note</small>
            `);
        }

        $('#editAuditSuggestionModal').modal('show');
    }

    $('#saveDBLinkBtn').on('click', async function() {
        const sku = $('#dbLinkSku').val();
        const dbLink = $('#dbLinkInput').val();

        if (!dbLink || !dbLink.trim()) {
            showToast('Please enter a DB link', 'error');
            return;
        }

        try {
            const result = await safeJsonFetch('/a-plus-images-master/update-db-link', {
                method: 'POST',
                body: JSON.stringify({ sku, db: dbLink })
            });

            if (result.success) {
                showToast('DB link updated successfully', 'success');
                $('#dbLinkModal').modal('hide');
                table.setData('/a-plus-images-master-data-view');
            } else {
                showToast(result.message || 'Failed to update DB link', 'error');
            }
        } catch (error) {
            showToast(error.message || 'Failed to update DB link', 'error');
        }
    });

    $('#saveEditAuditBtn').on('click', async function() {
        const sku = $('#editAuditSku').val();
        const auditSuggestion = $('#editAuditSuggestion').val();
        const saveBtn = $(this);
        const originalHtml = saveBtn.html();

        try {
            saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            const formData = new FormData();
            formData.append('sku', sku);
            formData.append('audit_suggestion', auditSuggestion);

            // Up to 4 link URLs — always send all 4 slots (even empty) so that
            // clearing a previously-saved URL actually persists on the server.
            ['#auditLinkData', '#auditLinkData2', '#auditLinkData3', '#auditLinkData4'].forEach(sel => {
                formData.append('link_data[]', ($(sel).val() || '').trim());
            });

            // Multi-file screenshots — pre-check 5MB / each before sending.
            const screenshotFiles = $('#auditScreenshot')[0].files || [];
            for (let i = 0; i < screenshotFiles.length; i++) {
                const f = screenshotFiles[i];
                if (f.size > 5 * 1024 * 1024) {
                    showToast(`"${f.name}" is ${(f.size / 1024 / 1024).toFixed(2)} MB — each screenshot must be 5MB or smaller`, 'error');
                    return;
                }
                formData.append('screenshot[]', f);
            }

            if (window.audioBlob) {
                const voiceNoteFile = new File([window.audioBlob], `voice_note_${Date.now()}.webm`, { type: 'audio/webm' });
                formData.append('voice_note', voiceNoteFile);
            }

            const result = await safeJsonFetch('/a-plus-images-master/update-audit-suggestion', {
                method: 'POST',
                body: formData
            });

            if (result.success) {
                showToast('Audit updated successfully', 'success');
                $('#editAuditSuggestionModal').modal('hide');
                table.setData('/a-plus-images-master-data-view');

                $('#auditLinkData, #auditLinkData2, #auditLinkData3, #auditLinkData4').val('');
                $('#auditScreenshot').val('');
                $('#screenshotPreview').html('');
                $('#voiceNotePreview').html('');
                window.audioBlob = null;
            } else {
                showToast(result.message || 'Failed to update audit', 'error');
            }
        } catch (error) {
            showToast(error.message || 'Failed to update audit', 'error');
        } finally {
            saveBtn.prop('disabled', false).html(originalHtml);
        }
    });

    // Character counter for audit suggestion
    $('#editAuditSuggestion').on('input', function() {
        $('#charCount').text(this.value.length);
    });

    // Fixed button functionality
    $('#fixedAuditBtn').on('click', async function() {
        const sku = $('#editAuditSku').val();
        const fixedBtn = $(this);
        const originalHtml = fixedBtn.html();

        try {
            fixedBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Saving...');

            const formData = new FormData();
            formData.append('sku', sku);
            formData.append('audit_suggestion', '');
            formData.append('is_fixed', '1');

            const result = await safeJsonFetch('/a-plus-images-master/update-audit-suggestion', {
                method: 'POST',
                body: formData
            });

            if (result.success) {
                showToast('Marked as Fixed successfully!', 'success');
                $('#editAuditSuggestionModal').modal('hide');
                table.setData('/a-plus-images-master-data-view');
            } else {
                showToast(result.message || 'Failed to mark as fixed', 'error');
            }
        } catch (error) {
            showToast(error.message || 'Failed to mark as fixed', 'error');
        } finally {
            fixedBtn.prop('disabled', false).html(originalHtml);
        }
    });

    // Voice to Text functionality
    $('#voiceToTextBtn').on('click', function() {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
            showToast('Speech recognition not supported in this browser', 'error');
            return;
        }
        
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        
        recognition.lang = 'en-US';
        recognition.interimResults = false;
        recognition.maxAlternatives = 1;
        
        recognition.onstart = function() {
            $('#voiceStatus').show();
        };
        
        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            const currentText = $('#editAuditSuggestion').val();
            $('#editAuditSuggestion').val(currentText + (currentText ? ' ' : '') + transcript);
            $('#charCount').text($('#editAuditSuggestion').val().length);
        };
        
        recognition.onerror = function(event) {
            showToast('Speech recognition error: ' + event.error, 'error');
            $('#voiceStatus').hide();
        };
        
        recognition.onend = function() {
            $('#voiceStatus').hide();
        };
        
        recognition.start();
    });

    // Screenshot preview (supports multi-select)
    $('#auditScreenshot').on('change', function(e) {
        renderScreenshotPreview(e.target.files);
    });

    // Render thumbnails for the current FileList in the file input.
    // Also re-appends any "Saved" thumbnails (existing screenshots loaded
    // when the modal was opened) by leaving them in place if no new files
    // are selected.
    function renderScreenshotPreview(files) {
        const $preview = $('#screenshotPreview');

        // Keep already-rendered "Saved" thumbnails (they have the .saved-thumb class)
        // and only clear newly-added previews when the file list is replaced.
        $preview.find('.new-thumb').remove();

        if (!files || !files.length) return;

        Array.from(files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(ev) {
                $preview.append(`
                    <div class="position-relative new-thumb" title="${file.name}">
                        <img src="${ev.target.result}" class="img-thumbnail" style="width: 110px; height: 110px; object-fit: cover;">
                        <span class="badge bg-dark position-absolute bottom-0 start-0" style="font-size: 10px; max-width: 110px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${file.name}</span>
                    </div>
                `);
            };
            reader.readAsDataURL(file);
        });
    }

    // === Paste-from-clipboard support for screenshots ===
    //
    // After the user takes a screenshot (Cmd+Shift+4 on macOS, PrintScreen on
    // Windows, Snipping Tool, etc.) the image lives on the system clipboard.
    // We accept it two ways:
    //   1) The "Paste from Clipboard" button reads the clipboard via the async
    //      Clipboard API (`navigator.clipboard.read`).
    //   2) Pressing Cmd/Ctrl+V anywhere in the dialog fires a "paste" event,
    //      from which we can pull image items even on browsers that disallow
    //      the async read API without explicit user gesture / permission.
    //
    // Both paths funnel through addClipboardImage() so behaviour is identical.

    async function readClipboardImages() {
        if (!navigator.clipboard || !navigator.clipboard.read) {
            throw new Error('Your browser does not support reading the clipboard. Use Ctrl/Cmd+V instead.');
        }

        // navigator.clipboard.read() may require a permission prompt the first time.
        const items = await navigator.clipboard.read();
        const blobs = [];
        for (const item of items) {
            const imageType = (item.types || []).find(t => t.startsWith('image/'));
            if (!imageType) continue;
            const blob = await item.getType(imageType);
            blobs.push({ blob, type: imageType });
        }
        if (!blobs.length) {
            throw new Error('No image found on the clipboard. Take a screenshot first, then click Paste.');
        }
        return blobs;
    }

    // Merge new clipboard-blobs into the file input, render previews, and flash status.
    function addClipboardImage(blobsOrFiles) {
        const fileInput = document.getElementById('auditScreenshot');
        if (!fileInput) return;

        // Build a DataTransfer to safely merge with any already-selected files.
        const dt = new DataTransfer();
        // Keep files the user may have already chosen via "Choose files".
        if (fileInput.files) {
            Array.from(fileInput.files).forEach(f => dt.items.add(f));
        }

        const stamp = Date.now();
        let addedCount = 0;
        blobsOrFiles.forEach((entry, idx) => {
            // entry may be a {blob, type} pair (from Clipboard API) or a real File (from paste event).
            let file;
            if (entry instanceof File) {
                file = entry;
            } else {
                const ext = (entry.type.split('/')[1] || 'png').replace(/[^a-z0-9]/gi, '');
                file = new File([entry.blob], `clipboard_${stamp}_${idx + 1}.${ext}`, { type: entry.type });
            }

            // Pre-flight 5MB check (mirrors saveEditAuditBtn).
            if (file.size > 5 * 1024 * 1024) {
                showToast(`"${file.name}" is ${(file.size / 1024 / 1024).toFixed(2)} MB — each screenshot must be 5MB or smaller`, 'error');
                return;
            }
            dt.items.add(file);
            addedCount++;
        });

        if (!addedCount) return;

        fileInput.files = dt.files;
        renderScreenshotPreview(fileInput.files);

        const $status = $('#pasteStatus');
        $status.text(addedCount + ' image' + (addedCount > 1 ? 's' : '') + ' pasted').show();
        setTimeout(() => $status.fadeOut(), 1500);
        showToast('Screenshot pasted from clipboard', 'success');
    }

    // Button: read clipboard explicitly.
    $('#pasteScreenshotBtn').on('click', async function() {
        const $btn = $(this);
        const original = $btn.html();
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Reading…');
        try {
            const blobs = await readClipboardImages();
            addClipboardImage(blobs);
        } catch (err) {
            showToast(err.message || 'Could not read clipboard', 'error');
        } finally {
            $btn.prop('disabled', false).html(original);
        }
    });

    // Keyboard: Ctrl/Cmd+V anywhere in the modal — but only when the user is NOT
    // typing into a text field (otherwise we'd block normal text paste).
    document.addEventListener('paste', function(e) {
        const $modal = $('#editAuditSuggestionModal');
        if (!$modal.hasClass('show')) return; // only when this modal is open

        // If the paste target is a text input/textarea, let it paste text normally
        // unless the clipboard contains an image (then we capture it).
        const items = (e.clipboardData && e.clipboardData.items) || [];
        const imageFiles = [];
        for (const it of items) {
            if (it.kind === 'file' && it.type && it.type.startsWith('image/')) {
                const f = it.getAsFile();
                if (f) imageFiles.push(f);
            }
        }
        if (!imageFiles.length) return;

        // We have at least one image — capture the event and add it.
        e.preventDefault();
        addClipboardImage(imageFiles);
    });

    // Voice Note Recording
    let mediaRecorder;
    let audioChunks = [];
    let recordingStartTime;
    let recordingInterval;

    $('#voiceNoteRecordBtn').on('click', async function() {
        if (!mediaRecorder || mediaRecorder.state === 'inactive') {
            // Start recording
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = (event) => {
                    audioChunks.push(event.data);
                };
                
                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    window.audioBlob = audioBlob;
                    const audioUrl = URL.createObjectURL(audioBlob);
                    $('#voiceNotePreview').html(`
                        <audio controls class="w-100 mt-2">
                            <source src="${audioUrl}" type="audio/webm">
                        </audio>
                    `);
                    stream.getTracks().forEach(track => track.stop());
                };
                
                mediaRecorder.start();
                recordingStartTime = Date.now();
                $('#voiceNoteRecordBtn').html('<i class="fas fa-stop"></i> Stop');
                $('#voiceNoteRecordBtn').removeClass('btn-success').addClass('btn-danger');
                $('#voiceNoteStatus').show();
                
                // Update timer
                recordingInterval = setInterval(() => {
                    const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
                    const minutes = Math.floor(elapsed / 60);
                    const seconds = elapsed % 60;
                    $('#voiceNoteTimer').text(`${minutes}:${seconds.toString().padStart(2, '0')}`);
                    
                    // Auto-stop after 2 minutes
                    if (elapsed >= 120) {
                        $('#voiceNoteRecordBtn').click();
                    }
                }, 1000);
                
            } catch (error) {
                showToast('Microphone access denied: ' + error.message, 'error');
            }
        } else {
            // Stop recording
            mediaRecorder.stop();
            $('#voiceNoteRecordBtn').html('<i class="fas fa-circle"></i> Record');
            $('#voiceNoteRecordBtn').removeClass('btn-danger').addClass('btn-success');
            $('#voiceNoteStatus').hide();
            clearInterval(recordingInterval);
        }
    });

    async function viewAuditHistory(sku) {
        try {
            const result = await safeJsonFetch(`/a-plus-images-master/audit-history/${encodeURIComponent(sku)}`);

            if (result.success && result.data && result.data.length > 0) {
                let html = '<div class="list-group">';
                result.data.forEach(item => {
                    const date = new Date(item.created_at || item.date).toLocaleString();
                    const actionType = item.action_type === 'FIXED' ? '<span class="badge bg-success ms-2">FIXED</span>' : '';
                    const userName = item.user_name || 'Unknown User';
                    const auditText = item.audit_suggestion || '<em class="text-muted">Cleared</em>';
                    
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <i class="fas fa-user me-2"></i>${userName}
                                        ${actionType}
                                    </h6>
                                    <p class="mb-1">${auditText}</p>
                                </div>
                                <small class="text-muted">${date}</small>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                $('#auditHistoryContent').html(html);
            } else {
                $('#auditHistoryContent').html('<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No history found for this SKU.</div>');
            }
            
            $('#auditHistoryModal').modal('show');
        } catch (error) {
            showToast('Error loading history: ' + error.message, 'error');
            $('#auditHistoryContent').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to load history: ' + error.message + '</div>');
            $('#auditHistoryModal').modal('show');
        }
    }

    async function viewStatusHistory(sku) {
        try {
            const result = await safeJsonFetch(`/a-plus-images-master/status-history/${encodeURIComponent(sku)}`);

            if (result.success && result.data) {
                let html = '<table class="table table-sm"><thead><tr><th>Date</th><th>Status</th></tr></thead><tbody>';
                result.data.forEach(item => {
                    html += `<tr><td>${item.date}</td><td>${item.status}</td></tr>`;
                });
                html += '</tbody></table>';
                $('#statusHistoryContent').html(html);
            } else {
                $('#statusHistoryContent').html('<p>No history found</p>');
            }
            
            $('#statusHistoryModal').modal('show');
        } catch (error) {
            showToast('Error loading history: ' + error.message, 'error');
        }
    }

    async function toggleStatus(sku, currentStatus) {
        const button = document.querySelector(`.status-toggle-btn[data-sku="${sku}"]`);
        if (!button || button.classList.contains('loading')) return;

        const newStatus = currentStatus === 'green' ? 'red' : 'green';

        try {
            button.classList.add('loading');

            const result = await safeJsonFetch('/a-plus-images-master/toggle-status', {
                method: 'POST',
                body: JSON.stringify({ sku: sku, status: newStatus })
            });

            if (result.success) {
                button.classList.remove('red', 'green', 'loading');
                button.classList.add(newStatus);
                button.setAttribute('onclick', `toggleStatus('${sku}', '${newStatus}')`);
                showToast('Status updated successfully', 'success');
            } else {
                showToast(result.message || 'Failed to toggle status', 'error');
                button.classList.remove('loading');
            }
        } catch (error) {
            console.error('Error toggling status:', error);
            showToast(error.message || 'Failed to toggle status', 'error');
            button.classList.remove('loading');
        }
    }

    async function pushData(sku) {
        try {
            const result = await safeJsonFetch('/a-plus-images-master/push', {
                method: 'POST',
                body: JSON.stringify({ sku })
            });

            if (result.success) {
                showToast('Data pushed successfully', 'success');
            } else {
                showToast(result.message || 'Failed to push data', 'error');
            }
        } catch (error) {
            showToast(error.message || 'Failed to push data', 'error');
        }
    }

    // View Competitors function
    async function viewCompetitors(sku) {
        const competitorsList = $('#competitorsList');
        
        // Set SKU in modal and form
        $('#competitorsSku').text(sku);
        $('#compSku').val(sku);
        
        // Show loading
        competitorsList.html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading competitors...</p>
            </div>
        `);
        
        $('#competitorsModal').modal('show');
        
        try {
            const data = await safeJsonFetch(`/amazon/competitors?sku=${encodeURIComponent(sku)}`, {
                method: 'GET'
            });

            if (data.success && data.competitors && data.competitors.length > 0) {
                renderCompetitorsList(data.competitors, data.lowest_price);
            } else {
                competitorsList.html(`
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No competitors found yet. Add your first competitor above!
                    </div>
                `);
            }
        } catch (error) {
            console.error('Error loading competitors:', error);
            competitorsList.html(`
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>Error: ${error.message || 'Failed to load competitors'}. Please try again.
                </div>
            `);
        }
    }

    // Render Competitors List
    function renderCompetitorsList(competitors, lowestPrice) {
        function escapeHtml(text) {
            if (text == null) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        if (!competitors || competitors.length === 0) {
            $('#competitorsList').html(`
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>No competitors found for this SKU
                </div>
            `);
            return;
        }
        
        let html = '<div class="table-responsive"><table class="table table-hover table-bordered table-sm">';
        html += `
            <thead class="table-light">
                <tr>
                    <th style="width: 30px;">#</th>
                    <th style="width: 60px;">Image</th>
                    <th style="width: 100px;">ASIN</th>
                    <th style="width: 250px;">Product Title</th>
                    <th>Seller</th>
                    <th style="width: 80px;">Price</th>
                    <th style="width: 90px;">Revenue<br><small>(30d)</small></th>
                    <th style="width: 70px;">Units<br><small>(30d)</small></th>
                    <th style="width: 100px;">Buy Box</th>
                    <th style="width: 60px;">Type</th>
                    <th style="width: 70px;">Rating</th>
                    <th style="width: 70px;">Reviews</th>
                    <th style="width: 60px;">Link</th>
                    <th style="width: 80px;">Actions</th>
                </tr>
            </thead>
            <tbody>
        `;
        
        competitors.forEach((item, index) => {
            const isLowest = (parseFloat(item.price) === parseFloat(lowestPrice));
            const rowClass = isLowest ? 'table-success' : '';
            const priceFormatted = '$' + parseFloat(item.price).toFixed(2);
            const priceBadge = isLowest ? 
                `<span class="badge bg-success">${priceFormatted} <i class="fas fa-trophy"></i></span>` : 
                `<strong>${priceFormatted}</strong>`;
            
            const productLink = item.link || item.product_link || '#';
            const productTitle = item.title || item.product_title || 'N/A';
            const sellerName = item.seller_name || '—';
            const imageUrl = item.image || '';
            const imageHtml = imageUrl ? `<img src="${imageUrl}" style="width: 50px; height: 50px; object-fit: contain;" />` : '<span style="color: #999;">—</span>';
            
            const revenue = item.monthly_revenue ? `<span style="color: #28a745; font-weight: 600;">$${parseFloat(item.monthly_revenue).toFixed(0)}</span>` : '<span style="color: #999;">—</span>';
            const units = item.monthly_units_sold ? `<span style="color: #007bff; font-weight: 600;">${parseInt(item.monthly_units_sold)}</span>` : '<span style="color: #999;">—</span>';
            const buyBox = item.buy_box_owner ? `<span style="font-size: 11px;">${item.buy_box_owner}</span>` : '<span style="color: #999;">—</span>';
            const sellerType = item.seller_type ? `<span class="badge bg-${item.seller_type === 'FBA' ? 'warning' : 'secondary'}">${item.seller_type}</span>` : '<span style="color: #999;">—</span>';
            
            const rating = item.rating ? `<span style="color: #ffc107;">${parseFloat(item.rating).toFixed(1)} <i class="fas fa-star"></i></span>` : '<span style="color: #999;">—</span>';
            const reviews = item.reviews ? `<span style="font-weight: 600;">${parseInt(item.reviews).toLocaleString()}</span>` : '<span style="color: #999;">—</span>';
            
            html += `
                <tr class="${rowClass}">
                    <td style="text-align: center;">${index + 1}</td>
                    <td style="text-align: center;">${imageHtml}</td>
                    <td><strong>${escapeHtml(item.asin || 'N/A')}</strong></td>
                    <td style="font-size: 12px;">${escapeHtml(productTitle)}</td>
                    <td style="font-size: 11px;">${escapeHtml(sellerName)}</td>
                    <td style="text-align: right;">${priceBadge}</td>
                    <td style="text-align: right;">${revenue}</td>
                    <td style="text-align: center;">${units}</td>
                    <td>${buyBox}</td>
                    <td style="text-align: center;">${sellerType}</td>
                    <td style="text-align: center;">${rating}</td>
                    <td style="text-align: center;">${reviews}</td>
                    <td style="text-align: center;">
                        <a href="${escapeHtml(productLink)}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </td>
                    <td style="text-align: center;">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCompetitor('${escapeHtml(item.id || '')}', '${escapeHtml(item.sku || '')}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody></table></div>';
        $('#competitorsList').html(html);
    }

    // Add Competitor Form Submit
    $('#addCompetitorForm').on('submit', async function(e) {
        e.preventDefault();
        
        const sku = $('#compSku').val();
        const asin = $('#compAsin').val().trim();
        const price = parseFloat($('#compPrice').val());
        const link = $('#compLink').val().trim();
        const marketplace = $('#compMarketplace').val();
        
        if (!asin) {
            showToast('ASIN is required', 'error');
            return;
        }
        
        if (!price || price <= 0) {
            showToast('Valid price is required', 'error');
            return;
        }
        
        const submitBtn = $(this).find('button[type="submit"]');
        const originalHtml = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Adding...');
        
        try {
            await safeJsonFetch('/amazon/lmp/add', {
                method: 'POST',
                body: JSON.stringify({
                    sku: sku,
                    asin: asin,
                    price: price,
                    product_link: link || null,
                    marketplace: marketplace
                })
            });

            showToast('Competitor added successfully', 'success');
            
            // Reset form
            this.reset();
            $('#compSku').val(sku);
            
            // Reload competitors list
            viewCompetitors(sku);
            
        } catch (error) {
            console.error('Error adding competitor:', error);
            showToast(error.message || 'Failed to add competitor', 'error');
        } finally {
            submitBtn.prop('disabled', false).html(originalHtml);
        }
    });

    // Delete Competitor
    async function deleteCompetitor(competitorId, sku) {
        if (!confirm('Are you sure you want to delete this competitor?')) {
            return;
        }
        
        try {
            await safeJsonFetch('/amazon/lmp/delete', {
                method: 'POST',
                body: JSON.stringify({ id: competitorId })
            });

            showToast('Competitor deleted successfully', 'success');
            
            // Reload competitors list
            viewCompetitors(sku);
            
        } catch (error) {
            console.error('Error deleting competitor:', error);
            showToast(error.message || 'Failed to delete competitor', 'error');
        }
    }

    // Image Upload Functions
    function openImageUploadModal(sku, type) {
        $('#imageUploadSku').val(sku);
        $('#imageUploadType').val(type);
        $('#imageTypeLabel').text(type.charAt(0).toUpperCase() + type.slice(1));
        $('#imageFileInput').val('');
        $('#imagePreviewContainer').hide();
        $('#imageUploadModal').modal('show');
    }

    // Preview image when selected
    $('#imageFileInput').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#imagePreview').attr('src', e.target.result);
                $('#imagePreviewContainer').show();
            };
            reader.readAsDataURL(file);
        }
    });

    // Save Image
    $('#saveImageBtn').on('click', async function() {
        const fileInput = $('#imageFileInput')[0];
        const file = fileInput.files[0];
        
        if (!file) {
            showToast('Please select an image file', 'error');
            return;
        }

        const sku = $('#imageUploadSku').val();
        const imageType = $('#imageUploadType').val();

        if (!sku) {
            showToast('SKU is missing', 'error');
            return;
        }

        if (file.size > 10 * 1024 * 1024) {
            showToast('Image must be 10MB or smaller (selected: ' + (file.size / 1024 / 1024).toFixed(2) + ' MB)', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('sku', sku);
        formData.append('image_type', imageType);
        formData.append('image_file', file);

        const saveBtn = $('#saveImageBtn');
        const originalText = saveBtn.html();
        saveBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Uploading...');

        try {
            const result = await safeJsonFetch('/a-plus-images-master/upload-image', {
                method: 'POST',
                body: formData
            });

            if (result.success) {
                showToast('Image uploaded successfully!', 'success');
                $('#imageUploadModal').modal('hide');
                table.setData('/a-plus-images-master-data-view');
            } else {
                showToast(result.message || result.error || 'Failed to upload image', 'error');
            }
        } catch (error) {
            console.error('Error uploading image:', error);
            showToast(error.message || 'Failed to upload image', 'error');
        } finally {
            saveBtn.prop('disabled', false).html(originalText);
        }
    });
</script>
@endsection
