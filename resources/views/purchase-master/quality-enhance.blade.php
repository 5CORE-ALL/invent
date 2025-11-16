@extends('layouts.vertical', ['title' => 'Quality Enhance'])

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
    .enhance-row .form-control {
        width: 100%;
    }
    .enhance-row .input-col {
        flex: 0 0 19%;
        max-width: 19%;
    }
    .enhance-row .delete-btn-col {
        flex: 0 0 5%;
        max-width: 5%;
    }
    .header-col {
        font-weight: 600;
        text-align: left;
    }

    #qualityEnhanceRows .col,
    #qualityEnhanceRows .col-auto {
        padding-right: 8px;
        padding-left: 8px;
    }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Quality Enhance', 'sub_title' => 'Quality Enhance'])

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
                    <h4 class="mb-0">Quality Enhance</h4>
                    <button id="add-new-row" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#qualityEnhance">
                        <i class="fas fa-plus-circle me-1"></i>Add Quality Enhance
                    </button>
                </div>
                <div id="quality-enhance"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qualityEnhance" tabindex="-1" aria-labelledby="qualityEnhanceLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="qualityEnhanceLabel">Add Quality Enhance</h5>
        <button type="button" class="btn-close bg-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <form id="qualityEnhanceForm" action="{{ route('quality.enhance.save') }}" method="POST">
          @csrf

            <div class="container">
            <!-- HEADER ROW (Labels aligned with inputs) -->
            <div class="row fw-semibold mb-2">
                <div class="col header-col">Parent</div>
                <div class="col header-col" >SKU</div>
                <div class="col header-col" >Issue</div>
                <div class="col header-col" >Action Req</div>
                <div class="col header-col" style="margin-right: 30px;">Status/Remark</div>
                <div class="col-auto"></div> <!-- Delete Button Column -->
            </div>

            <!-- INPUT ROWS -->
            <div id="qualityEnhanceRows">
                <div class="row mb-3 enhance-row align-items-end">
                    <div class="col">
                        <input type="text" class="form-control parent-input" readonly placeholder="Parent">
                    </div>
                    <div class="col">
                        <input type="text" name="sku[]" class="form-control" required placeholder="SKU">
                    </div>
                    <div class="col">
                        <input type="text" name="issue[]" class="form-control" required placeholder="Issue">
                    </div>
                    <div class="col">
                        <input type="text" name="action_req[]" class="form-control" required placeholder="Action Req">
                    </div>
                    <div class="col">
                        <input type="text" name="status_remark[]" class="form-control" required placeholder="Status/Remark">
                    </div>
                    <div class="col-auto d-flex align-items-end">
                        <button type="button" class="btn btn-danger remove-row-btn"><i class="fa fa-trash"></i></button>
                    </div>
                </div>
            </div>

            <!-- Add Row Button -->
            <div class="d-flex justify-content-between mt-2">
                <button type="button" id="addRowBtn" class="btn btn-success">Add Row</button>
                <button type="submit" form="qualityEnhanceForm" class="btn btn-primary">Submit</button>
            </div>

        </div>
        </form>
      </div>
    </div>
  </div>
</div>


@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const table = new Tabulator("#quality-enhance", {
            ajaxURL: "/quality-enhance/data",
            ajaxConfig: "GET",
            layout: "fitColumns",
            height: "500px",
            pagination: "remote", 
            paginationSize: 10,
            columns: [
                { title: "S.No", formatter: "rownum", hozAlign: "center", width: 80 },
                { title: "Parent", field: "parent", },
                { title: "SKU", field: "sku", },
                { title: "Issue", field: "issue", editor: "input" },
                { title: "Action Req", field: "action_req", editor: "input" },
                { title: "Status/Remark", field: "status_remark", editor: "input" },
                { 
                    title: "Created At", 
                    field: "created_at", 
                    formatter: function(cell) {
                        let dateStr = cell.getValue();
                        if (dateStr) {
                            return dateStr.split(' ')[0]; // Get only YYYY-MM-DD
                        }
                        return '';
                    }
                },
            ],
            
        });

        table.on("cellEdited", function(cell){
            let rowData = cell.getRow().getData();
            $.ajax({
                url: "{{ route('quality.enhance.update') }}",
                type: "POST",
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    id: rowData.id,  // Assuming 'id' is primary key
                    field: cell.getField(),
                    value: cell.getValue()
                },
                success: function(response) {
                    console.log("Updated Successfully", response);
                },
                error: function(xhr) {
                    alert('Update Failed');
                    cell.restoreOldValue(); // Revert if failed
                }
            });
        });

        const qualityEnhanceRows = document.getElementById('qualityEnhanceRows');
        const addRowBtn = document.getElementById('addRowBtn');

        addRowBtn.addEventListener('click', function() {
            const newRow = document.createElement('div');
            newRow.className = 'row mb-3 enhance-row align-items-end';
            newRow.innerHTML = `
                <div class="col">
                    <input type="text" class="form-control parent-input" readonly placeholder="Parent">
                </div>
                <div class="col">
                    <input type="text" name="sku[]" class="form-control" required placeholder="SKU">
                </div>
                <div class="col">
                    <input type="text" name="issue[]" class="form-control" required placeholder="Issue">
                </div>
                <div class="col">
                    <input type="text" name="action_req[]" class="form-control" required placeholder="Action Req">
                </div>
                <div class="col">
                    <input type="text" name="status_remark[]" class="form-control" required placeholder="Status/Remark">
                </div>
                <div class="col-auto d-flex align-items-end">
                    <button type="button" class="btn btn-danger remove-row-btn"><i class="fa fa-trash"></i></button>
                </div>
            `;
            qualityEnhanceRows.appendChild(newRow);
        });

        qualityEnhanceRows.addEventListener('click', function(event) {
            if (event.target.closest('.remove-row-btn')) {
                const allRows = document.querySelectorAll('#qualityEnhanceRows .enhance-row');
                if (allRows.length > 1) {
                    const rowToRemove = event.target.closest('.enhance-row');
                    qualityEnhanceRows.removeChild(rowToRemove);
                }
            }
        });

        document.addEventListener('input', function(e) {
            if (e.target.matches('input[name="sku[]"]')) {
                const $skuInput = e.target;
                const sku = $skuInput.value.trim();

                if (sku.length > 0) {
                    fetch("{{ route('quality.enhance.getParent') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ sku: sku })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const $parentInput = $skuInput.closest('.row').querySelector('.parent-input');
                            $parentInput.value = data.parent;
                        } else {
                            console.log('Parent not found for SKU:', sku);
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching parent:', err);
                    });
                }
            }
        });

});


</script>

@endsection