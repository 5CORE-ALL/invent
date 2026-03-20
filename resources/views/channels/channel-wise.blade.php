@extends('layouts.vertical', ['title' => 'Channel Wise', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .w-25 {
            width: 10% !important;
            margin-left: 20px;
        }
    </style>
@endsection

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <title>Add Product</title> -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Menu', 'page_title' => 'Channel Wise'])
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="responsive-table-plugin">
                        <div class="d-flex justify-content-end mb-2">
                            <input type="text" id="searchInput" class="form-control w-25" placeholder="Search...">
                            {{-- <button class="btn btn-success" id="downloadExcel" style="margin-left:10px;">
                                <i class="fas fa-file-excel"></i>
                            </button> --}}
                        </div>
                        <div class="table-rep-plugin">
                            <!-- Bulk Delete Button (for selected checkboxes) -->
                            {{-- <div class="d-flex justify-content-end mb-2">
                                <button class="btn btn-danger" data-bs-toggle="modal" id="bulkDelete"
                                    style="display: none;">Delete
                                    Selected</button>
                            </div> --}}
                            <div class="table-responsive" data-pattern="priority-columns">
                                <table id="tech-companies-1" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAll"></th>
                                            <th>ID</th>
                                            <th class="channelColumn" data-priority="1">Channel</th>
                                            <th class="linkColumn" data-priority="3">Link</th>
                                            <th class="statusColumn" data-priority="1">Status</th>
                                            <th class="executiveColumn" data-priority="1">Exec</th>
                                            <th class="l60SalesColumn" data-priority="3">L-60 Sales</th>
                                            <th class="l30SalesColumn" data-priority="3">L30 Sales</th>
                                            <th class="growthColumn" data-priority="3">Growth</th>
                                            <th class="l60OrdersColumn" data-priority="3">L60 Orders</th>
                                            <th class="l30OrdersColumn" data-priority="3">L30 Orders</th>
                                            <th class="gprofitColumn" data-priority="3">Gprofit%</th>
                                            <th class="groiColumn" data-priority="3">G Roi%</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody> @php
                                        $channelSheetsArray = $channelSheets->items(); // Convert Paginator to array
                                        $filteredData = array_slice($channelSheetsArray, 1); // Skip the first entry
                                    @endphp

                                        @foreach ($filteredData as $index => $channelSheet)
                                            <tr>
                                                <td><input type="checkbox" class="channelCheckbox" value=""></td>
                                                <td class="idColumn"></td>
                                                <td class="channelColumn">{{ $channelSheet['Channel'] ?? '-' }}</td>
                                                <td class="linkColumn">
                                                    @if ($channelSheet['URL LINK'])
                                                        <a href="{{ $channelSheet['URL LINK'] }}" target="_blank"
                                                            class="text-decoration-none">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                    @else
                                                        N/A
                                                    @endif
                                                </td>
                                                <td class="statusColumn"></td>
                                                <td class="executiveColumn">{{ $channelSheet['Exec'] ?? '-' }}</td>
                                                <td class="l60SalesColumn">{{ $channelSheet['L-60 Sales'] ?? '-' }}</td>
                                                <td class="l30SalesColumn">{{ $channelSheet['L30 Sales'] ?? '-' }}</td>
                                                <!-- <td>{{ $channelSheet['Growth'] ?? '-' }}</td> -->
                                                <td class="growthColumn">
                                                    @if (isset($channelSheet['Growth']) && is_string($channelSheet['Growth']))
                                                        {{ $channelSheet['Growth'] }}
                                                    @else
                                                        @php
                                                            $l60Sales = (float) ($channelSheet['L-60 Sales'] ?? 0);
                                                            $l30Sales = (float) ($channelSheet['L30 Sales'] ?? 0);
                                                        @endphp

                                                        @if ($l30Sales != 0)
                                                            {{ number_format((($l30Sales - $l60Sales) / $l30Sales) * 100, 2) }}%
                                                        @else
                                                            N/A
                                                        @endif
                                                    @endif
                                                </td>
                                                <td class="l60OrdersColumn">{{ $channelSheet['L60 Orders'] ?? '-' }}</td>
                                                <td class="l30OrdersColumn">{{ $channelSheet['L30 Orders'] ?? '-' }}</td>
                                                <td class="gprofitColumn">{{ $channelSheet['Gprofit%'] ?? '-' }}</td>
                                                <td class="groiColumn">{{ $channelSheet['G Roi%'] ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <!-- Pagination Links -->
                                <div class="justify-content-end mt-3">
                                    {{ $channelSheets->links('pagination::bootstrap-4') }}
                                </div>

                            </div> <!-- end .table-responsive -->

                        </div> <!-- end .table-rep-plugin-->
                    </div> <!-- end .responsive-table-plugin-->
                </div>
            </div> <!-- end card -->
        </div> <!-- end col -->
    </div>
    <!-- Add Channel Modal -->

    <!-- edit Channel Modal -->
    <div class="modal fade" id="editChannelModal" tabindex="-1" aria-labelledby="editChannelModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title" id="editChannelModalLabel">Edit Channel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editChannelForm">
                        @csrf
                        <input type="hidden" id="edit_channel_id">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Channel</label>
                                <input type="text" class="form-control" id="edit_channel" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-control" id="edit_channel_status">
                                    <option value="Active">Active</option>
                                    <option value="In Active">Inactive</option>
                                    <option value="To Onboard">To Onboard</option>
                                    <option value="In Progress">In Progress</option>
                                </select>
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="form-label fw-bold">Executive</label>
                                <input type="text" class="form-control" id="edit_executive">
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="form-label fw-bold">B Link</label>
                                <input type="text" class="form-control" id="edit_b_link" required>
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="form-label fw-bold">S Link</label>
                                <input type="text" class="form-control" id="edit_s_link">
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="form-label fw-bold">User ID</label>
                                <input type="text" class="form-control" id="edit_uid" required>
                            </div>
                            <div class="col-md-6 mt-3">
                                <label class="form-label fw-bold">Action Req</label>
                                <input type="text" class="form-control" id="edit_action_req">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-warning" id="updateChannel">Update</button>
                </div>
            </div>
        </div>
    </div>
@endsection

<!-- Update Add Button to Trigger Modal -->
@section('script')
    <!-- Load jQuery First -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Then Load DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    @vite(['resources/js/pages/responsive-table.init.js'])

    <script>
        $(document).ready(function() {
            // Identify the current page URL to determine which settings to use
            let pageKey = window.location.href.includes("channel-wise") ? "channelWise" : "";

            // Load stored selections from localStorage
            loadSelectedItems(pageKey);

            // Listen for changes in the checkboxes inside the dropdown menu
            $(".dropdown-menu input[type='checkbox']").change(function() {
                updateDropdownDisplay(pageKey);
            });

            $("#displayAllBtn").click(function() {
                // Update the display of the dropdown based on the selection
                updateDropdownDisplay(pageKey);
            });

            function updateDropdownDisplay(pageKey) {
                let selectedItems = [];
                $(".dropdown-menu input[type='checkbox']:checked").each(function() {
                    let labelText = $(this).next("label").text().trim();
                    let extractedText = labelText.replace(/:: before\s*|\s*:: after/g, '').trim();

                    selectedItems.push(extractedText);
                });

                // Save selected checkboxes in localStorage under the page-specific key
                localStorage.setItem(`${pageKey}_selectedItems`, JSON.stringify(selectedItems));

                // Toggle table visibility based on selection
                toggleTableVisibility(selectedItems);

                console.log("Selected Items:", selectedItems);
            }

            function loadSelectedItems(pageKey) {
                let storedItems = localStorage.getItem(`${pageKey}_selectedItems`);
                let selectedItems = storedItems ? JSON.parse(storedItems) : [];

                // Uncheck all checkboxes first
                $(".dropdown-menu input[type='checkbox']").prop("checked", false);

                // Check stored items
                $(".dropdown-menu input[type='checkbox']").each(function() {
                    let labelText = $(this).next("label").text().trim();
                    let extractedText = labelText.replace(/:: before\s*|\s*:: after/g, '').trim();

                    if (selectedItems.includes(extractedText)) {
                        $(this).prop("checked", true);
                    }
                });

                // Ensure table is displayed correctly
                toggleTableVisibility(selectedItems);
            }

            function toggleTableVisibility(selectedItems) {
                let columnMapping = {
                    "Channel": ".channelColumn",
                    "Link": ".linkColumn",
                    "Status": ".statusColumn",
                    "Exec": ".executiveColumn",
                    "L-60 Sales": ".l60SalesColumn",
                    "L30 Sales": ".l30SalesColumn",
                    "Growth": ".growthColumn",
                    "L60 Orders": ".l60OrdersColumn",
                    "L30 Orders": ".l30OrdersColumn",
                    "Gprofit": ".gprofitColumn",
                    "G Roi%": ".groiColumn"
                };

                // Loop through each column and hide/show based on selection
                $.each(columnMapping, function(key, value) {
                    if (selectedItems.includes(key)) {
                        $(value).show(); // Show both <th> and <td>
                    } else {
                        $(value).hide(); // Hide both <th> and <td>
                    }
                });
            }

            // Open Edit Modal and Populate Fields
            $(".editChannelBtn").on("click", function() {
                $("#edit_channel_id").val($(this).data("id"));
                $("#edit_channel").val($(this).data("channel"));
                $("#edit_channel_status").val($(this).data("status"));
                $("#edit_executive").val($(this).data("executive"));
                $("#edit_b_link").val($(this).data("b_link"));
                $("#edit_s_link").val($(this).data("s_link"));
                $("#edit_uid").val($(this).data("user_id"));
                $("#edit_action_req").val($(this).data("action_req"));
                $("#editChannelModal").modal("show");
            });

            // Update Channel via AJAX
            $("#updateChannel").on("click", function() {
                let channelId = $("#edit_channel_id").val();
                let formData = {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    channel: $("#edit_channel").val(),
                    status: $("#edit_channel_status").val(),
                    executive: $("#edit_executive").val(),
                    b_link: $("#edit_b_link").val(),
                    s_link: $("#edit_s_link").val(),
                    user_id: $("#edit_uid").val(),
                    action_req: $("#edit_action_req").val(),
                };

                $.ajax({
                    url: `/channel_master/update/${channelId}`,
                    type: "PUT",
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $("#editChannelModal").modal("hide");
                            location.reload();
                        } else {
                            alert("Error: " + response.message);
                        }
                    },
                    error: function() {
                        alert("Something went wrong.");
                    }
                });
            });
        });
    </script>

    {{-- pagination section --}}
    <script>
        document.getElementById("searchInput").addEventListener("keyup", function() {
            let value = this.value.toLowerCase();
            document.querySelectorAll("#tech-companies-1 tbody tr").forEach(function(row) {
                row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
            });
        });

        $(document).ready(function() {
            $('#tech-companies-1').DataTable({
                "paging": true, // Enable pagination
                "searching": true, // Enable search
                "ordering": true, // Enable sorting
                "lengthMenu": [10, 25, 50, 100] // Items per page options
            });
        });
    </script>
    <script>
        $(document).ready(function() {
            // Hide bulk delete button initially
            $("#bulkDelete").hide();

            // ✅ Select All Checkbox Functionality
            $("#selectAll").on("change", function() {
                $(".channelCheckbox").prop("checked", $(this).prop("checked"));
                toggleBulkDeleteButton();
            });

            $(".channelCheckbox").on("change", function() {
                $("#selectAll").prop("checked", $(".channelCheckbox:checked").length === $(
                    ".channelCheckbox").length);
                toggleBulkDeleteButton();
            });

            // Function to Show/Hide Bulk Delete Button
            function toggleBulkDeleteButton() {
                if ($(".channelCheckbox:checked").length > 1) {
                    $("#bulkDelete").show();
                } else {
                    $("#bulkDelete").hide();
                }
            }


            // Bulk Delete
            $("#bulkDelete").on("click", function() {
                let selectedIds = [];

                $(".channelCheckbox:checked").each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    alert("Please select at least one product to delete.");
                    return;
                }

                if (confirm(`Are you sure you want to delete ${selectedIds.length} product(s)?`)) {
                    $.ajax({
                        url: "/product_master/delete",
                        type: "DELETE",
                        data: {
                            ids: selectedIds
                        },
                        headers: {
                            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
                        },
                        success: function(response) {
                            alert(response.message);
                            if (response.success) {
                                location.reload();
                            }
                        },
                        error: function(xhr) {
                            alert("Error deleting products. Please try again.");
                        }
                    });
                }
            });

            // ✅ Form Validation & Submission
            function checkFormValidity() {
                let isValid = $("#addChannelForm").find("[required]").toArray().every(input => $(input).val()
                    .trim() !== "");
                $("#saveChannel").prop("disabled", !isValid);
            }

            $("#addChannelForm").on("input change", "input, select, textarea", checkFormValidity);
            $("#saveChannel").prop("disabled", true);

            // ✅ AJAX Submission
            $("#saveChannel").on("click", function(e) {
                e.preventDefault(); // Prevent default form submission

                let formData = new FormData($("#addChannelForm")[0]);

                $.ajax({
                    url: "{{ route('channel_master.store') }}",
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            $("#addChannelForm")[0].reset();
                            $("#addChannelModal").modal("hide");
                            location.reload();
                        } else {
                            alert("Error: " + response.message);
                        }
                    },
                    error: function() {
                        alert("Something went wrong.");
                    }
                });
            });

        });
    </script>
@endsection
