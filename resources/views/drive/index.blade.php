@extends('layouts.vertical', ['title' => '5Core Drive', 'mode' => $mode ?? '', 'demo' => $demo ?? '', 'skipHighcharts' => true])

@section('css')
    <link rel="stylesheet" href="{{ asset('css/drive.css') }}?v={{ @filemtime(public_path('css/drive.css')) }}">
@endsection

@section('content')
<div id="drive-app" class="drv" data-layout="grid">
    {{-- ===== Left navigation ===== --}}
    <aside class="drv-side">
        <div class="drv-brand">
            <img class="drv-brand-logo" src="{{ asset('images/5core-drive-logo.png') }}" alt="5Core">
            <div>
                <div class="drv-brand-name">5Core Drive</div>
                <div class="drv-brand-sub">Files, media &amp; docs in one place</div>
            </div>
        </div>

        <div class="dropdown">
            <button class="drv-new-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="drv-new-btn">
                <i class="ri-add-line"></i> New
            </button>
            <ul class="dropdown-menu drv-menu shadow-lg">
                <li><a class="dropdown-item" href="#" data-action="new-folder"><i class="ri-folder-add-line"></i> New folder</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-action="upload-files"><i class="ri-file-upload-line"></i> Upload files</a></li>
                <li><a class="dropdown-item" href="#" data-action="upload-folder"><i class="ri-folder-upload-line"></i> Upload folder</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-action="new-file" data-ext="txt"><i class="ri-file-text-line text-secondary"></i> Text document</a></li>
                <li><a class="dropdown-item" href="#" data-action="new-file" data-ext="csv"><i class="ri-file-excel-2-line text-success"></i> CSV spreadsheet</a></li>
                <li><a class="dropdown-item" href="#" data-action="new-file" data-ext="md"><i class="ri-markdown-line text-primary"></i> Markdown note</a></li>
                <li><a class="dropdown-item" href="#" data-action="new-file" data-ext="html"><i class="ri-html5-line text-danger"></i> HTML snippet</a></li>
            </ul>
        </div>

        <nav class="drv-nav">
            <a href="#/my" data-view="my" class="drv-nav-link drv-drop-root"><i class="ri-hard-drive-2-line"></i> My Drive</a>
            <a href="#/shared" data-view="shared" class="drv-nav-link"><i class="ri-group-line"></i> Shared with me</a>
            <a href="#/shared_by_me" data-view="shared_by_me" class="drv-nav-link"><i class="ri-share-forward-line"></i> Shared by me</a>
            <a href="#/recent" data-view="recent" class="drv-nav-link"><i class="ri-time-line"></i> Recent</a>
            <a href="#/starred" data-view="starred" class="drv-nav-link"><i class="ri-star-line"></i> Starred</a>
            <a href="#/trash" data-view="trash" class="drv-nav-link"><i class="ri-delete-bin-6-line"></i> Trash</a>
        </nav>

        <div class="drv-storage">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="fw-semibold"><i class="ri-cloud-line me-1"></i>Storage</span>
                <span class="small text-muted" id="drv-storage-files"></span>
            </div>
            <div class="drv-storage-bar" id="drv-storage-bar"></div>
            <div class="drv-storage-total" id="drv-storage-total">—</div>
            <div class="drv-storage-legend" id="drv-storage-legend"></div>
        </div>
    </aside>

    {{-- ===== Main ===== --}}
    <section class="drv-main" id="drv-main">
        <header class="drv-top">
            <div class="drv-search">
                <i class="ri-search-line"></i>
                <input type="search" id="drv-search" placeholder="Search in Drive" autocomplete="off">
                <kbd>/</kbd>
            </div>
            <div class="drv-top-actions">
                <div class="dropdown">
                    <button class="drv-icon-btn" data-bs-toggle="dropdown" title="Sort"><i class="ri-sort-desc"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end drv-menu" id="drv-sort-menu">
                        <li><h6 class="dropdown-header">Sort by</h6></li>
                        <li><a class="dropdown-item" href="#" data-sort="name"><i class="ri-font-size"></i> Name</a></li>
                        <li><a class="dropdown-item" href="#" data-sort="updated_at"><i class="ri-calendar-line"></i> Last modified</a></li>
                        <li><a class="dropdown-item" href="#" data-sort="size"><i class="ri-database-2-line"></i> File size</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#" data-dir="asc"><i class="ri-sort-asc"></i> Ascending</a></li>
                        <li><a class="dropdown-item" href="#" data-dir="desc"><i class="ri-sort-desc"></i> Descending</a></li>
                    </ul>
                </div>
                <div class="drv-seg">
                    <button class="drv-seg-btn" data-layout-btn="list" title="List view"><i class="ri-list-check"></i></button>
                    <button class="drv-seg-btn" data-layout-btn="grid" title="Grid view"><i class="ri-layout-grid-line"></i></button>
                </div>
                <button class="drv-icon-btn" id="drv-toggle-details" title="Details"><i class="ri-information-line"></i></button>
            </div>
        </header>

        <div class="drv-subbar">
            <nav class="drv-crumbs" id="drv-crumbs"></nav>
            <div class="drv-chips" id="drv-chips">
                <button class="drv-chip active" data-filter="all">All</button>
                <button class="drv-chip" data-filter="folder"><i class="ri-folder-3-line"></i> Folders</button>
                <button class="drv-chip" data-filter="image"><i class="ri-image-2-line"></i> Images</button>
                <button class="drv-chip" data-filter="video"><i class="ri-movie-2-line"></i> Videos</button>
                <button class="drv-chip" data-filter="docs"><i class="ri-file-text-line"></i> Documents</button>
                <button class="drv-chip" data-filter="pdf"><i class="ri-file-pdf-2-line"></i> PDFs</button>
                <button class="drv-chip" data-filter="sheet"><i class="ri-file-excel-2-line"></i> Sheets</button>
                <button class="drv-chip" data-filter="audio"><i class="ri-music-2-line"></i> Audio</button>
                <button class="drv-chip" data-filter="archive"><i class="ri-file-zip-line"></i> Archives</button>
            </div>
        </div>

        <div class="drv-selbar" id="drv-selbar" hidden>
            <button class="drv-icon-btn" data-sel="clear" title="Clear selection"><i class="ri-close-line"></i></button>
            <span class="drv-selbar-count" id="drv-sel-count">0 selected</span>
            <div class="drv-selbar-actions" id="drv-sel-actions"></div>
        </div>

        <div class="drv-body" id="drv-body" tabindex="0">
            <div class="drv-loading" id="drv-loading"><div class="drv-spinner"></div></div>
            <div id="drv-content"></div>
        </div>

        <div class="drv-dropzone" id="drv-dropzone">
            <div class="drv-dropzone-inner">
                <i class="ri-upload-cloud-2-line"></i>
                <div class="drv-dropzone-title">Drop files to upload</div>
                <div class="drv-dropzone-sub" id="drv-dropzone-sub">to My Drive</div>
            </div>
        </div>
    </section>

    {{-- ===== Details panel ===== --}}
    <aside class="drv-details" id="drv-details">
        <div class="drv-details-head">
            <div class="drv-details-title" id="drv-details-title">Details</div>
            <button class="drv-icon-btn" id="drv-details-close"><i class="ri-close-line"></i></button>
        </div>
        <div class="drv-details-body" id="drv-details-body"></div>
    </aside>
