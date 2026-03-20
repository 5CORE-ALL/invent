@extends('layouts.vertical', ['title' => 'Permissions', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Toastify CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ========== TABLE STRUCTURE ========== */

        .table-container {
            overflow-x: auto;
            overflow-y: visible;
            position: relative;
            max-height: 600px;
        }

        .custom-resizable-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .custom-resizable-table th,
        .custom-resizable-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            position: relative;
            white-space: nowrap;
            overflow: visible !important;
        }

        .custom-resizable-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            user-select: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* ========== RESIZABLE COLUMNS ========== */
        .resize-handle {
            position: absolute;
            top: 0;
            right: 0;
            width: 5px;
            height: 100%;
            background: rgba(0, 0, 0, 0.1);
            cursor: col-resize;
            z-index: 100;
        }

        .resize-handle:hover,
        .resize-handle.resizing {
            background: rgba(0, 0, 0, 0.3);
        }

        /* ========== LOADER ========== */
        .card-loader-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            z-index: 1050;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 0.25rem;
        }

        .loader-content {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .loader-text {
            margin-top: 15px;
            font-weight: 500;
            color: #333;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        /* ========== CARD BODY ========== */
        .card-body {
            position: relative;
        }

        /* ========== ACTION BUTTONS ========== */
        .action-btn {
            padding: 5px 10px;
            margin: 0 3px;
            font-size: 14px;
            border: none;
            background: transparent;
        }

        .view-permissions {
            color: #17a2b8;
        }

        .edit-permissions {
            color: #ffc107;
        }

        .view-permissions:hover {
            color: #138496;
        }

        .edit-permissions:hover {
            color: #e0a800;
        }

        /* ========== SEARCH INPUTS ========== */
        .search-input {
            margin-bottom: 10px;
            width: 100%;
        }

        /* ========== ROLE BADGES ========== */
        .badge-secondary {
            background-color: #6c5ce7;
        }

        .badge-admin {
            background-color: #6c5ce7;
        }

        .badge-manager {
            background-color: #00b894;
        }

        .badge-user {
            background-color: #0984e3;
        }

        /* ========== EDIT PERMISSIONS MODAL ========== */
        .permission-controls {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .permission-row {
            display: flex;
            margin-bottom: 15px;
            align-items: center;
            transition: border-color 0.2s ease;
        }

        .page-select {
            flex: 1;
            margin-right: 10px;
        }

        .permission-select {
            flex: 1;
            margin-right: 10px;
        }

        .selected-permissions {
            flex: 2;
            min-height: 38px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 8px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            align-content: flex-start;
        }

        .permission-tag {
            display: inline-flex;
            align-items: center;
            background-color: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 14px;
        }

        .permission-tag .remove-perm {
            margin-left: 5px;
            cursor: pointer;
            color: #dc3545;
        }

        .remove-page {
            color: #dc3545;
            cursor: pointer;
            margin-left: 10px;
        }

        .add-page-btn {
            margin-top: 10px;
        }

        .select-all-btn {
            margin-bottom: 10px;
        }

        /* ========== VIEW PERMISSIONS MODAL ========== */
        .permission-platform {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .permission-platform:last-child {
            border-bottom: none;
        }

        .platform-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .view-permission-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .view-permission-option {
            display: flex;
            align-items: center;
        }

        /* ========== TOAST NOTIFICATIONS ========== */
        .toastify {
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 15px 20px;
            font-family: inherit;
            font-weight: 500;
        }

        /* ========== MODAL LOADING STATES ========== */
        .modal-loading .modal-content {
            opacity: 0.7;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .modal-loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1051;
            display: none;
        }

        /* ========== BUTTON LOADING STATES ========== */
        .btn-loader {
            display: inline-block;
            margin-right: 8px;
            transition: opacity 0.3s ease;
        }

        #save-permissions[disabled] {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .page-reloading {
            overflow: hidden;
            position: relative;
        }

        .page-reloading::after {
            content: "Refreshing data...";
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            z-index: 9999;
        }

        /* ========== TRANSITIONS ========== */
        .modal {
            transition: all 0.3s ease;
        }

        /* ========== VALIDATION STATES ========== */
        .is-invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875em;
        }

        /* Add this to your style section */
        select optgroup {
            font-weight: bold;
            color: #333;
            background: #f8f9fa;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', [
        'page_title' => 'Permissions',
        'sub_title' => 'User Management',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">User Permissions</h4>

                    <!-- Search and Filter Row -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control search-input" id="nameSearch"
                                placeholder="Search by name...">
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control search-input" id="emailSearch"
                                placeholder="Search by email...">
                        </div>
                        <div class="col-md-4">
                            <select class="form-control search-input" id="roleFilter">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="custom-resizable-table" id="permissions-table">
                            <thead>
                                <tr>
                                    <th data-field="id">ID <span class="sort-arrow">↓</span></th>
                                    <th data-field="name">Name <span class="sort-arrow">↓</span></th>
                                    <th data-field="email">Email <span class="sort-arrow">↓</span></th>
                                    <th data-field="role">Role <span class="sort-arrow">↓</span></th>
                                    <th data-field="actions">Actions</th>
                                </tr>
                            </thead>
                            <!-- Table body: remove , will be rendered by JS -->
                            <tbody>
                                <!-- Table rows will be rendered by JS -->
                            </tbody>
                        </table>
                    </div>

                    <!-- View Permissions Modal -->
                    <div class="modal fade" id="viewPermissionsModal" tabindex="-1" role="dialog"
                        aria-labelledby="viewPermissionsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="viewPermissionsModalLabel">Permissions for <span
                                            id="view-user-name"></span></h5>
                                    <button type="button" class="close" onclick="closeModalview()" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p><strong>Email:</strong> <span id="view-user-email"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Role:</strong> <span id="view-user-role" class="badge"></span></p>
                                        </div>
                                    </div>

                                    <h6 class="mb-3">Current Permissions:</h6>

                                    <div id="view-permissions-container">
                                        <!-- Permissions will be loaded here -->
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Permissions Modal -->
                    <div class="modal fade" id="editPermissionsModal" tabindex="-1" role="dialog"
                        aria-labelledby="editPermissionsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editPermissionsModalLabel">Edit Permissions for <span
                                            id="edit-user-name"></span></h5>
                                    <button type="button" class="close" onclick="closeModal()" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>

                                </div>
                                <div class="modal-body">
                                    <div class="modal-loading-overlay">
                                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;"></div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <p><strong>Email:</strong> <span id="edit-user-email"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Role:</strong> <span id="edit-user-role" class="badge"></span></p>
                                        </div>
                                    </div>

                                  <div class="permission-controls">
                                        <h6>Edit Permissions:</h6>
                                        <button type="button" class="btn btn-sm btn-primary select-all-btn"
                                            id="select-all-permissions">
                                            <i class="fas fa-check-double"></i> Select All Pages & Permissions
                                        </button>
                                    </div> 
                                    <div class="permission-controls">
                                        <select class="form-control select-all-permissions" id="select-permissions-dropdown" multiple>
                                            <option value="view">View</option>
                                            <option value="create">Create</option>
                                            <option value="edit">Edit</option>
                                            <option value="delete">Delete</option>
                                        </select>
                                        <button type="button" class="btn btn-sm btn-primary apply-permissions-btn"
                                            id="apply-permissions">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>

                                    <div id="edit-permissions-container">
                                        <!-- Permission rows will be added here dynamically -->
                                    </div>

                                    <button type="button" class="btn btn-sm btn-primary add-page-btn" id="add-page-row">
                                        <i class="fas fa-plus"></i> Add Page
                                    </button>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal"
                                        id="cancel-permissions">
                                        <i class="fas fa-times mr-1"></i> Cancel
                                    </button>
                                    <button type="button" class="btn btn-primary" id="save-permissions">
                                        <span class="btn-loader" style="display: none;">
                                            <i class="fas fa-spinner fa-spin mr-1"></i>
                                        </span>
                                        <span class="btn-text">
                                            <i class="fas fa-save mr-1"></i> Save Changes
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="data-loader" class="card-loader-overlay" style="display: none;">
                        <div class="loader-content">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="loader-text">Loading permissions data...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
  function closeModal() {
    $('#editPermissionsModal').modal('hide');
  }
  function closeModalview()
  {
      $('#viewPermissionsModal').modal('hide');
  }
        $(document).ready(function() {

             $('#select-permissions-dropdown').select2({
                placeholder: "Select Permissions for All Pages",
                allowClear: true
            });
            // Use all users from PHP (not paginated)
            let allUsers = @json($users);
            let filteredUsers = [...allUsers];
            let currentPage = 1;
            const rowsPerPage = 10; // Set your desired page size

            // Initialize variables
            let isResizing = false;
            let currentEditingUserId = null;

            // Available pages and permissions
            const availablePages = @json($sidebarPages);

            const availablePermissions = [{
                    value: 'view',
                    text: 'View'
                },
                {
                    value: 'create',
                    text: 'Create'
                },
                {
                    value: 'edit',
                    text: 'Edit'
                },
                {
                    value: 'delete',
                    text: 'Delete'
                }
            ];

            // Toast Notification Helper
            function showToast(message, type = 'success') {
                const background = type === 'success' ? '#4CAF50' : '#F44336';
                Toastify({
                    text: message,
                    duration: 3000,
                    close: true,
                    gravity: "top",
                    position: "right",
                    backgroundColor: background,
                    stopOnFocus: true
                }).showToast();
            }

            // Initialize everything
            function initTable() {
                renderTable();
                initResizableColumns();
                initSorting();
                initPagination();
                initSearch();
                initModalHandlers();
                updatePaginationInfo();
            }

            // Render table with current data
            function renderTable() {
                const $tbody = $('#permissions-table tbody');
                $tbody.empty();

                // Show all filtered users, no pagination
                filteredUsers.forEach(user => {
                    const $row = $('<tr>');
                    $row.append($('<td>').text(user.id));
                    $row.append($('<td>').text(user.name));
                    $row.append($('<td>').text(user.email));

                    // Handle undefined role with default value
                    const userRole = user.role || 'user';
                    let roleBadgeClass = 'badge-user';
                    if (userRole === 'admin') roleBadgeClass = 'badge-admin';
                    if (userRole === 'manager') roleBadgeClass = 'badge-manager';

                    $row.append(
                        $('<td>').append(
                            $('<span>').addClass(`badge ${roleBadgeClass}`)
                            .text(userRole.charAt(0).toUpperCase() + userRole.slice(1))
                        )
                    );

                    $row.append(
                        $('<td>').append(
                            $('<button>').addClass('action-btn view-permissions')
                            .html('<i class="fas fa-eye"></i>')
                            .attr('data-user-id', user.id)
                            .attr('title', 'View Permissions'),
                            $('<button>').addClass('action-btn edit-permissions')
                            .html('<i class="fas fa-edit"></i>')
                            .attr('data-user-id', user.id)
                            .attr('title', 'Edit Permissions')
                        )
                    );

                    $tbody.append($row);
                });
            }

            // Make columns resizable
            function initResizableColumns() {
                const $table = $('#permissions-table');
                const $headers = $table.find('th');
                let startX, startWidth, columnIndex;

                $headers.each(function() {
                    $(this).append('<div class="resize-handle"></div>');
                });

                $table.on('mousedown', '.resize-handle', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    isResizing = true;
                    $(this).addClass('resizing');

                    const $th = $(this).parent();
                    columnIndex = $th.index();
                    startX = e.pageX;
                    startWidth = $th.outerWidth();

                    $('body').css('user-select', 'none');
                });

                $(document).on('mousemove', function(e) {
                    if (!isResizing) return;

                    const $resizer = $('.resize-handle.resizing');
                    if ($resizer.length) {
                        const $th = $resizer.parent();
                        const newWidth = startWidth + (e.pageX - startX);
                        $th.css('width', newWidth + 'px');
                        $th.css('min-width', newWidth + 'px');
                        $th.css('max-width', newWidth + 'px');
                    }
                });

                $(document).on('mouseup', function(e) {
                    if (!isResizing) return;

                    e.stopPropagation();
                    $('.resize-handle').removeClass('resizing');
                    $('body').css('user-select', '');
                    isResizing = false;
                });
            }

            // Initialize sorting functionality
            function initSorting() {
                const $table = $('#permissions-table');
                const $headers = $table.find('th[data-field]');

                $headers.on('click', function() {
                    const field = $(this).data('field');
                    const isAsc = $(this).hasClass('sort-asc');

                    $headers.removeClass('sort-asc sort-desc');
                    $headers.find('.sort-arrow').html('↓');

                    $(this).addClass(isAsc ? 'sort-desc' : 'sort-asc');
                    $(this).find('.sort-arrow').html(isAsc ? '↑' : '↓');

                    filteredUsers.sort((a, b) => {
                        let valA = a[field];
                        let valB = b[field];

                        if (typeof valA === 'string') valA = valA.toLowerCase();
                        if (typeof valB === 'string') valB = valB.toLowerCase();

                        if (valA < valB) return isAsc ? -1 : 1;
                        if (valA > valB) return isAsc ? 1 : -1;
                        return 0;
                    });

                    renderTable();
                });
            }

            // Initialize pagination controls
            function initPagination() {
                $('#first-page').on('click', function(e) {
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage = 1;
                        window.location.href = updateUrlParameter(window.location.href, 'page',
                            currentPage);
                    }
                });

                $('#prev-page').on('click', function(e) {
                    e.preventDefault();
                    if (currentPage > 1) {
                        currentPage--;
                        window.location.href = updateUrlParameter(window.location.href, 'page',
                            currentPage);
                    }
                });

                $('#next-page').on('click', function(e) {
                    e.preventDefault();
                    const totalPages = Math.ceil(filteredUsers.length / rowsPerPage);
                    if (currentPage < totalPages) {
                        currentPage++;
                        window.location.href = updateUrlParameter(window.location.href, 'page',
                            currentPage);
                    }
                });

                $('#last-page').on('click', function(e) {
                    e.preventDefault();
                    const totalPages = Math.ceil(filteredUsers.length / rowsPerPage);
                    if (currentPage < totalPages) {
                        currentPage = totalPages;
                        window.location.href = updateUrlParameter(window.location.href, 'page',
                            currentPage);
                    }
                });
            }

            // Helper function to update URL parameters
            function updateUrlParameter(url, key, value) {
                const re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
                const separator = url.indexOf('?') !== -1 ? "&" : "?";
                if (url.match(re)) {
                    return url.replace(re, '$1' + key + "=" + value + '$2');
                } else {
                    return url + separator + key + "=" + value;
                }
            }

            // Update pagination information
            function updatePaginationInfo() {
                const totalUsers = filteredUsers.length;
                const totalPages = Math.ceil(totalUsers / rowsPerPage);
                const startItem = (currentPage - 1) * rowsPerPage + 1;
                const endItem = Math.min(currentPage * rowsPerPage, totalUsers);

                $('#visible-rows').text(`Showing ${startItem}-${endItem} of ${totalUsers}`);
                $('#page-info').text(`Page ${currentPage} of ${totalPages}`);

                $('#first-page, #prev-page').prop('disabled', currentPage === 1);
                $('#next-page, #last-page').prop('disabled', currentPage === totalPages || totalPages === 0);
            }

            // Initialize search functionality
            function initSearch() {
                $('#nameSearch').on('input', function() {
                    applyFilters();
                });

                $('#emailSearch').on('input', function() {
                    applyFilters();
                });

                $('#roleFilter').on('change', function() {
                    applyFilters();
                });
            }

            // Apply all filters and search
            function applyFilters() {
                const nameSearch = $('#nameSearch').val().toLowerCase();
                const emailSearch = $('#emailSearch').val().toLowerCase();
                const roleFilter = $('#roleFilter').val();

                filteredUsers = allUsers.filter(user => {
                    const nameMatch = user.name.toLowerCase().includes(nameSearch);
                    const emailMatch = user.email.toLowerCase().includes(emailSearch);
                    const roleMatch = roleFilter === '' || user.role === roleFilter;

                    return nameMatch && emailMatch && roleMatch;
                });

                currentPage = 1;
                renderTable();
                updatePaginationInfo();
            }

            // Group availablePages by group
            function groupPagesBySection(pages) {
                const grouped = {};
                pages.forEach(page => {
                    if (!grouped[page.group]) grouped[page.group] = [];
                    grouped[page.group].push(page);
                });
                return grouped;
            }

            // Create page select options with optgroup
            function createPageSelectOptions(selectedPage = '') {
                const groupedPages = groupPagesBySection(availablePages);
                let options = '<option value="">Select Page</option>';
                Object.keys(groupedPages).forEach(group => {
                    options += `<optgroup label="${group}">`;
                    groupedPages[group].forEach(page => {
                        const selected = page.value === selectedPage ? 'selected' : '';
                        options +=
                            `<option value="${page.value}" ${selected}>${page.text}</option>`;
                    });
                    options += '</optgroup>';
                });
                return options;
            }

            // Create a permission row HTML
            function createPermissionRow(page = '', permissions = []) {
                const rowId = 'row-' + Math.random().toString(36).substr(2, 9);

                // Get available pages that haven't been selected in other rows
                const availablePagesForRow = getAvailablePages(page);

                // Create page select options
                let pageOptions = createPageSelectOptions(page);
                // let pageOptions = '<option value="">Select Page</option>';
                // availablePagesForRow.forEach(p => {
                //     const selected = p.value === page ? 'selected' : '';
                //     pageOptions += `<option value="${p.value}" ${selected}>${p.text}</option>`;
                // });

                // Get available permissions for dropdown
                const availablePerms = getAvailablePermissionsForDropdown(page, permissions);

                // Create permission select options
                let permOptions = '<option value="">Add Permission</option>';
                availablePerms.forEach(perm => {
                    permOptions += `<option value="${perm.value}">${perm.text}</option>`;
                });

                // Create selected permissions tags
                let permissionTags = '';
                permissions.forEach(perm => {
                    const permName = availablePermissions.find(p => p.value === perm)?.text || perm;
                    permissionTags += `
                    <div class="permission-tag">
                        ${permName}
                        <span class="remove-perm" data-perm="${perm}">×</span>
                    </div>
                `;
                });

                return `
                <div class="permission-row" id="${rowId}" data-page="${page}">
                    <select class="form-control page-select">
                        ${pageOptions}
                    </select>
                    <select class="form-control permission-select">
                        ${permOptions}
                    </select>
                    <div class="selected-permissions">
                        ${permissionTags}
                    </div>
                    <span class="remove-page">×</span>
                </div>
            `;
            }

            // Get available pages that haven't been selected in other rows
            function getAvailablePages(currentPage = '') {
                const selectedPages = [];

                $('#edit-permissions-container .permission-row').each(function() {
                    const page = $(this).data('page');
                    if (page && page !== currentPage) {
                        selectedPages.push(page);
                    }
                });

                return availablePages.filter(page => !selectedPages.includes(page.value));
            }

            // Get available permissions for dropdown (not already selected)
            function getAvailablePermissionsForDropdown(page, currentPermissions = []) {
                if (!page) return [...availablePermissions];
                return availablePermissions.filter(perm => !currentPermissions.includes(perm.value));
            }

            // Load user permissions from server
            async function loadUserPermissions(userId) {
                showLoader();
                try {
                    const response = await fetch(`/auth/users/${userId}/permissions`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });

                    if (!response.ok) {
                        const errorData = await response.json().catch(() => ({}));
                        console.error('Permission load failed:', errorData.message || response.statusText);
                        return {};
                    }

                    const data = await response.json();
                    return data.permissions || {};

                } catch (error) {
                    console.error('Network error:', error);
                    return {};
                } finally {
                    hideLoader();
                }
            }

            // Save user permissions to server
            async function saveUserPermissions(userId, permissions) {
                const $btn = $('#save-permissions');
                const $btnLoader = $btn.find('.btn-loader');
                const $btnText = $btn.find('.btn-text');
                const $modal = $('#editPermissionsModal');

                // Set loading state
                $btn.prop('disabled', true);
                $btnLoader.show();
                $btnText.text('Saving...');
                $modal.addClass('modal-loading');
                $modal.find('.modal-loading-overlay').show();

                try {
                    const response = await fetch(`/auth/users/${userId}/permissions`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            permissions
                        })
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Failed to save permissions');
                    }

                    showToast('Permissions updated successfully!');
                    $('#editPermissionsModal').modal('hide');

                    // Optional: Refresh user data
                    await loadInitialData();

                    return data;

                } catch (error) {
                    console.error('Save failed:', error);
                    showToast(error.message, 'error');
                    throw error;
                } finally {
                    $btn.prop('disabled', false);
                    $btnLoader.hide();
                    $btnText.html('<i class="fas fa-save mr-1"></i> Save Changes');
                    $modal.removeClass('modal-loading');
                    $modal.find('.modal-loading-overlay').hide();
                }
            }

            // Load initial data
            async function loadInitialData() {
                const $btn = $('#save-permissions');
                const $btnLoader = $btn.find('.btn-loader');
                const $btnText = $btn.find('.btn-text');

                // Set loading state
                $btn.prop('disabled', true);
                $btnLoader.show();
                $btnText.text('Refreshing...');
                showLoader();

                try {
                    // Store current scroll position
                    const scrollPosition = window.scrollY || document.documentElement.scrollTop;

                    // Create a small delay to ensure loading states are visible
                    await new Promise(resolve => setTimeout(resolve, 300));

                    // Full page reload
                    window.location.reload();

                    // Restore scroll position after reload (won't execute but good practice)
                    window.scrollTo(0, scrollPosition);

                } catch (error) {
                    console.error('Data load error:', error);
                    showToast('Failed to refresh data. Please try again.', 'error');

                    // Fallback: Manual table refresh if reload fails
                    try {
                        await manualTableRefresh();
                    } catch (fallbackError) {
                        console.error('Fallback refresh failed:', fallbackError);
                    }

                } finally {
                    // These will execute briefly before the page reloads
                    $btn.prop('disabled', false);
                    $btnLoader.hide();
                    $btnText.html('<i class="fas fa-save mr-1"></i> Save Changes');
                    hideLoader();
                }
            }

            // Fallback function if page reload fails
            async function manualTableRefresh() {
                showLoader();
                try {
                    // Get fresh data from the server (simplified version)
                    const response = await fetch(window.location.href);
                    const html = await response.text();

                    // Parse the HTML and extract table data
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newTableBody = doc.querySelector('#permissions-table tbody').innerHTML;

                    // Update the table
                    document.querySelector('#permissions-table tbody').innerHTML = newTableBody;

                    showToast('Data refreshed successfully', 'success');
                } catch (error) {
                    throw error; // Re-throw to be caught by the outer catch
                } finally {
                    hideLoader();
                }
            }

            // Initialize modal handlers
            function initModalHandlers() {
                // View permissions modal
                $(document).on('click', '.view-permissions', async function() {
                    const userId = $(this).data('user-id');
                    const user = allUsers.find(u => u.id == userId);

                    if (user) {
                        $('#view-user-name').text(user.name);
                        $('#view-user-email').text(user.email);

                        // Handle undefined role with default value
                        const userRole = user.role || 'user';
                        let roleBadgeClass = 'badge-user';
                        if (userRole === 'admin') roleBadgeClass = 'badge-admin';
                        if (userRole === 'manager') roleBadgeClass = 'badge-manager';

                        $('#view-user-role').removeClass().addClass(`badge ${roleBadgeClass}`)
                            .text(userRole.charAt(0).toUpperCase() + userRole.slice(1));

                        const userPermissions = await loadUserPermissions(userId);
                        const $container = $('#view-permissions-container');
                        $container.empty();

                        Object.keys(userPermissions).forEach(page => {
                            if (userPermissions[page].length > 0) {
                                const pageName = availablePages.find(p => p.value === page)
                                    ?.text || page;
                                const permissions = userPermissions[page];

                                const $platform = $(`
                                <div class="permission-platform">
                                    <div class="platform-title">${pageName}</div>
                                    <div class="view-permission-options"></div>
                                </div>
                            `);

                                const $options = $platform.find('.view-permission-options');
                                permissions.forEach(perm => {
                                    const permName = availablePermissions.find(p =>
                                        p.value === perm)?.text || perm;
                                    $options.append(`
                                    <div class="view-permission-option">
                                        <span class="badge badge-secondary">${permName}</span>
                                    </div>
                                `);
                                });

                                $container.append($platform);
                            }
                        });

                        $('#viewPermissionsModal').modal('show');
                    }
                });

                // Edit permissions modal
                $(document).on('click', '.edit-permissions', async function() {
                    const userId = $(this).data('user-id');
                    const $button = $(this);

                    try {
                        // Disable button during loading
                        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                        // Find user in local data
                        const user = allUsers.find(u => u.id == userId);
                        if (!user) {
                            throw new Error('User data not found');
                        }

                        // Set editing user ID
                        currentEditingUserId = userId;

                        // Update modal header info
                        $('#edit-user-name').text(user.name || 'Unknown User');
                        $('#edit-user-email').text(user.email || 'No email');

                        // Set role badge with fallback
                        const role = user.role || 'user';
                        let roleClass = 'badge-secondary';
                        if (role === 'admin') roleClass = 'badge-admin';
                        if (role === 'manager') roleClass = 'badge-manager';

                        $('#edit-user-role')
                            .removeClass()
                            .addClass(`badge ${roleClass}`)
                            .text(role.charAt(0).toUpperCase() + role.slice(1));

                        // Show loading state in modal
                        const $container = $('#edit-permissions-container');
                        $container.html(`
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading permissions...</span>
                            </div>
                            <p class="mt-2">Loading permissions...</p>
                        </div>
                    `);

                        // Show modal immediately while loading
                        $('#editPermissionsModal').modal('show');

                        // Load permissions from server
                        const userPermissions = await loadUserPermissions(userId);

                        // Clear and rebuild permissions UI
                        $container.empty();

                        if (Object.keys(userPermissions).length > 0) {
                            // Add rows for existing permissions
                            Object.entries(userPermissions).forEach(([page, perms]) => {
                                if (perms && perms.length > 0) {
                                    $container.append(createPermissionRow(page, perms));
                                }
                            });
                        } else {
                            // Start with one empty row if no permissions exist
                            $container.append(createPermissionRow());
                        }

                        // Update all page selects to reflect current state
                        updateAllPageSelects();

                    } catch (error) {
                        console.error('Error loading permissions:', error);

                        // Show error state in modal
                        $('#edit-permissions-container').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Failed to load permissions: ${error.message}
                        </div>
                        <button class="btn btn-sm btn-primary mt-2" onclick="$(this).closest('.modal').find('.edit-permissions').click()">
                            <i class="fas fa-sync-alt me-1"></i> Retry
                        </button>
                    `);

                    } finally {
                        // Re-enable button
                        $button.prop('disabled', false).html('<i class="fas fa-edit"></i>');
                    }
                });

                // Add new page row
                $('#add-page-row').on('click', function() {
                    $('#edit-permissions-container').append(createPermissionRow());
                    updateAllPageSelects();
                });

                // Handle page selection change
                $(document).on('change', '.page-select', function() {
                    const $row = $(this).closest('.permission-row');
                    const newPage = $(this).val();
                    const oldPage = $row.data('page');

                    $row.data('page', newPage);
                    $row.find('.selected-permissions').empty();
                    updatePermissionSelect($row);
                    updateAllPageSelects();
                });

                // Handle permission selection
                $(document).on('change', '.permission-select', function() {
                    const $select = $(this);
                    const perm = $select.val();

                    if (!perm) return;

                    const $row = $select.closest('.permission-row');
                    const page = $row.find('.page-select').val();

                    if (!page) {
                        showToast('Please select a page first', 'error');
                        $select.val('');
                        return;
                    }

                    const $selectedPerms = $row.find('.selected-permissions');
                    const permName = availablePermissions.find(p => p.value === perm)?.text || perm;

                    $selectedPerms.append(`
                    <div class="permission-tag">
                        ${permName}
                        <span class="remove-perm" data-perm="${perm}">×</span>
                    </div>
                `);

                    $select.val('');
                    updatePermissionSelect($row);
                });

                // Handle permission removal
                $(document).on('click', '.remove-perm', function() {
                    const perm = $(this).data('perm');
                    const $tag = $(this).closest('.permission-tag');
                    const $row = $tag.closest('.permission-row');

                    $tag.remove();
                    updatePermissionSelect($row);
                });

                // Handle page removal
                $(document).on('click', '.remove-page', function() {
                    $(this).closest('.permission-row').remove();
                    updateAllPageSelects();

                    if ($('#edit-permissions-container .permission-row').length === 0) {
                        $('#edit-permissions-container').append(createPermissionRow());
                    }
                });

                // Select all pages and permissions
               $('#apply-permissions').on('click', function() {
                    const selectedPerms = $('#select-permissions-dropdown').val();
                    $('#edit-permissions-container').empty();
                    availablePages.forEach(page => {
                        $('#edit-permissions-container').append(createPermissionRow(page.value, selectedPerms));
                    });
                    $('.page-select').prop('disabled', true);
                });
                $('#select-all-permissions').on('click', function() {
                    $('#edit-permissions-container').empty();

                    availablePages.forEach(page => {
                        const allPerms = availablePermissions.map(p => p.value);
                        console.log(allPerms,'allPerms');
                        $('#edit-permissions-container').append(createPermissionRow(page.value,
                            allPerms));
                    });

                    $('.page-select').prop('disabled', true);
                });

                // Save permissions
                $('#save-permissions').on('click', async function() {
                    const permissions = {};
                    const permissionRows = $('.permission-row');
                    let hasInvalid = false;

                    // Reset validation states
                    permissionRows.removeClass('border border-danger');

                    // Collect permissions
                    permissionRows.each(function() {
                        const $row = $(this);
                        const page = $row.find('.page-select').val();
                        const perms = [];

                        $row.find('.permission-tag').each(function() {
                            perms.push($(this).find('.remove-perm').data('perm'));
                        });

                        if (page && perms.length) {
                            permissions[page] = perms;
                        } else if (page || perms.length) {
                            hasInvalid = true;
                            $row.addClass('border border-danger');
                        }
                    });

                    try {
                        if (hasInvalid) {
                            throw new Error('Please complete all permission selections');
                        }

                        if (Object.keys(permissions).length === 0) {
                            throw new Error('No permissions selected to save');
                        }

                        await saveUserPermissions(currentEditingUserId, permissions);

                    } catch (error) {
                        console.error('Save error:', error);
                        // Error is already handled in saveUserPermissions
                    }
                });

                // Cancel button handler
                $('#cancel-permissions').on('click', function() {
                    if ($('#save-permissions').prop('disabled')) {
                        if (!confirm('Are you sure? Changes will not be saved.')) {
                            return false;
                        }
                    }
                    $('#editPermissionsModal').modal('hide');
                });

                // Update permission select options for a row
                function updatePermissionSelect($row) {
                    const page = $row.find('.page-select').val();
                    const currentPerms = [];

                    $row.find('.permission-tag').each(function() {
                        currentPerms.push($(this).find('.remove-perm').data('perm'));
                    });

                    const availablePerms = getAvailablePermissionsForDropdown(page, currentPerms);
                    const $select = $row.find('.permission-select');
                    const currentValue = $select.val();

                    let options = '<option value="">Add Permission</option>';
                    availablePerms.forEach(perm => {
                        options += `<option value="${perm.value}">${perm.text}</option>`;
                    });

                    $select.html(options).val(currentValue);
                }

                // Update all page selects to reflect available pages
                function updateAllPageSelects() {
                    const allSelectedPages = [];

                    $('#edit-permissions-container .permission-row').each(function() {
                        const page = $(this).data('page');
                        if (page) allSelectedPages.push(page);
                    });

                    // Group pages by section
                    const groupedPages = groupPagesBySection(availablePages);

                    $('#edit-permissions-container .permission-row').each(function() {
                        const $row = $(this);
                        const currentPage = $row.data('page');
                        const $select = $row.find('.page-select');
                        const currentValue = $select.val();

                        let options = '<option value="">Select Page</option>';
                        Object.keys(groupedPages).forEach(group => {
                            options += `<optgroup label="${group}">`;
                            groupedPages[group].forEach(page => {
                                if (!allSelectedPages.includes(page.value) || page.value ===
                                    currentPage) {
                                    const selected = page.value === currentPage ?
                                        'selected' : '';
                                    options +=
                                        `<option value="${page.value}" ${selected}>${page.text}</option>`;
                                }
                            });
                            options += '</optgroup>';
                        });

                        $select.html(options).val(currentValue);
                    });
                }
            }

            // Loader functions
            function showLoader() {
                $('#data-loader').fadeIn();
            }

            function hideLoader() {
                $('#data-loader').fadeOut();
            }

            // Initialize everything
            initTable();
        });
    </script>
@endsection
