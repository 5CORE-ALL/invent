@extends('layouts.vertical', ['title' => 'Quotations Container'])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
    <style>
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
            padding: 8px 16px; margin: 0 4px; border-radius: 6px; font-size: 0.95rem; font-weight: 500;
        }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active { background: #2563eb; color: #fff; }
        .qc-q-btn {
            width: 28px; height: 28px; border-radius: 50%; border: 2px solid #94a3b8; background: #fff;
            color: #64748b; font-weight: 800; font-size: 14px; line-height: 1; padding: 0; cursor: pointer;
        }
        .qc-q-btn:hover { border-color: #2563eb; color: #2563eb; }
        .qc-q-btn.filled { background: #16a34a; border-color: #16a34a; color: #fff; }
        .qc-q-btn.filled:hover { background: #15803d; border-color: #15803d; color: #fff; }
        .qc-qr-btn {
            min-width: 40px; height: 28px; border-radius: 14px; border: 2px solid #94a3b8; background: #fff;
            color: #64748b; font-weight: 800; font-size: 12px; line-height: 1; padding: 0 8px; cursor: pointer;
        }
        .qc-qr-btn:hover { border-color: #2563eb; color: #2563eb; }
        .qc-qr-btn.filled { background: #2563eb; border-color: #2563eb; color: #fff; }
        .qc-qr-btn.filled:hover { background: #1d4ed8; border-color: #1d4ed8; }
        .qc-qr-btn .qc-qr-count { font-weight: 600; margin-left: 4px; opacity: .9; }
        .qc-qf-btn, .qc-send-btn {
            height: 28px; border-radius: 14px; border: 2px solid #94a3b8; background: #fff; color: #64748b;
            font-weight: 700; font-size: 12px; line-height: 1; padding: 0 10px; cursor: pointer; white-space: nowrap;
        }
        .qc-qf-btn:hover, .qc-send-btn:hover { border-color: #2563eb; color: #2563eb; }
        .qc-qf-btn.filled { background: #16a34a; border-color: #16a34a; color: #fff; }
        .qc-qf-btn.changed { border-color: #dc2626; box-shadow: 0 0 0 2px rgba(220, 38, 38, .25); }
        .qc-alert-tri { color: #dc2626; font-size: 14px; margin-right: 4px; animation: qcPulse 1.4s ease-in-out infinite; }
        .qc-qf-btn.filled .qc-alert-tri { color: #fecaca; }
        @keyframes qcPulse { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }
        .qc-send-btn.filled { background: #2563eb; border-color: #2563eb; color: #fff; }
        .qc-qf-btn[disabled], .qc-send-btn[disabled] { opacity: .5; cursor: not-allowed; }
        .qf-locked { background: #f8fafc !important; }
        #qrModal .modal-dialog { max-width: 98vw; width: 98vw; margin-left: auto; margin-right: auto; }
        #qrTable input { min-width: 110px; }
        #qrTable tr.qr-lowest td { background: #ecfdf5; }
        /* ───────── Zen UI ───────── */
        .qz-page { --qz-ink:#0f2a3d; --qz-teal:#2a9d9f; --qz-teal-d:#1f7f81; --qz-soft:#f4f8fa; --qz-line:#e3ebf0; }
        .qz-hero {
            position: relative; overflow: hidden; border-radius: 22px; margin: 4px 0 22px; min-height: 240px;
            background: #0f2a3d url('{{ asset('images/quotations-cargo-ship.jpg') }}') right 56% / cover no-repeat;
            box-shadow: 0 18px 40px -22px rgba(15, 42, 61, .55);
        }
        .qz-hero::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(95deg, rgba(10, 32, 48, .92) 0%, rgba(10, 32, 48, .7) 34%, rgba(10, 32, 48, .1) 62%, rgba(10, 32, 48, 0) 100%);
        }
        .qz-hero-inner { position: relative; z-index: 1; padding: 26px 34px 30px; color: #fff; }
        .qz-logo {
            display: inline-flex; align-items: center; background: #fff; border-radius: 14px; padding: 9px 18px;
            box-shadow: 0 10px 24px -12px rgba(0, 0, 0, .5);
        }
        .qz-logo img { height: 40px; width: auto; display: block; }
        .qz-hero h1 { font-size: 2rem; font-weight: 700; letter-spacing: .01em; margin: 18px 0 4px; color: #fff; }
        .qz-hero p { margin: 0; color: rgba(255, 255, 255, .78); font-size: .98rem; max-width: 520px; }
        .qz-chips { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 18px; }
        .qz-chip {
            background: rgba(255, 255, 255, .14); border: 1px solid rgba(255, 255, 255, .28); backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px); color: #fff; border-radius: 999px; padding: 5px 14px; font-size: .82rem; font-weight: 600;
        }
        .qz-page > .row .card { border: 1px solid var(--qz-line); border-radius: 20px; box-shadow: 0 14px 36px -26px rgba(15, 42, 61, .5) !important; }
        .qz-page .card-body { padding: 22px 24px; }
        .qz-page .form-control, .qz-page .form-select, .qz-page .input-group-text {
            border-color: var(--qz-line); border-radius: 12px; background-color: #fff;
        }
        .qz-page .input-group > .form-control { border-radius: 0 12px 12px 0; }
        .qz-page .input-group > .input-group-text { border-radius: 12px 0 0 12px; }
        .qz-page .form-control:focus, .qz-page .form-select:focus {
            border-color: var(--qz-teal); box-shadow: 0 0 0 .22rem rgba(42, 157, 159, .16);
        }
        .qz-page .form-label { color: #4a6272; font-size: .82rem; }
        .qz-page .btn { border-radius: 999px; font-weight: 600; padding-left: 16px; padding-right: 16px; }
        .qz-page .btn-primary { background: var(--qz-teal); border-color: var(--qz-teal); }
        .qz-page .btn-primary:hover, .qz-page .btn-primary:focus { background: var(--qz-teal-d); border-color: var(--qz-teal-d); }
        .qz-page .btn-outline-primary { color: var(--qz-teal-d); border-color: var(--qz-teal); }
        .qz-page .btn-outline-primary:hover { background: var(--qz-teal); border-color: var(--qz-teal); color: #fff; }
        .qz-page .btn-outline-primary .badge { background: var(--qz-teal) !important; }
        .qz-page .btn-outline-primary:hover .badge { background: #fff !important; color: var(--qz-teal-d); }
        .qz-page .btn-outline-secondary { border-color: #b8c7d1; color: #4a6272; }
        .qz-page .btn-outline-secondary:hover { background: #4a6272; border-color: #4a6272; color: #fff; }
        #qc-count { background: var(--qz-soft) !important; border: 1px solid var(--qz-line); border-radius: 999px; font-size: .85rem !important; padding: 7px 14px; }
        /* table */
        .qz-page .tabulator { border: 1px solid var(--qz-line); border-radius: 14px; overflow: hidden; background: #fff; }
        .qz-page .tabulator .tabulator-header { background: var(--qz-soft); border-bottom: 1px solid var(--qz-line); }
        .qz-page .tabulator .tabulator-header .tabulator-col { background: var(--qz-soft); border-right-color: var(--qz-line); color: var(--qz-ink); }
        .qz-page .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title-holder { text-align: center; }
        .qz-page .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title {
            text-align: center; width: 100%; padding-right: 0; white-space: normal;
        }
        .qz-page .tabulator-row { border-bottom: 1px solid #eef3f6; }
        .qz-page .tabulator-row:hover { background: #f0f8f8; }
        .qz-page .tabulator-row .tabulator-cell { border-right-color: #f0f4f7; }
        .qz-page .tabulator .tabulator-footer { background: var(--qz-soft); border-top: 1px solid var(--qz-line); }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page { border-radius: 999px; }
        .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active { background: var(--qz-teal, #2a9d9f); color: #fff; }
        /* modals */
        .qz-page .modal-content { border: 0 !important; border-radius: 22px; overflow: hidden; box-shadow: 0 30px 70px -28px rgba(15, 42, 61, .65) !important; }
        .qz-page .modal-header.bg-primary {
            position: relative; padding: 18px 24px;
            background: linear-gradient(100deg, rgba(10, 32, 48, .94), rgba(10, 32, 48, .6)), url('{{ asset('images/quotations-cargo-ship.jpg') }}') right 58% / cover !important;
        }
        .qz-page .modal-header .modal-title { font-size: 1.08rem; letter-spacing: .01em; }
        .qz-page .modal-body { background: #fff; padding: 22px 24px; }
        .qz-page .modal-footer { background: var(--qz-soft); border-top: 1px solid var(--qz-line); padding: 14px 24px; }
        .qz-page .modal .table thead th { background: var(--qz-soft); color: var(--qz-ink); border-bottom: 1px solid var(--qz-line); font-weight: 700; }
        .qz-page .modal .table > :not(caption) > * > * { border-color: #eef3f6; }
        .qz-page .alert { border-radius: 14px; border: 0; }
        .qz-page .qc-q-btn, .qz-page .qc-qr-btn, .qz-page .qc-qf-btn, .qz-page .qc-send-btn { transition: all .15s ease; }
        @media (max-width: 767px) {
            .qz-hero-inner { padding: 20px; }
            .qz-hero h1 { font-size: 1.5rem; }
            .qz-hero::before { background: linear-gradient(180deg, rgba(10, 32, 48, .85), rgba(10, 32, 48, .55)); }
        }    </style>
@endsection
@section('content')
    <div class="qz-page">
    <div class="qz-hero">
        <div class="qz-hero-inner">
            <span class="qz-logo"><img src="{{ asset('images/5core_logo_tight.png') }}" alt="5 Core"></span>
            <h1>Quotations Container</h1>
            <p>Request, compare and book freight quotes for every open container &mdash; calm, clear and in one place.</p>
            <div class="qz-chips">
                <span class="qz-chip"><i class="fas fa-box me-1"></i><span id="qz-hero-count">0</span> open containers</span>
                <span class="qz-chip"><i class="fas fa-ship me-1"></i>Forwarder quotes</span>
                <span class="qz-chip"><i class="fas fa-ranking-star me-1"></i>L1 &middot; L2 &middot; L3 ranking</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3 gap-3 flex-wrap">
                        <div class="input-group" style="max-width: 320px;">
                            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="qc-search" class="form-control border-start-0"
                                placeholder="Search container, supplier, port…" autocomplete="off">
                        </div>
                        @if ($canEdit)
                            <button type="button" id="qc-forwarder-btn" class="btn btn-outline-primary btn-sm" style="border-radius: 6px;"
                                title="Choose the forwarders quote requests are sent to">
                                <i class="fas fa-truck-fast"></i> Forwarder <span class="badge bg-primary" id="qc-forwarder-count">0</span>
                            </button>
                            <button type="button" id="qc-text-btn" class="btn btn-outline-secondary btn-sm" style="border-radius: 6px;"
                                title="Edit the predefined email text sent to forwarders">
                                <i class="fas fa-pen-to-square"></i> Text
                            </button>
                            <button type="button" id="qc-add-container-btn" class="btn btn-primary btn-sm" style="border-radius: 6px;">
                                <i class="fas fa-plus"></i> Add Container
                            </button>
                        @endif
                        <span class="badge bg-light text-dark fs-6" id="qc-count">0 containers</span>
                        @if ($canEdit)
                            <span class="text-muted small">Click <b>Q</b> to enter or edit a container's quotation details.</span>
                        @else
                            <span class="text-muted small"><i class="fas fa-lock me-1"></i>View only — this form can be filled in by 5Core users only.</span>
                        @endif
                    </div>
                    <div id="quotations-container-table"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="qcModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-file-invoice-dollar me-2"></i>Quotation — <span id="qcModalContainer"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="qcForm" autocomplete="off">
                    <div class="modal-body">
                        <input type="hidden" name="container_number" id="qc_container_number">
                        <div class="alert alert-danger py-2 d-none" id="qcError"></div>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Supplier</label>
                                <input type="text" name="supplier" id="qc_supplier" class="form-control"
                                    list="qcSupplierList" placeholder="Type to search supplier…" maxlength="255">
                                <datalist id="qcSupplierList">
                                    @foreach ($suppliers as $s)
                                        <option value="{{ $s }}"></option>
                                    @endforeach
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Container Size</label>
                                <input type="text" name="container_size" id="qc_container_size" class="form-control"
                                    list="qcSizeList" placeholder="e.g. 45'" maxlength="50">
                                <datalist id="qcSizeList">
                                    <option value="45'"></option>
                                    <option value="20GP"></option>
                                    <option value="40GP"></option>
                                    <option value="40HQ"></option>
                                    <option value="45HQ"></option>
                                    <option value="LCL"></option>
                                </datalist>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Gross Weight</label>
                                <input type="text" name="gross_weight" id="qc_gross_weight" class="form-control"
                                    placeholder="e.g. &lt; 15000 Kgs" maxlength="100">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Pickup Address</label>
                                <textarea name="pickup_address" id="qc_pickup_address" class="form-control" rows="2" maxlength="2000"></textarea>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Loading Port</label>
                                <select name="loading_port" id="qc_loading_port" class="form-select">
                                    <option value="">Select loading port</option>
                                    <option value="Ningbo">Ningbo</option>
                                    <option value="Guangzhou">Guangzhou</option>
                                    <option value="Tianjin">Tianjin</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Via Port</label>
                                <input type="text" name="via_port" id="qc_via_port" class="form-control" maxlength="255">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Discharge Port</label>
                                <input type="text" name="discharge_port" id="qc_discharge_port" class="form-control" maxlength="255">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="qcSaveBtn">
                            <i class="fas fa-save me-1"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-envelope-open-text me-2"></i>Quotes received — <span id="qrModalContainer"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="qrError"></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="qrTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="min-width:200px;">Forwarder</th>
                                    <th style="min-width:150px;">Shipping Line</th>
                                    <th style="min-width:150px;">Est Dt Sail</th>
                                    <th>Pickup Charges ($)</th>
                                    <th>Freight ($)</th>
                                    <th>Total ($)</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="qrBody"></tbody>
                        </table>
                    </div>
                    <datalist id="qcForwarderList">
                        @foreach ($forwarders as $f)
                            <option value="{{ $f }}"></option>
                        @endforeach
                    </datalist>
                    <div class="text-muted small d-none" id="qrEmpty">No quotes entered yet — add the first forwarder quote.</div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="qrAddRow">
                        <i class="fas fa-plus me-1"></i> Add forwarder quote
                    </button>
                    <span class="text-muted small ms-2">Total = Pickup Charges + Freight. The cheapest total is highlighted.</span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="qrSaveBtn">
                        <i class="fas fa-save me-1"></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>    {{-- Combined QR Form (Q details read-only + forwarder quote section) --}}
    <div class="modal fade" id="qfModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-file-lines me-2"></i>QR Form — <span id="qfContainer"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 d-none" id="qfNoQ">The Q details are not filled in yet — fill them with the Q button first.</div>
                    <div class="fw-bold text-uppercase small text-muted mb-2">Container details (from Q — auto-filled, not editable)</div>
                    <div class="row g-3 mb-4" id="qfQ"></div>
                    <div class="fw-bold text-uppercase small text-muted mb-1">Forwarder quote</div>
                    <div class="text-muted small mb-2">This section is filled in by the forwarder through the link emailed to them. 5Core users cannot fill it.</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><label class="form-label small fw-semibold">Forwarder</label><input class="form-control qf-locked" disabled></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold">Shipping Line</label><input class="form-control qf-locked" disabled></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold">Est Dt Sail</label><input type="date" class="form-control qf-locked" disabled></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold">Pickup Charges ($)</label><input class="form-control qf-locked" disabled></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold">Freight ($)</label><input class="form-control qf-locked" disabled></div>
                        <div class="col-md-4"><label class="form-label small fw-semibold">Total ($)</label><input class="form-control qf-locked" disabled></div>
                    </div>
                    <div class="fw-bold text-uppercase small text-muted mb-2">Requests sent for this container</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr>
                                <th>Forwarder</th><th>Email</th><th>Sent</th><th>Status</th><th>Total ($)</th><th></th>
                            </tr></thead>
                            <tbody id="qfRequests"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    {{-- Send quote requests to forwarders --}}
    <div class="modal fade" id="sendModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-paper-plane me-2"></i>Send quote request — <span id="sendContainer"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 d-none" id="sendNoQ">Fill in the Q details for this container before sending.</div>
                    <div class="alert alert-danger py-2 d-none" id="sendError"></div>
                    <div class="small text-muted mb-2">Each ticked forwarder gets a separate email from <b>sourcing1@5core.com</b> with their own personal form link.</div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <select id="sendTypeFilter" class="form-select form-select-sm" style="width: 180px;" title="Filter by type">
                            <option value="Supplier">Supplier</option>
                            <option value="Forwarders" selected>Forwarders</option>
                            <option value="Photographer">Photographer</option>
                        </select>
                        <div class="input-group input-group-sm" style="max-width: 240px;">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="sendSearch" class="form-control" placeholder="Search…" autocomplete="off">
                        </div>
                        <div class="form-check mb-0 ms-2">
                            <input class="form-check-input" type="checkbox" id="sendAll">
                            <label class="form-check-label small fw-semibold" for="sendAll">Select all shown</label>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm ms-auto" id="sendAddToggle"><i class="fas fa-plus"></i> Add</button>
                    </div>
                    <div class="border rounded p-2 bg-light mb-2 d-none" id="sendAddBox">
                        <div class="row g-2">
                            <div class="col-md-3"><input type="text" id="sendNewName" class="form-control form-control-sm" placeholder="Name *" maxlength="100"></div>
                            <div class="col-md-3"><input type="text" id="sendNewCompany" class="form-control form-control-sm" placeholder="Company" maxlength="100"></div>
                            <div class="col-md-3"><input type="email" id="sendNewEmail" class="form-control form-control-sm" placeholder="Email" maxlength="100"></div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-success flex-fill" id="sendNewSave">Save</button>
                                <button type="button" class="btn btn-sm btn-light" id="sendNewCancel">Cancel</button>
                            </div>
                        </div>
                        <div class="small text-muted mt-1">Saved with the type currently chosen in the filter.</div>
                    </div>
                    <div id="sendList" class="list-group mb-2"></div>                    <div id="sendResults" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <span class="badge bg-light text-dark me-auto" id="sendSelectedCount">0 selected</span>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="sendBtn"><i class="fas fa-paper-plane me-1"></i> Send</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Forwarder list (from the Suppliers page data) with checkboxes --}}
    <div class="modal fade" id="forwarderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-truck-fast me-2"></i>Forwarders</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="fwError"></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                        <select id="fwTypeFilter" class="form-select form-select-sm" style="width: 180px;" title="Filter by type">
                            <option value="Supplier">Supplier</option>
                            <option value="Forwarders" selected>Forwarders</option>
                            <option value="Photographer">Photographer</option>
                        </select>
                        <div class="input-group input-group-sm" style="max-width: 240px;">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="fwSearch" class="form-control" placeholder="Search…" autocomplete="off">
                        </div>
                        <div class="form-check mb-0 ms-2">
                            <input class="form-check-input" type="checkbox" id="fwAll">
                            <label class="form-check-label small fw-semibold" for="fwAll">Select all shown</label>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm ms-auto" id="fwAddToggle"><i class="fas fa-plus"></i> Add</button>
                    </div>
                    <div class="border rounded p-2 bg-light mb-2 d-none" id="fwAddBox">
                        <div class="row g-2">
                            <div class="col-md-3"><input type="text" id="fwNewName" class="form-control form-control-sm" placeholder="Name *" maxlength="100"></div>
                            <div class="col-md-3"><input type="text" id="fwNewCompany" class="form-control form-control-sm" placeholder="Company" maxlength="100"></div>
                            <div class="col-md-3"><input type="email" id="fwNewEmail" class="form-control form-control-sm" placeholder="Email" maxlength="100"></div>
                            <div class="col-md-3 d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-success flex-fill" id="fwNewSave">Save</button>
                                <button type="button" class="btn btn-sm btn-light" id="fwNewCancel">Cancel</button>
                            </div>
                        </div>
                        <div class="small text-muted mt-1">Saved with the type currently chosen in the filter.</div>
                    </div>
                    <div id="fwList" class="list-group"></div>
                </div>
                <div class="modal-footer">
                    <span class="badge bg-light text-dark me-auto" id="fwSelectedCount">0 selected</span>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="fwSave"><i class="fas fa-save me-1"></i> Save selection</button>
                </div>
            </div>
        </div>
    </div>
    {{-- Editable predefined email text --}}
    <div class="modal fade" id="textModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fas fa-pen-to-square me-2"></i>Email text sent to forwarders</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="textError"></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject</label>
                        <input type="text" id="textSubject" class="form-control" maxlength="255">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Message</label>
                        <textarea id="textBody" class="form-control" rows="14" maxlength="10000"></textarea>
                    </div>
                    <div class="small text-muted">
                        These placeholders are replaced for each email:
                        <span id="textPlaceholders"></span>
                        <br>Keep <code>{link}</code> in the message — it is the forwarder's personal form link.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary me-auto" id="textReset">Reset to default</button>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="textSave"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@section('script')
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canEdit = @json((bool) $canEdit);
            const dataUrl = @json(route('container.quotations.data'));
            const saveUrl = @json(route('container.quotations.save'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
            // Prefilled on the form until a value is saved.
            const DEFAULTS = {
                container_size: "45'",
                gross_weight: '< 15000 Kgs',
                discharge_port: 'CMH, Ohio, USA',
            };
            const fields = ['supplier', 'container_size', 'gross_weight', 'pickup_address',
                'loading_port', 'via_port', 'discharge_port'];

            function esc(v) {
                return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }
            function textCell(cell) {
                const v = String(cell.getValue() ?? '').trim();
                return v ? esc(v) : '<span class="text-muted">—</span>';
            }
            function numCell(prefix, suffix) {
                return function (cell) {
                    const v = cell.getValue();
                    if (v === null || v === undefined || v === '') return '<span class="text-muted">—</span>';
                    return prefix + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + suffix;
                };
            }

            // Quotes ranked cheapest first (by total); quotes without a total are ignored.
            function rankedQuotes(quotes) {
                return (quotes || [])
                    .filter(q => q && q.total !== null && q.total !== undefined && q.total !== '')
                    .slice()
                    .sort((a, b) => Number(a.total) - Number(b.total));
            }
            // L1 / L2 / L3 = lowest / second lowest / third lowest quote, with forwarder name.
            function lowestColumn(title, rank) {
                return {
                    title: title, field: 'quotes', minWidth: 130,
                    sorter: function (a, b) {
                        const x = rankedQuotes(a)[rank], y = rankedQuotes(b)[rank];
                        if (!x && !y) return 0;
                        if (!x) return 1;
                        if (!y) return -1;
                        return Number(x.total) - Number(y.total);
                    },
                    formatter: function (cell) {
                        const q = rankedQuotes(cell.getValue())[rank];
                        if (!q) return '<span class="text-muted">—</span>';
                        return '<div style="line-height:1.25"><b>$' + Number(q.total).toLocaleString(undefined,
                            { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</b><br><span class="small text-muted">' +
                            esc(q.forwarder || '') + '</span></div>';
                    },
                };
            }
            const table = new Tabulator('#quotations-container-table', {
                ajaxURL: dataUrl,
                ajaxConfig: 'GET',
                layout: 'fitData',
                pagination: true,
                paginationSize: 50,
                paginationMode: 'local',
                movableColumns: false,
                resizableColumns: true,
                height: '70vh',
                placeholder: 'No containers found on the Ready To Ship page.',
                index: 'container_number',
                columns: [
                    { title: 'Container', field: 'container_number', frozen: true, headerSort: true, minWidth: 120,
                      formatter: c => '<b>' + esc(c.getValue()) + '</b>' },
                    { title: 'Q', field: 'has_details', frozen: true, width: 56, hozAlign: 'center', headerSort: false,
                      headerHozAlign: 'center',
                      formatter: function (cell) {
                          const filled = cell.getValue() ? ' filled' : '';
                          let tip = cell.getValue() ? 'Edit quotation details' : 'Add quotation details';
                          if (!canEdit) tip = 'Only 5Core users can fill this form';
                          return '<button type="button" class="qc-q-btn' + filled + '" title="' + tip + '"' + (canEdit ? '' : ' disabled style="opacity:.5;cursor:not-allowed"') + '>Q</button>';
                      },
                      cellClick: function (e, cell) {
                          if (canEdit && e.target.closest('.qc-q-btn')) openModal(cell.getRow().getData());
                      } },
                    { title: 'Supplier', field: 'supplier', formatter: textCell, minWidth: 140 },
                    { title: 'Size', field: 'container_size', formatter: textCell },
                    { title: 'Pickup Address', field: 'pickup_address', formatter: textCell, minWidth: 220, widthGrow: 2 },
                    { title: 'Loading Port', field: 'loading_port', formatter: textCell },
                    { title: 'Via Port', field: 'via_port', formatter: textCell },
                    { title: 'Discharge Port', field: 'discharge_port', formatter: textCell, visible: false },
                    { title: 'Gross Weight', field: 'gross_weight', formatter: textCell, visible: false },
                    { title: 'Updated', field: 'updated_at', formatter: function (cell) {
                          const d = cell.getData();
                          if (!d.updated_at) return '<span class="text-muted">—</span>';
                          return '<span class="small">' + esc(d.updated_at) + '<br><span class="text-muted">' + esc(d.updated_by || '') + '</span></span>';
                      } },
                    { title: 'Quotes', field: 'quotes', width: 80, hozAlign: 'center', headerHozAlign: 'center',
                      headerSort: false,
                      formatter: function (cell) {
                          const n = (cell.getValue() || []).length;
                          const cls = n ? ' filled' : '';
                          const badge = n ? '<span class="qc-qr-count">' + n + '</span>' : '';
                          return '<button type="button" class="qc-qr-btn' + cls + '" title="' + (canEdit ? 'Quote received' : 'Only 5Core users can fill this form') + '"' + (canEdit ? '' : ' disabled style="opacity:.5;cursor:not-allowed"') + '>QR' + badge + '</button>';
                      },
                      cellClick: function (e, cell) {
                          if (canEdit && e.target.closest('.qc-qr-btn')) openQrModal(cell.getRow().getData());
                      } },
                    { title: 'QR Form', field: 'requests', width: 105, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                      formatter: function (cell) {
                          const reqs = cell.getValue() || [];
                          const done = reqs.filter(r => r.submitted_at).length;
                          const label = reqs.length ? ' ' + done + '/' + reqs.length : '';
                          const changed = reqs.some(r => r.changed);
                          const cls = (done ? ' filled' : '') + (changed ? ' changed' : '');
                          const tri = changed ? '<i class="fas fa-triangle-exclamation qc-alert-tri"></i>' : '';
                          const tip = changed ? 'A forwarder changed their quote after saving — open to review'
                              : (canEdit ? 'Open the combined quote form' : 'Only 5Core users can open this');
                          return '<button type="button" class="qc-qf-btn' + cls + '" title="' + tip + '"' +
                              (canEdit ? '' : ' disabled') + '>' + tri + '<i class="fas fa-file-lines"></i> Form' + label + '</button>';
                      },
                      cellClick: function (e, cell) {
                          if (canEdit && e.target.closest('.qc-qf-btn')) openFormModal(cell.getRow().getData());
                      } },
                    { title: 'Send', field: 'requests', width: 70, hozAlign: 'center', headerHozAlign: 'center', headerSort: false,
                      formatter: function (cell) {
                          const reqs = cell.getValue() || [];
                          const cls = reqs.some(r => r.sent_at) ? ' filled' : '';
                          return '<button type="button" class="qc-send-btn' + cls + '" title="' + (canEdit ? 'Send quote request to forwarders' : 'Only 5Core users can send') + '"' +
                              (canEdit ? '' : ' disabled') + '><i class="fas fa-paper-plane"></i></button>';
                      },
                      cellClick: function (e, cell) {
                          if (canEdit && e.target.closest('.qc-send-btn')) openSendModal(cell.getRow().getData());
                      } },
                    { title: 'Count', field: 'quotes', width: 80, hozAlign: 'center', headerHozAlign: 'center',
                      sorter: (a, b) => (a || []).length - (b || []).length,
                      formatter: function (cell) {
                          const n = (cell.getValue() || []).length;
                          return n ? '<b>' + n + '</b>' : '<span class="text-muted">0</span>';
                      } },
                    lowestColumn('L1', 0),
                    lowestColumn('L2', 1),
                    lowestColumn('L3', 2),                ],
            });

            function refreshCount() {
                const n = table.getDataCount('active');
                document.getElementById('qc-count').textContent = n + ' container' + (n === 1 ? '' : 's');
                const heroCount = document.getElementById('qz-hero-count'); if (heroCount) heroCount.textContent = n;
            }
            table.on('dataLoaded', refreshCount);
            table.on('dataFiltered', refreshCount);

            const searchEl = document.getElementById('qc-search');
            searchEl.addEventListener('input', function () {
                const terms = searchEl.value.toLowerCase().split(/\s+/).filter(Boolean);
                if (!terms.length) { table.clearFilter(); return; }
                table.setFilter(function (row) {
                    const hay = [row.container_number, (row.quotes || []).map(q => q.forwarder).join(' '), row.supplier, row.container_size, row.pickup_address, row.loading_port, row.via_port,
                        row.discharge_port, row.gross_weight]
                        .map(v => String(v ?? '').toLowerCase()).join(' ');
                    return terms.every(t => hay.indexOf(t) !== -1);
                });
            });

            // ── Add Container (same function as on /ready-to-ship) ───────
            document.getElementById('qc-add-container-btn')?.addEventListener('click', async function () {
                // Pre-fill with the next free number (last container + 1).
                let suggested = '';
                try {
                    const nr = await fetch('/transit-container/next-tab-name', { headers: { 'Accept': 'application/json' } });
                    const nj = await nr.json();
                    if (nj && nj.success) suggested = nj.name || '';
                } catch (e) { /* fall back to an empty prompt */ }
                const tabName = prompt('Enter new container name:', suggested);
                if (!tabName || tabName.trim() === '') {
                    alert('Tab name is required.');
                    return;
                }
                const response = await fetch('/transit-container/add-tab', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ tab_name: tabName.trim() })
                });
                const result = await response.json();
                if (!result.success) {
                    alert(result.message || 'Failed to create tab.');
                    return;
                }
                location.reload();
            });

            // ── Quotes received (QR) modal ───────────────────────────────
            const qrUrl = @json(route('container.quotations.quotes.save'));
            const qrModalEl = document.getElementById('qrModal');
            const qrBody = document.getElementById('qrBody');
            const qrErr = document.getElementById('qrError');
            const qrEmpty = document.getElementById('qrEmpty');
            const qrSave = document.getElementById('qrSaveBtn');
            let qrContainer = null;

            function num(v) {
                const n = parseFloat(v);
                return isNaN(n) ? 0 : n;
            }
            function fmtMoney(n) {
                return Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            function qrRecalc() {
                let lowest = null;
                const rows = [...qrBody.querySelectorAll('tr')];
                rows.forEach(function (tr) {
                    const pickup = tr.querySelector('.qr-pickup').value;
                    const freight = tr.querySelector('.qr-freight').value;
                    const has = String(pickup).trim() !== '' || String(freight).trim() !== '';
                    const total = num(pickup) + num(freight);
                    tr.querySelector('.qr-total').textContent = has ? fmtMoney(total) : '—';
                    tr.dataset.total = has ? String(total) : '';
                    if (has && (lowest === null || total < lowest)) lowest = total;
                });
                const comparable = rows.filter(tr => tr.dataset.total !== '').length;
                rows.forEach(function (tr) {
                    tr.classList.toggle('qr-lowest', comparable > 1 && tr.dataset.total !== '' && Number(tr.dataset.total) === lowest);
                });
                qrEmpty.classList.toggle('d-none', rows.length > 0);
            }
            function qrAddRow(q) {
                q = q || {};
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="text" class="form-control form-control-sm qr-forwarder" list="qcForwarderList" maxlength="255" placeholder="Forwarder"></td>' +
                    '<td><input type="text" class="form-control form-control-sm qr-line" maxlength="255" placeholder="Shipping line"></td>' +
                    '<td><input type="date" class="form-control form-control-sm qr-sail"></td>' +
                    '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm qr-pickup"></td>' +
                    '<td><input type="number" step="0.01" min="0" class="form-control form-control-sm qr-freight"></td>' +
                    '<td class="fw-bold qr-total">—</td>' +
                    '<td><button type="button" class="btn btn-sm btn-link text-danger p-0 qr-del" title="Remove"><i class="fas fa-trash-alt"></i></button></td>';
                tr.dataset.requestId = q.request_id || '';
                if (q.request_id) {
                    tr.querySelector('.qr-forwarder').closest('td').insertAdjacentHTML('beforeend',
                        '<div class="small text-muted">via forwarder form</div>');
                }
                tr.querySelector('.qr-forwarder').value = q.forwarder ?? '';
                tr.querySelector('.qr-line').value = q.shipping_line ?? '';
                tr.querySelector('.qr-sail').value = q.est_dt_sail ?? '';
                tr.querySelector('.qr-pickup').value = q.pickup_charges ?? '';
                tr.querySelector('.qr-freight').value = q.freight ?? '';
                qrBody.appendChild(tr);
                qrRecalc();
                return tr;
            }
            function openQrModal(row) {
                qrContainer = row.container_number;
                qrErr.classList.add('d-none');
                document.getElementById('qrModalContainer').textContent = row.container_number;
                qrBody.innerHTML = '';
                // Cheapest quote first; quotes without a total go to the bottom.
                const sorted = (row.quotes || []).slice().sort(function (a, b) {
                    const x = (a.total === null || a.total === undefined || a.total === '') ? Infinity : Number(a.total);
                    const y = (b.total === null || b.total === undefined || b.total === '') ? Infinity : Number(b.total);
                    return x - y;
                });
                sorted.forEach(q => qrAddRow(q));
                if (!sorted.length) qrAddRow();
                qrRecalc();
                bootstrap.Modal.getOrCreateInstance(qrModalEl).show();
            }
            qrBody.addEventListener('input', qrRecalc);
            qrBody.addEventListener('click', function (e) {
                const del = e.target.closest('.qr-del');
                if (!del) return;
                del.closest('tr').remove();
                qrRecalc();
            });
            document.getElementById('qrAddRow').addEventListener('click', function () {
                qrAddRow().querySelector('.qr-forwarder').focus();
            });
            qrSave.addEventListener('click', async function () {
                qrErr.classList.add('d-none');
                qrSave.disabled = true;
                const quotes = [...qrBody.querySelectorAll('tr')].map(tr => ({
                    request_id: tr.dataset.requestId || null,
                    forwarder: tr.querySelector('.qr-forwarder').value,
                    shipping_line: tr.querySelector('.qr-line').value,
                    est_dt_sail: tr.querySelector('.qr-sail').value,
                    pickup_charges: tr.querySelector('.qr-pickup').value,
                    freight: tr.querySelector('.qr-freight').value,
                }));
                try {
                    const res = await fetch(qrUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify({ container_number: qrContainer, quotes: quotes }),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) {
                        let msg = json.message || 'Could not save.';
                        if (json.errors) msg = Object.values(json.errors).flat().join(' ');
                        throw new Error(msg);
                    }
                    table.updateData([{ container_number: json.container_number, quotes: json.quotes }]);
                    bootstrap.Modal.getOrCreateInstance(qrModalEl).hide();
                } catch (err) {
                    qrErr.textContent = err.message || 'Could not save.';
                    qrErr.classList.remove('d-none');
                } finally {
                    qrSave.disabled = false;
                }
            });
            // ── QR Form / Send / Text ─────────────────────────────────────
            const urls = {
                forwarders: @json(route('container.quotations.forwarders')),
                forwarderSelection: @json(route('container.quotations.forwarders.selection')),
                forwarderSelected: @json(route('container.quotations.forwarders.selected')),
                text: @json(route('container.quotations.text')),
                formData: @json(route('container.quotations.form.data')),
                send: @json(route('container.quotations.send')),
                ack: @json(route('container.quotations.ack')),
            };
            async function api(url, method, body) {
                const opts = {
                    method: method || 'GET',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                };
                if (body !== undefined) {
                    opts.headers['Content-Type'] = 'application/json';
                    opts.body = JSON.stringify(body);
                }
                const res = await fetch(url, opts);
                const json = await res.json().catch(() => ({}));
                if (!res.ok || json.success === false) {
                    let msg = json.message || 'Request failed.';
                    if (json.errors) msg = Object.values(json.errors).flat().join(' ');
                    throw new Error(msg);
                }
                return json;
            }
            function showMsg(el, msg) {
                if (!msg) { el.classList.add('d-none'); return; }
                el.textContent = msg;
                el.classList.remove('d-none');
            }

            // QR Form (combined preview)
            const qfModalEl = document.getElementById('qfModal');
            async function openFormModal(row) {
                document.getElementById('qfContainer').textContent = row.container_number;
                const qBox = document.getElementById('qfQ');
                const reqBody = document.getElementById('qfRequests');
                qBox.innerHTML = '<div class="col-12 text-muted">Loading…</div>';
                reqBody.innerHTML = '';
                bootstrap.Modal.getOrCreateInstance(qfModalEl).show();
                try {
                    const j = await api(urls.formData + '?container=' + encodeURIComponent(row.container_number));
                    const d = j.details;
                    document.getElementById('qfNoQ').classList.toggle('d-none', !!d.has_details);
                    const fieldsQ = [
                        ['Container', d.container_number, 4], ['Container Size', d.container_size, 4], ['Gross Weight', d.gross_weight, 4],
                        ['Loading Port', d.loading_port, 4], ['Via Port', d.via_port, 4], ['Discharge Port', d.discharge_port, 4],
                    ];
                    qBox.innerHTML = fieldsQ.map(f =>
                        '<div class="col-md-' + f[2] + '"><label class="form-label small fw-semibold">' + f[0] + '</label>' +
                        '<input class="form-control qf-locked" disabled value="' + esc(f[1] || '') + '"></div>').join('') +
                        '<div class="col-12"><label class="form-label small fw-semibold">Pickup Address</label>' +
                        '<textarea class="form-control qf-locked" rows="2" disabled>' + esc(d.pickup_address || '') + '</textarea></div>';
                    renderRequestRows(j.requests);
                } catch (err) {
                    qBox.innerHTML = '<div class="col-12 text-danger">' + esc(err.message) + '</div>';
                }
            }
            function renderRequestRows(reqs) {
                const reqBody = document.getElementById('qfRequests');
                if (!reqs.length) {
                    reqBody.innerHTML = '<tr><td colspan="6" class="text-muted">Nothing sent yet — use the Send button.</td></tr>';
                    return;
                }
                reqBody.innerHTML = reqs.map(function (r) {
                    const status = r.submitted_at
                        ? '<span class="badge bg-success">Submitted</span><div class="small text-muted">' + esc(r.submitted_at) + '</div>'
                        : (r.last_error ? '<span class="badge bg-danger" title="' + esc(r.last_error) + '">Send failed</span>'
                            : '<span class="badge bg-warning text-dark">Awaiting quote</span>');
                    const total = r.total !== null && r.total !== undefined
                        ? '$' + Number(r.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
                    const money = f => ['Pickup Charges', 'Freight', 'Total'].includes(f);
                    const show = (f, v) => (v === null || v === undefined || v === '') ? '<i>empty</i>'
                        : esc(money(f) ? '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : v);
                    const alertRow = r.changed
                        ? '<tr class="table-danger"><td colspan="6"><i class="fas fa-triangle-exclamation qc-alert-tri"></i>' +
                          '<b>' + esc(r.forwarder) + ' changed the quote after saving</b> <span class="small text-muted">(' + esc(r.changed_at || '') + ')</span>' +
                          '<div class="small mt-1">' + (r.changes || []).map(c =>
                              '<span class="me-3">' + esc(c.label) + ': <s>' + show(c.label, c.old) + '</s> → <b>' + show(c.label, c.new) + '</b></span>').join('') + '</div>' +
                          '<button type="button" class="btn btn-sm btn-outline-danger mt-1 qf-ack" data-id="' + r.id + '"><i class="fas fa-check me-1"></i>Acknowledge</button></td></tr>'
                        : '';
                    return '<tr' + (r.changed ? ' class="table-danger"' : '') + '><td>' + esc(r.forwarder) + '</td><td class="small">' + esc(r.email) + '</td>' +
                        '<td class="small">' + (r.sent_at ? esc(r.sent_at) : '—') + '</td><td>' + status + '</td><td>' + total + '</td>' +
                        '<td><button type="button" class="btn btn-sm btn-outline-secondary qf-copy" data-url="' + esc(r.url || '') + '" title="Copy the forwarder\'s form link"><i class="fas fa-link"></i></button></td></tr>' + alertRow;
                }).join('');
            }
            qfModalEl.addEventListener('click', async function (e) {
                const ack = e.target.closest('.qf-ack');
                if (ack) {
                    ack.disabled = true;
                    try {
                        const j = await api(urls.ack, 'POST', { request_id: Number(ack.dataset.id) });
                        table.updateData([{ container_number: document.getElementById('qfContainer').textContent, requests: j.requests }]);
                        renderRequestRows(j.requests);
                    } catch (err) {
                        ack.disabled = false;
                        alert(err.message);
                    }
                    return;
                }
                const b = e.target.closest('.qf-copy');
                if (!b) return;
                const url = b.dataset.url;
                (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(
                    () => { b.innerHTML = '<i class="fas fa-check"></i>'; setTimeout(() => b.innerHTML = '<i class="fas fa-link"></i>', 1500); },
                    () => window.prompt('Copy this link:', url));
            });

            // Forwarder (list from the Suppliers page data, with checkboxes)
            const fwModalEl = document.getElementById('forwarderModal');
            const fwErr = document.getElementById('fwError');
            const fwList = document.getElementById('fwList');
            const fwSelected = new Set();
            let fwRows = [];

            function fwUpdateCount() {
                document.getElementById('fwSelectedCount').textContent = fwSelected.size + ' selected';
                const shown = fwList.querySelectorAll('.fw-cb:not(:disabled)');
                const checked = fwList.querySelectorAll('.fw-cb:checked');
                document.getElementById('fwAll').checked = shown.length > 0 && checked.length === shown.length;
            }
            function fwRender() {
                const q = document.getElementById('fwSearch').value.toLowerCase().trim();
                const rows = fwRows.filter(r => !q || (r.name + ' ' + (r.company || '') + ' ' + (r.email || '')).toLowerCase().indexOf(q) !== -1);
                if (!rows.length) {
                    fwList.innerHTML = '<div class="list-group-item text-muted">' + (fwRows.length ? 'Nothing matches your search.' : 'No entries of this type yet.') + '</div>';
                } else {
                    fwList.innerHTML = rows.map(function (r) {
                        const dis = r.has_email ? '' : ' disabled';
                        return '<label class="list-group-item d-flex align-items-center gap-2' + (r.has_email ? '' : ' text-muted') + '">' +
                            '<input class="form-check-input fw-cb m-0" type="checkbox" value="' + r.id + '"' + dis + (fwSelected.has(r.id) ? ' checked' : '') + '>' +
                            '<span class="flex-grow-1"><b>' + esc(r.name) + '</b>' +
                            (r.company ? ' <span class="small text-muted">· ' + esc(r.company) + '</span>' : '') +
                            '<div class="small ' + (r.has_email ? 'text-muted' : 'text-danger') + '">' +
                            (r.has_email ? esc(r.email) : 'No email saved — add it on the Suppliers page to send') + '</div></span></label>';
                    }).join('');
                }
                fwUpdateCount();
            }
            function fwApply(j) {
                fwRows = j.forwarders || [];
                fwSelected.clear();
                (j.selected || []).forEach(id => fwSelected.add(Number(id)));
                updateForwarderBadge((j.selected_list || []).length);
                fwRender();
            }
            function updateForwarderBadge(n) {
                const b = document.getElementById('qc-forwarder-count');
                if (b) b.textContent = String(n);
            }
            async function fwLoad() {
                showMsg(fwErr, '');
                fwList.innerHTML = '<div class="list-group-item text-muted">Loading…</div>';
                try {
                    fwApply(await api(urls.forwarders + '?type=' + encodeURIComponent(document.getElementById('fwTypeFilter').value)));
                } catch (err) { showMsg(fwErr, err.message); }
            }
            document.getElementById('qc-forwarder-btn')?.addEventListener('click', function () {
                document.getElementById('fwSearch').value = '';
                document.getElementById('fwAddBox').classList.add('d-none');
                bootstrap.Modal.getOrCreateInstance(fwModalEl).show();
                fwLoad();
            });
            document.getElementById('fwTypeFilter').addEventListener('change', fwLoad);
            document.getElementById('fwSearch').addEventListener('input', fwRender);
            fwList.addEventListener('change', function (e) {
                const cb = e.target.closest('.fw-cb');
                if (!cb) return;
                const id = Number(cb.value);
                if (cb.checked) fwSelected.add(id); else fwSelected.delete(id);
                fwUpdateCount();
            });
            document.getElementById('fwAll').addEventListener('change', function () {
                fwList.querySelectorAll('.fw-cb:not(:disabled)').forEach(cb => {
                    cb.checked = this.checked;
                    const id = Number(cb.value);
                    if (cb.checked) fwSelected.add(id); else fwSelected.delete(id);
                });
                fwUpdateCount();
            });
            document.getElementById('fwAddToggle').addEventListener('click', function () {
                document.getElementById('fwAddBox').classList.toggle('d-none');
                document.getElementById('fwNewName').focus();
            });
            document.getElementById('fwNewCancel').addEventListener('click', function () {
                document.getElementById('fwAddBox').classList.add('d-none');
            });
            document.getElementById('fwNewSave').addEventListener('click', async function () {
                showMsg(fwErr, '');
                const name = document.getElementById('fwNewName').value.trim();
                if (!name) { showMsg(fwErr, 'Enter a name.'); return; }
                try {
                    // keep the current ticks, then add the new party (which comes back ticked)
                    await api(urls.forwarderSelection, 'POST', { ids: [...fwSelected] });
                    fwApply(await api(urls.forwarders, 'POST', {
                        name: name,
                        company: document.getElementById('fwNewCompany').value.trim(),
                        email: document.getElementById('fwNewEmail').value.trim(),
                        type: document.getElementById('fwTypeFilter').value,
                    }));
                    ['fwNewName', 'fwNewCompany', 'fwNewEmail'].forEach(id => { document.getElementById(id).value = ''; });
                    document.getElementById('fwAddBox').classList.add('d-none');
                } catch (err) { showMsg(fwErr, err.message); }
            });
            document.getElementById('fwSave').addEventListener('click', async function () {
                showMsg(fwErr, '');
                try {
                    const j = await api(urls.forwarderSelection, 'POST', { ids: [...fwSelected] });
                    updateForwarderBadge((j.selected_list || []).length);
                    bootstrap.Modal.getOrCreateInstance(fwModalEl).hide();
                } catch (err) { showMsg(fwErr, err.message); }
            });
            if (canEdit) {
                api(urls.forwarderSelected).then(j => updateForwarderBadge((j.selected_list || []).length)).catch(() => {});
            }

            // Send (to the forwarders chosen with the Forwarder button)
            const sendModalEl = document.getElementById('sendModal');
            const sendList = document.getElementById('sendList');
            const sendErr = document.getElementById('sendError');
            const sendResults = document.getElementById('sendResults');
            let sendRow = null;
            let sendRows = [];
            const sendSel = new Set();      // ids ticked for this send (kept across filter changes)
            let sendSavedSel = new Set();   // the globally saved selection (Forwarder button)

            function selectedForwarderIds() { return [...sendSel]; }
            function updateSendCount() {
                document.getElementById('sendSelectedCount').textContent = sendSel.size + ' selected';
                const shown = sendList.querySelectorAll('.send-cb:not(:disabled)');
                const checked = sendList.querySelectorAll('.send-cb:checked');
                document.getElementById('sendAll').checked = shown.length > 0 && checked.length === shown.length;
            }
            function sendRender() {
                const q = document.getElementById('sendSearch').value.toLowerCase().trim();
                const rows = sendRows.filter(r => !q || (r.name + ' ' + (r.company || '') + ' ' + (r.email || '')).toLowerCase().indexOf(q) !== -1);
                const reqByEmail = {};
                ((sendRow && sendRow.requests) || []).forEach(r => { reqByEmail[String(r.email).toLowerCase()] = r; });
                if (!rows.length) {
                    sendList.innerHTML = '<div class="list-group-item text-muted">' + (sendRows.length ? 'Nothing matches your search.' : 'No entries of this type yet.') + '</div>';
                } else {
                    sendList.innerHTML = rows.map(function (r) {
                        const first = String(r.email || '').split(/[;,\s]+/)[0].toLowerCase();
                        const rq = reqByEmail[first];
                        let note = '';
                        if (rq && rq.submitted_at) note = ' <span class="badge bg-success ms-1">Quote received</span>';
                        else if (rq && rq.sent_at) note = ' <span class="badge bg-warning text-dark ms-1">Sent ' + esc(rq.sent_at) + '</span>';
                        const dis = r.has_email ? '' : ' disabled';
                        return '<label class="list-group-item d-flex align-items-center gap-2' + (r.has_email ? '' : ' text-muted') + '">' +
                            '<input class="form-check-input send-cb m-0" type="checkbox" value="' + r.id + '"' + dis + (sendSel.has(r.id) ? ' checked' : '') + '>' +
                            '<span class="flex-grow-1"><b>' + esc(r.name) + '</b>' +
                            (r.company ? ' <span class="small text-muted">· ' + esc(r.company) + '</span>' : '') + note +
                            '<div class="small ' + (r.has_email ? 'text-muted' : 'text-danger') + '">' +
                            (r.has_email ? esc(r.email) : 'No email saved — add it on the Suppliers page to send') + '</div></span></label>';
                    }).join('');
                }
                updateSendCount();
            }
            async function sendLoad(firstOpen) {
                sendList.innerHTML = '<div class="list-group-item text-muted">Loading…</div>';
                try {
                    const j = await api(urls.forwarders + '?type=' + encodeURIComponent(document.getElementById('sendTypeFilter').value));
                    sendRows = j.forwarders || [];
                    sendSavedSel = new Set((j.selected || []).map(Number));
                    if (firstOpen) {
                        sendSel.clear();
                        sendSavedSel.forEach(id => sendSel.add(id));
                    }
                    sendRender();
                } catch (err) { showMsg(sendErr, err.message); }
            }
            function openSendModal(row) {
                sendRow = row;
                document.getElementById('sendContainer').textContent = row.container_number;
                document.getElementById('sendNoQ').classList.toggle('d-none', !!row.has_details);
                showMsg(sendErr, '');
                sendResults.innerHTML = '';
                document.getElementById('sendSearch').value = '';
                document.getElementById('sendAddBox').classList.add('d-none');
                bootstrap.Modal.getOrCreateInstance(sendModalEl).show();
                sendLoad(true);
            }
            sendList.addEventListener('change', function (e) {
                const cb = e.target.closest('.send-cb');
                if (!cb) return;
                const id = Number(cb.value);
                if (cb.checked) sendSel.add(id); else sendSel.delete(id);
                updateSendCount();
            });
            document.getElementById('sendTypeFilter').addEventListener('change', function () { sendLoad(false); });
            document.getElementById('sendSearch').addEventListener('input', sendRender);
            document.getElementById('sendAll').addEventListener('change', function () {
                sendList.querySelectorAll('.send-cb:not(:disabled)').forEach(cb => {
                    cb.checked = this.checked;
                    const id = Number(cb.value);
                    if (cb.checked) sendSel.add(id); else sendSel.delete(id);
                });
                updateSendCount();
            });
            document.getElementById('sendAddToggle').addEventListener('click', function () {
                document.getElementById('sendAddBox').classList.toggle('d-none');
                document.getElementById('sendNewName').focus();
            });
            document.getElementById('sendNewCancel').addEventListener('click', function () {
                document.getElementById('sendAddBox').classList.add('d-none');
            });
            document.getElementById('sendNewSave').addEventListener('click', async function () {
                showMsg(sendErr, '');
                const name = document.getElementById('sendNewName').value.trim();
                if (!name) { showMsg(sendErr, 'Enter a name.'); return; }
                try {
                    const j = await api(urls.forwarders, 'POST', {
                        name: name,
                        company: document.getElementById('sendNewCompany').value.trim(),
                        email: document.getElementById('sendNewEmail').value.trim(),
                        type: document.getElementById('sendTypeFilter').value,
                    });
                    // the new party comes back as part of the saved selection: tick it here too
                    (j.selected || []).map(Number).forEach(id => { if (!sendSavedSel.has(id)) sendSel.add(id); });
                    sendSavedSel = new Set((j.selected || []).map(Number));
                    sendRows = j.forwarders || [];
                    updateForwarderBadge((j.selected_list || []).length);
                    ['sendNewName', 'sendNewCompany', 'sendNewEmail'].forEach(id => { document.getElementById(id).value = ''; });
                    document.getElementById('sendAddBox').classList.add('d-none');
                    sendRender();
                } catch (err) { showMsg(sendErr, err.message); }
            });            document.getElementById('sendBtn').addEventListener('click', async function () {
                const btn = this;
                const ids = selectedForwarderIds();
                showMsg(sendErr, '');
                sendResults.innerHTML = '';
                if (!ids.length) { showMsg(sendErr, 'Select at least one forwarder.'); return; }
                if (!confirm('Send a separate email to ' + ids.length + ' forwarder' + (ids.length === 1 ? '' : 's') + '?')) return;
                btn.disabled = true;
                try {
                    const j = await api(urls.send, 'POST', { container_number: sendRow.container_number, forwarder_ids: ids });
                    sendResults.innerHTML = j.results.map(r =>
                        '<div class="small ' + (r.ok ? 'text-success' : 'text-danger') + '">' +
                        (r.ok ? '<i class="fas fa-check-circle me-1"></i>Sent to ' : '<i class="fas fa-times-circle me-1"></i>') +
                        esc(r.forwarder) + (r.email ? ' (' + esc(r.email) + ')' : '') + (r.ok ? '' : ' — ' + esc(r.message || 'failed')) + '</div>').join('');
                    sendRow.requests = j.requests;
                    table.updateData([{ container_number: sendRow.container_number, requests: j.requests }]);
                    sendRender();
                } catch (err) {
                    showMsg(sendErr, err.message);
                } finally {
                    btn.disabled = false;
                }
            });
            // Text (editable predefined email text)
            const textModalEl = document.getElementById('textModal');
            const textErr = document.getElementById('textError');
            function fillText(j) {
                document.getElementById('textSubject').value = j.subject || '';
                document.getElementById('textBody').value = j.body || '';
                if (j.placeholders) {
                    document.getElementById('textPlaceholders').innerHTML = j.placeholders.map(p => '<code>{' + p + '}</code>').join(' ');
                }
            }
            document.getElementById('qc-text-btn')?.addEventListener('click', async function () {
                showMsg(textErr, '');
                bootstrap.Modal.getOrCreateInstance(textModalEl).show();
                try { fillText(await api(urls.text)); } catch (err) { showMsg(textErr, err.message); }
            });
            document.getElementById('textSave').addEventListener('click', async function () {
                showMsg(textErr, '');
                try {
                    await api(urls.text, 'POST', {
                        subject: document.getElementById('textSubject').value,
                        body: document.getElementById('textBody').value,
                    });
                    bootstrap.Modal.getOrCreateInstance(textModalEl).hide();
                } catch (err) { showMsg(textErr, err.message); }
            });
            document.getElementById('textReset').addEventListener('click', async function () {
                if (!confirm('Replace the text with the default message?')) return;
                showMsg(textErr, '');
                try { fillText(await api(urls.text, 'POST', { reset: true })); } catch (err) { showMsg(textErr, err.message); }
            });
            // ── Modal ─────────────────────────────────────────────────────
            const modalEl = document.getElementById('qcModal');
            const errEl = document.getElementById('qcError');
            const saveBtn = document.getElementById('qcSaveBtn');

            function openModal(row) {
                errEl.classList.add('d-none');
                document.getElementById('qcModalContainer').textContent = row.container_number;
                document.getElementById('qc_container_number').value = row.container_number;
                fields.forEach(function (f) {
                    const el = document.getElementById('qc_' + f);
                    if (!el) return;
                    let val = row[f] ?? '';
                    // Keep a previously saved port even if it is not one of the standard choices.
                    if (f === 'loading_port' && val && ![...el.options].some(o => o.value === val)) {
                        el.add(new Option(val, val));
                    }
                    // Autofill defaults for anything not saved yet.
                    if (String(val).trim() === '' && DEFAULTS[f]) val = DEFAULTS[f];
                    el.value = val;
                });
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
            modalEl.addEventListener('shown.bs.modal', () => document.getElementById('qc_supplier').focus());

            document.getElementById('qcForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                errEl.classList.add('d-none');
                saveBtn.disabled = true;
                const body = { container_number: document.getElementById('qc_container_number').value };
                fields.forEach(f => { body[f] = document.getElementById('qc_' + f).value; });
                try {
                    const res = await fetch(saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(body),
                    });
                    const json = await res.json().catch(() => ({}));
                    if (!res.ok || !json.success) {
                        let msg = json.message || 'Could not save.';
                        if (json.errors) msg = Object.values(json.errors).flat().join(' ');
                        throw new Error(msg);
                    }
                    table.updateData([json.row]);
                    bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                } catch (err) {
                    errEl.textContent = err.message || 'Could not save.';
                    errEl.classList.remove('d-none');
                } finally {
                    saveBtn.disabled = false;
                }
            });
        });
    </script>
@endsection