</div>

{{-- Hidden inputs --}}
<input type="file" id="drv-input-files" multiple hidden>
<input type="file" id="drv-input-folder" webkitdirectory directory multiple hidden>
<input type="file" id="drv-input-version" hidden>

{{-- Context menu --}}
<div class="drv-ctx" id="drv-ctx" hidden></div>

{{-- Upload progress --}}
<div class="drv-uploads" id="drv-uploads" hidden>
    <div class="drv-uploads-head">
        <span id="drv-uploads-title">Uploading…</span>
        <div>
            <button class="drv-icon-btn sm" id="drv-uploads-min" title="Minimize"><i class="ri-subtract-line"></i></button>
            <button class="drv-icon-btn sm" id="drv-uploads-close" title="Close"><i class="ri-close-line"></i></button>
        </div>
    </div>
    <div class="drv-uploads-list" id="drv-uploads-list"></div>
</div>

{{-- Toasts --}}
<div class="drv-toasts" id="drv-toasts"></div>

{{-- Preview --}}
<div class="drv-preview" id="drv-preview" hidden>
    <div class="drv-preview-head">
        <div class="drv-preview-name"><span id="drv-preview-icon"></span><span id="drv-preview-name"></span></div>
        <div class="drv-preview-actions" id="drv-preview-actions"></div>
    </div>
    <button class="drv-preview-nav prev" id="drv-preview-prev"><i class="ri-arrow-left-s-line"></i></button>
    <div class="drv-preview-stage" id="drv-preview-stage"></div>
    <button class="drv-preview-nav next" id="drv-preview-next"><i class="ri-arrow-right-s-line"></i></button>
