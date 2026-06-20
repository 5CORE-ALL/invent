@extends('layouts.vertical', ['title' => 'FAQ / FFP Customers', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <style>
        .cfaq-stat-card {
            border-radius: 0.5rem;
            padding: 0.6rem 0.85rem;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .cfaq-stat-card .label {
            font-size: 0.7rem;
            text-transform: uppercase;
            opacity: 0.9;
            letter-spacing: 0.04em;
        }

        .cfaq-stat-card .value {
            font-size: 1.25rem;
            font-weight: 700;
            line-height: 1;
        }

        .cfaq-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            align-items: center;
        }

        .cfaq-toolbar .form-control,
        .cfaq-toolbar .form-select {
            height: 32px;
            font-size: 0.82rem;
        }

        #cfaqTable {
            font-size: 0.82rem;
        }

        .cfaq-badge {
            display: inline-block;
            font-size: 0.7rem;
            padding: 0.18rem 0.5rem;
            border-radius: 0.3rem;
            line-height: 1.1;
        }

        .cfaq-esc-pill {
            display: inline-block;
            font-size: 0.7rem;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            background: #f1f3f5;
            color: #495057;
            font-weight: 600;
        }

        .cfaq-esc-pill.l1 { background: #fff3cd; color: #8a6d00; }
        .cfaq-esc-pill.l2 { background: #ffe5b4; color: #b25e00; }
        .cfaq-esc-pill.l3 { background: #f8d7da; color: #842029; }

        /* Row tinting based on escalation/resolution. */
        .tabulator-row.cfaq-row-escalated { background-color: #fff5f5 !important; }
        .tabulator-row.cfaq-row-resolved  { background-color: #f4fff6 !important; }

        .tabulator .tabulator-header {
            background: #cfe2ff;
        }
        .tabulator .tabulator-header .tabulator-col {
            background: #cfe2ff;
            color: #1e3a8a;
            font-weight: 600;
        }
        .tabulator-row .tabulator-cell {
            white-space: normal !important;
            word-break: break-word;
            line-height: 1.25;
        }
        .cfaq-row-action-btns .btn {
            padding: 0.15rem 0.35rem;
        }

        .cfaq-esc-matrix {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.6rem;
        }
        .cfaq-esc-matrix > div {
            border: 1px solid #e9ecef;
            border-radius: 0.4rem;
            padding: 0.6rem;
            background: #fafbfc;
        }
        .cfaq-esc-matrix > div h6 {
            margin-bottom: 0.4rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #6c757d;
        }
    </style>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'FAQ / FFP — Customers',
        'sub_title' => 'Customer Care',
    ])

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Stat tiles --}}
    <div class="row g-2 mb-2">
        <div class="col-6 col-md">
            <div class="cfaq-stat-card" style="background:#5a67d8;">
                <div><div class="label">Total</div><div class="value" id="cfaq-stat-total">{{ $stats['total'] }}</div></div>
                <i class="fas fa-question-circle fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="cfaq-stat-card" style="background:#3b82f6;">
                <div><div class="label">Open</div><div class="value" id="cfaq-stat-open">{{ $stats['open'] }}</div></div>
                <i class="fas fa-folder-open fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="cfaq-stat-card" style="background:#dc3545;">
                <div><div class="label">Escalated</div><div class="value" id="cfaq-stat-escalated">{{ $stats['escalated'] }}</div></div>
                <i class="fas fa-bell fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="cfaq-stat-card" style="background:#f59f00;">
                <div><div class="label">Critical</div><div class="value" id="cfaq-stat-critical">{{ $stats['critical'] }}</div></div>
                <i class="fas fa-triangle-exclamation fs-3"></i>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="cfaq-stat-card" style="background:#198754;">
                <div><div class="label">Resolved</div><div class="value" id="cfaq-stat-resolved">{{ $stats['resolved'] }}</div></div>
                <i class="fas fa-circle-check fs-3"></i>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body py-3">
            <div class="cfaq-toolbar mb-2">
                <input type="text" id="cfaq-search" class="form-control" style="max-width: 280px;" placeholder="Search FAQ / Answer / Action ...">

                <select id="cfaq-status" class="form-select" style="width:auto;">
                    <option value="">Any status</option>
                    @foreach ($statusOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select id="cfaq-severity" class="form-select" style="width:auto;">
                    <option value="">Any severity</option>
                    @foreach ($severityOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select id="cfaq-customer-type" class="form-select" style="width:auto;">
                    <option value="">Any customer type</option>
                    @foreach ($customerTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>

                <select id="cfaq-escalation" class="form-select" style="width:auto;" title="Escalation level">
                    <option value="">Any escalation</option>
                    <option value="0">Not escalated</option>
                    <option value="1">Level 1</option>
                    <option value="2">Level 2</option>
                    <option value="3">Level 3</option>
                </select>

                <button id="cfaq-clear-btn" type="button" class="btn btn-light btn-sm" title="Clear filters">
                    <i class="fas fa-rotate-left"></i> Clear
                </button>

                <span class="ms-auto d-flex gap-2">
                    <button id="cfaq-refresh-btn" type="button" class="btn btn-outline-secondary btn-sm" title="Reload">
                        <i class="fas fa-arrows-rotate"></i>
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="cfaq-add-btn">
                        <i class="fas fa-plus"></i> Add FAQ / FFP
                    </button>
                </span>
            </div>

            <div id="cfaqTable" style="min-height: 480px;"></div>
        </div>
    </div>

    {{-- Add / Edit Modal --}}
    <div class="modal fade" id="cfaqCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
            <form id="cfaqForm" action="{{ route('customer.care.faq.customers.store') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="_method" id="cfaqFormMethod" value="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="cfaqModalTitle">Add Customer FAQ / FFP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Group</label>
                            <input type="text" name="group_name" class="form-control" placeholder="e.g. Returns, Shipping">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Customer Type</label>
                            <select name="customer_type" class="form-select">
                                <option value="">— Any —</option>
                                @foreach ($customerTypes as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Severity</label>
                            <select name="severity" class="form-select">
                                @foreach ($severityOptions as $val => $label)
                                    <option value="{{ $val }}" @selected($val === 'medium')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach ($statusOptions as $val => $label)
                                    <option value="{{ $val }}" @selected($val === 'open')>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">FAQ / FFP / Issue <span class="text-danger">*</span></label>
                            <textarea name="faq" class="form-control" rows="2" required placeholder="Enter the customer question / problem"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Answer / Solution</label>
                            <textarea name="answers" class="form-control" rows="2" placeholder="Standard answer / solution"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type / Variant</label>
                            <textarea name="type_variant" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">What (context)</label>
                            <textarea name="what" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Action</label>
                            <textarea name="action" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Corrective Action (CA)</label>
                            <textarea name="ca" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">+ Action</label>
                            <textarea name="plus_action" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Messages / Templates</label>
                            <textarea name="messages" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Link 1</label>
                            <input type="text" name="link" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Link 2</label>
                            <input type="text" name="link2" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">SOP</label>
                            <input type="text" name="sop" class="form-control" placeholder="https://...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Video</label>
                            <input type="text" name="video" class="form-control" placeholder="https://...">
                        </div>

                        <div class="col-12">
                            <h6 class="text-uppercase text-muted mt-3 mb-2"><i class="fas fa-sitemap"></i> Escalation Matrix</h6>
                        </div>

                        @foreach ([1, 2, 3] as $lvl)
                            <div class="col-12">
                                <div class="border rounded p-2">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-1 text-center fw-bold text-uppercase">L{{ $lvl }}</div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Role</label>
                                            <input type="text" name="escalation_l{{ $lvl }}_role" class="form-control form-control-sm" placeholder="e.g. Team Lead">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Name</label>
                                            <input type="text" name="escalation_l{{ $lvl }}_name" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Email</label>
                                            <input type="email" name="escalation_l{{ $lvl }}_email" class="form-control form-control-sm" placeholder="user@5core.com">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small mb-1">SLA</label>
                                            <input type="text" name="escalation_l{{ $lvl }}_sla" class="form-control form-control-sm" placeholder="e.g. 2h">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Escalate Modal --}}
    <div class="modal fade" id="cfaqEscalateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="cfaqEscalateForm" method="POST" class="modal-content">
                @csrf
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-bell"></i> Escalate Issue</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Escalate to Level</label>
                        <select name="level" class="form-select" id="cfaqEscalateLevel">
                            <option value="1">Level 1</option>
                            <option value="2">Level 2</option>
                            <option value="3">Level 3</option>
                        </select>
                        <div class="form-text" id="cfaqEscalateHint"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Escalate to (email, optional)</label>
                        <input type="email" name="escalated_to_email" class="form-control" id="cfaqEscalateTo" placeholder="Leave blank to use the matrix default">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Why is this being escalated?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-arrow-up-right-from-square"></i> Escalate</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Resolve Modal --}}
    <div class="modal fade" id="cfaqResolveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form id="cfaqResolveForm" method="POST" class="modal-content">
                @csrf
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-double"></i> Resolve Issue</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Resolution Note</label>
                        <textarea name="resolution_note" class="form-control" rows="4" placeholder="What was done to resolve this?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check-double"></i> Mark Resolved</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Escalation Matrix View --}}
    <div class="modal fade" id="cfaqMatrixModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-sitemap"></i> Escalation Matrix &amp; History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cfaq-esc-matrix mb-3" id="cfaqMatrixGrid"></div>
                    <h6 class="text-uppercase text-muted mb-2">Escalation Log</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>When</th>
                                    <th>Level</th>
                                    <th>By</th>
                                    <th>To</th>
                                    <th>Reason / Note</th>
                                </tr>
                            </thead>
                            <tbody id="cfaqMatrixLog">
                                <tr><td colspan="5" class="text-center text-muted">No history.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        (function () {
            const csrfToken = '{{ csrf_token() }}';
            const dataUrl = '{{ route('customer.care.faq.customers.data') }}';
            const baseUrl = '{{ url('customer-care/faq-customers') }}';
            const storeUrl = '{{ route('customer.care.faq.customers.store') }}';

            const STATUS_LABELS = @json($statusOptions);
            const SEVERITY_LABELS = @json($severityOptions);

            function escapeHtml(v) {
                const d = document.createElement('div');
                d.textContent = v == null ? '' : String(v);
                return d.innerHTML;
            }

            function showToast(message, type) {
                type = type || 'info';
                const container = document.querySelector('.toast-container');
                if (!container) return;
                const bg = type === 'error' ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
                const t = document.createElement('div');
                t.className = `toast align-items-center text-white bg-${bg} border-0`;
                t.setAttribute('role', 'alert');
                t.innerHTML = `<div class="d-flex"><div class="toast-body">${escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
                container.appendChild(t);
                new bootstrap.Toast(t, { delay: 3000 }).show();
                t.addEventListener('hidden.bs.toast', () => t.remove());
            }

            // ── Cell formatters ─────────────────────────────────────────────
            function severityFormatter(cell) {
                const v = cell.getValue() || '';
                const label = SEVERITY_LABELS[v] || v || '—';
                const cls = {
                    critical: 'bg-danger text-white',
                    high: 'bg-warning text-dark',
                    low: 'bg-secondary text-white',
                }[v] || 'bg-info text-dark';
                return `<span class="cfaq-badge ${cls}">${escapeHtml(label)}</span>`;
            }

            function statusFormatter(cell) {
                const v = cell.getValue() || '';
                const label = STATUS_LABELS[v] || v || '—';
                const cls = {
                    open: 'bg-primary text-white',
                    in_progress: 'bg-info text-dark',
                    escalated: 'bg-danger text-white',
                    resolved: 'bg-success text-white',
                    closed: 'bg-dark text-white',
                }[v] || 'bg-light text-dark';
                return `<span class="cfaq-badge ${cls}">${escapeHtml(label)}</span>`;
            }

            function customerTypeFormatter(cell) {
                const v = cell.getValue();
                if (!v) return '<span class="text-muted">—</span>';
                return `<span class="cfaq-badge bg-soft-primary text-primary">${escapeHtml(v)}</span>`;
            }

            function escalationFormatter(cell) {
                const r = cell.getRow().getData();
                const lvl = parseInt(r.current_escalation_level, 10) || 0;
                let html = '';
                if (lvl > 0) {
                    html += `<span class="cfaq-esc-pill l${Math.min(3, lvl)}">L${lvl}</span>`;
                    const bits = [];
                    if (r.escalated_to_email) bits.push('→ ' + escapeHtml(r.escalated_to_email));
                    if (r.escalated_at_human) bits.push(escapeHtml(r.escalated_at_human));
                    if (bits.length) html += `<div class="small text-muted mt-1">${bits.join('<br>')}</div>`;
                } else {
                    html = '<span class="text-muted small">Not escalated</span>';
                }
                html += `<div><button type="button" class="btn btn-link btn-sm p-0 cfaq-matrix-trigger"
                    data-id="${r.id}" title="View matrix"><i class="fas fa-sitemap"></i> matrix</button></div>`;
                return html;
            }

            function activityFormatter(cell) {
                const r = cell.getRow().getData();
                if (r.resolved_at_human) {
                    return `<div class="small"><span class="text-success">Resolved</span> ${escapeHtml(r.resolved_at_human)}` +
                        (r.resolved_by_email ? `<br><span class="text-muted">${escapeHtml(r.resolved_by_email)}</span>` : '') +
                        `</div>`;
                }
                if (r.escalated_at_human) {
                    return `<div class="small"><span class="text-danger">Escalated</span> ${escapeHtml(r.escalated_at_human)}</div>`;
                }
                if (r.updated_at_human) {
                    return `<div class="small">Updated ${escapeHtml(r.updated_at_human)}</div>`;
                }
                return '<span class="text-muted">—</span>';
            }

            function faqFormatter(cell) {
                const r = cell.getRow().getData();
                let html = `<div class="fw-semibold">${escapeHtml(r.faq || '')}</div>`;
                if (r.what) {
                    const w = String(r.what);
                    const short = w.length > 90 ? w.slice(0, 90) + '…' : w;
                    html += `<div class="text-muted small">${escapeHtml(short)}</div>`;
                }
                return html;
            }

            function actionsFormatter(cell) {
                const r = cell.getRow().getData();
                const id = r.id;
                const closed = r.status === 'resolved' || r.status === 'closed';
                let html = '<div class="d-flex gap-1 flex-wrap cfaq-row-action-btns">';
                html += `<button type="button" class="btn btn-sm btn-soft-warning cfaq-edit-trigger" data-id="${id}" title="Edit"><i class="fas fa-pen"></i></button>`;
                if (!closed) {
                    html += `<button type="button" class="btn btn-sm btn-soft-danger cfaq-escalate-trigger" data-id="${id}" title="Escalate"><i class="fas fa-bell"></i></button>`;
                    html += `<button type="button" class="btn btn-sm btn-soft-success cfaq-resolve-trigger" data-id="${id}" title="Resolve"><i class="fas fa-check-double"></i></button>`;
                }
                html += `<button type="button" class="btn btn-sm btn-soft-secondary cfaq-archive-trigger" data-id="${id}" title="Archive"><i class="fas fa-box-archive"></i></button>`;
                html += '</div>';
                return html;
            }

            // ── Table init ──────────────────────────────────────────────────
            const table = new Tabulator('#cfaqTable', {
                ajaxURL: dataUrl,
                layout: 'fitDataStretch',
                height: 'calc(100vh - 360px)',
                pagination: 'local',
                paginationSize: 25,
                paginationSizeSelector: [25, 50, 100, 200, 500],
                placeholder: 'No customer FAQs / FFPs yet. Click “Add FAQ / FFP” to create one.',
                rowFormatter: function (row) {
                    const d = row.getData();
                    const lvl = parseInt(d.current_escalation_level, 10) || 0;
                    const closed = d.status === 'resolved' || d.status === 'closed';
                    const el = row.getElement();
                    el.classList.remove('cfaq-row-escalated', 'cfaq-row-resolved');
                    if (closed) {
                        el.classList.add('cfaq-row-resolved');
                    } else if (lvl > 0) {
                        el.classList.add('cfaq-row-escalated');
                    }
                },
                columns: [
                    { title: 'ID', field: 'id', width: 60, hozAlign: 'center', headerSort: true },
                    { title: 'Group', field: 'group_name', width: 120, formatter: c => c.getValue() || '<span class="text-muted">—</span>' },
                    { title: 'FAQ / FFP / Issue', field: 'faq', minWidth: 240, formatter: faqFormatter },
                    { title: 'Answer / Solution', field: 'answers', minWidth: 220, formatter: c => {
                        const v = c.getValue();
                        if (!v) return '<span class="text-muted">—</span>';
                        return escapeHtml(v.length > 140 ? v.slice(0, 140) + '…' : v);
                    } },
                    { title: 'Customer', field: 'customer_type', width: 120, formatter: customerTypeFormatter },
                    { title: 'Severity', field: 'severity', width: 100, formatter: severityFormatter, hozAlign: 'center' },
                    { title: 'Status', field: 'status', width: 120, formatter: statusFormatter, hozAlign: 'center' },
                    { title: 'Escalation', field: 'current_escalation_level', width: 180, formatter: escalationFormatter },
                    { title: 'Latest Activity', field: 'updated_at', width: 170, formatter: activityFormatter, sorter: 'datetime' },
                    { title: 'Actions', field: '__actions', width: 160, formatter: actionsFormatter, headerSort: false, hozAlign: 'center' },
                ],
            });

            // Re-fetch the JSON feed and refresh the stat tiles.
            function reload() {
                table.setData(dataUrl).then(() => {
                    refreshStats();
                });
            }

            function refreshStats() {
                const all = table.getData();
                const counts = { total: all.length, open: 0, escalated: 0, critical: 0, resolved: 0 };
                all.forEach(r => {
                    if (r.status === 'open') counts.open++;
                    if ((parseInt(r.current_escalation_level, 10) || 0) > 0 && r.status !== 'resolved' && r.status !== 'closed') counts.escalated++;
                    if (r.severity === 'critical') counts.critical++;
                    if (r.status === 'resolved' || r.status === 'closed') counts.resolved++;
                });
                $('#cfaq-stat-total').text(counts.total);
                $('#cfaq-stat-open').text(counts.open);
                $('#cfaq-stat-escalated').text(counts.escalated);
                $('#cfaq-stat-critical').text(counts.critical);
                $('#cfaq-stat-resolved').text(counts.resolved);
            }

            table.on('dataLoaded', refreshStats);

            // ── Filters ────────────────────────────────────────────────────
            function applyFilters() {
                const q = ($('#cfaq-search').val() || '').trim().toLowerCase();
                const status = $('#cfaq-status').val();
                const severity = $('#cfaq-severity').val();
                const customerType = $('#cfaq-customer-type').val();
                const escalation = $('#cfaq-escalation').val();

                table.setFilter(function (row) {
                    if (q) {
                        const hay = [row.faq, row.answers, row.group_name, row.action, row.messages, row.what, row.type_variant, row.customer_type]
                            .map(v => String(v || '').toLowerCase()).join(' ');
                        if (hay.indexOf(q) === -1) return false;
                    }
                    if (status && row.status !== status) return false;
                    if (severity && row.severity !== severity) return false;
                    if (customerType && row.customer_type !== customerType) return false;
                    if (escalation !== '' && escalation !== undefined && escalation !== null) {
                        const lvl = parseInt(row.current_escalation_level, 10) || 0;
                        if (parseInt(escalation, 10) !== lvl) return false;
                    }
                    return true;
                });
            }

            $('#cfaq-search').on('input', applyFilters);
            $('#cfaq-status, #cfaq-severity, #cfaq-customer-type, #cfaq-escalation').on('change', applyFilters);
            $('#cfaq-clear-btn').on('click', function () {
                $('#cfaq-search').val('');
                $('#cfaq-status, #cfaq-severity, #cfaq-customer-type, #cfaq-escalation').val('');
                applyFilters();
            });
            $('#cfaq-refresh-btn').on('click', reload);

            // ── Add / Edit modal wiring ────────────────────────────────────
            const createModalEl = document.getElementById('cfaqCreateModal');
            const formEl = document.getElementById('cfaqForm');
            const titleEl = document.getElementById('cfaqModalTitle');
            const methodInput = document.getElementById('cfaqFormMethod');

            function resetForm() {
                formEl.reset();
                formEl.setAttribute('action', storeUrl);
                methodInput.value = 'POST';
                titleEl.textContent = 'Add Customer FAQ / FFP';
            }

            $('#cfaq-add-btn').on('click', function () {
                resetForm();
                new bootstrap.Modal(createModalEl).show();
            });

            $('#cfaqTable').on('click', '.cfaq-edit-trigger', function () {
                const id = parseInt(this.getAttribute('data-id'), 10);
                const row = table.getRows().find(r => r.getData().id === id);
                if (!row) return;
                const d = row.getData();
                resetForm();
                formEl.setAttribute('action', baseUrl + '/' + id);
                methodInput.value = 'PUT';
                titleEl.textContent = 'Edit Customer FAQ / FFP #' + id;

                Object.keys(d).forEach(function (k) {
                    const f = formEl.querySelector('[name="' + k + '"]');
                    if (f) f.value = d[k] == null ? '' : d[k];
                });

                new bootstrap.Modal(createModalEl).show();
            });

            // ── Escalate modal wiring ─────────────────────────────────────
            const escModalEl = document.getElementById('cfaqEscalateModal');
            const escFormEl = document.getElementById('cfaqEscalateForm');
            const escLevel = document.getElementById('cfaqEscalateLevel');
            const escTo = document.getElementById('cfaqEscalateTo');
            const escHint = document.getElementById('cfaqEscalateHint');

            $('#cfaqTable').on('click', '.cfaq-escalate-trigger', function () {
                const id = parseInt(this.getAttribute('data-id'), 10);
                const row = table.getRows().find(r => r.getData().id === id);
                if (!row) return;
                const d = row.getData();
                const current = parseInt(d.current_escalation_level, 10) || 0;
                const next = Math.min(3, Math.max(1, current + 1));
                escFormEl.setAttribute('action', baseUrl + '/' + id + '/escalate');
                escLevel.value = String(next);

                const emails = {
                    '1': d.escalation_l1_email || '',
                    '2': d.escalation_l2_email || '',
                    '3': d.escalation_l3_email || '',
                };
                escTo.value = emails[String(next)] || '';
                escHint.textContent = 'Currently at L' + current + '. Default contact: ' + (emails[String(next)] || '—');
                escFormEl.querySelector('[name="reason"]').value = '';
                escLevel.onchange = function () { escTo.value = emails[escLevel.value] || ''; };

                new bootstrap.Modal(escModalEl).show();
            });

            // ── Resolve modal wiring ──────────────────────────────────────
            const resModalEl = document.getElementById('cfaqResolveModal');
            const resFormEl = document.getElementById('cfaqResolveForm');
            $('#cfaqTable').on('click', '.cfaq-resolve-trigger', function () {
                const id = parseInt(this.getAttribute('data-id'), 10);
                resFormEl.setAttribute('action', baseUrl + '/' + id + '/resolve');
                resFormEl.querySelector('[name="resolution_note"]').value = '';
                new bootstrap.Modal(resModalEl).show();
            });

            // ── Archive (soft-delete) ─────────────────────────────────────
            $('#cfaqTable').on('click', '.cfaq-archive-trigger', function () {
                const id = parseInt(this.getAttribute('data-id'), 10);
                if (!confirm('Archive this FAQ? You can restore it later.')) return;

                fetch(baseUrl + '/' + id, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: '_method=DELETE',
                })
                .then(r => {
                    if (r.ok || r.redirected) {
                        showToast('Archived.', 'success');
                        reload();
                    } else {
                        showToast('Failed to archive.', 'error');
                    }
                })
                .catch(() => showToast('Network error while archiving.', 'error'));
            });

            // ── Matrix view modal ─────────────────────────────────────────
            const matrixModalEl = document.getElementById('cfaqMatrixModal');
            const matrixGrid = document.getElementById('cfaqMatrixGrid');
            const matrixLog = document.getElementById('cfaqMatrixLog');

            $('#cfaqTable').on('click', '.cfaq-matrix-trigger', function () {
                const id = parseInt(this.getAttribute('data-id'), 10);
                const row = table.getRows().find(r => r.getData().id === id);
                if (!row) return;
                const d = row.getData();

                const cells = [1, 2, 3].map(function (lvl) {
                    const role = d['escalation_l' + lvl + '_role'] || '';
                    const name = d['escalation_l' + lvl + '_name'] || '';
                    const email = d['escalation_l' + lvl + '_email'] || '';
                    const sla = d['escalation_l' + lvl + '_sla'] || '';
                    return '<div>'
                        + '<h6>Level ' + lvl + '</h6>'
                        + '<div><strong>Role:</strong> ' + (escapeHtml(role) || '—') + '</div>'
                        + '<div><strong>Name:</strong> ' + (escapeHtml(name) || '—') + '</div>'
                        + '<div><strong>Email:</strong> ' + (email ? '<a href="mailto:' + escapeHtml(email) + '">' + escapeHtml(email) + '</a>' : '—') + '</div>'
                        + '<div><strong>SLA:</strong> ' + (escapeHtml(sla) || '—') + '</div>'
                        + '</div>';
                }).join('');
                matrixGrid.innerHTML = cells;

                const log = Array.isArray(d.escalation_log) ? d.escalation_log : [];
                if (!log.length) {
                    matrixLog.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No history.</td></tr>';
                } else {
                    matrixLog.innerHTML = log.map(function (h) {
                        const reason = h.reason || h.note || h.action || '';
                        return '<tr>'
                            + '<td>' + escapeHtml(h.at || '') + '</td>'
                            + '<td>L' + escapeHtml(h.level || '?') + '</td>'
                            + '<td>' + escapeHtml(h.by_email || '') + '</td>'
                            + '<td>' + escapeHtml(h.to_email || '') + '</td>'
                            + '<td>' + escapeHtml(reason) + '</td>'
                            + '</tr>';
                    }).join('');
                }

                new bootstrap.Modal(matrixModalEl).show();
            });
        })();
    </script>
@endsection
