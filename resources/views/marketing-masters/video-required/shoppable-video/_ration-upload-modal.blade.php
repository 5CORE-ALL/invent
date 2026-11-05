<style>
    .ration-act {
        border: 0;
        background: transparent;
        color: #2563eb;
        padding: 0 4px;
        margin-left: 4px;
        font-size: 12px;
        line-height: 1;
        cursor: pointer;
    }
    .ration-act:hover { opacity: 0.7; }
    .ration-act.ration-dl-btn { color: #16a34a; }
    .ration-act-empty { color: #6b7280; font-weight: 600; font-size: 12px; }
    #ration-upload-toast {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 2000;
        max-width: 360px;
        padding: 10px 14px;
        border-radius: 6px;
        color: #fff;
        font-size: 13px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.25);
        display: none;
    }
    #ration-upload-toast.is-run { background: #2563eb; }
    #ration-upload-toast.is-ok { background: #16a34a; }
    #ration-upload-toast.is-err { background: #dc2626; }
    #rationUploadsModal .ru-table-wrap { max-height: 380px; overflow-y: auto; }
    #rationUploadsModal .ru-table th { position: sticky; top: 0; background: #f3f4f6; z-index: 1; font-size: 12px; }
    #rationUploadsModal .ru-table td { font-size: 12px; vertical-align: middle; }
    #rationUploadsModal .ru-preview-box { display: none; background: #000; border-radius: 6px; padding: 8px; position: relative; text-align: center; }
    #rationUploadsModal .ru-preview-box video { max-width: 100%; max-height: 340px; }
    #rationUploadsModal .ru-preview-close { position: absolute; top: 6px; right: 10px; color: #fff; background: transparent; border: 0; font-size: 22px; line-height: 1; cursor: pointer; }
</style>

<!-- Uploaded videos: upload a file, see what was uploaded, play / download / delete it -->
<div class="modal fade" id="rationUploadsModal" tabindex="-1" aria-labelledby="rationUploadsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rationUploadsModalLabel"><i class="fas fa-photo-video"></i> Uploaded Videos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="border rounded p-3 mb-3">
                    <div class="fw-semibold mb-2">Upload a video</div>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small mb-1" for="ru-sku">SKU</label>
                            <input type="text" class="form-control form-control-sm" id="ru-sku" list="ru-sku-list" placeholder="Type or pick a SKU" autocomplete="off">
                            <datalist id="ru-sku-list"></datalist>
                        </div>
                        <div class="col-md-2" id="ru-ratio-wrap">
                            <label class="form-label small mb-1" for="ru-ratio">Ratio</label>
                            <select class="form-select form-select-sm" id="ru-ratio"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1" for="ru-file">Video file</label>
                            <input type="file" class="form-control form-control-sm" id="ru-file" accept="video/mp4,video/quicktime,video/webm,video/x-matroska,.mp4,.mov,.webm,.m4v,.mkv">
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-primary btn-sm w-100" id="ru-upload-btn">
                                <i class="fas fa-upload"></i> Upload
                            </button>
                        </div>
                    </div>
                    <div class="progress mt-2" style="height: 8px; display: none;" id="ru-progress-wrap">
                        <div class="progress-bar" id="ru-progress" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="text-muted d-block mt-2">mp4, mov, webm, m4v or mkv, up to 500 MB. The uploaded file becomes the video link of that SKU.</small>
                </div>

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" id="ru-search" placeholder="Search uploads by SKU">
                    </div>
                    <div class="col-md-3" id="ru-filter-wrap">
                        <select class="form-select form-select-sm" id="ru-filter-ratio"></select>
                    </div>
                    <div class="col-md-5 text-md-end small text-muted" id="ru-count"></div>
                </div>

                <div class="ru-preview-box mb-3" id="ru-preview-box">
                    <button type="button" class="ru-preview-close" id="ru-preview-close" aria-label="Close preview">&times;</button>
                    <video id="ru-preview" controls playsinline></video>
                </div>

                <div class="ru-table-wrap border rounded">
                    <table class="table table-sm table-hover mb-0 ru-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Ratio</th>
                                <th>File</th>
                                <th>Size</th>
                                <th>Uploaded by</th>
                                <th>Uploaded at</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ru-rows">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