</div>

{{-- Generic prompt --}}
<div class="modal fade drv-modal" id="drv-prompt" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="drv-prompt-form">
            <div class="modal-header"><h5 class="modal-title" id="drv-prompt-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" class="form-control form-control-lg" id="drv-prompt-input" autocomplete="off" required maxlength="250">
                <div class="drv-swatches mt-3" id="drv-prompt-colors" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn drv-btn-primary" id="drv-prompt-ok">OK</button>
            </div>
        </form>
    </div>
</div>

{{-- Confirm --}}
<div class="modal fade drv-modal" id="drv-confirm" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="drv-confirm-title"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="drv-confirm-body"></div>
            <div class="modal-footer" id="drv-confirm-footer"></div>
        </div>
    </div>
</div>

{{-- Share --}}
<div class="modal fade drv-modal" id="drv-share" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-user-shared-line me-2"></i>Share “<span id="drv-share-name"></span>”</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="drv-share-body"></div>
        </div>
    </div>
</div>

{{-- Move / copy --}}
<div class="modal fade drv-modal" id="drv-move" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="drv-move-title">Move</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0">
                <div class="drv-move-crumbs" id="drv-move-crumbs"></div>
                <div class="drv-move-list" id="drv-move-list"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-light btn-sm" id="drv-move-newfolder"><i class="ri-folder-add-line"></i> New folder</button>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn drv-btn-primary" id="drv-move-ok">Move here</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Text editor --}}
<div class="modal fade drv-modal" id="drv-editor" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ri-edit-2-line me-2"></i><span id="drv-editor-name"></span></h5>
                <span class="ms-3 small text-muted" id="drv-editor-status"></span>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <textarea id="drv-editor-text" class="drv-editor-text" spellcheck="false"></textarea>
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-muted">Ctrl + S to save · every save keeps the previous version</span>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn drv-btn-primary" id="drv-editor-save"><i class="ri-save-3-line"></i> Save</button>
            </div>
        </div>
    </div>
</div>

{{-- Links (for listings) --}}
<div class="modal fade drv-modal" id="drv-links" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="ri-links-line me-2"></i>Direct file links</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Anyone with these links can view the files. Paste them into listings, image URL fields or sheets.</p>
                <textarea class="form-control drv-mono" id="drv-links-text" rows="8" readonly></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn drv-btn-primary" id="drv-links-copy"><i class="ri-file-copy-line"></i> Copy all</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
    <script>
        window.DRIVE_CONFIG = {
            base: @json(url('/drive')),
            chunkSize: {{ (int) $chunkSize }},
            maxFileBytes: {{ (int) $maxFileBytes }},
            me: @json(['id' => auth()->id(), 'name' => auth()->user()->name, 'email' => auth()->user()->email]),
            csrf: @json(csrf_token()),
        };
    </script>
    <script src="{{ asset('js/drive.js') }}?v={{ @filemtime(public_path('js/drive.js')) }}"></script>
@endsection
