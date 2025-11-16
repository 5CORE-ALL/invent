@extends('layouts.vertical', ['title' => 'Claim & Reimbursement'])

@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
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
                    <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#claimModal">
                        <i class="fas fa-plus-circle me-1"></i>Add Claim / Reimbursement
                    </button>
                </div>
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
                  <th>Item</th>
                  <th>Qty</th>
                  <th>Rate USD</th>
                  <th>Amount</th>
                  <th>Reason</th>
                  <th>Image (if ANY)</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="claimTableBody">
                <!-- Default row -->
                <tr>
                  <td><input type="text" name="item[]" class="form-control" required></td>
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

{{-- Modal for Item Details --}}
<!-- Item Details Modal -->
<div class="modal fade" id="itemDetailsModal" tabindex="-1" aria-labelledby="itemDetailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title" id="itemDetailsModalLabel">Item Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered m-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Item (SKU)</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Amount</th>
                <th>Reason</th>
                <th>Image</th>
              </tr>
            </thead>
            <tbody id="item-details-body">
              <!-- JS will inject rows here -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const table = new Tabulator("#claim-reimbursement-table", {
            ajaxURL: "/claim-reimbursement/view-data",
            ajaxConfig: "GET",
            layout: "fitColumns",
            height: "500px",
            pagination: "remote", 
            paginationSize: 10,
            columns: [
                { title: "Claim Number", field: "claim_number", hozAlign: "center" },
                { title: "Supplier", field: "supplier_name", hozAlign: "center" },
                { title: "Claim Date", field: "claim_date", hozAlign: "center" },
                {
                  title: "Details",
                  field: "details",
                  formatter: function () {
                      return "<button class='btn btn-sm btn-info' data-bs-toggle='modal' data-bs-target='#itemDetailsModal'><i class='fas fa-eye me-1'></i> View</button>";
                  },
                  cellClick: function (e, cell) {
                      const rowData = cell.getRow().getData();
                      showItemDetailsModal(rowData.details);
                  },
                  hozAlign: "center"
                },
                { title: "Amount", field: "total_amount", hozAlign: "center"},
                {
                    title: "Communication Links",
                    field: "communication",
                    formatter: function(cell) {
                        const url = cell.getValue();
                        return `<a href="${url}" target="_blank">Open</a>`;
                    }
                },
            ],
        });
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
              <td><input type="text" name="item[]" class="form-control" required></td>
              <td><input type="number" name="qty[]" class="form-control qty" required></td>
              <td><input type="number" step="0.01" name="rate[]" class="form-control rate" required></td>
              <td><input type="number" name="amount[]" class="form-control amount" readonly></td>
              <td><input type="text" name="reason[]" class="form-control"></td>
              <td><input type="file" name="image[]" class="form-control"></td>
              <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-close"></i></button></td>
          `;
          claimTableBody.appendChild(newRow);
          attachListeners(newRow);
      });

      const firstRow = claimTableBody.querySelector('tr');
      if (firstRow) {
          attachListeners(firstRow);
      }
    }

    function showItemDetailsModal(detailsData) {
    const tbody = document.getElementById("item-details-body");
    tbody.innerHTML = "";

    try {
        const items = JSON.parse(detailsData);

        if (!Array.isArray(items)) throw new Error("Invalid format");

        items.forEach((item, index) => {
            const tr = document.createElement("tr");

            tr.innerHTML = `
                <td>${index + 1}</td>
                <td>${item.item || ''}</td>
                <td>${item.qty || ''}</td>
                <td>${item.rate || ''}</td>
                <td>${item.amount || ''}</td>
                <td>${item.reason || ''}</td>
                <td>
                    ${item.image ? `<img src="/${item.image}" alt="image" style="height:40px;">` : 'N/A'}
                </td>
            `;

            tbody.appendChild(tr);
        });

        const modal = new bootstrap.Modal(document.getElementById('itemDetailsModal'));
        modal.show();

    } catch (error) {
        console.error("Error parsing item details:", error);
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger text-center">Invalid item data</td></tr>`;
    }
}




</script>
@endsection