@extends('layouts.vertical', ['title' => 'Claim & Reimbursement'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<link href="{{ asset('css/select-searchable.css') }}" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
<style>
    /* Custom styles for the Tabulator table */
    /* Pagination styling */
    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page {
        padding: 8px 16px;
        margin: 0 4px;
        border-radius: 6px;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page:hover {
        background: #e0eaff;
        color: #2563eb;
    }

    .tabulator .tabulator-footer .tabulator-paginator .tabulator-page.active {
        background: #2563eb;
        color: white;
    }

    /* Communication / platform dropdown icons (matches MFRG In-Progress) */
    .mip-plat-icon-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: transform 0.12s;
    }
    .mip-plat-icon-link:hover {
        transform: scale(1.2);
    }

    /* Allow the communication dropdown to overflow Tabulator's clipped cells */
    .tabulator .tabulator-cell.comm-cell {
        overflow: visible !important;
    }
    .tabulator-row {
        overflow: visible !important;
    }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Claim & Reimbursement', 'sub_title' => 'Claim & Reimbursement'])

@if(Session::has('flash_message'))
<div class="alert alert-primary bg-primary text-white alert-dismissible fade show" role="alert" style="background-color: #169e28 !important; color: #fff !important;">
    {{ Session::get('flash_message') }}
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Claim & Reimbursement</h4>
                    <div class="d-flex align-items-center gap-2">
                        @include('purchase-master.partials.page-info-toolbar', ['pageKey' => 'claim_reimbursement'])
                        <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#claimModal">
                        <i class="fas fa-plus-circle me-1"></i>Add Claim / Reimbursement
                    </button>
                    </div>
                </div>
                <div class="row mb-3 align-items-end">
                    <div class="col-md-4 col-lg-3">
                        <label for="supplier-filter" class="form-label fw-semibold mb-1">Suppler</label>
                        <select id="supplier-filter" class="form-select form-select-sm select-searchable">
                            <option value="">All Supplers</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="show-archived-filter">
                            <label class="form-check-label fw-semibold" for="show-archived-filter">
                                Show archived (resolved)
                            </label>
                        </div>
                    </div>
                </div>

@php $canArchive = $canArchive ?? false; @endphp
<script>window.CLAIM_CAN_ARCHIVE = @json($canArchive);</script>
                <div id="claim-reimbursement-table"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="claimModal" tabindex="-1" aria-labelledby="claimModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="claimModalLabel">Add Claim / Reimbursement</h5>
        <button type="button" class="btn-close bg-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <form id="claimForm" action="{{ route('claim.reimbursement.save') }}" method="POST" enctype="multipart/form-data">
            @csrf
          <div class="row mb-3">
            <div class="col-md-4">
              <label for="supplier" class="form-label fw-semibold">From Supplier</label>
              <select id="supplier" name="supplier" class="form-select" required>
                <option value="">Select Supplier</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label for="claimNo" class="form-label fw-semibold">Claim No.</label>
              <input type="text" id="claimNo" name="claim_number" class="form-control" value="{{ $claimNumber }}" readonly>
            </div>
            <div class="col-md-4">
              <label for="claimDate" class="form-label fw-semibold">Date</label>
              <input type="date" id="claimDate" name="claim_date" class="form-control" required>
            </div>
          </div>

          <!-- Dynamic Item Table -->
          <div class="table-responsive">
            <table class="table table-bordered align-middle text-center" id="claimTable">
              <thead class="table-light">
                <tr>
                  <th style="min-width: 220px;">SKU</th>
                  <th>Qty</th>
                  <th>Rate USD</th>
                  <th>Amount</th>
                  <th>Reason/Notes</th>
                  <th>Image (if ANY)</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="claimTableBody">
                <!-- Default row -->
                <tr>
                  <td><select name="item[]" class="form-control sku-select" required style="width: 100%;"><option value="">Search SKU...</option></select></td>
                  <td><input type="number" name="qty[]" class="form-control qty" required></td>
                  <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
                  <td><input type="number" name="amount[]" class="form-control amount" readonly></td>
                  <td><input type="text" name="reason[]" class="form-control"></td>
                  <td><input type="file" name="image[]" class="form-control"></td>
                  <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-close"></i></button></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mb-3">
            <button type="button" class="btn btn-outline-success btn-sm" id="addRowBtn"><i class="fas fa-plus-circle me-1"></i> Add Row</button>
          </div>

          <div class="text-end">
            <strong>Total Amount: $<span id="totalAmount">0.00</span></strong>
          </div>
        </form>
      </div>

      <div class="modal-footer">
        <button type="submit" form="claimForm" class="btn btn-primary">Save Claim</button>
      </div>
    </div>
  </div>
</div>

{{-- Action History Modal --}}
<div class="modal fade" id="actionHistoryModal" tabindex="-1" aria-labelledby="actionHistoryModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title" id="actionHistoryModalLabel"><i class="fas fa-history me-2"></i>Action being taken</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="action_claim_id">
        <div class="mb-2">
          <label for="action_note_input" class="form-label fw-semibold">Action being taken</label>
          <textarea id="action_note_input" class="form-control" rows="3" placeholder="Enter action being taken..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveActionBtn"><i class="fas fa-plus me-1"></i> Add</button>
      </div>
    </div>
  </div>
</div>

{{-- Received Amount / Goods Modal --}}
<div class="modal fade" id="receivedAmountModal" tabindex="-1" aria-labelledby="receivedAmountModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="receivedAmountModalLabel">Recd Amt/Goods $</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="received_amount_claim_id">
        <div class="mb-2">
          <label for="received_amount_input" class="form-label fw-semibold">Received Amount / Goods</label>
          <textarea id="received_amount_input" class="form-control" rows="3" placeholder="Enter received amount / goods details..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveReceivedAmountBtn">Save</button>
      </div>
    </div>
  </div>
</div>

{{-- Details Note Modal --}}
<div class="modal fade" id="detailsNoteModal" tabindex="-1" aria-labelledby="detailsNoteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="detailsNoteModalLabel">Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="details_note_claim_id">
        <div class="mb-2">
          <label for="details_note_input" class="form-label fw-semibold">Details</label>
          <textarea id="details_note_input" class="form-control" rows="4" placeholder="Enter details..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveDetailsNoteBtn">Save</button>
      </div>
    </div>
  </div>
</div>


@endsection

@section('script')
<script src="{{ asset('js/select-searchable.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const supplierFilter = document.getElementById('supplier-filter');
        const table = new Tabulator("#claim-reimbursement-table", {
            ajaxURL: "/claim-reimbursement/view-data",
            ajaxConfig: "GET",
            layout: "fitColumns",
            height: "500px",
            resizableColumns: true,
            pagination: "remote", 
            paginationSize: 10,
            columnDefaults: {
                headerHozAlign: "center",
                resizable: true,
            },
            columns: [
                { title: "Claim Number", field: "claim_number", hozAlign: "center", minWidth: 130, visible: false },
                { title: "Supplier", field: "supplier_name", hozAlign: "center", width: 70 },
                {
                    title: "Claim",
                    field: "claim_date",
                    hozAlign: "center",
                    width: 65,
                    formatter: function (cell) {
                        const val = cell.getValue();
                        if (!val) return '';
                        const d = new Date(String(val).substring(0, 10) + 'T00:00:00');
                        if (isNaN(d.getTime())) return val;
                        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                        return d.getDate() + ' ' + months[d.getMonth()];
                    }
                },
                {
                  title: "Details",
                  field: "details",
                  minWidth: 200,
                  widthGrow: 3,
                  headerSort: false,
                  variableHeight: true,
                  formatter: function (cell) {
                      const rowData = cell.getRow().getData();
                      return buildDetailsInline(cell.getValue(), rowData.details_note);
                  },
                  cellClick: function (e, cell) {
                      if (e.target.closest('.details-note-btn')) {
                          const rowData = cell.getRow().getData();
                          openDetailsNoteModal(rowData.id, rowData.details_note);
                      }
                  },
                  hozAlign: "left"
                },
                {
                    title: "Amt $",
                    field: "total_amount",
                    hozAlign: "center",
                    width: 70,
                    formatter: function (cell) {
                        const num = parseFloat(cell.getValue());
                        if (isNaN(num)) return '';
                        return Math.round(num).toLocaleString('en-US');
                    }
                },
                {
                    title: "Recd Amt/Goods $",
                    field: "received_amount",
                    hozAlign: "center",
                    width: 110,
                    headerSort: false,
                    formatter: function (cell) {
                        const val = cell.getValue();
                        if (val && String(val).trim() !== '') {
                            return `<span class="d-inline-block text-truncate" style="max-width: 140px; vertical-align: middle;" title="${escapeHtml(val)}">${escapeHtml(val)}</span>
                                    <button class="btn btn-sm btn-link p-0 ms-1 recd-amt-btn" title="Edit"><i class="fas fa-pen"></i></button>`;
                        }
                        return `<button class="btn btn-sm btn-outline-primary recd-amt-btn"><i class="fas fa-plus me-1"></i> Add</button>`;
                    },
                    cellClick: function (e, cell) {
                        if (e.target.closest('.recd-amt-btn')) {
                            const d = cell.getRow().getData();
                            openReceivedAmountModal(d.id, d.received_amount);
                        }
                    }
                },
                {
                    title: "Created by",
                    field: "created_by",
                    hozAlign: "center",
                    width: 85,
                    formatter: function (cell) {
                        const data = cell.getRow().getData();
                        const name = cell.getValue() || 'System';
                        const date = data.created_date ? `<div class="text-muted small">${data.created_date}</div>` : '';
                        return `<div><span class="fw-semibold">${name}</span>${date}</div>`;
                    }
                },
                {
                    title: '<i class="fas fa-comments" title="Communication"></i>',
                    field: "communication",
                    hozAlign: "center",
                    headerSort: false,
                    width: 45,
                    cssClass: "comm-cell",
                    formatter: function(cell) {
                        return buildCommunicationDropdown(cell.getValue());
                    }
                },
                {
                    title: "Action",
                    field: "action_history",
                    hozAlign: "left",
                    headerSort: false,
                    minWidth: 180,
                    widthGrow: 2,
                    variableHeight: true,
                    formatter: function (cell) {
                        return buildActionHistoryInline(cell.getValue());
                    },
                    cellClick: function (e, cell) {
                        if (e.target.closest('.add-action-btn')) {
                            const d = cell.getRow().getData();
                            openAddActionModal(d.id);
                        }
                    }
                },
                {
                    title: "Follow",
                    field: "follow_up_date",
                    hozAlign: "center",
                    headerSort: false,
                    width: 80,
                    editor: "date",
                    formatter: function (cell) {
                        return buildFollowUpDisplay(cell.getValue());
                    },
                    cellEdited: function (cell) {
                        saveFollowUpDate(cell.getRow().getData().id, cell.getValue(), cell);
                    }
                },
                {
                    title: '<i class="fas fa-box-archive" title="Resolved"></i>',
                    field: "is_archived",
                    hozAlign: "center",
                    headerSort: false,
                    width: 50,
                    formatter: function (cell) {
                        return buildResolvedCell(cell.getRow().getData());
                    },
                    cellClick: function (e, cell) {
                        const btn = e.target.closest('.archive-btn');
                        if (btn) {
                            const d = cell.getRow().getData();
                            toggleArchive(d.id, btn.dataset.archive === '1');
                        }
                    }
                },
            ],
        });

        const showArchivedFilter = document.getElementById('show-archived-filter');

        window.reloadClaimTable = function () {
            table.setData("/claim-reimbursement/view-data", {
                supplier_id: supplierFilter ? (supplierFilter.value || '') : '',
                show_archived: (showArchivedFilter && showArchivedFilter.checked) ? 1 : 0
            });
        };

        if (supplierFilter) {
            supplierFilter.addEventListener('change', window.reloadClaimTable);
        }
        if (showArchivedFilter) {
            showArchivedFilter.addEventListener('change', window.reloadClaimTable);
        }

        const saveReceivedAmountBtn = document.getElementById('saveReceivedAmountBtn');
        if (saveReceivedAmountBtn) {
            saveReceivedAmountBtn.addEventListener('click', function () {
                const id = document.getElementById('received_amount_claim_id').value;
                const value = document.getElementById('received_amount_input').value;
                const btn = this;
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

                const formData = new FormData();
                formData.append('received_amount', value);
                formData.append('_token', '{{ csrf_token() }}');

                fetch(`/claim-reimbursement/${id}/received-amount`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const row = table.getRow(id);
                        if (row) {
                            row.update({
                                received_amount: res.received_amount,
                                action_history: res.action_history
                            });
                        }
                        bootstrap.Modal.getInstance(document.getElementById('receivedAmountModal')).hide();
                    } else {
                        alert('Failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Something went wrong while saving.'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            });
        }

        const saveDetailsNoteBtn = document.getElementById('saveDetailsNoteBtn');
        if (saveDetailsNoteBtn) {
            saveDetailsNoteBtn.addEventListener('click', function () {
                const id = document.getElementById('details_note_claim_id').value;
                const value = document.getElementById('details_note_input').value;
                const btn = this;
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Saving...';

                const formData = new FormData();
                formData.append('details_note', value);
                formData.append('_token', '{{ csrf_token() }}');

                fetch(`/claim-reimbursement/${id}/details-note`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const row = table.getRow(id);
                        if (row) {
                            row.update({ details_note: res.details_note });
                        }
                        bootstrap.Modal.getInstance(document.getElementById('detailsNoteModal')).hide();
                    } else {
                        alert('Failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Something went wrong while saving.'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            });
        }

        const saveActionBtn = document.getElementById('saveActionBtn');
        if (saveActionBtn) {
            saveActionBtn.addEventListener('click', function () {
                const id = document.getElementById('action_claim_id').value;
                const note = document.getElementById('action_note_input').value.trim();
                if (note === '') {
                    document.getElementById('action_note_input').focus();
                    return;
                }
                const btn = this;
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                const formData = new FormData();
                formData.append('note', note);
                formData.append('_token', '{{ csrf_token() }}');

                fetch(`/claim-reimbursement/${id}/action`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const row = table.getRow(id);
                        if (row) {
                            row.update({ action_history: res.action_history });
                        }
                        bootstrap.Modal.getInstance(document.getElementById('actionHistoryModal')).hide();
                    } else {
                        alert('Failed: ' + (res.message || 'Unknown error'));
                    }
                })
                .catch(() => alert('Something went wrong while saving.'))
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                });
            });
        }

        addNewRow();
    });

    function addNewRow() {
      const addRowBtn = document.getElementById('addRowBtn');
      const claimTableBody = document.getElementById('claimTableBody');
      const totalAmountDisplay = document.getElementById('totalAmount');

      function updateTotal() {
          let total = 0;
          document.querySelectorAll('.amount').forEach(input => {
              total += parseFloat(input.value) || 0;
          });
          totalAmountDisplay.textContent = total.toFixed(2);
      }

      function attachListeners(row) {
          const qtyInput = row.querySelector('.qty');
          const rateInput = row.querySelector('.rate');
          const amountInput = row.querySelector('.amount');

          [qtyInput, rateInput].forEach(input => {
              input.addEventListener('input', () => {
                  const qty = parseFloat(qtyInput.value) || 0;
                  const rate = parseFloat(rateInput.value) || 0;
                  const amt = qty * rate;
                  amountInput.value = amt.toFixed(2);
                  updateTotal();
              });
          });

          const removeBtn = row.querySelector('.remove-row');
          removeBtn.addEventListener('click', () => {
              if (claimTableBody.querySelectorAll('tr').length > 1) {
                  row.remove();
                  updateTotal();
              } else {
                  alert("At least one row must remain.");
              }
          });
      }

      // Handle Add Row button
      addRowBtn.addEventListener('click', () => {
          const newRow = document.createElement('tr');
          newRow.innerHTML = `
              <td><select name="item[]" class="form-control sku-select" required style="width: 100%;"><option value="">Search SKU...</option></select></td>
              <td><input type="number" name="qty[]" class="form-control qty" required></td>
              <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
              <td><input type="number" name="amount[]" class="form-control amount" readonly></td>
              <td><input type="text" name="reason[]" class="form-control"></td>
              <td><input type="file" name="image[]" class="form-control"></td>
              <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-close"></i></button></td>
          `;
          claimTableBody.appendChild(newRow);
          attachListeners(newRow);
          initializeSkuSelect(newRow.querySelector('.sku-select'));
      });

      const firstRow = claimTableBody.querySelector('tr');
      if (firstRow) {
          attachListeners(firstRow);
          initializeSkuSelect(firstRow.querySelector('.sku-select'));
      }
    }

    // Initialize Select2 searchable SKU dropdown on a given <select> element
    function initializeSkuSelect(element) {
        if (!element) return;
        jQuery(element).select2({
            ajax: {
                url: '/purchase/search-sku',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.items,
                        pagination: {
                            more: data.has_more
                        }
                    };
                },
                cache: true
            },
            placeholder: 'Search SKU...',
            allowClear: true,
            minimumInputLength: 0,
            dropdownParent: jQuery('#claimModal')
        });
    }

    function openReceivedAmountModal(claimId, currentValue) {
        document.getElementById('received_amount_claim_id').value = claimId || '';
        document.getElementById('received_amount_input').value = currentValue || '';
        const modal = new bootstrap.Modal(document.getElementById('receivedAmountModal'));
        modal.show();
    }

    function buildDetailsInline(detailsData, detailsNote) {
        let items = detailsData || [];
        if (typeof items === 'string') {
            try { items = JSON.parse(items) || []; } catch (e) { items = []; }
        }
        if (!Array.isArray(items)) items = [];

        let html = '';

        if (items.length > 0) {
            html += '<table class="table table-sm table-bordered mb-1" style="font-size: 12px;">';
            html += '<thead class="table-light"><tr>'
                + '<th>SKU</th><th>Qty</th><th>Rate</th><th>Amount</th><th>Reason/Notes</th><th>Image</th>'
                + '</tr></thead><tbody>';
            items.forEach(item => {
                const img = item.image
                    ? `<a href="/${escapeHtml(item.image)}" target="_blank"><img src="/${escapeHtml(item.image)}" style="height:28px;"></a>`
                    : '<span class="text-muted">N/A</span>';
                html += '<tr>'
                    + `<td>${escapeHtml(item.item || '')}</td>`
                    + `<td class="text-center">${escapeHtml(item.qty || '')}</td>`
                    + `<td class="text-center">${escapeHtml(item.rate || '')}</td>`
                    + `<td class="text-center">${escapeHtml(item.amount || '')}</td>`
                    + `<td>${escapeHtml(item.reason || '')}</td>`
                    + `<td class="text-center">${img}</td>`
                    + '</tr>';
            });
            html += '</tbody></table>';
        } else {
            html += '<span class="text-muted">No items</span><br>';
        }

        if (detailsNote && String(detailsNote).trim() !== '') {
            html += `<div class="small"><span class="fw-semibold">Note:</span> ${escapeHtml(detailsNote)} `
                + `<button class="btn btn-sm btn-link p-0 details-note-btn" title="Edit details"><i class="fas fa-pen"></i></button></div>`;
        } else {
            html += `<button class="btn btn-sm btn-outline-secondary details-note-btn" title="Enter details"><i class="fas fa-pen me-1"></i> Add Details</button>`;
        }

        return html;
    }

    function openDetailsNoteModal(claimId, currentValue) {
        document.getElementById('details_note_claim_id').value = claimId || '';
        document.getElementById('details_note_input').value = currentValue || '';
        const modal = new bootstrap.Modal(document.getElementById('detailsNoteModal'));
        modal.show();
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function buildCommunicationDropdown(linksData) {
        let list = linksData || [];
        if (typeof list === 'string') {
            try { list = JSON.parse(list) || []; } catch (e) { list = []; }
        }
        if (!Array.isArray(list) || list.length === 0) {
            return '<span class="text-muted">-</span>';
        }

        const iconMap = {
            'Website':  'fas fa-globe',
            'Email':    'fas fa-envelope',
            'WhatsApp': 'fab fa-whatsapp',
            'WeChat':   'fab fa-weixin',
            'Alibaba':  'fas fa-store',
        };
        const colorMap = {
            'Website':  '#2563eb',
            'Email':    '#dc3545',
            'WhatsApp': '#25d366',
            'WeChat':   '#09b83e',
            'Alibaba':  '#ff6a00',
        };

        let items = '';
        for (let i = 0; i < list.length; i++) {
            const p = list[i];
            const icon = iconMap[p.label] || 'fas fa-link';
            const color = colorMap[p.label] || '#6b7280';
            const title = escapeHtml(p.label + (p.display ? ': ' + p.display : ''));
            if (p.url) {
                const ext = p.external ? ' target="_blank" rel="noopener noreferrer"' : '';
                items += '<a class="mip-plat-icon-link" href="' + escapeHtml(p.url) + '"' + ext + ' title="' + title + '" style="color:' + color + ';font-size:18px;"><i class="' + icon + '"></i></a>';
            } else {
                items += '<span class="mip-plat-icon-link" title="' + title + '" style="color:' + color + ';font-size:18px;"><i class="' + icon + '"></i></span>';
            }
        }

        return '<div class="dropdown d-inline-block">' +
            '<button class="btn btn-sm btn-light py-0 px-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 11px;" title="Communication">C (' + list.length + ')</button>' +
            '<ul class="dropdown-menu dropdown-menu-end" style="overflow: visible; min-width: auto; padding: 6px;"><li class="d-flex align-items-center gap-2 px-2">' + items + '</li></ul></div>';
    }

    function buildResolvedCell(data) {
        const canArchive = window.CLAIM_CAN_ARCHIVE === true;
        const archived = data.is_archived === true || data.is_archived === 1;

        let html = '';

        if (archived) {
            const by = data.archived_by ? escapeHtml(data.archived_by) : '';
            const date = data.archived_date ? escapeHtml(data.archived_date) : '';
            const tip = ('Resolved' + (by ? ' by ' + by : '') + (date ? ' · ' + date : '')).replace(/"/g, '');
            html += `<span class="text-success" title="${tip}"><i class="fas fa-circle-check"></i></span>`;
            if (canArchive) {
                html += `<button class="btn btn-sm btn-link p-0 ms-1 archive-btn" data-archive="0" title="Restore"><i class="fas fa-rotate-left"></i></button>`;
            }
        } else {
            if (canArchive) {
                html += `<button class="btn btn-sm btn-outline-success archive-btn" data-archive="1" title="Archive (resolve)"><i class="fas fa-box-archive"></i></button>`;
            } else {
                html += `<span class="text-muted small">-</span>`;
            }
        }

        return html;
    }

    function toggleArchive(id, archive) {
        if (archive && !confirm('Archive (mark resolved) this claim?')) return;
        if (!archive && !confirm('Restore this claim?')) return;

        const formData = new FormData();
        formData.append('archive', archive ? 1 : 0);
        formData.append('_token', '{{ csrf_token() }}');

        fetch(`/claim-reimbursement/${id}/archive`, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json().then(j => ({ ok: r.ok, body: j })))
        .then(({ ok, body }) => {
            if (ok && body.success) {
                if (typeof window.reloadClaimTable === 'function') {
                    window.reloadClaimTable();
                }
            } else {
                alert(body.message || 'Action failed.');
            }
        })
        .catch(() => alert('Something went wrong.'));
    }

    function buildFollowUpDisplay(val) {
        if (!val || String(val).trim() === '') {
            return '<span class="text-muted"><i class="fas fa-calendar-plus me-1"></i>Set date</span>';
        }
        const d = new Date(val + 'T00:00:00');
        if (isNaN(d.getTime())) {
            return '<span class="text-muted">Set date</span>';
        }
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const label = d.getDate() + ' ' + months[d.getMonth()];

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (d < today) {
            return `<span class="fw-semibold text-danger">${label} <i class="fas fa-exclamation-circle" title="Overdue"></i></span>`;
        }
        return `<span class="fw-semibold">${label}</span>`;
    }

    function saveFollowUpDate(id, value, cell) {
        const formData = new FormData();
        formData.append('follow_up_date', value || '');
        formData.append('_token', '{{ csrf_token() }}');

        fetch(`/claim-reimbursement/${id}/follow-up`, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert('Failed: ' + (res.message || 'Unknown error'));
            }
        })
        .catch(() => alert('Something went wrong while saving the follow up date.'));
    }

    function buildActionHistoryInline(historyData) {
    let history = historyData || [];
    if (typeof history === 'string') {
        try { history = JSON.parse(history) || []; } catch (e) { history = []; }
    }
    if (!Array.isArray(history)) history = [];

    let html = '';

    if (history.length > 0) {
        html += '<table class="table table-sm table-bordered mb-1" style="font-size: 12px;">';
        html += '<thead class="table-light"><tr><th>Action</th><th>Note</th><th>User</th><th>Date</th></tr></thead><tbody>';
        history.forEach(entry => {
            html += '<tr>'
                + `<td><span class="badge bg-info-subtle text-dark">${escapeHtml(entry.action || '-')}</span></td>`
                + `<td>${escapeHtml(entry.note || '-')}</td>`
                + `<td>${escapeHtml(entry.user || '-')}</td>`
                + `<td>${escapeHtml(entry.date || '-')}</td>`
                + '</tr>';
        });
        html += '</tbody></table>';
    } else {
        html += '<span class="text-muted">No history</span><br>';
    }

    html += '<button class="btn btn-sm btn-outline-primary add-action-btn"><i class="fas fa-plus me-1"></i> Add Action</button>';
    return html;
}

function openAddActionModal(claimId) {
    document.getElementById('action_claim_id').value = claimId || '';
    document.getElementById('action_note_input').value = '';
    const modal = new bootstrap.Modal(document.getElementById('actionHistoryModal'));
    modal.show();
}




</script>
@endsection