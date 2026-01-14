@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Campaigns', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/admin-resources/rwd-table/rwd-table.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <style>
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-active { background-color: #d4edda; color: #155724; }
        .status-paused { background-color: #fff3cd; color: #856404; }
        .status-disabled { background-color: #f8d7da; color: #721c24; }
        .status-pending { background-color: #d1ecf1; color: #0c5460; }
        .table th { background-color: #f8f9fa; font-weight: 600; }
        .metric-badge {
            display: inline-block;
            padding: 2px 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-weight: 600;
            color: #495057;
        }
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
    </style>
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Meta Ads Manager - Campaigns'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Campaigns</h4>
                    <div class="d-flex gap-2">
                        <button type="button" id="exportExcelBtn" class="btn btn-sm btn-success" style="display: none;">
                            <i class="mdi mdi-file-excel"></i> Excel
                        </button>
                        <button type="button" id="exportPrintBtn" class="btn btn-sm btn-info" style="display: none;">
                            <i class="mdi mdi-printer"></i> Print
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="location.reload()">
                            <i class="mdi mdi-refresh"></i> Refresh
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="campaignsTable" class="table table-hover table-striped table-bordered dt-responsive nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>AD Type</th>
                                    <th>Groups</th>
                                    <th>Parents</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Objective</th>
                                    <th>Daily Budget</th>
                                    <th>AdSets</th>
                                    <th>Ads</th>
                                    <th>Spend</th>
                                    <th>Impressions</th>
                                    <th>Clicks</th>
                                    <th>CTR</th>
                                    <th class="no-export">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables will populate this -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create New Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createGroupModalLabel">
                        <i class="mdi mdi-plus-circle me-2"></i>Create New Group
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createGroupForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="groupName" class="form-label">Group Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="groupName" name="name" required placeholder="Enter group name">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-check me-1"></i>Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create New Ad Type Modal -->
    <div class="modal fade" id="createAdTypeModal" tabindex="-1" aria-labelledby="createAdTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="createAdTypeModalLabel">
                        <i class="mdi mdi-plus-circle me-2"></i>Create New Ad Type
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="createAdTypeForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="adTypeName" class="form-label">Ad Type Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="adTypeName" name="name" required placeholder="Enter ad type name">
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="mdi mdi-check me-1"></i>Create Ad Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        // Load DataTables scripts sequentially and initialize
        (function() {
            var scripts = [
                'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
                'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js',
                'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js',
                'https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js'
            ];

            function loadScript(src, callback) {
                var script = document.createElement('script');
                script.src = src;
                script.async = false;
                script.onload = callback;
                script.onerror = function() {
                    console.error('Failed to load script: ' + src);
                    callback();
                };
                document.head.appendChild(script);
            }

            function loadScriptsSequentially(index) {
                if (index >= scripts.length) {
                    setTimeout(initCampaignsTable, 300);
                    return;
                }
                loadScript(scripts[index], function() {
                    loadScriptsSequentially(index + 1);
                });
            }

            function initCampaignsTable() {
                if (typeof jQuery === 'undefined' || typeof window.jQuery === 'undefined') {
                    console.error('jQuery is not available');
                    alert('jQuery failed to load. Please refresh the page.');
                    return;
                }

                var $ = window.jQuery || jQuery;

                if (typeof $.fn.DataTable === 'undefined') {
                    console.error('DataTables is not available');
                    alert('DataTables failed to load. Please refresh the page.');
                    return;
                }

                if ($.fn.DataTable.isDataTable('#campaignsTable')) {
                    return;
                }

                try {
                    $(document).ready(function() {
                        function getStatusBadge(status) {
                            if (!status) return '<span class="status-badge status-pending">Unknown</span>';
                            status = status.toLowerCase();
                            if (status === 'active') {
                                return '<span class="status-badge status-active">Active</span>';
                            } else if (status === 'paused') {
                                return '<span class="status-badge status-paused">Paused</span>';
                            } else if (status === 'archived' || status === 'deleted') {
                                return '<span class="status-badge status-disabled">Archived</span>';
                            }
                            return '<span class="status-badge status-pending">' + status + '</span>';
                        }

                        var adAccountId = '{{ $selectedAdAccountId ?? "" }}';
                        var url = '{{ route("meta.ads.manager.campaigns.data") }}';
                        if (adAccountId) {
                            url += '?ad_account_id=' + adAccountId;
                        }
                        
                        // Get ad types list
                        var adTypes = @json($adTypes ?? []);
                        // Get groups list
                        var groups = @json($groups ?? []);
                        // Get parents list
                        var parents = @json($parents ?? []);
                        
                        // Function to update campaign group
                        function updateCampaignGroup(campaignId, groupValue, $select) {
                            $.ajax({
                                url: '{{ url("/meta-ads-manager/campaigns") }}/' + campaignId + '/group',
                                method: 'POST',
                                data: {
                                    group: groupValue,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Success - dropdown already has the value selected
                                        console.log('Group updated successfully');
                                    }
                                },
                                error: function(xhr) {
                                    // Revert dropdown
                                    $select.val('');
                                    var errorMsg = 'Failed to update group';
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg = xhr.responseJSON.message;
                                    }
                                    alert(errorMsg);
                                }
                            });
                        }
                        
                        // Function to update campaign ad type
                        function updateCampaignAdType(campaignId, adTypeValue, $select) {
                            $.ajax({
                                url: '{{ url("/meta-ads-manager/campaigns") }}/' + campaignId + '/ad-type',
                                method: 'POST',
                                data: {
                                    ad_type: adTypeValue,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Success - dropdown already has the value selected
                                        console.log('Ad Type updated successfully');
                                    }
                                },
                                error: function(xhr) {
                                    // Revert dropdown
                                    $select.val('');
                                    var errorMsg = 'Failed to update ad type';
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg = xhr.responseJSON.message;
                                    }
                                    alert(errorMsg);
                                }
                            });
                        }
                        
                        // Function to update campaign parent
                        function updateCampaignParent(campaignId, parentValue, $select) {
                            $.ajax({
                                url: '{{ url("/meta-ads-manager/campaigns") }}/' + campaignId + '/parent',
                                method: 'POST',
                                data: {
                                    parent: parentValue,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Success - dropdown already has the value selected
                                        console.log('Parent updated successfully');
                                    }
                                },
                                error: function(xhr) {
                                    // Revert dropdown
                                    $select.val('');
                                    var errorMsg = 'Failed to update parent';
                                    if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg = xhr.responseJSON.message;
                                    }
                                    alert(errorMsg);
                                }
                            });
                        }

                        var table = $('#campaignsTable').DataTable({
                            processing: true,
                            serverSide: false,
                            responsive: true,
                            ajax: {
                                url: url,
                                dataSrc: function(json) {
                                    return json.data || [];
                                },
                                error: function(xhr, error, thrown) {
                                    console.error('DataTables AJAX error:', error);
                                    alert('Error loading campaigns data. Please refresh the page.');
                                }
                            },
                            columns: [
                                { data: 'id', name: 'id', width: '60px' },
                                { 
                                    data: 'ad_type', 
                                    name: 'ad_type',
                                    orderable: false,
                                    searchable: false,
                                    render: function(data, type, row) {
                                        var selectHtml = '<select class="form-select form-select-sm campaign-ad-type-select" data-campaign-id="' + row.id + '" style="min-width: 200px;">';
                                        selectHtml += '<option value="">Select AD Type</option>';
                                        adTypes.forEach(function(adType) {
                                            var selected = (data === adType) ? 'selected' : '';
                                            selectHtml += '<option value="' + adType + '" ' + selected + '>' + adType + '</option>';
                                        });
                                        selectHtml += '<option value="__NEW__" class="text-primary fw-bold">+ New Ad Type</option>';
                                        selectHtml += '</select>';
                                        return selectHtml;
                                    }
                                },
                                { 
                                    data: 'group', 
                                    name: 'group',
                                    orderable: false,
                                    searchable: false,
                                    render: function(data, type, row) {
                                        var selectHtml = '<select class="form-select form-select-sm campaign-group-select" data-campaign-id="' + row.id + '" style="min-width: 200px;">';
                                        selectHtml += '<option value="">Select Group</option>';
                                        groups.forEach(function(group) {
                                            var selected = (data === group) ? 'selected' : '';
                                            selectHtml += '<option value="' + group + '" ' + selected + '>' + group + '</option>';
                                        });
                                        selectHtml += '<option value="__NEW__" class="text-primary fw-bold">+ New Group</option>';
                                        selectHtml += '</select>';
                                        return selectHtml;
                                    }
                                },
                                { 
                                    data: 'parent', 
                                    name: 'parent',
                                    orderable: false,
                                    searchable: false,
                                    render: function(data, type, row) {
                                        var selectHtml = '<select class="form-select form-select-sm campaign-parent-select" data-campaign-id="' + row.id + '" style="min-width: 200px;">';
                                        selectHtml += '<option value="">Select Parent</option>';
                                        parents.forEach(function(parent) {
                                            var selected = (data === parent) ? 'selected' : '';
                                            selectHtml += '<option value="' + parent + '" ' + selected + '>' + parent + '</option>';
                                        });
                                        selectHtml += '</select>';
                                        return selectHtml;
                                    }
                                },
                                { 
                                    data: 'name', 
                                    name: 'name',
                                    render: function(data, type, row) {
                                        return '<strong>' + (data || 'N/A') + '</strong>';
                                    }
                                },
                                { 
                                    data: 'status', 
                                    name: 'status',
                                    render: function(data) {
                                        return getStatusBadge(data);
                                    }
                                },
                                { 
                                    data: 'objective', 
                                    name: 'objective',
                                    render: function(data) {
                                        return data ? '<span class="badge bg-info">' + data + '</span>' : '-';
                                    }
                                },
                                { 
                                    data: 'daily_budget', 
                                    name: 'daily_budget',
                                    render: function(data) {
                                        return data ? '<span class="metric-value">$' + parseFloat(data).toFixed(2) + '</span>' : '-';
                                    }
                                },
                                { 
                                    data: 'adsets_count', 
                                    name: 'adsets_count',
                                    render: function(data) {
                                        return '<span class="badge bg-secondary">' + (data || 0) + '</span>';
                                    }
                                },
                                { 
                                    data: 'ads_count', 
                                    name: 'ads_count',
                                    render: function(data) {
                                        return '<span class="badge bg-secondary">' + (data || 0) + '</span>';
                                    }
                                },
                                { 
                                    data: 'spend', 
                                    name: 'spend',
                                    render: function(data) {
                                        return '<span class="metric-value text-danger">$' + parseFloat(data || 0).toFixed(2) + '</span>';
                                    }
                                },
                                { 
                                    data: 'impressions', 
                                    name: 'impressions',
                                    render: function(data) {
                                        return '<span class="metric-value">' + parseInt(data || 0).toLocaleString() + '</span>';
                                    }
                                },
                                { 
                                    data: 'clicks', 
                                    name: 'clicks',
                                    render: function(data) {
                                        return '<span class="metric-value">' + parseInt(data || 0).toLocaleString() + '</span>';
                                    }
                                },
                                { 
                                    data: 'ctr', 
                                    name: 'ctr',
                                    render: function(data) {
                                        const ctr = parseFloat(data || 0);
                                        const color = ctr > 2 ? 'text-success' : ctr > 1 ? 'text-warning' : 'text-danger';
                                        return '<span class="metric-value ' + color + '">' + ctr.toFixed(2) + '%</span>';
                                    }
                                },
                                { 
                                    data: 'meta_id', 
                                    name: 'actions',
                                    orderable: false,
                                    searchable: false,
                                    className: 'action-buttons',
                                    render: function(data, type, row) {
                                        return '<a href="/meta-ads-manager/adsets?campaign_id=' + row.id + '" class="btn btn-sm btn-primary"><i class="mdi mdi-eye"></i> View</a>';
                                    }
                                }
                            ],
                            order: [[10, 'desc']],
                            pageLength: 25,
                            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                            dom: 'frtip',
                            buttons: [
                                {
                                    extend: 'excel',
                                    text: 'Excel',
                                    exportOptions: {
                                        columns: ':not(.no-export)'
                                    }
                                },
                                {
                                    extend: 'print',
                                    text: 'Print',
                                    exportOptions: {
                                        columns: ':not(.no-export)'
                                    }
                                }
                            ],
                            language: {
                                processing: '<div class="spinner-border spinner-border-sm" role="status"></div> Loading campaigns...',
                                emptyTable: "No campaigns available",
                                zeroRecords: "No matching campaigns found"
                            }
                        });

                        // Move export buttons to header
                        var excelBtn = table.button('.buttons-excel').node();
                        var printBtn = table.button('.buttons-print').node();
                        
                        if (excelBtn) {
                            $('#exportExcelBtn').on('click', function() {
                                $(excelBtn).click();
                            }).show();
                        }
                        if (printBtn) {
                            $('#exportPrintBtn').on('click', function() {
                                $(printBtn).click();
                            }).show();
                        }
                        
                        // Handle group dropdown changes (event delegation)
                        $(document).on('change', '.campaign-group-select', function() {
                            var $select = $(this);
                            var campaignId = $select.data('campaign-id');
                            var selectedValue = $select.val();
                            
                            if (selectedValue === '__NEW__') {
                                // Open create group modal
                                var modal = new bootstrap.Modal(document.getElementById('createGroupModal'));
                                modal.show();
                                
                                // Store the select element and campaign ID for later use
                                $('#createGroupModal').data('select-element', $select);
                                $('#createGroupModal').data('campaign-id', campaignId);
                                
                                // Reset dropdown
                                $select.val('');
                            } else {
                                // Update campaign group
                                updateCampaignGroup(campaignId, selectedValue, $select);
                            }
                        });
                        
                        // Handle ad type dropdown changes (event delegation)
                        $(document).on('change', '.campaign-ad-type-select', function() {
                            var $select = $(this);
                            var campaignId = $select.data('campaign-id');
                            var selectedValue = $select.val();
                            
                            if (selectedValue === '__NEW__') {
                                // Open create ad type modal
                                var modal = new bootstrap.Modal(document.getElementById('createAdTypeModal'));
                                modal.show();
                                
                                // Store the select element and campaign ID for later use
                                $('#createAdTypeModal').data('select-element', $select);
                                $('#createAdTypeModal').data('campaign-id', campaignId);
                                
                                // Reset dropdown
                                $select.val('');
                            } else {
                                // Update campaign ad type
                                updateCampaignAdType(campaignId, selectedValue, $select);
                            }
                        });
                        
                        // Handle parent dropdown changes (event delegation)
                        $(document).on('change', '.campaign-parent-select', function() {
                            var $select = $(this);
                            var campaignId = $select.data('campaign-id');
                            var selectedValue = $select.val();
                            
                            // Update campaign parent
                            updateCampaignParent(campaignId, selectedValue, $select);
                        });
                        
                        // Handle create group form submission
                        $('#createGroupForm').on('submit', function(e) {
                            e.preventDefault();
                            
                            var formData = {
                                name: $('#groupName').val()
                            };
                            
                            $.ajax({
                                url: '{{ route("meta.ads.manager.campaigns.groups.store") }}',
                                method: 'POST',
                                data: formData,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Add new group to the list
                                        groups.push(response.group);
                                        
                                        // Close modal
                                        var modal = bootstrap.Modal.getInstance(document.getElementById('createGroupModal'));
                                        modal.hide();
                                        
                                        // Clear form
                                        $('#createGroupForm')[0].reset();
                                        
                                        // Get the stored select element and campaign ID
                                        var $select = $('#createGroupModal').data('select-element');
                                        var campaignId = $('#createGroupModal').data('campaign-id');
                                        
                                        // Update the campaign with the new group
                                        if ($select && campaignId) {
                                            updateCampaignGroup(campaignId, response.group, $select);
                                        }
                                        
                                        // Reload table to show new group in all dropdowns
                                        table.ajax.reload();
                                        
                                        // Show success message
                                        alert('Group created and assigned successfully!');
                                    }
                                },
                                error: function(xhr) {
                                    var errorMsg = 'Failed to create group';
                                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg = xhr.responseJSON.message;
                                    }
                                    alert(errorMsg);
                                }
                            });
                        });
                        
                        // Handle create ad type form submission
                        $('#createAdTypeForm').on('submit', function(e) {
                            e.preventDefault();
                            
                            var formData = {
                                name: $('#adTypeName').val()
                            };
                            
                            $.ajax({
                                url: '{{ route("meta.ads.manager.campaigns.ad-types.store") }}',
                                method: 'POST',
                                data: formData,
                                headers: {
                                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Add new ad type to the list
                                        adTypes.push(response.ad_type);
                                        
                                        // Close modal
                                        var modal = bootstrap.Modal.getInstance(document.getElementById('createAdTypeModal'));
                                        modal.hide();
                                        
                                        // Clear form
                                        $('#createAdTypeForm')[0].reset();
                                        
                                        // Get the stored select element and campaign ID
                                        var $select = $('#createAdTypeModal').data('select-element');
                                        var campaignId = $('#createAdTypeModal').data('campaign-id');
                                        
                                        // Update the campaign with the new ad type
                                        if ($select && campaignId) {
                                            updateCampaignAdType(campaignId, response.ad_type, $select);
                                        }
                                        
                                        // Reload table to show new ad type in all dropdowns
                                        table.ajax.reload();
                                        
                                        // Show success message
                                        alert('Ad Type created and assigned successfully!');
                                    }
                                },
                                error: function(xhr) {
                                    var errorMsg = 'Failed to create ad type';
                                    if (xhr.responseJSON && xhr.responseJSON.errors) {
                                        errorMsg = Object.values(xhr.responseJSON.errors).flat().join(', ');
                                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                                        errorMsg = xhr.responseJSON.message;
                                    }
                                    alert(errorMsg);
                                }
                            });
                        });
                    });
                } catch(e) {
                    console.error('Error initializing table:', e);
                    alert('Error initializing table: ' + e.message);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    loadScriptsSequentially(0);
                });
            } else {
                loadScriptsSequentially(0);
            }
        })();
    </script>
@endsection
