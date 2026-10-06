<div class="row mb-3" id="wayfair-price-upload-panel">
    <div class="col-12">
        <div class="card border-secondary">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h5 class="mb-0">Wayfair Price Upload</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-secondary" id="wf-upload-queue-badge">Queue: checking</span>
                        <span class="badge bg-secondary" id="wf-upload-worker-badge">Wayfair Upload Worker: checking</span>
                    </div>
                </div>
                <div id="wf-upload-alert" class="alert alert-warning py-2" style="display:none;"></div>
                <div class="row g-2 small">
                    <div class="col-md-3"><strong>Automatic Upload:</strong> <span id="wf-upload-enabled">—</span>
                        <button type="button" class="btn btn-sm btn-outline-primary ms-1" id="wf-upload-toggle">ON / OFF</button>
                    </div>
                    <div class="col-md-3"><strong>Schedule:</strong> <span id="wf-upload-schedule">—</span></div>
                    <div class="col-md-3"><strong>Last Upload:</strong> <span id="wf-upload-last">—</span></div>
                    <div class="col-md-3"><strong>Last Status:</strong> <span id="wf-upload-status">—</span></div>
                    <div class="col-md-3"><strong>Last File:</strong> <span id="wf-upload-file">—</span></div>
                    <div class="col-md-3"><strong>SKUs in File:</strong> <span id="wf-upload-skus">—</span></div>
                    <div class="col-md-3"><strong>Changed SKUs:</strong> <span id="wf-upload-changed">—</span></div>
                    <div class="col-md-3"><strong>Successful:</strong> <span id="wf-upload-ok">—</span></div>
                    <div class="col-md-3"><strong>Failed:</strong> <span id="wf-upload-bad">—</span></div>
                    <div class="col-md-3"><strong>Attempts:</strong> <span id="wf-upload-attempts">—</span></div>
                    <div class="col-md-6"><strong>Wayfair Reference:</strong> <span id="wf-upload-ref">—</span></div>
                </div>
                <div class="small text-muted mt-2">
                    Last job started: <span id="wf-upload-job-started">—</span>
                    · completed: <span id="wf-upload-job-completed">—</span>
                    · failed: <span id="wf-upload-job-failed">—</span>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="wf-upload-generate">Generate File</button>
                    <button type="button" class="btn btn-sm btn-primary" id="wf-upload-now">Upload Now</button>
                    <button type="button" class="btn btn-sm btn-warning" id="wf-upload-retry">Retry Failed</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="wf-upload-history-btn" data-bs-toggle="modal" data-bs-target="#wfUploadHistoryModal">View History</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="wfUploadHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Wayfair upload history</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle" id="wf-upload-history-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>File</th>
                                <th>Total SKUs</th>
                                <th>Changed SKUs</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Wayfair Reference</th>
                                <th>Error</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="wfUploadErrorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body"><pre id="wf-upload-error-text" class="mb-0 small" style="white-space:pre-wrap;"></pre></div>
        </div>
    </div>
</div>
