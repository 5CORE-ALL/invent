@extends('layouts.vertical', ['title' => 'Product Master', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
@endsection

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <title>Add Product</title> -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Menu', 'page_title' => 'Product Master'])

    <div class="container mt-5">
        <h2 class="text-center mb-4">Done Task</h2>
        <div class="row mb-3">
            <div class="col-md-2">
                <label for="pageLength">Rows per page:</label>
                <select id="pageLength" class="form-select">
                    <option value="10">10</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="monthFilter">Select Month:</label>
                <select id="monthFilter" class="form-select">
                    <option value="">All Months</option>
                    <option value="01">January</option>
                    <option value="02">February</option>
                    <option value="03">March</option>
                    <option value="04">April</option>
                    <option value="05">May</option>
                    <option value="06">June</option>
                    <option value="07">July</option>
                    <option value="08">August</option>
                    <option value="09">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="assignorFilter">Assignor:</label>
                <select id="assignorFilter" class="form-select">
                    <option value="">All Assignors</option>
                    <!-- Options will be populated dynamically -->
                </select>
            </div>
            <div class="col-md-3">
                <label for="assigneeFilter">Assignee:</label>
                <select id="assigneeFilter" class="form-select">
                    <option value="">All Assignees</option>
                    <!-- Options will be populated dynamically -->
                </select>
            </div>
            <div class="col-md-1">
                <button id="downloadCSV" class="btn btn-primary mt-4">Download CSV</button>
            </div>
        </div>
        <table id="taskTable" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Group</th>
                    <th>Task</th>
                    <th>Assignor</th>
                    <th>Assignee</th>
                    <th>Start Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>L1&L2</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Design</td>
                    <td>Design Homepage</td>
                    <td>Manager</td>
                    <td>John Doe</td>
                    <td>2023-10-01</td>
                    <td>2023-10-15</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Development</td>
                    <td>Develop API</td>
                    <td>Team Lead</td>
                    <td>Jane Smith</td>
                    <td>2023-11-05</td>
                    <td>2023-11-20</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Medium</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Testing</td>
                    <td>Test Application</td>
                    <td>QA Lead</td>
                    <td>Mike Johnson</td>
                    <td>2023-12-10</td>
                    <td>2023-12-25</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Low</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Design</td>
                    <td>Design Homepage</td>
                    <td>Manager</td>
                    <td>John Doe</td>
                    <td>2023-10-01</td>
                    <td>2023-10-15</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Development</td>
                    <td>Develop API</td>
                    <td>Team Lead</td>
                    <td>Jane Smith</td>
                    <td>2023-11-05</td>
                    <td>2023-11-20</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Medium</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Testing</td>
                    <td>Test Application</td>
                    <td>QA Lead</td>
                    <td>Mike Johnson</td>
                    <td>2023-12-10</td>
                    <td>2023-12-25</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Low</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Marketing</td>
                    <td>Create Campaign</td>
                    <td>Marketing Head</td>
                    <td>Alice Brown</td>
                    <td>2023-09-01</td>
                    <td>2023-09-30</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Finance</td>
                    <td>Prepare Budget</td>
                    <td>CFO</td>
                    <td>David Wilson</td>
                    <td>2023-08-15</td>
                    <td>2023-08-31</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Medium</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>HR</td>
                    <td>Recruit Developers</td>
                    <td>HR Manager</td>
                    <td>Emily Davis</td>
                    <td>2023-07-10</td>
                    <td>2023-07-25</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Operations</td>
                    <td>Optimize Workflow</td>
                    <td>Operations Head</td>
                    <td>Michael Lee</td>
                    <td>2023-06-05</td>
                    <td>2023-06-20</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Low</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Sales</td>
                    <td>Close Deals</td>
                    <td>Sales Manager</td>
                    <td>Sarah Johnson</td>
                    <td>2023-05-01</td>
                    <td>2023-05-15</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Support</td>
                    <td>Resolve Tickets</td>
                    <td>Support Lead</td>
                    <td>Chris Evans</td>
                    <td>2023-04-10</td>
                    <td>2023-04-25</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Medium</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Design</td>
                    <td>Design Logo</td>
                    <td>Design Lead</td>
                    <td>Laura Smith</td>
                    <td>2023-03-01</td>
                    <td>2023-03-15</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Low</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Development</td>
                    <td>Fix Bugs</td>
                    <td>Dev Lead</td>
                    <td>Robert Brown</td>
                    <td>2023-02-05</td>
                    <td>2023-02-20</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Testing</td>
                    <td>Test API</td>
                    <td>QA Lead</td>
                    <td>Mike Johnson</td>
                    <td>2023-01-10</td>
                    <td>2023-01-25</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Medium</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Marketing</td>
                    <td>Launch Ad Campaign</td>
                    <td>Marketing Head</td>
                    <td>Alice Brown</td>
                    <td>2022-12-01</td>
                    <td>2022-12-15</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Finance</td>
                    <td>Audit Accounts</td>
                    <td>CFO</td>
                    <td>David Wilson</td>
                    <td>2022-11-05</td>
                    <td>2022-11-20</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Low</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>HR</td>
                    <td>Conduct Interviews</td>
                    <td>HR Manager</td>
                    <td>Emily Davis</td>
                    <td>2022-10-10</td>
                    <td>2022-10-25</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Medium</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Operations</td>
                    <td>Streamline Processes</td>
                    <td>Operations Head</td>
                    <td>Michael Lee</td>
                    <td>2022-09-01</td>
                    <td>2022-09-15</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>High</td>
                    <td>L2</td>
                </tr>
                <tr>
                    <td>Sales</td>
                    <td>Generate Leads</td>
                    <td>Sales Manager</td>
                    <td>Sarah Johnson</td>
                    <td>2022-08-05</td>
                    <td>2022-08-20</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Low</td>
                    <td>L1</td>
                </tr>
                <tr>
                    <td>Support</td>
                    <td>Handle Complaints</td>
                    <td>Support Lead</td>
                    <td>Chris Evans</td>
                    <td>2022-07-10</td>
                    <td>2022-07-25</td>
                    <td><span class="badge bg-success">Done</span></td>
                    <td>Medium</td>
                    <td>L2</td>
                </tr>
                <!-- Add more rows as needed -->
            </tbody>
        </table>
    </div>


@endsection



@section('script')
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        var table = $('#taskTable').DataTable({
            paging: true,
            pageLength: 50, // Default rows per page
            lengthMenu: [10, 50, 100], // Options for rows per page
            dom: 'lrtip' // Remove 'B' (buttons) from dom to hide the default buttons
        });

        // Function to populate dropdowns with unique values
        function populateDropdown(columnIndex, dropdownId) {
            var columnData = table.column(columnIndex).data().unique().sort().toArray();
            var dropdown = $('#' + dropdownId);
            dropdown.empty().append('<option value="">All ' + dropdown.attr('name') + '</option>');
            columnData.forEach(function(value) {
                dropdown.append('<option value="' + value + '">' + value + '</option>');
            });
        }

        // Populate Assignor and Assignee dropdowns
        populateDropdown(2, 'assignorFilter'); // Column index 2 for Assignor
        populateDropdown(3, 'assigneeFilter'); // Column index 3 for Assignee

        // Month filter functionality
        $('#monthFilter').on('change', function() {
            var month = this.value;
            table.columns(4).search(month ? '^\\d{4}-' + month + '-\\d{2}' : '', true, false).draw();
        });

        // Assignor filter functionality
        $('#assignorFilter').on('change', function() {
            var assignor = this.value;
            table.columns(2).search(assignor).draw();
        });

        // Assignee filter functionality
        $('#assigneeFilter').on('change', function() {
            var assignee = this.value;
            table.columns(3).search(assignee).draw();
        });

        // Rows per page dropdown functionality
        $('#pageLength').on('change', function() {
            var pageLength = $(this).val();
            table.page.len(pageLength).draw();
        });

        // Custom Download CSV button
        $('#downloadCSV').on('click', function() {
            // Trigger CSV export with applied filters
            table.button().add(0, {
                extend: 'csv',
                text: 'Download CSV',
                className: 'btn-primary',
                exportOptions: {
                    modifier: {
                        search: 'applied' // Export only filtered data
                    }
                }
            }).trigger();
        });
    });
</script>

@endsection