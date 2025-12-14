@extends('layouts.vertical', ['title' => 'Category Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
@endsection

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.shared.page-title', [
        'page_title' => 'Category Master',
        'sub_title' => 'Manage Categories',
    ])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="category-datatable" class="table dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Parent</th>
                                    <th>SKU</th>
                                    <th>Status</th>
                                    <th>INV</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamic rows will be added here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            $('#category-datatable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('category.data') }}',
                    type: 'GET',
                },
                columns: [
                    { data: 'image', name: 'image' },
                    { data: 'parent', name: 'parent' },
                    { data: 'sku', name: 'sku' },
                    { data: 'status', name: 'status' },
                    { data: 'inv', name: 'inv' },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                ],
            });
        });
    </script>
@endsection