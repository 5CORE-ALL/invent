@extends('layouts.vertical', ['title' => 'Transit Container Changes'])
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
    color: #000000;
    font-weight: 600;
    vertical-align: middle;
    max-width: 300px;
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
    .nav-tabs {
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
    white-space: nowrap;
    scrollbar-width: thin; /* Firefox */
  }

  .nav-tabs .nav-item {
    flex-shrink: 0;
  }

  /* Optional: customize scrollbar */
  .nav-tabs::-webkit-scrollbar {
    height: 6px;
  }

  .nav-tabs::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 5px;
  }

  .nav-tabs::-webkit-scrollbar-track {
    background: transparent;
  }
</style>
@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Transit Container Changes', 'sub_title' => 'Transit Container Changes'])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-2">
                  <h5 class="mb-0">Transit Container Changes</h5>
                </div>
                <div style="overflow-x: auto; overflow-y: hidden; scrollbar-width: none; -ms-overflow-style: none;">
                    <style>
                      div[style*="overflow-x: auto"]::-webkit-scrollbar {
                        display: none;
                      }
                    </style>
                    <ul class="nav nav-tabs flex-nowrap d-flex mb-0" id="tabList" role="tablist" style="min-width: max-content;">
                        @foreach($tabs as $index => $tab)
                            <li class="nav-item" style="flex-shrink: 0;">
                                <button class="nav-link {{ $index == 0 ? 'active' : '' }}" id="tab-{{ $index }}-tab" data-bs-toggle="tab" data-bs-target="#tab-{{ $index }}" type="button" role="tab">
                                    {{ $tab }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="tab-content mt-3" id="tabContent">
                    @foreach($groupedData as $tabName => $items)
                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $loop->index }}" role="tabpanel">
                            <div id="tabulator-{{ $loop->index }}" class="tabulator-table"></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
  let tabCounter = {{ count($tabs) }};
const groupedData = @json($groupedData);
  Object.entries(groupedData).forEach(([tabName, data], index) => {
    let table = new Tabulator(`#tabulator-${index}`, {
        layout: "fitColumns",
        data: data,
        pagination: "local",
        paginationSize: 10,
        height: "700px",
        rowHeight: 55,
        columns: [
            {
              title: "Sl No.",
              formatter: function(cell) {
                  return cell.getRow().getPosition(true) + 0;
              },
              hozAlign: "center",
              headerSort: false
            },
            {
              title: "Images",
              field: "photos",
              formatter: function(cell) {
                const row = cell.getRow().getData();
                let url = cell.getValue();
                
                if (!url && row.image_src) {
                  url = row.image_src;
                }

                if (!url && row.Values) {
                  try {
                    const values = typeof row.Values === "string" ? JSON.parse(row.Values) : row.Values;
                    if (values.image_path) {
                      url = "/storage/" + values.image_path.replace(/^storage\//, "");
                    }
                  } catch (err) {
                    console.error("JSON parse error:", err);
                  }
                }

                return url
                  ? `<a href="${url}" target="_blank"><img src="${url}" style="height:40px;border-radius:4px;border:1px solid #ccc"/></a>`
                  : '<span class="text-muted">No Image</span>';
              }
            },
            { title: "Parent", field: "parent"},
            { title: "Our Sku", field: "our_sku", editor: "input" },
            { title: "Supplier Name", field: "supplier_name", editor: "input"},
            { 
              title: "Total QTY", 
              field: "pcs_qty", 
              editor: false,
              formatter: function(cell) {
                  const data = cell.getRow().getData();
                  const units = parseFloat(data.no_of_units) || 0;
                  const ctn = parseFloat(data.total_ctn) || 0;
                  return units * ctn;
              }
            },
            { title: "Changes", field: "changes", editor: "input" },
        ],
    });

    if (data.length === 0) {
        table.addRow({ tab_name: tabName });
    }

    table.on("cellEdited", function(cell) {
        const row = cell.getRow();
        const data = row.getData();
        data.tab_name = tabName;
        const field = cell.getField();
        
        fetch('/transit-container/save-row', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
            if (response.success || response.id) {
                console.log("Row saved successfully:", response);
                if (response.id) {
                    row.update({ id: response.id }); 
                }

                const allRows = table.getRows();
                if (row === allRows[allRows.length - 1]) {
                    const field = cell.getField();
                    const value = cell.getValue();
                    const isNotEmpty = value !== null && value !== "" && value !== 0;

                    if (isNotEmpty) {
                        const newRowData = { tab_name: tabName };
                        table.addRow(newRowData).then((newRow) => {
                            fetch('/transit-container/save-row', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(newRowData)
                            })
                            .then(res => res.json())
                            .then(newRes => {
                                if (newRes.id) {
                                    newRow.update({ id: newRes.id });
                                    console.log("New row added with ID:", newRes.id);
                                }
                            })
                            .catch(err => {
                                console.error("Error saving new row:", err);
                            });
                        });
                    }
                }

            } else {
                alert(response.message || "Update failed");
            }
        })
        .catch(err => {
            console.error("Save error:", err);
            alert("Something went wrong while saving");
        });
    });

    window.tabTables = window.tabTables || {};
    window.tabTables[index] = table;

});

</script>

@endsection