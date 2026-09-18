    @include('layouts.shared.page-title', [
        'page_title' => $lqsPage['title'] ?? 'LQS',
        'sub_title'  => $lqsPage['subtitle'] ?? 'Listing Quality Score – Parent SKU, Listing ID, Sessions, Units & LQS metrics',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    {{-- ── Filter bar ── --}}
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">

                        {{-- Row type filter --}}
                        <select id="amz-row-type-filter" class="form-select form-select-sm" style="width:120px;">
                            <option value="all" selected>All Rows</option>
                            <option value="parents">Parents</option>
                            <option value="skus">SKUs</option>
                        </select>

                        {{-- Inventory filter --}}
                        <select id="amz-inv-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all" @if(empty($lqsPage['has_yoast_seo'])) selected @endif>All Inventory</option>
                            <option value="zero">0 Inventory</option>
                            <option value="more" @if(!empty($lqsPage['has_yoast_seo'])) selected @endif>More than 0</option>
                        </select>

                        {{-- LQS filter --}}
                        <select id="amz-score-filter" class="form-select form-select-sm" style="width:140px;">
                            <option value="all" selected>All LQS</option>
                            @if(!empty($lqsPage['has_audit']))
                                <option value="80-100">High (80-100%)</option>
                                <option value="60-79">Good (60-79%)</option>
                                <option value="40-59">Medium (40-59%)</option>
                                <option value="1-39">Low (1-39%)</option>
                                <option value="below-90">Below 90%</option>
                            @else
                                <option value="8-10">High (8-10)</option>
                                <option value="6-7">Good (6-7)</option>
                                <option value="4-5">Medium (4-5)</option>
                                <option value="1-3">Low (1-3)</option>
                                <option value="below-9">Below 9</option>
                            @endif
                            <option value="missing">No LQS</option>
                        </select>

                        {{-- DIL% dropdown --}}
                        <div class="amz-lqs-dropdown">
                            <button class="btn btn-light btn-sm amz-dil-toggle" type="button" id="amz-dil-btn">
                                <span class="amz-sc def"></span>DIL%
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="amz-dil-item" href="#" data-color="all">
                                    <span class="amz-sc def"></span>All DIL</a></li>
                                <li><a class="amz-dil-item" href="#" data-color="red">
                                    <span class="amz-sc red"></span>Red (&lt;25%)</a></li>
                                <li><a class="amz-dil-item" href="#" data-color="green">
                                    <span class="amz-sc green"></span>Green (25–50%)</a></li>
                                <li><a class="amz-dil-item" href="#" data-color="pink">
                                    <span class="amz-sc pink"></span>Pink (50%+)</a></li>
                            </ul>
                        </div>

                        {{-- SKU search --}}
                        <input type="text" id="amz-sku-search" class="form-control form-control-sm"
                            style="max-width:220px;" placeholder="Search SKU / {{ $lqsPage['listing_label'] ?? 'Listing ID' }}...">

                        <button type="button" id="amz-refresh-btn" class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                        <button type="button" id="amz-export-btn" class="btn btn-sm btn-success">
                            <i class="fas fa-file-csv"></i> Export CSV
                        </button>
                        <a href="{{ $lqsPage['routes']['sample'] }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-excel"></i> Sample Sheet
                        </a>
                        <a href="{{ $lqsPage['routes']['download'] }}" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-download"></i> Download Sheet
                        </a>
                        <button type="button" id="amz-upload-btn" class="btn btn-sm btn-primary">
                            <i class="fas fa-upload"></i> Upload Sheet
                        </button>
                        @if(!empty($lqsPage['has_yoast_seo']))
                            <select id="amz-seo-filter" class="form-select form-select-sm" style="width:160px;">
                                <option value="all" selected>All SEO</option>
                                <option value="good">SEO Good</option>
                                <option value="ok">SEO OK</option>
                                <option value="bad">SEO Needs improvement</option>
                                <option value="na">SEO Not analyzed</option>
                            </select>
                            <select id="amz-read-filter" class="form-select form-select-sm" style="width:190px;">
                                <option value="all" selected>All Readability</option>
                                <option value="good">Readability Good</option>
                                <option value="ok">Readability OK</option>
                                <option value="bad">Readability Needs improvement</option>
                                <option value="na">Readability Not analyzed</option>
                            </select>
                            <button type="button" id="lqs-yoast-sync-btn" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-magnifying-glass me-1"></i> Sync Yoast scores
                            </button>
                        @endif
                        @if(!empty($lqsPage['has_api_metrics']))
                            <span class="badge bg-success align-self-center">API metrics</span>
                        @else
                            <span class="badge bg-secondary align-self-center">Sheet metrics</span>
                        @endif
                    </div>

                    @if(!empty($lqsPage['has_yoast_seo']))
                    <div id="lqs-yoast-dashboard" class="lqs-yoast-dash mb-3">
                        <div class="lqs-yoast-card">
                            <div class="lqs-yoast-card-head">
                                <h6>SEO scores</h6>
                                <span class="lqs-yoast-note">In-stock listings only (INV &gt; 0)</span>
                            </div>
                            <div class="lqs-yoast-rows" data-kind="seo">
                                <button type="button" class="lqs-yoast-row" data-rating="good"><span class="lqs-yoast-dot good"></span><span>Good</span><strong id="lqs-seo-good">0</strong></button>
                                <button type="button" class="lqs-yoast-row" data-rating="ok"><span class="lqs-yoast-dot ok"></span><span>OK</span><strong id="lqs-seo-ok">0</strong></button>
                                <button type="button" class="lqs-yoast-row" data-rating="bad"><span class="lqs-yoast-dot bad"></span><span>Needs improvement</span><strong id="lqs-seo-bad">0</strong></button>
                                <button type="button" class="lqs-yoast-row" data-rating="na"><span class="lqs-yoast-dot na"></span><span>Not analyzed</span><strong id="lqs-seo-na">0</strong></button>
                            </div>
                        </div>
                        <div class="lqs-yoast-card">
                            <div class="lqs-yoast-card-head">
                                <h6>Readability scores</h6>
                                <span class="lqs-yoast-note">In-stock listings only (INV &gt; 0)</span>
                            </div>
                            <div class="lqs-yoast-rows" data-kind="read">
                                <button type="button" class="lqs-yoast-row" data-rating="good"><span class="lqs-yoast-dot good"></span><span>Good</span><strong id="lqs-read-good">0</strong></button>
                                <button type="button" class="lqs-yoast-row" data-rating="ok"><span class="lqs-yoast-dot ok"></span><span>OK</span><strong id="lqs-read-ok">0</strong></button>
                                <button type="button" class="lqs-yoast-row" data-rating="bad"><span class="lqs-yoast-dot bad"></span><span>Needs improvement</span><strong id="lqs-read-bad">0</strong></button>
                                <button type="button" class="lqs-yoast-row" data-rating="na"><span class="lqs-yoast-dot na"></span><span>Not analyzed</span><strong id="lqs-read-na">0</strong></button>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ── Summary badges ── --}}
                    <div id="amz-summary-stats" class="mt-2 p-3 bg-light rounded mb-3">
                        <div class="d-flex flex-wrap gap-2 amz-badge-row" role="group" aria-label="Summary metrics">
                            <span class="badge bg-info fs-6 p-2 amz-badge-chart" id="amz-total-sess-badge"
                                  data-metric="total_sessions" style="font-weight:700;color:#111;cursor:pointer;" title="Click for trend">Sessions L30: 0</span>
                            <span class="badge bg-warning fs-6 p-2 amz-badge-chart" id="amz-avg-dil-badge"
                                  data-metric="avg_dil" style="font-weight:700;color:#111;cursor:pointer;"
                                  title="Dilution % · Click for trend">DIL: 0%</span>
                            <span class="badge bg-success fs-6 p-2 amz-badge-chart" id="amz-avg-lqs-badge"
                                  data-metric="avg_lqs" style="font-weight:700;cursor:pointer;" title="Click for trend">LQS: –</span>
                            <span class="badge bg-secondary fs-6 p-2 amz-badge-chart" id="amz-avg-rating-badge"
                                  data-metric="avg_rating" style="font-weight:700;cursor:pointer;" title="Click for trend">Rating: –</span>
                            <span class="badge fs-6 p-2 amz-badge-chart" id="amz-lqs-below9-badge"
                                  data-metric="lqs_below_9_count"
                                  style="font-weight:700;cursor:pointer;background:#dc3545;color:#fff;"
                                  title="SKUs with LQS score below 9 · Click for trend">&lt; 9: –</span>
                            <span class="badge fs-6 p-2" id="amz-cvr-badge"
                                  style="font-weight:700;background:#146eb4;color:#fff;cursor:pointer;" title="CVR = Total Sold ÷ Total Sessions × 100 · Click for trend">CVR: –</span>
                            <span class="badge fs-6 p-2" id="amz-sheet-upload-badge"
                                  style="font-weight:700;background:#198754;color:#fff;cursor:pointer;user-select:none;"
                                  title="Upload LQS sheet for this marketplace">
                                <i class="fas fa-upload me-1"></i>Upload LQS
                            </span>
                        </div>
                    </div>

                    <div id="amz-lqs-table"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── CVR History Chart Modal (matches all-marketplace-master design) ── --}}
    <div class="modal fade p-0" id="amzCvrChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzCvrChartTitle">LQS Amz – CVR (Rolling)</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzCvrChartRange" class="form-select form-select-sm bg-white"
                            style="width:110px;height:26px;font-size:11px;padding:1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30">30 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="amzCvrChartContainer" style="height:20vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="amzCvrChart"></canvas>
                        </div>
                        <div id="amzCvrRefPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="amzCvrHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="amzCvrMedian" style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="amzCvrLowest" style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzCvrLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data…</p>
                    </div>
                    <div id="amzCvrNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No CVR history yet. Data is saved each time the page loads.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Action Taken Modal ── --}}
    <div class="modal fade" id="amzActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm" style="max-width:400px;">
            <div class="modal-content" style="border-radius:10px;overflow:hidden;">
                <div class="modal-header py-2 px-3" style="background:#ff9900;">
                    <h6 class="modal-title mb-0 text-white" style="font-size:13px;">
                        <i class="fas fa-edit me-1"></i> Action Taken
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted mb-1" style="font-size:11px;">
                        SKU: <strong id="amzActionSkuLabel" class="text-dark"></strong>
                    </p>
                    <textarea id="amzActionText" class="form-control form-control-sm" rows="3"
                        maxlength="100" placeholder="Describe the action taken… (max 100 chars)"
                        style="resize:none;font-size:12px;"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small class="text-muted"><span id="amzActionCharCount">0</span>/100</small>
                        <small id="amzActionErr" class="text-danger" style="display:none;font-size:11px;"></small>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="amzActionSaveBtn" class="btn btn-sm" style="background:#ff9900;color:#fff;border-color:#e88e00;">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── History Modal ── --}}
    <div class="modal fade" id="amzHistoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" style="max-width:480px;">
            <div class="modal-content" style="border-radius:10px;overflow:hidden;">
                <div class="modal-header py-2 px-3" style="background:#495057;">
                    <h6 class="modal-title mb-0 text-white" style="font-size:13px;">
                        <i class="fas fa-history me-1"></i> Action History –
                        <span id="amzHistorySkuLabel" style="font-size:11px;opacity:.85;"></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3" style="max-height:55vh;overflow-y:auto;">
                    <div id="amzHistoryLoading" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-secondary"></div>
                        <p class="mt-1 small text-muted mb-0">Loading history…</p>
                    </div>
                    <div id="amzHistoryList" style="display:none;"></div>
                    <div id="amzHistoryEmpty" class="text-center py-3" style="display:none;">
                        <i class="fas fa-inbox text-muted fa-2x mb-2"></i>
                        <p class="small text-muted mb-0">No actions recorded yet.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Badge Trend Chart Modal – matches all-marketplace-master design --}}
    <div class="modal fade p-0" id="amzBadgeChartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog shadow-none m-0 mx-0">
            <div class="modal-content" style="overflow:hidden;">
                <div class="modal-header bg-info text-white py-1 px-3">
                    <h6 class="modal-title mb-0" style="font-size:13px;">
                        <i class="fas fa-chart-area me-1"></i>
                        <span id="amzBadgeChartTitle">LQS Amz – Trend</span>
                    </h6>
                    <div class="d-flex align-items-center gap-2">
                        <select id="amzBadgeChartRange" class="form-select form-select-sm bg-white"
                            style="width:110px;height:26px;font-size:11px;padding:1px 8px;">
                            <option value="7">7 Days</option>
                            <option value="14">14 Days</option>
                            <option value="30">30 Days</option>
                            <option value="32" selected>32 Days</option>
                            <option value="60">60 Days</option>
                            <option value="90">90 Days</option>
                            <option value="0">Lifetime</option>
                        </select>
                        <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body p-2">
                    <div id="amzBadgeChartContainer" style="height:20vh;display:flex;align-items:stretch;">
                        <div style="flex:1;min-width:0;position:relative;">
                            <canvas id="amzBadgeChart"></canvas>
                        </div>
                        <div id="amzBadgeRefPanel" style="width:100px;display:flex;flex-direction:column;justify-content:center;
                                gap:8px;padding:6px 8px;border-left:1px solid #e9ecef;background:#f8f9fa;border-radius:0 4px 4px 0;">
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#dc3545;margin-bottom:1px;">Highest</div>
                                <div id="amzBadgeHighest" style="font-size:13px;font-weight:700;color:#dc3545;">–</div>
                            </div>
                            <div style="text-align:center;border-top:1px dashed #adb5bd;border-bottom:1px dashed #adb5bd;padding:4px 0;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6c757d;margin-bottom:1px;">Median</div>
                                <div id="amzBadgeMedian" style="font-size:13px;font-weight:700;color:#6c757d;">–</div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#198754;margin-bottom:1px;">Lowest</div>
                                <div id="amzBadgeLowest" style="font-size:13px;font-weight:700;color:#198754;">–</div>
                            </div>
                        </div>
                    </div>
                    <div id="amzBadgeLoading" class="text-center py-3" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <p class="mt-1 text-muted small mb-0">Loading chart data…</p>
                    </div>
                    <div id="amzBadgeNoData" class="text-center py-3" style="display:none;">
                        <i class="fas fa-exclamation-circle text-warning fa-2x mb-2"></i>
                        <p class="text-muted small mb-0">No trend data yet. Data is saved each time the page loads.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── LQS Sheet Upload Modal ── --}}
    <div class="modal fade" id="amzSheetUploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm" style="max-width:420px;">
            <div class="modal-content" style="border-radius:10px;overflow:hidden;">
                <div class="modal-header py-2 px-3" style="background:#198754;">
                    <h6 class="modal-title mb-0 text-white" style="font-size:13px;">
                        <i class="fas fa-file-excel me-1"></i> Upload LQS Sheet
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted mb-2" style="font-size:11px;">
                        Columns: SKU, {{ $lqsPage['listing_label'] ?? 'Listing ID' }}, LQS, Rating, Reviews, L30, Sessions, Price
                    </p>
                    <input type="file" id="amzSheetFile" class="form-control form-control-sm" accept=".xlsx,.xls,.csv">
                    <small id="amzSheetUploadErr" class="text-danger d-none"></small>
                    <div class="d-flex gap-2 mt-2">
                        <a href="{{ $lqsPage['routes']['sample'] }}" class="small">Download sample</a>
                        <a href="{{ $lqsPage['routes']['download'] }}" class="small">Download current</a>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="amzSheetUploadSave" class="btn btn-sm btn-success">
                        <i class="fas fa-upload me-1"></i> Upload
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if(!empty($lqsPage['has_yoast_seo']))
    <div class="modal fade" id="lqsYoastDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" style="max-width:560px;">
            <div class="modal-content" style="border-radius:10px;overflow:hidden;">
                <div class="modal-header py-2 px-3" style="background:#1d2327;">
                    <h6 class="modal-title mb-0 text-white" style="font-size:13px;">
                        <i class="fas fa-chart-pie me-1"></i> Yoast-style listing score –
                        <span id="lqsYoastSkuLabel" style="font-size:11px;opacity:.85;"></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div class="d-flex gap-2 mb-2">
                        <span class="badge" id="lqsYoastSeoBadge">SEO: –</span>
                        <span class="badge" id="lqsYoastReadBadge">Readability: –</span>
                    </div>
                    <div class="small text-muted mb-1">Focus keyphrase</div>
                    <div id="lqsYoastKeyphrase" class="small mb-2">–</div>
                    <div class="small text-muted mb-1">SEO title</div>
                    <div id="lqsYoastSeoTitle" class="small mb-2">–</div>
                    <div class="small fw-bold mb-1">What to fix</div>
                    <div id="lqsYoastFindings" class="small" style="white-space:pre-wrap;"></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if(!empty($lqsPage['has_audit']))
    <div class="modal fade" id="lqsAuditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog" style="max-width:720px;">
            <div class="modal-content" style="border-radius:10px;overflow:hidden;">
                <div class="modal-header py-2 px-3" style="background:#0d6efd;">
                    <h6 class="modal-title mb-0 text-white" style="font-size:13px;">
                        <i class="fas fa-magnifying-glass me-1"></i> Audit LQS –
                        <span id="lqsAuditSkuLabel" style="font-size:11px;opacity:.85;"></span>
                    </h6>
                    <button type="button" class="btn-close btn-close-white" style="font-size:10px;" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <p class="text-muted mb-2" style="font-size:11px;">
                        First-time eBay audit prompt is created for this channel. Edit it before you run the AI.
                    </p>
                    <label for="lqsAuditPrompt" class="form-label mb-1" style="font-size:12px;">Prompt</label>
                    <textarea id="lqsAuditPrompt" class="form-control form-control-sm" rows="10"
                        style="font-size:12px;resize:vertical;white-space:pre-wrap;letter-spacing:normal;word-spacing:normal;word-break:normal;"></textarea>
                    <small id="lqsAuditErr" class="text-danger d-none" style="letter-spacing:normal;word-spacing:normal;"></small>
                    <div id="lqsAuditResult" class="mt-3" style="display:none;">
                        <div class="mb-2">
                            <span class="badge bg-primary" id="lqsAuditScoreBadge">LQS: –</span>
                        </div>
                        <div class="small fw-bold mb-1">Findings</div>
                        <div id="lqsAuditFindings" class="small mb-2" style="white-space:pre-wrap;"></div>
                        <div class="small fw-bold mb-1">Suggestions for Content Editor</div>
                        <div id="lqsAuditSuggestions" class="small" style="white-space:pre-wrap;"></div>
                    </div>
                </div>
                <div class="modal-footer py-2 px-3 justify-content-between">
                    <button type="button" id="lqsAuditSavePromptBtn" class="btn btn-sm btn-outline-secondary">
                        Save Prompt
                    </button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="button" id="lqsAuditRunBtn" class="btn btn-sm btn-primary">
                            <i class="fas fa-magnifying-glass me-1"></i> Run Audit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
