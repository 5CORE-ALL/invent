@extends('layouts.vertical', ['title' => 'Setup Account & Shop', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
<style>
  .tabulator .tabulator-header {
    background: linear-gradient(90deg, #e0e7ff 0%, #f4f7fa 100%);
    border-bottom: 2px solid #2563eb;
    box-shadow: 0 4px 16px rgba(37, 99, 235, 0.10);
  }

  .tabulator .tabulator-header .tabulator-col {
    text-align: center;
    background: transparent;
    border-right: 1px solid #e5e7eb;
    padding: 16px 10px;
    font-weight: 700;
    color: #1e293b;
    font-size: 1.08rem;
    letter-spacing: 0.02em;
    transition: background 0.2s;
  }

  .tabulator .tabulator-header .tabulator-col:hover {
    background: #e0eaff;
    color: #2563eb;
  }

  .tabulator-row {
    background-color: #fff !important;
    transition: background 0.18s;
  }

  .tabulator-row:nth-child(even) {
    background-color: #f8fafc !important;
  }

  .tabulator .tabulator-cell {
    text-align: center;
    padding: 14px 10px;
    border-right: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    font-size: 1rem;
    color: #22223b;
    vertical-align: middle;
    transition: background 0.18s, color 0.18s;
  }

  .tabulator .tabulator-cell:focus {
    outline: 2px solid #2563eb;
    background: #e0eaff;
  }

  .tabulator-row:hover {
    background-color: #dbeafe !important;
  }

  .parent-row {
    background-color: #e0eaff !important;
    font-weight: 700;
  }

  #account-health-master .tabulator {
    border-radius: 18px;
    box-shadow: 0 6px 24px rgba(37, 99, 235, 0.13);
    overflow: hidden;
    border: 1px solid #e5e7eb;
  }

  .tabulator .tabulator-row .tabulator-cell:last-child,
  .tabulator .tabulator-header .tabulator-col:last-child {
    border-right: none;
  }

  .tabulator .tabulator-footer {
    background: #f4f7fa;
    border-top: 1px solid #e5e7eb;
    font-size: 1rem;
    color: #4b5563;
    padding: 5px;
    height: 100px;
  }

  .tabulator .tabulator-footer:hover {
    background: #e0eaff;
  }

  @media (max-width: 768px) {

    .tabulator .tabulator-header .tabulator-col,
    .tabulator .tabulator-cell {
      padding: 8px 2px;
      font-size: 0.95rem;
    }
  }

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

  .custom-select-wrapper {
    width: 100%;
    cursor: pointer;
    position: relative;
  }

  .custom-select-display {
    background-color: #fff;
    border: 1px solid #ced4da;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
  }

  .custom-select-options {
    position: absolute;
    z-index: 999;
    top: 100%;
    left: 0;
    right: 0;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ced4da;
    border-top: none;
    background-color: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  }

  .custom-select-search {
    width: 100%;
    padding: 0.5rem;
    border: none;
    border-bottom: 1px solid #eee;
    outline: none;
  }

  .custom-select-option {
    padding: 0.5rem 0.75rem;
    cursor: pointer;
  }

  .custom-select-option:hover {
    background-color: #f1f1f1;
  }
</style>
@endsection
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Setup Account & Shop', 'sub_title' => 'Setup Account & Shop'])
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert"
  style="background-color: #d1e7dd; color: #0f5132;">
  <style>
    .alert-success .btn-close {
      filter: invert(41%) sepia(94%) saturate(362%) hue-rotate(89deg) brightness(90%) contrast(92%);
    }
  </style>
  <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-body">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
          <h4 class="mb-0 fw-bold text-primary">Setup Account & Shop</h4>
          
        </div>
        <div id="setup-account-master"></div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script>
  document.addEventListener("DOMContentLoaded", function () {
        const table = new Tabulator("#setup-account-master", {
            ajaxURL: '/setup-account/fetch-data',
            ajaxConfig: "GET",
            layout: "fitColumns",
            pagination: "local",
            paginationSize: 25,
            height: "700px",
            columns: [
                  {
                      title: "Type",
                      field: "type",
                      headerSort: false,
                      formatter: function (cell) {
                          const row = cell.getRow();
                          const value = cell.getValue() ?? '';
                          const id = row.getData().setupAccountId;

                          return `
                              <select class="form-select form-select-sm editable-select"
                                  data-id="${id}"
                                  data-type="type"
                                  style="width: 120px;">
                                  <option value="">Select</option>
                                  <option value="Dropship" ${value === 'Dropship' ? 'selected' : ''}>Dropship</option>
                                  <option value="B2B" ${value === 'B2B' ? 'selected' : ''}>B2B</option>
                                  <option value="B2C" ${value === 'B2C' ? 'selected' : ''}>B2C</option>
                                  <option value="C2C" ${value === 'C2C' ? 'selected' : ''}>C2C</option>
                                  <option value="Promotional" ${value === 'Promotional' ? 'selected' : ''}>Promotional</option>
                                  <option value="Coupons" ${value === 'Coupons' ? 'selected' : ''}>Coupons</option>
                              </select>
                          `;
                      },
                      hozAlign: "center"
                  },
                  { title: "Channel Name", field: "channel_name" },
                  { 
                    title: "Status", 
                    field: "status",
                    headerSort: false,
                    formatter: function (cell) {
                        const row = cell.getRow();
                        const value = cell.getValue() ?? '';
                        const id = row.getData().setupAccountId;

                        return `
                            <select class="form-select form-select-sm editable-select"
                                data-id="${id}"
                                data-type="status"
                                style="width: 120px;">
                                <option value="">Select</option>
                                <option value="setup" ${value === 'setup' ? 'selected' : ''}>Setup</option>
                                <option value="active" ${value === 'active' ? 'selected' : ''}>Active</option>
                            </select>
                        `;
                    },
                    hozAlign: "center"
                  },
                  { 
                    title: "Login Link", 
                    field: "login_link", 
                    editor:"input", 
                    formatter: function(cell) {
                        const value = cell.getValue();
                        if (!value) return '';
                        return `
                            <a href="${value}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                        `;
                    },
                  }, 
                  { title: "Email/User ID", field: "email_userid", editor:"input", },
                  { title: "Password", field: "password", editor:"input", },
                  { title: "Remarks", field: "remarks", editor:"input", },
                  { field: "setupAccountId", visible: false },

            ],
        });
        table.on("cellEdited", function(cell) {
          const rowData = cell.getRow().getData();
          const field = cell.getField();
          const value = cell.getValue();

          $.ajax({
              url: '/setup-account-channel-master/save', 
              type: 'POST',
              data: {
                  _token: $('meta[name="csrf-token"]').attr('content'),
                  id: rowData.setupAccountId,
                  field: field,
                  value: value
              },
              success: function(response) {
                  if (response.success) {
                      console.log("sucess");
                  } else {
                      console.log("failed");
                  }
              },
              error: function(xhr) {
                  console.error(xhr.responseText);
              }
          });
      });

      $(document).on('change', '.editable-select', function() {
          const $select = $(this);
          const id = $(this).data('id');
          const value = $(this).val();
          const field = $select.data('type');

          $.ajax({
              url: '/setup-account-channel-master/save',
              type: 'POST',
              data: {
                  _token: $('meta[name="csrf-token"]').attr('content'),
                  id: id,
                  field: field,
                  value: value
              },
              success: function(response) {
                  if (response.success) {
                      console.log("success");
                      
                  } else {
                      console.log("failed");
                  }
              },
              error: function(xhr) {
                  console.error(xhr.responseText);
              }
          });
      });


    });

    </script>
@endsection