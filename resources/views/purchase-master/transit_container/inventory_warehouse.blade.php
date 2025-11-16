@extends('layouts.vertical', ['title' => 'Inventory Warehouse'])
@section('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
{{-- <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet"> --}}
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
    font-weight: 500;
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

    td.spec-cell {
        max-width: 300px;
        overflow-x: auto;
        white-space: nowrap;
        display: block;
    }

</style>

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Inventory Warehouse</h5>
            <a href="{{ url('/transit-container-details') }}" class="btn btn-sm btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Containers
            </a>
        </div>
        <div class="card-body">
            
            <!-- Table -->
            <div class="table-responsive">
                <table id="inventoryTable" class="table table-bordered table-striped align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Sl No.</th>
                            <th>Tab Name</th>
                            <th>Parent</th>
                            <th>SKU</th>
                            <th>Supplier</th>
                            <th>Qty/Ctns</th>
                            <th>Qty Ctns</th>
                            <th>Qty</th>
                            <th>Rate ($)</th>
                            <th>Amount ($)</th>
                            <th>Unit</th>
                            <th>Changes</th>
                            <th>Specification</th>
                            {{-- <th>Pushed At</th> --}}
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($warehouses as $index => $row)
                            @php
                                $no_of_units = (float) ($row->no_of_units ?? 0);
                                $total_ctn   = (float) ($row->total_ctn ?? 0);
                                $rate        = (float) ($row->rate ?? 0);

                                $qty    = $no_of_units * $total_ctn;
                                $amount = $qty * $rate;
                            @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $row->tab_name ?? '—' }}</td>
                            <td>{{ $row->parent ?? '—' }}</td>
                            <td>{{ $row->our_sku ?? '—' }}</td>
                            <td>{{ $row->supplier_name ?? '—' }}</td>
                            <td>{{ $row->no_of_units }}</td>
                            <td>{{ $row->total_ctn }}</td>
                            <td>{{ $qty }}</td>
                            <td>{{ number_format($row->rate, 2) }}</td>
                            <td>{{ number_format($amount) }}</td>
                            <td><span class="badge bg-info text-dark">{{ ucfirst($row->unit) }}</span></td>
                            <td>{{ $row->changes ?? '—' }}</td>
                            {{-- <td>{{ $row->specification ?? '—' }}</td> --}}
                            <td style="max-width: 300px; overflow-x: auto; white-space: nowrap; display: block;">
                                {{ $row->specification ?? '—' }}
                            </td>
                            {{-- <td>{{ $row->created_at->format('Y-m-d H:i') }}</td> --}}
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- DataTables -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>


<script>
$(document).ready(function() {
    // $('#inventoryTable').DataTable({
    //     pageLength: 25,
    //     lengthMenu: [10, 25, 50, 100],  
    //     order: [[ 0, "asc" ]],
    //     paging: true,                     // enable pagination
    //     searching: true,                  // enable search box
    //     info: true
    // });
    $('#inventoryTable').DataTable({
        pageLength: 25,                   // default rows per page
        lengthMenu: [10, 25, 50, 100],    // dropdown options
        paging: true,                     // enable pagination
        searching: true,                  // enable search box
        ordering: true,                   // enable sorting
        info: true,                       // show "Showing x of y entries"
        order: [[0, "asc"]],              // sort by Sl No.
        responsive: true
    });
   
});
</script>
@endpush
