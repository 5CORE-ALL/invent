@extends('layouts.vertical', ['title' => 'Customer Followups', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        /* Header table stays visible; body scrolls (avoids sticky thead bugs inside overflow) */
        .followup-table-outer {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .followup-table-shell {
            min-width: 960px;
        }

        .followup-table-header {
            border-bottom: 2px solid #1a56b7;
            background: #2c6ed5;
        }

        .followup-table-header table,
        .followup-table-body table {
            table-layout: fixed;
            width: 100%;
            min-width: 960px;
            margin-bottom: 0;
        }

        .followup-table-header thead th {
            background: #2c6ed5 !important;
            color: #fff !important;
            font-weight: 600;
            padding: 12px 10px;
            border: 1px solid #1a56b7;
            vertical-align: middle;
            white-space: nowrap;
        }

        .followup-table-body {
            max-height: 60vh;
            overflow: auto;
            border: 1px solid #dee2e6;
            border-top: 0;
        }

        .followup-table-body td {
            padding: 10px;
            vertical-align: middle;
            word-break: break-word;
        }

        .followup-table-body tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }

        tr.followup-row-overdue { background-color: rgba(220, 53, 69, 0.12) !important; }
        tr.followup-row-overdue:hover { background-color: rgba(220, 53, 69, 0.2) !important; }
        .stat-card { border-radius: 0.5rem; border: 1px solid #e9ecef; }
    </style>
@endsection

@section('content')
    {{--
        Channel integration: channel_master (active), same as /all-marketplace-master.
        // TODO: Fetch channels via API endpoint
        Dummy fallback when empty: Amazon, Flipkart, Shopify, Website, WhatsApp (id 0 — stored as null in DB).

        // TODO: Replace static data with API integration (table loaded via AJAX from followups/data).
    --}}

    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <h4 class="mb-0">Customer Followups</h4>
            <button type="button" class="btn btn-primary" id="btnAddFollowup" data-bs-toggle="modal"
                data-bs-target="#followupModal">
                <i class="mdi mdi-plus me-1"></i> Add Followup
            </button>
        </div>

        {{-- Performance summary --}}
        <div class="row g-3 mb-4" id="statsRow">
            <div class="col-6 col-md-4 col-xl">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="text-muted text-uppercase small mb-1">Total tickets</h6>
                        <span class="h4 mb-0" id="statTotal">—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="text-muted text-uppercase small mb-1">Pending</h6>
                        <span class="h4 mb-0" id="statPending">—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="text-muted text-uppercase small mb-1">Resolved today</h6>
                        <span class="h4 mb-0 text-success" id="statResolved">—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="text-muted text-uppercase small mb-1">Escalations</h6>
                        <span class="h4 mb-0 text-danger" id="statEscalations">—</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <div class="card stat-card h-100 shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="text-muted text-uppercase small mb-1">Avg response</h6>
                        <span class="h5 mb-0 text-muted" id="statAvg">—</span>
                        <div class="small text-muted">(placeholder)</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters + search --}}
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Search</label>
                        <input type="text" class="form-control" id="filterSearch"
                            placeholder="Ticket ID, customer, order ID">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Channel</label>
                        <select class="form-select" id="filterChannel">
                            <option value="">All</option>
                            @foreach ($channels as $ch)
                                @if ($ch->id)
                                    <option value="{{ $ch->id }}">{{ $ch->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Status</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">All</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Escalated">Escalated</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Priority</label>
                        <select class="form-select" id="filterPriority">
                            <option value="">All</option>
                            <option value="Low">Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Executive</label>
                        <select class="form-select" id="filterExecutive">
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">From</label>
                        <input type="date" class="form-control" id="filterDateFrom">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">To</label>
                        <input type="date" class="form-control" id="filterDateTo">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-primary w-100" id="btnApplyFilters">Apply</button>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-secondary w-100" id="btnResetFilters">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="followup-table-outer">
                <div class="followup-table-shell">
                    <div class="followup-table-header">
                        <table class="table align-middle mb-0">
                            <colgroup>
                                <col style="width:9%"><col style="width:7%"><col style="width:10%"><col
                                    style="width:14%"><col style="width:7%"><col style="width:8%"><col
                                    style="width:7%"><col style="width:11%"><col style="width:11%"><col
                                    style="width:9%"><col style="width:7%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">Ticket ID</th>
                                    <th scope="col">Order ID</th>
                                    <th scope="col">Channel</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col">Issue</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Follow-up</th>
                                    <th scope="col">Next</th>
                                    <th scope="col">Executive</th>
                                    <th scope="col" class="text-end">Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <div class="followup-table-body">
                        <table class="table table-hover mb-0 align-middle">
                            <colgroup>
                                <col style="width:9%"><col style="width:7%"><col style="width:10%"><col
                                    style="width:14%"><col style="width:7%"><col style="width:8%"><col
                                    style="width:7%"><col style="width:11%"><col style="width:11%"><col
                                    style="width:9%"><col style="width:7%">
                            </colgroup>
                            <tbody id="followupTableBody">
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">Loading…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add / Edit modal --}}
    <div class="modal fade" id="followupModal" tabindex="-1" aria-labelledby="followupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="followupModalLabel">Add Followup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="formAlert" class="alert alert-danger d-none" role="alert"></div>
                    @include('customer-care.partials.followup-modal-form')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btnSaveFollowup">Save</button>
                </div>
            </div>
        </div>
    </div>

    {{-- View (read-only) --}}
    <div class="modal fade" id="viewFollowupModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ticket details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewFollowupBody"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function() {
            const dataUrl = @json(route('customer.care.followups.data'));
            const storeUrl = @json(route('customer.care.followups.store'));
            const followupBase = @json(url('/customer-care/followups'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function escapeHtml(s) {
                if (s == null) return '';
                const d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            function statusBadgeHtml(status) {
                const map = {
                    'Pending': 'secondary',
                    'In Progress': 'primary',
                    'Resolved': 'success',
                    'Escalated': 'danger'
                };
                const cls = map[status] || 'secondary';
                return '<span class="badge bg-' + cls + '">' + escapeHtml(status) + '</span>';
            }

            function priorityBadgeHtml(priority) {
                let cls = 'bg-secondary';
                let style = '';
                if (priority === 'Low') cls = 'bg-light text-dark border';
                else if (priority === 'Medium') cls = 'bg-warning text-dark';
                else if (priority === 'High') {
                    cls = 'text-white';
                    style = 'background-color:#fd7e14;';
                } else if (priority === 'Urgent') cls = 'bg-danger';
                const st = style ? ' style="' + style + '"' : '';
                return '<span class="badge ' + cls + '"' + st + '>' + escapeHtml(priority) + '</span>';
            }

            function buildQuery() {
                const p = new URLSearchParams();
                const s = document.getElementById('filterSearch').value.trim();
                if (s) p.set('search', s);
                const ch = document.getElementById('filterChannel').value;
                if (ch) p.set('channel_id', ch);
                const st = document.getElementById('filterStatus').value;
                if (st) p.set('status', st);
                const pr = document.getElementById('filterPriority').value;
                if (pr) p.set('priority', pr);
                const ex = document.getElementById('filterExecutive').value.trim();
                if (ex) p.set('executive', ex);
                const df = document.getElementById('filterDateFrom').value;
                if (df) p.set('date_from', df);
                const dt = document.getElementById('filterDateTo').value;
                if (dt) p.set('date_to', dt);
                return p.toString();
            }

            async function loadTable() {
                const tbody = document.getElementById('followupTableBody');
                try {
                    const res = await fetch(dataUrl + '?' + buildQuery(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const json = await res.json();
                    if (!json.data) throw new Error('Invalid response');

                    document.getElementById('statTotal').textContent = json.stats?.total ?? '—';
                    document.getElementById('statPending').textContent = json.stats?.pending ?? '—';
                    document.getElementById('statResolved').textContent = json.stats?.resolved_today ?? '—';
                    document.getElementById('statEscalations').textContent = json.stats?.escalations ?? '—';
                    document.getElementById('statAvg').textContent = json.stats?.avg_response ?? '—';

                    const execSel = document.getElementById('filterExecutive');
                    const cur = execSel.value;
                    const opts = new Set();
                    (json.executives || []).forEach(e => {
                        if (e) opts.add(e);
                    });
                    const htmlOpts = ['<option value="">All</option>'];
                    [...opts].sort().forEach(e => htmlOpts.push('<option value="' + escapeHtml(e) + '">' +
                        escapeHtml(e) + '</option>'));
                    execSel.innerHTML = htmlOpts.join('');
                    if ([...opts].includes(cur)) execSel.value = cur;

                    if (!json.data.length) {
                        tbody.innerHTML =
                            '<tr><td colspan="11" class="text-center py-4 text-muted">No records match filters.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = json.data.map(row => {
                        const overdue = row.overdue ? ' followup-row-overdue' : '';
                        let ref = '—';
                        if (row.reference_link) {
                            ref = '<a href="' + escapeHtml(row.reference_link) +
                                '" target="_blank" rel="noopener noreferrer">Open</a>';
                        }
                        return '<tr class="' + overdue.trim() + '" data-id="' + row.id + '">' +
                            '<td>' + escapeHtml(row.ticket_id) + '</td>' +
                            '<td>' + escapeHtml(row.order_id) + '</td>' +
                            '<td>' + escapeHtml(row.channel_name) + '</td>' +
                            '<td>' + escapeHtml(row.customer_name) + '</td>' +
                            '<td>' + escapeHtml(row.issue_type) + '</td>' +
                            '<td>' + statusBadgeHtml(row.status) + '</td>' +
                            '<td>' + priorityBadgeHtml(row.priority) + '</td>' +
                            '<td>' + escapeHtml(row.followup_display) + '</td>' +
                            '<td>' + escapeHtml(row.next_followup) + '</td>' +
                            '<td>' + escapeHtml(row.executive) + '</td>' +
                            '<td class="text-end text-nowrap">' +
                            '<button type="button" class="btn btn-sm btn-outline-info btn-view me-1" data-id="' + row
                            .id +
                            '" data-bs-toggle="tooltip" title="View"><i class="mdi mdi-eye"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="' +
                            row.id +
                            '" data-bs-toggle="tooltip" title="Edit"><i class="mdi mdi-pencil"></i></button>' +
                            '<button type="button" class="btn btn-sm btn-outline-danger btn-del" data-id="' + row
                            .id +
                            '" data-bs-toggle="tooltip" title="Delete"><i class="mdi mdi-delete"></i></button>' +
                            '</td></tr>';
                    }).join('');

                    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
                } catch (e) {
                    tbody.innerHTML =
                        '<tr><td colspan="11" class="text-center text-danger py-4">Failed to load data.</td></tr>';
                }
            }

            function clearFormErrors() {
                document.querySelectorAll('[data-error-for]').forEach(el => {
                    el.textContent = '';
                    el.classList.remove('d-block');
                });
                document.querySelectorAll('#followupForm .is-invalid').forEach(el => el.classList.remove(
                    'is-invalid'));
                document.getElementById('formAlert').classList.add('d-none');
            }

            function showErrors(err) {
                clearFormErrors();
                const alert = document.getElementById('formAlert');
                if (err.message && typeof err.errors !== 'object') {
                    alert.textContent = err.message;
                    alert.classList.remove('d-none');
                    return;
                }
                if (err.errors) {
                    for (const [k, msgs] of Object.entries(err.errors)) {
                        const el = document.querySelector('[data-error-for="' + k + '"]');
                        const inp = document.getElementById(k) || document.querySelector('[name="' + k + '"]');
                        if (inp) inp.classList.add('is-invalid');
                        if (el) {
                            el.textContent = (msgs || []).join(' ');
                            el.classList.add('d-block');
                        }
                    }
                    alert.textContent = err.message || 'Please fix the highlighted fields.';
                    alert.classList.remove('d-none');
                }
            }

            function formPayload() {
                const fd = new FormData(document.getElementById('followupForm'));
                const ch = fd.get('channel_master_id');
                const payload = {
                    ticket_id: fd.get('ticket_id'),
                    order_id: fd.get('order_id') || null,
                    channel_master_id: ch ? parseInt(ch, 10) : null,
                    customer_name: fd.get('customer_name'),
                    email: fd.get('email') || null,
                    phone: fd.get('phone') || null,
                    issue_type: fd.get('issue_type'),
                    status: fd.get('status'),
                    priority: fd.get('priority'),
                    followup_date: fd.get('followup_date'),
                    followup_time: fd.get('followup_time') || null,
                    next_followup_at: fd.get('next_followup_at') || null,
                    assigned_executive: fd.get('assigned_executive') || null,
                    comments: fd.get('comments') || null,
                    internal_remarks: fd.get('internal_remarks') || null,
                    reference_link: fd.get('reference_link') || null,
                };
                if (payload.channel_master_id === 0 || isNaN(payload.channel_master_id)) payload.channel_master_id =
                    null;
                return payload;
            }

            document.getElementById('btnApplyFilters').addEventListener('click', loadTable);
            document.getElementById('btnResetFilters').addEventListener('click', () => {
                document.getElementById('filterSearch').value = '';
                document.getElementById('filterChannel').value = '';
                document.getElementById('filterStatus').value = '';
                document.getElementById('filterPriority').value = '';
                document.getElementById('filterExecutive').value = '';
                document.getElementById('filterDateFrom').value = '';
                document.getElementById('filterDateTo').value = '';
                loadTable();
            });

            document.getElementById('btnAddFollowup').addEventListener('click', () => {
                document.getElementById('followupModalLabel').textContent = 'Add Followup';
                document.getElementById('edit_id').value = '';
                document.getElementById('followupForm').reset();
                document.getElementById('ticket_id_display').value = '';
                document.getElementById('ticket_id').value = '';
                document.getElementById('ticket_id_hint').textContent =
                    'Generated automatically when you save (e.g. TKT-000001).';
                document.getElementById('ticket_id_hint').classList.remove('d-none');
                clearFormErrors();
            });

            document.getElementById('btnSaveFollowup').addEventListener('click', async () => {
                clearFormErrors();
                const form = document.getElementById('followupForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                const id = document.getElementById('edit_id').value;
                const payload = formPayload();
                if (!id) {
                    delete payload.ticket_id;
                }
                const url = id ? followupBase + '/' + id : storeUrl;
                const method = id ? 'PUT' : 'POST';
                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        showErrors({
                            message: data.message || 'Validation failed',
                            errors: data.errors || {}
                        });
                        return;
                    }
                    bootstrap.Modal.getInstance(document.getElementById('followupModal')).hide();
                    loadTable();
                } catch (e) {
                    document.getElementById('formAlert').textContent = 'Network error.';
                    document.getElementById('formAlert').classList.remove('d-none');
                }
            });

            document.getElementById('followupTableBody').addEventListener('click', async (e) => {
                const btn = e.target.closest('button');
                if (!btn) return;
                const id = btn.dataset.id;
                if (btn.classList.contains('btn-del')) {
                    if (!confirm('Delete this follow-up?')) return;
                    await fetch(followupBase + '/' + id, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    loadTable();
                    return;
                }
                if (btn.classList.contains('btn-view')) {
                    const res = await fetch(followupBase + '/' + id, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const d = await res.json();
                    const ref = d.reference_link ?
                        '<a href="' + escapeHtml(d.reference_link) +
                        '" target="_blank" rel="noopener noreferrer">' + escapeHtml(d.reference_link) + '</a>' :
                        '—';
                    document.getElementById('viewFollowupBody').innerHTML =
                        '<dl class="row mb-0">' +
                        '<dt class="col-sm-4">Ticket</dt><dd class="col-sm-8">' + escapeHtml(d.ticket_id) +
                        '</dd>' +
                        '<dt class="col-sm-4">Order</dt><dd class="col-sm-8">' + escapeHtml(d.order_id || '—') +
                        '</dd>' +
                        '<dt class="col-sm-4">Customer</dt><dd class="col-sm-8">' + escapeHtml(d.customer_name) +
                        '</dd>' +
                        '<dt class="col-sm-4">Email / Phone</dt><dd class="col-sm-8">' + escapeHtml(d.email ||
                            '—') + ' / ' + escapeHtml(d.phone || '—') + '</dd>' +
                        '<dt class="col-sm-4">Issue</dt><dd class="col-sm-8">' + escapeHtml(d.issue_type) +
                        '</dd>' +
                        '<dt class="col-sm-4">Status</dt><dd class="col-sm-8">' + statusBadgeHtml(d.status) +
                        '</dd>' +
                        '<dt class="col-sm-4">Priority</dt><dd class="col-sm-8">' + priorityBadgeHtml(d.priority) +
                        '</dd>' +
                        '<dt class="col-sm-4">Comments</dt><dd class="col-sm-8">' + escapeHtml(d.comments || '—')
                        .replace(/\n/g, '<br>') + '</dd>' +
                        '<dt class="col-sm-4">Internal</dt><dd class="col-sm-8">' + escapeHtml(d.internal_remarks ||
                            '—').replace(/\n/g, '<br>') + '</dd>' +
                        '<dt class="col-sm-4">Reference</dt><dd class="col-sm-8">' + ref + '</dd></dl>';
                    new bootstrap.Modal(document.getElementById('viewFollowupModal')).show();
                    return;
                }
                if (btn.classList.contains('btn-edit')) {
                    const res = await fetch(followupBase + '/' + id, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const d = await res.json();
                    document.getElementById('followupModalLabel').textContent = 'Edit Followup';
                    document.getElementById('edit_id').value = d.id;
                    document.getElementById('ticket_id').value = d.ticket_id;
                    document.getElementById('ticket_id_display').value = d.ticket_id;
                    document.getElementById('ticket_id_hint').textContent = 'Ticket ID cannot be changed.';
                    document.getElementById('ticket_id_hint').classList.remove('d-none');
                    document.getElementById('order_id').value = d.order_id || '';
                    document.getElementById('channel_master_id').value = d.channel_master_id || '';
                    document.getElementById('customer_name').value = d.customer_name;
                    document.getElementById('email').value = d.email || '';
                    document.getElementById('phone').value = d.phone || '';
                    document.getElementById('issue_type').value = d.issue_type;
                    document.getElementById('status').value = d.status;
                    document.getElementById('priority').value = d.priority;
                    document.getElementById('followup_date').value = d.followup_date;
                    document.getElementById('followup_time').value = d.followup_time || '';
                    document.getElementById('next_followup_at').value = d.next_followup_at || '';
                    document.getElementById('assigned_executive').value = d.assigned_executive || '';
                    document.getElementById('comments').value = d.comments || '';
                    document.getElementById('internal_remarks').value = d.internal_remarks || '';
                    document.getElementById('reference_link').value = d.reference_link || '';
                    clearFormErrors();
                    new bootstrap.Modal(document.getElementById('followupModal')).show();
                }
            });

            loadTable();

            // TODO: Replace static data with API integration (single endpoint for list + stats).
            // TODO: Fetch channels via API endpoint for the Channel dropdown refresh.
        })();
    </script>
    {{--
    Sample $channels in controller:
        $channels = \App\Models\ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])->orderBy('type')->orderBy('id')->get(['id','channel']);
    Dummy row shape from /customer-care/followups/data:
        {"id":1,"ticket_id":"TKT-DEMO-001","order_id":"ORD-1001","channel_name":"Amazon",
         "customer_name":"Sample Customer","issue_type":"Refund","status":"Pending",
         "priority":"High","followup_display":"03-17-2026 10:00","next_followup":"03-18-2026 10:00",
         "executive":"Executive A","reference_link":"https://...","overdue":false}
    --}}
@endsection
