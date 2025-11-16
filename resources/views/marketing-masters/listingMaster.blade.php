@extends('layouts.vertical', ['title' => 'Listing Audit Master'])

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <style>
        /* Essential styles only */
        .table-wrapper {
            position: relative;
            margin-top: 20px;
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .table thead {
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
        }

        .table thead th {
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #4361ee, #3f37c9);
            color: white;
            font-weight: 500;
            border-bottom: 0;
            z-index: 2;
        }

        .search-container {
            margin-bottom: 1rem;
        }

        .table-responsive {
            max-height: 600px;
            overflow-y: auto;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Listing Master</h4>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="row mb-3">
            <div class="col-auto">
                <a href="javascript:void(0);" id="exportExcelBtn" class="btn btn-primary">
                    <i class="mdi mdi-download me-1"></i> Export Data
                </a>
            </div>
            <div class="col">
                <input type="text" class="form-control" id="channelSearchInput" placeholder="Search Channel..."
                    style="max-width: 300px;">
            </div>
        </div>

        <!-- Table Section -->
        <div class="table-wrapper position-relative">
            <!-- Improved Loading Overlay -->
            <div id="tableLoader" class="position-absolute w-100 h-100 bg-white" style="display: none; z-index: 1000;">
                <div class="d-flex justify-content-center align-items-center h-100">
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div>Loading data...</div>
                    </div>
                </div>
            </div>

            <!-- DataTable Container -->
            <div id="tableContainer" class="table-responsive">
                <table class="table table-hover" id="channelTable">
                    <thead>
                        <tr>
                            <th>Sl No.</th>
                            <th>Channel</th>
                            {{-- <th>
                                REQ
                                <div id="req-total"
                                    style="display:inline-block; background:#41d833da; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px; margin-left:8px;">
                                    0
                                </div>
                            </th>
                            <th>
                                Listed
                                <div id="listed-total"
                                    style="display:inline-block; background:#41d833da; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px; margin-left:8px;">
                                    0
                                </div>
                            </th> --}}
                            <th>
                                Pending
                                <span id="pending-arrow" style="cursor:pointer;">⬍</span>
                                <div id="pending-total"
                                    style="display:inline-block; background:#f60920; color:white; border-radius:8px; padding:8px 18px; font-weight:600; font-size:15px; margin-left:8px;">
                                    0
                                </div>
                            </th>
                            <!-- <th>Action</th> -->
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <!-- Add jQuery before your custom script -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
        document.body.style.zoom = "80%";

        // $(document).ready(function() {
        //     let currentSort = 'desc';
        //     let tableData = [];
        //     $('#tableLoader').show();

        //     $.ajax({
        //         url: '/listing-master-data',
        //         type: 'GET',
        //         success: function(json) {
        //             $('#tableLoader').hide();
        //             $('#tableContainer').css('opacity', '1');
        //             let rows = '';
        //             let totalReq = 0;
        //             let totalListed = 0;
        //             let totalPending = 0;
        //             json.data.forEach(function(row, idx) {
        //                 if (typeof row === 'string') {
        //                     rows += `<tr>
        //                         <td>${idx + 1}</td>
        //                         <td><strong>${row}</strong></td>
        //                         <td>0</td>
        //                     </tr>`;
        //                 } else {
        //                     rows += `<tr>
        //                         <td>${idx + 1}</td>
        //                         <td><a href="${row.channel_url}" target="_blank"><strong>${row.Channel}</strong></a></td>
        //                         <td>${row.Pending ?? 0}</td>
                                
        //                     </tr>`;
        //                     // <td>
        //                     //     <button class="btn btn-sm btn-danger delete-btn" data-marketplace="${row.Channel}">Delete</button>
        //                     // </td>
        //                     totalReq += Number(row.REQ ?? 0);
        //                     totalListed += Number(row.Listed ?? 0);
        //                     totalPending += Number(row.Pending ?? 0);
        //                 }
        //             });
        //             $('#channelTable tbody').remove();
        //             $('#channelTable').append('<tbody>' + rows + '</tbody>');
        //             $('#req-total').text(totalReq);
        //             $('#listed-total').text(totalListed);
        //             $('#pending-total').text(totalPending);
        //         },
        //         error: function(xhr, error, thrown) {
        //             $('#tableLoader').hide();
        //             $('#tableContainer').css('opacity', '1');
        //             alert('Error loading data. Please try again.');
        //         }
        //     });

        //     $(document).on('click', '#pending-arrow', function() {
        //         console.log('ghgh');
                
        //         if (currentSort === 'desc') {
        //             tableData.sort((a, b) => (a.Pending ?? 0) - (b.Pending ?? 0));
        //             currentSort = 'asc';
        //             $(this).text('Pending ↑');
        //         } else {
        //             tableData.sort((a, b) => (b.Pending ?? 0) - (a.Pending ?? 0));
        //             currentSort = 'desc';
        //             $(this).text('Pending ↓');
        //         }
        //         renderTable(tableData);
        //     });

        //     $('#channelSearchInput').on('keyup', function() {
        //         const searchTerm = $(this).val().toLowerCase();
        //         $('#channelTable tbody tr').each(function() {
        //             const channel = $(this).find('td').eq(1).text().toLowerCase();
        //             if (channel.includes(searchTerm)) {
        //                 $(this).show();
        //             } else {
        //                 $(this).hide();
        //             }
        //         });
        //     });

        //     $('#exportExcelBtn').on('click', function() {
        //         // Get table data
        //         var wb = XLSX.utils.table_to_book(document.getElementById('channelTable'), {
        //             sheet: "Sheet1"
        //         });
        //         // Export to Excel file
        //         XLSX.writeFile(wb, 'listing_audit_master.xlsx');
        //     });

        //     // Delete marketplace
        //     $(document).on('click', '.delete-btn', function() {
        //         if (!confirm('Are you sure you want to delete this marketplace?')) return;

        //         let marketplace  = $(this).data('marketplace');

        //         $.ajax({
        //             url: '/listing-master/' + marketplace,
        //             type: 'DELETE',
        //             data: {
        //                 _token: '{{ csrf_token() }}'
        //             },
        //             success: function(res) {
        //                 if (res.status === 200) {
        //                     alert(res.message);
        //                     location.reload(); // reload table
        //                 } else {
        //                     alert(res.message || 'Delete failed');
        //                 }
        //             },
        //             error: function() {
        //                 alert('Error deleting marketplace. Please try again.');
        //             }
        //         });
        //     });

        // });

        $(document).ready(function () {
            let currentSort = 'desc';
            let tableData = [];
            $('#tableLoader').show();

            // function to render table (moved from inside AJAX success)
            function renderTable(data) {
                let rows = '';
                let totalReq = 0;
                let totalListed = 0;
                let totalPending = 0;

                data.forEach(function (row, idx) {
                    if (typeof row === 'string') {
                        rows += `<tr>
                            <td>${idx + 1}</td>
                            <td><strong>${row}</strong></td>
                            <td>0</td>
                        </tr>`;
                    } else {
                        rows += `<tr>
                            <td>${idx + 1}</td>
                            <td><a href="${row.channel_url}" target="_blank"><strong>${row.Channel}</strong></a></td>
                            <td>${row.Pending ?? 0}</td>
                        </tr>`;
                        totalReq += Number(row.REQ ?? 0);
                        totalListed += Number(row.Listed ?? 0);
                        totalPending += Number(row.Pending ?? 0);
                    }
                });

                $('#channelTable tbody').remove();
                $('#channelTable').append('<tbody>' + rows + '</tbody>');
                $('#req-total').text(totalReq);
                $('#listed-total').text(totalListed);
                $('#pending-total').text(totalPending);
            }

            // Fetch data
            $.ajax({
                url: '/listing-master-data',
                type: 'GET',
                success: function (json) {
                    $('#tableLoader').hide();
                    $('#tableContainer').css('opacity', '1');

                    tableData = json.data;

                    // default sort: Pending high → low
                    tableData.sort((a, b) => (b.Pending ?? 0) - (a.Pending ?? 0));

                    renderTable(tableData);
                },
                error: function () {
                    $('#tableLoader').hide();
                    $('#tableContainer').css('opacity', '1');
                    alert('Error loading data. Please try again.');
                }
            });

            // Sort on arrow click
            $(document).on('click', '#pending-arrow', function () {
                if (currentSort === 'desc') {
                    tableData.sort((a, b) => (a.Pending ?? 0) - (b.Pending ?? 0));
                    currentSort = 'asc';
                    $(this).text('Pending ↑');
                } else {
                    tableData.sort((a, b) => (b.Pending ?? 0) - (a.Pending ?? 0));
                    currentSort = 'desc';
                    $(this).text('Pending ↓');
                }
                renderTable(tableData);
            });

            // Search filter
            $('#channelSearchInput').on('keyup', function () {
                const searchTerm = $(this).val().toLowerCase();
                $('#channelTable tbody tr').each(function () {
                    const channel = $(this).find('td').eq(1).text().toLowerCase();
                    $(this).toggle(channel.includes(searchTerm));
                });
            });

            // Export to Excel
            $('#exportExcelBtn').on('click', function () {
                var wb = XLSX.utils.table_to_book(document.getElementById('channelTable'), {
                    sheet: "Sheet1"
                });
                XLSX.writeFile(wb, 'listing_audit_master.xlsx');
            });

            // Delete marketplace
            $(document).on('click', '.delete-btn', function () {
                if (!confirm('Are you sure you want to delete this marketplace?')) return;

                let marketplace = $(this).data('marketplace');

                $.ajax({
                    url: '/listing-master/' + marketplace,
                    type: 'DELETE',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (res) {
                        if (res.status === 200) {
                            alert(res.message);
                            location.reload(); // reload table
                        } else {
                            alert(res.message || 'Delete failed');
                        }
                    },
                    error: function () {
                        alert('Error deleting marketplace. Please try again.');
                    }
                });
            });
        });

    </script>
@endsection
