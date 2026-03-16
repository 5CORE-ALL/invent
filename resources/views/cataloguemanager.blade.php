@extends('layouts.vertical', ['title' => 'Catalogue Manager', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <style>
        .w-25 {
            width: 10% !important;
            margin-left: 20px;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Menu', 'page_title' => 'Catalogue Manager'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="responsive-table-plugin">
                        <div class="d-flex align-items-center gap-3">
                            <span class="badge bg-info fs-5 p-2 fw-bold">Total SKU : <span id="skuCount" style="color:black;"> {{ $totalSKUs }}</span></span>
                            <span class="badge bg-primary fs-5 p-2">Total AMZ FBM LIST: <span id="fbmListCount">{{ $totalFbmList }}</span></span>
                            <span class="badge bg-warning fs-5 p-2">Total AMZ FBM NR: <span id="fbmNrCount">{{ $totalFbmNr }}</span></span>
                        </div>

                        <div class="d-flex justify-content-end mb-2">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">Add</button>
                            <input type="text" id="searchInput" class="form-control w-25" placeholder="Search...">
                            <button class="btn btn-success" id="downloadExcel" style="margin-left:10px;">
                                <i class="fas fa-file-excel"></i>
                            </button>
                        </div>

                        <div class="table-rep-plugin">
                            <div class="table-responsive" data-pattern="priority-columns">
                                <table id="tech-companies-1" class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAll"></th>
                                            <th>ID</th>
                                            <th class="imageColumn" data-priority="1">Image</th>
                                            <th class="parentColumn" class="sortable" data-priority="1">Parent</th> <!-- Sorting Applied -->
                                            <th class="skuColumn" class="sortable" data-priority="1">SKU</th>
                                            <th class="unitColumn" data-priority="3">Unit</th>
                                            <th class="amzFbmNrColumn" data-priority="3">AMZ FBM NR</th>
                                            <th class="amzFbmListColumn" data-priority="3">AMZ FBM LIST</th>
                                            <th class="titleColumn" data-priority="3">Title</th>
                                            <th class="bulletColumn" data-priority="6">Bullet</th>
                                            <th class="aPlusColumn" data-priority="6">A+</th>
                                            <th class="descColumn" data-priority="6">Desc</th>
                                            <th class="video1Column" data-priority="6">Video 1</th>
                                            <th class="video2Column" data-priority="6">Video 2</th>
                                            <th class="percentageColumn" data-priority="6">Percentage</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($products as $product)
                                            <tr>
                                                <td><input type="checkbox" class="productCheckbox" value="{{ $product->id }}"></td>
                                                <td>{{ $product->id }}</td>
                                                <td class="imageColumn account-user-avatar"><img src="{{ asset($product->image) }}" alt="Product" width="60" class="rounded-circle" /></td>
                                                <td class="parentColumn">{{ $product->parent }}</td> <!-- Parent Column -->
                                                <td class="skuColumn">{{ $product->sku }}</td>
                                                <td class="unitColumn">{{ $product->unit }}</td>
                                                <td class="amzFbmNrColumn"><input type="checkbox"></td>
                                                <td class="amzFbmListColumn"><input type="checkbox"></td>
                                                <td class="titleColumn"><input type="checkbox"></td>
                                                <td class="bulletColumn"><input type="checkbox"></td>
                                                <td class="aPlusColumn"><input type="checkbox"></td>
                                                <td class="descColumn"><input type="checkbox"></td>
                                                <td class="video1Column"><input type="checkbox"></td>
                                                <td class="video2Column"><input type="checkbox"></td>
                                                <td class="percentage percentageColumn">0%</td>
                                                <td><button class="btn btn-success">Update</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <div class="justify-content-end mt-3">
                                    {{ $products->links('pagination::bootstrap-4') }}
                                </div>
                            </div> <!-- end .table-responsive -->
                        </div> <!-- end .table-rep-plugin -->
                    </div> <!-- end .responsive-table-plugin -->
                </div>
            </div> <!-- end card -->
        </div> <!-- end col -->
    </div>
@endsection

@section('script')
    <!-- Load jQuery First -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Then Load DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

    @vite(['resources/js/pages/responsive-table.init.js'])

    <!-- Sorting and Reset Filter Script -->
    <script>
        $(document).ready(function () {
             //Dispaly list save to cash storage

            
            // Identify the current page URL to determine which settings to use
            let pageKey = window.location.href.includes("catalogue/cataloguemanager") ? "catalogueManager" : ""; 
            // Load stored selections from localStorage
            loadSelectedItems();

            // Listen for changes in the checkboxes inside the dropdown menu
            $(".dropdown-menu input[type='checkbox']").change(function() {
                updateDropdownDisplay(pageKey);
            });

            $("#displayAllBtn").click(function() {
                // Update the display of the dropdown based on the selection
                updateDropdownDisplay(pageKey);
            });

            function updateDropdownDisplay() {
                let selectedItems = [];
                $(".dropdown-menu input[type='checkbox']:checked").each(function() {
                    let labelText = $(this).next("label").text().trim();
                    let extractedText = labelText.replace(/:: before\s*|\s*:: after/g, '').trim();

                    selectedItems.push(extractedText);
                });

                // Save selected checkboxes in localStorage
                localStorage.setItem(`${pageKey}_selectedItems`, JSON.stringify(selectedItems));

                // Toggle table visibility based on selection
                toggleTableVisibility(selectedItems);

                console.log("Selected Items:", selectedItems);
            }

            function loadSelectedItems() {
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
                    "Image": ".imageColumn",
                    "Parent": ".parentColumn",
                    "SKU": ".skuColumn",
                    "Unit": ".unitColumn",
                    "AMZ FBM NR": ".amzFbmNrColumn",
                    "AMZ FBM LIST": ".amzFbmListColumn",
                    "Title": ".titleColumn",
                    "Bullet": ".bulletColumn",
                    "A+": ".aPlusColumn",
                    "Desc": ".descColumn",
                    "Video 1": ".video1Column",
                    "Video 2": ".video2Column",
                    "Percentage": ".percentageColumn"
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
            //end cash storage script 


            let sortDirection = 1; // 1 for A-Z, -1 for Z-A

            // Sorting on click
            $("#tech-companies-1 thead th.sortable").on("click", function () {
                let column = $(this).index();
                let table = $("#tech-companies-1 tbody");
                let rows = table.find("tr").toArray();

                rows.sort(function (a, b) {
                    let A = $(a).find("td").eq(column).text().toLowerCase();
                    let B = $(b).find("td").eq(column).text().toLowerCase();

                    return A.localeCompare(B) * sortDirection;
                });

                sortDirection *= -1; // Toggle direction
                table.append(rows);
            });

            // Reset sorting on double click
            $("#tech-companies-1 thead th.sortable").on("dblclick", function () {
                location.reload();
            });

            // Percentage Calculation
            function calculatePercentage() {
                $("#tech-companies-1 tbody tr").each(function () {
                    let checkboxes = $(this).find("td input[type='checkbox']").not(":lt(3)");
                    let checked = checkboxes.filter(":checked").length;
                    let total = checkboxes.length;
                    let percentage = total > 0 ? (checked / total * 100).toFixed(2) + "%" : "0%";

                    let percentageCell = $(this).find(".percentage");
                    percentageCell.text(percentage);

                    percentageCell.css({
                        "font-weight": "bold",
                        "color": function () {
                            let percentValue = parseFloat(percentage);
                            if (percentValue === 100) return "green";
                            if (percentValue >= 50) return "#b59a0c";
                            if (percentValue >= 10) return "red";
                            return "black";
                        }()
                    });
                });
            }

            $(document).on("change", "#tech-companies-1 tbody input[type='checkbox']", function () {
                calculatePercentage();
            });

            calculatePercentage();

            // Search Function
            $("#searchInput").on("keyup", function () {
                let value = $(this).val().toLowerCase();
                $("#tech-companies-1 tbody tr").filter(function () {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });

            // Select All Checkbox
            $("#selectAll").on("change", function () {
                $(".productCheckbox").prop("checked", $(this).prop("checked"));
            });

            $(".productCheckbox").on("change", function () {
                $("#selectAll").prop("checked", $(".productCheckbox:checked").length === $(".productCheckbox").length);
            });
        });
    </script>
@endsection

