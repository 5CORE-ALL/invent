<script>
    $(document).ready(function() {
        var table;
        var availableGroups = [];
        
        // Fetch groups from Group Masters
        function loadGroups() {
            $.ajax({
                url: "{{ route('group.master.groups') }}",
                method: 'GET',
                        success: function(response) {
                            if (response.success && response.groups) {
                                availableGroups = response.groups;
                                const groupFilter = $('#group-filter');
                                groupFilter.empty();
                                groupFilter.append('<option value="">All Groups</option>');
                                response.groups.forEach(function(group) {
                                    groupFilter.append(`<option value="${group.group_name}">${group.group_name}</option>`);
                                });
                                
                                // Refresh all group header dropdowns
                                if (table) {
                                    table.redraw(true);
                                }
                            }
                        },
                error: function(xhr, status, error) {
                    console.error('Error loading groups:', error);
                }
            });
        }
        
        // Load groups on page load
        loadGroups();
        
        // Load groups in form dropdown - with saved groups from database
        function loadGroupsInForm() {
            const formGroupSelect = $('#form-group');
            formGroupSelect.empty();
            formGroupSelect.append('<option value="">Select Group...</option>');
            
            // Add default groups first
            formGroupSelect.append('<option value="DRUM THRONE">DRUM THRONE</option>');
            formGroupSelect.append('<option value="KB BENCH">KB BENCH</option>');
            
            // Fetch saved groups from database
            $.ajax({
                url: "{{ route('meta.ads.group.list') }}",
                method: 'GET',
                success: function(response) {
                    if (response.success && response.groups) {
                        // Get default groups to avoid duplicates
                        const defaultGroups = ['DRUM THRONE', 'KB BENCH'];
                        
                        // Add saved groups (excluding defaults)
                        response.groups.forEach(function(group) {
                            const groupName = group.group_name || group;
                            if (defaultGroups.indexOf(groupName) === -1) {
                                formGroupSelect.find('option[value="__add_new__"]').before(
                                    `<option value="${groupName}">${groupName}</option>`
                                );
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading saved groups:', error);
                }
            });
            
            // Add "+ NEW GROUP" option at the end
            formGroupSelect.append('<option value="__add_new__">+ NEW GROUP</option>');
        }
        
        // Load groups in form on page load
        loadGroupsInForm();
        
        // Handle modal show event to reload groups
        $('#newCampaignModal').on('show.bs.modal', function() {
            loadGroupsInForm();
        });
        
        // Handle "+ NEW GROUP" selection in form
        $('#form-group').on('change', function() {
            const selectedValue = $(this).val();
            if (selectedValue === '__add_new__') {
                const newGroupName = prompt('Enter new group name:');
                if (!newGroupName || !newGroupName.trim()) {
                    $(this).val('');
                    return;
                }
                
                // Create new group via API
                $.ajax({
                    url: "{{ route('meta.ads.group.store') }}",
                    method: 'POST',
                    data: {
                        group_name: newGroupName.trim(),
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Reload the form dropdown to include the new group
                            loadGroupsInForm();
                            
                            // Set the newly created group as selected
                            setTimeout(function() {
                                $('#form-group').val(newGroupName.trim());
                            }, 100);
                            
                            // Also reload groups for filter and header dropdowns
                            loadGroups();
                        } else {
                            alert('Error: ' + (response.message || 'Failed to create group'));
                            $('#form-group').val('');
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Error creating group';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        alert(errorMsg);
                        $('#form-group').val('');
                    }
                });
            }
        });
        
        // Handle form submission via save button
        $('#save-campaign-btn').on('click', function() {
            const form = $('#campaign-form');
            
            // Trigger form validation
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return;
            }
            
            // Prevent default form submission
            const e = { preventDefault: function() {} };
            
            const formData = {
                group: $('#form-group').val(),
                l_page: $('#form-l-page').val(),
                purpose: $('#form-purpose').val(),
                audience: $('#form-audience').val(),
                campaign: $('#form-campaign').val(),
                campaign_id: $('#form-campaign-id').val(),
                _token: '{{ csrf_token() }}'
            };
            
            if (!formData.group) {
                alert('Please select a group');
                return;
            }
            
            // Disable submit button
            const submitBtn = $('#save-campaign-btn');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-2"></i>Saving...');
            
            $.ajax({
                url: "{{ route('meta.ads.facebook.carousal.new.store') }}",
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Add row to table
                        if (table && response.data) {
                            const newRowData = {
                                group: response.data.group,
                                l_page: response.data.l_page || '',
                                purpose: response.data.purpose || '',
                                audience: response.data.audience || '',
                                ad_type: response.data.ad_type || '',
                                campaign_id: response.data.campaign_id || '',
                                campaign: response.data.campaign || '',
                                bgt: response.data.bgt || 0,
                                imp_l30: response.data.imp_l30 || 0,
                                spend_l30: response.data.spend_l30 || 0,
                                clks_l30: response.data.clks_l30 || 0,
                                ad_sls_l30: response.data.ad_sls_l30 || 0,
                                ad_sld_l30: response.data.ad_sld_l30 || 0,
                                acos_l30: response.data.acos_l30 || 0,
                                cvr_l30: response.data.cvr_l30 || 0,
                                status: response.data.status || ''
                            };
                            
                            table.addRow(newRowData, true).then(function() {
                                // Reorganize groups
                                setTimeout(function() {
                                    if (window.organizeGroups) {
                                        window.organizeGroups();
                                    }
                                }, 100);
                                
                                // Reset form and close modal
                                $('#campaign-form')[0].reset();
                                $('#newCampaignModal').modal('hide');
                                alert('Campaign created successfully!');
                            });
                        }
                    } else {
                        alert('Error: ' + (response.message || 'Failed to create campaign'));
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Error creating campaign';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    alert(errorMsg);
                },
                complete: function() {
                    // Re-enable submit button
                    submitBtn.prop('disabled', false).html('<i class="fa fa-save me-2"></i>Save Campaign');
                }
            });
        });
        
        // Reset form when modal is closed
        $('#newCampaignModal').on('hidden.bs.modal', function() {
            $('#campaign-form')[0].reset();
            $('#save-campaign-btn').prop('disabled', false).html('<i class="fa fa-save me-2"></i>Save Campaign');
        });
        
        table = new Tabulator("#budget-under-table", {
            ajaxURL: dataUrl,
            layout: "fitDataStretch",
            pagination: false,
            paginationMode: "local",
            height: "auto",
            placeholder: "No Data Available",
            rowFormatter: function(row) {
                const data = row.getData();
                const group = data.group || '';
                const rowElement = row.getElement();
                
                if (group) {
                    rowElement.setAttribute('data-group', group);
                }
            },
            columns: [
                {
                    title: "AD TYPE",
                    field: "ad_type",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value || '';
                    },
                    editor: "input"
                },
                {
                    title: "Group",
                    field: "group",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const row = cell.getRow();
                        const group = value || '';
                        
                        if (!group) {
                            return '<span class="text-muted">No Group</span>';
                        }
                        
                        // Check if this row is marked as group header
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        const isGroupChild = rowElement.classList.contains('group-child-row');
                        
                        if (isGroupHeader) {
                            // Get all available groups - start with default groups
                            const defaultGroups = ['KB BENCH', 'DRUM THRONE'];
                            const allGroups = [...defaultGroups];
                            
                            // Add groups from API if available
                            if (availableGroups && availableGroups.length > 0) {
                                availableGroups.forEach(function(g) {
                                    const groupName = g.group_name || g;
                                    if (allGroups.indexOf(groupName) === -1) {
                                        allGroups.push(groupName);
                                    }
                                });
                            }
                            
                            // Sort groups alphabetically
                            allGroups.sort();
                            
                            // Generate dropdown items HTML
                            let dropdownItemsHtml = '';
                            allGroups.forEach(function(groupName) {
                                // Check if group is default (case-insensitive)
                                const isDefault = defaultGroups.some(function(dg) {
                                    return dg.toLowerCase() === groupName.toLowerCase();
                                });
                                
                                // Create delete button for non-default groups
                                let deleteBtn = '';
                                if (!isDefault) {
                                    deleteBtn = `<button type="button" class="btn-delete-group" data-group="${groupName}" onclick="event.stopPropagation(); event.preventDefault(); window.deleteGroup('${groupName}', this);" style="background: transparent; border: 1px solid #dc3545; color: #dc3545; cursor: pointer; padding: 2px 6px; font-size: 10px; border-radius: 3px; display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; margin-left: 8px; transition: all 0.2s; opacity: 0.8;" onmouseover="this.style.opacity='1'; this.style.backgroundColor='#fee'; this.style.borderColor='#c82333';" onmouseout="this.style.opacity='0.8'; this.style.backgroundColor='transparent'; this.style.borderColor='#dc3545';" title="Delete group"><i class="fas fa-times" style="font-size: 9px;"></i></button>`;
                                }
                                
                                const activeClass = groupName === value ? 'active' : '';
                                dropdownItemsHtml += `
                                    <div class="group-dropdown-item ${activeClass}" data-value="${groupName}" style="display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; cursor: pointer; transition: background-color 0.2s; border-bottom: 1px solid #f0f0f0;">
                                        <span style="flex: 1; font-weight: ${groupName === value ? '600' : '400'}; color: ${groupName === value ? '#007bff' : '#212529'};">${groupName}</span>
                                        ${deleteBtn}
                                    </div>
                                `;
                            });
                            dropdownItemsHtml += `
                                <div class="group-dropdown-item" data-value="__add_new__" style="display: flex; align-items: center; padding: 8px 12px; cursor: pointer; transition: background-color 0.2s; border-top: 2px solid #e0e0e0; background-color: #f8f9fa; font-weight: 600; color: #007bff;">
                                    <span><i class="fas fa-plus me-2"></i>+ Add New Group</span>
                                </div>
                            `;
                            
                            const uniqueId = 'group-dropdown-' + Math.random().toString(36).substr(2, 9);
                            
                            return `
                                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 8px; position: relative;">
                                    <div class="group-dropdown-wrapper" style="flex: 1; position: relative;">
                                        <button type="button" class="group-dropdown-btn form-select form-select-sm" 
                                                data-group="${value}" 
                                                data-dropdown-id="${uniqueId}"
                                                onclick="event.stopPropagation(); window.toggleGroupDropdown('${uniqueId}');"
                                                style="width: 100%; min-width: 120px; font-weight: 600; text-align: left; background: white; border: 1px solid #ced4da; padding: 6px 12px; cursor: pointer; display: flex; align-items: center; justify-content: space-between;">
                                            <span>${value}</span>
                                            <i class="fas fa-chevron-down" style="font-size: 10px; margin-left: 8px;"></i>
                                        </button>
                                        <div id="${uniqueId}" class="group-dropdown-menu" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ced4da; border-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; max-height: 300px; overflow-y: auto; margin-top: 4px;">
                                            ${dropdownItemsHtml}
                                        </div>
                                    </div>
                                    <span class="group-toggle-icon" style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background-color: #f8f9fa; border: 1.5px solid #dee2e6; transition: all 0.3s ease; box-shadow: 0 2px 4px rgba(0,0,0,0.05); flex-shrink: 0;">
                                        <i class="fas fa-chevron-down" style="font-size: 11px; color: #495057; transition: all 0.3s ease; font-weight: 600;"></i>
                                    </span>
                                </div>
                            `;
                        }
                        
                        // For child rows, show empty (group name only in header)
                        if (isGroupChild) {
                            return '';
                        }
                        
                        return `<span>${value}</span>`;
                    },
                    cellClick: function(e, cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const group = (cell.getValue() || '').trim();
                        
                        // Only handle clicks on group header rows with toggle icons
                        if (!rowElement || !rowElement.classList.contains('group-header-row') || !group) {
                            return;
                        }
                        
                        // Check if click was on the icon or the group text
                        const clickedElement = e.target;
                        const toggleIcon = rowElement.querySelector('.group-toggle-icon');
                        
                        // Only proceed if clicking on the icon area or group text
                        if (!toggleIcon && !clickedElement.closest('.group-toggle-icon')) {
                            // Allow clicking elsewhere in the cell, but check if it's the group header
                            if (!clickedElement.closest('.tabulator-cell') || 
                                !rowElement.classList.contains('group-header-row')) {
                                return;
                            }
                        }
                        
                        const icon = rowElement.querySelector('.group-toggle-icon i');
                        if (!icon) {
                            return;
                        }
                        
                        const isExpanded = rowElement.classList.toggle('expanded');
                        const toggleButton = icon.closest('.group-toggle-icon');
                        
                        // Change icon and button style
                        if (isExpanded) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-up');
                            if (toggleButton) {
                                toggleButton.style.backgroundColor = '#007bff';
                                toggleButton.style.borderColor = '#007bff';
                                toggleButton.style.boxShadow = '0 2px 8px rgba(0, 123, 255, 0.3)';
                                icon.style.color = '#ffffff';
                            }
                        } else {
                            icon.classList.remove('fa-chevron-up');
                            icon.classList.add('fa-chevron-down');
                            if (toggleButton) {
                                toggleButton.style.backgroundColor = '#f8f9fa';
                                toggleButton.style.borderColor = '#dee2e6';
                                toggleButton.style.boxShadow = '0 2px 4px rgba(0,0,0,0.05)';
                                icon.style.color = '#495057';
                            }
                        }
                        
                        // Show/hide all rows with the same group
                        const allRows = table.getRows();
                        allRows.forEach(r => {
                            try {
                                const rData = r.getData();
                                const rElement = r.getElement();
                                const rGroup = (rData.group || '').trim();
                                
                                if (rGroup === group && rElement && rElement.classList.contains('group-child-row')) {
                                    rElement.style.display = isExpanded ? 'table-row' : 'none';
                                }
                            } catch(err) {
                                console.error('Error toggling row:', err);
                            }
                        });
                        
                        e.stopPropagation();
                    }
                },
                {
                    title: "L Page",
                    field: "l_page",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value || '';
                    },
                    editor: "input"
                },
                {
                    title: "Purpose",
                    field: "purpose",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value || '';
                    },
                    editor: "input"
                },
                {
                    title: "Audience",
                    field: "audience",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value || '';
                    },
                    editor: "input"
                },
                {
                    title: "Campaign ID",
                    field: "campaign_id",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value || '';
                    },
                    editor: "input"
                },
                {
                    title: "CAMPAIGN",
                    field: "campaign",
                    minWidth: 200,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value || '';
                    }
                },
                {
                    title: "BGT",
                    field: "bgt",
                    minWidth: 100,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    }
                },
                {
                    title: "IMP L30",
                    field: "imp_l30",
                    minWidth: 135,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    }
                },
                {
                    title: "SPEND L30",
                    field: "spend_l30",
                    width: 155,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    }
                },
                {
                    title: "CLKS L30",
                    field: "clks_l30",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    }
                },
                {
                    title: "AD SLS L30",
                    field: "ad_sls_l30",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    }
                },
                {
                    title: "AD SLD L30",
                    field: "ad_sld_l30",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    }
                },
                {
                    title: "ACOS L30",
                    field: "acos_l30",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) + '%' : '0.00%';
                    }
                },
                {
                    title: "CVR L30",
                    field: "cvr_l30",
                    width: 145,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        let value = parseFloat(cell.getValue()) || 0;
                        let cvr = Number.isInteger(value) ? value.toFixed(0) : value.toFixed(1);
                        let color = "";

                        if (value < 5) {
                            color = "red";
                        } else if (value >= 5 && value <= 10) {
                            color = "green";
                        } else if (value > 10) {
                            color = "pink";
                        }

                        if (color == "pink") {
                            return `
                                <span class="dil-percent-value ${color}">
                                    ${cvr}%
                                </span>
                            `;
                        } else {
                            return `
                                <span style="font-weight:600; color:${color};">
                                    ${cvr}%
                                </span>
                            `;
                        }
                    }
                },
                {
                    title: "STATUS",
                    field: "status",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const row = cell.getRow();
                        const rowElement = row.getElement();
                        const isGroupHeader = rowElement.classList.contains('group-header-row');
                        
                        if (isGroupHeader) {
                            return '';
                        }
                        
                        const value = cell.getValue() || '';
                        let bgColor = '#6c757d';
                        let displayText = value || '';

                        if (value === 'ACTIVE' || value === 'active') {
                            bgColor = '#28a745';
                            displayText = 'Active';
                        } else if (value === 'INACTIVE' || value === 'inactive') {
                            bgColor = '#dc3545';
                            displayText = 'Inactive';
                        } else if (value === 'NOT_DELIVERING' || value === 'not_delivering') {
                            bgColor = '#ffc107';
                            displayText = 'Not Delivering';
                        }
                        
                        if (displayText) {
                            return `<span class="badge" style="background-color: ${bgColor}; color: white; font-size: 0.85rem; padding: 6px 12px;">${displayText}</span>`;
                        }
                        return '';
                    }
                }
            ],
            ajaxResponse: function(url, params, response) {
                // Sort data by group name alphabetically, then by SKU
                if (response.data && Array.isArray(response.data)) {
                    response.data.sort((a, b) => {
                        const groupA = (a.group || '').trim().toLowerCase();
                        const groupB = (b.group || '').trim().toLowerCase();
                        
                        // First sort by group (alphabetically)
                        if (groupA !== groupB) {
                            if (!groupA) return 1; // No group goes to end
                            if (!groupB) return -1;
                            return groupA.localeCompare(groupB);
                        }
                        
                        // If same group, sort by SKU
                        const skuA = (a.sku || '').toLowerCase();
                        const skuB = (b.sku || '').toLowerCase();
                        return skuA.localeCompare(skuB);
                    });
                }
                return response.data;
            }
        });

        // Function to organize groups after data is loaded (make it accessible globally)
        window.organizeGroups = function() {
            try {
                const allRows = table.getRows();
                if (!allRows || allRows.length === 0) {
                    return;
                }
                
                const groupMap = {};
                
                // First pass: identify all groups and their rows
                allRows.forEach((row, index) => {
                    try {
                        const data = row.getData();
                        const group = (data.group || '').trim();
                        const rowElement = row.getElement();
                        
                        if (group && rowElement) {
                            if (!groupMap[group]) {
                                groupMap[group] = [];
                            }
                            groupMap[group].push({ row: row, index: index, element: rowElement });
                        }
                    } catch(e) {
                        console.error('Error processing row:', e);
                    }
                });
                
                // Sort group names alphabetically
                const sortedGroupNames = Object.keys(groupMap).sort((a, b) => {
                    return a.localeCompare(b, undefined, { sensitivity: 'base' });
                });
                
                // Second pass: mark first row of each group and hide others (process in alphabetical order)
                sortedGroupNames.forEach(group => {
                    const groupRows = groupMap[group];
                    
                    if (groupRows.length > 1) {
                        // Sort by index to ensure proper order
                        groupRows.sort((a, b) => a.index - b.index);
                        
                        // Mark first row as header (only this row will show group name)
                        groupRows[0].element.classList.add('group-header-row');
                        groupRows[0].element.classList.remove('group-child-row');
                        groupRows[0].element.classList.remove('expanded');
                        groupRows[0].element.style.fontWeight = '600';
                        groupRows[0].element.style.backgroundColor = '#e3f2fd';
                        
                        // Mark and hide other rows (these won't show group name)
                        for (let i = 1; i < groupRows.length; i++) {
                            groupRows[i].element.classList.add('group-child-row');
                            groupRows[i].element.classList.remove('group-header-row');
                            groupRows[i].element.style.display = 'none';
                            groupRows[i].element.style.fontWeight = 'normal';
                            // Ensure group name is not shown in child rows
                            groupRows[i].row.reformat();
                        }
                        
                        // Redraw the first row to update the formatter (show arrow icon and hide data)
                        groupRows[0].row.reformat();
                    } else if (groupRows.length === 1) {
                        // Single row in group, no need for collapse
                        groupRows[0].element.classList.remove('group-header-row', 'group-child-row', 'expanded');
                        groupRows[0].row.reformat();
                    }
                });
            } catch(e) {
                console.error('Error organizing groups:', e);
            }
        }

        table.on("tableBuilt", function() {
            if (window.organizeGroups) window.organizeGroups();
            
            function combinedFilter(data) {
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.sku?.toLowerCase().includes(searchVal) || data.group?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                // INV filter
                let invFilter = $("#inv-filter").val() || "";
                if (invFilter) {
                    let inv = parseInt(data.inv) || 0;
                    
                    if (invFilter === "0") {
                        if (inv !== 0) return false;
                    } else if (invFilter === ">0") {
                        if (inv <= 0) return false;
                    } else if (invFilter === "1-10") {
                        if (inv < 1 || inv > 10) return false;
                    } else if (invFilter === "11-50") {
                        if (inv < 11 || inv > 50) return false;
                    } else if (invFilter === "51-100") {
                        if (inv < 51 || inv > 100) return false;
                    } else if (invFilter === ">100") {
                        if (inv <= 100) return false;
                    }
                }

                return true;
            }

            table.setFilter(combinedFilter);

            function updateCampaignStats() {
                let allRows = table.getData();
                let filteredRows = allRows.filter(combinedFilter);

                let total = allRows.length;
                let filtered = filteredRows.length;

                let percentage = total > 0 ? ((filtered / total) * 100).toFixed(0) : 0;

                document.getElementById("total-campaigns").innerText = filtered;
                document.getElementById("percentage-campaigns").innerText = percentage + "%";
            }

            table.on("dataFiltered", function() {
                updateCampaignStats();
                setTimeout(function() {
                    if (window.organizeGroups) window.organizeGroups();
                }, 100);
            });
            table.on("pageLoaded", function() {
                updateCampaignStats();
                setTimeout(function() {
                    if (window.organizeGroups) window.organizeGroups();
                }, 100);
            });
            table.on("dataProcessed", function() {
                updateCampaignStats();
                setTimeout(function() {
                    if (window.organizeGroups) window.organizeGroups();
                }, 100);
            });
            table.on("dataLoaded", function() {
                setTimeout(function() {
                    if (window.organizeGroups) window.organizeGroups();
                }, 100);
            });

            $("#global-search").on("keyup", function() {
                table.setFilter(combinedFilter);
            });

            $("#inv-filter").on("change", function() {
                table.setFilter(combinedFilter);
            });
            
            // Handle group filter
            $("#group-filter").on("change", function() {
                table.setFilter(combinedFilter);
            });
            
            // Note: Group dropdown change is now handled by custom dropdown item click handler

            updateCampaignStats();
        });

        // Function to update group for all rows in a group
        window.updateGroupForAllRows = function(oldGroup, newGroup) {
            if (!table) return;
            
            const allRows = table.getRows();
            const rowsToUpdate = [];
            
            // Find all rows with the old group
            allRows.forEach(function(row) {
                const data = row.getData();
                if ((data.group || '').trim() === oldGroup.trim()) {
                    rowsToUpdate.push({ row: row, data: data });
                }
            });
            
            if (rowsToUpdate.length === 0) return;
            
            // Update group in table
            rowsToUpdate.forEach(function(item) {
                const newData = Object.assign({}, item.data);
                newData.group = newGroup;
                item.row.update(newData);
            });
            
            // Update group in database
            $.ajax({
                url: "{{ route('meta.ads.facebook.carousal.new.update.group') }}",
                method: 'POST',
                data: {
                    old_group: oldGroup,
                    new_group: newGroup,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Reload groups list
                        loadGroups();
                        loadGroupsInForm();
                        
                        // Reorganize groups after update
                        setTimeout(function() {
                            if (window.organizeGroups) {
                                window.organizeGroups();
                            }
                        }, 200);
                    }
                },
                error: function(xhr) {
                    console.error('Error updating group in database:', xhr);
                    alert('Error updating group in database. Please refresh the page.');
                }
            });
        };

        // Function to add a new group row
        window.addGroupRow = function(groupName) {
            if (!table) return;
            
            // Check if group already exists
            const existingRows = table.getRows();
            let groupExists = false;
            
            existingRows.forEach(function(row) {
                const data = row.getData();
                if (data.group && data.group.trim() === groupName.trim()) {
                    groupExists = true;
                }
            });
            
            if (groupExists) {
                alert('Group "' + groupName + '" already exists in the table.');
                return;
            }
            
            // Create new row data with empty values for all fields
            const newRowData = {
                group: groupName,
                l_page: '',
                purpose: '',
                audience: '',
                campaign_id: '',
                campaign: '',
                bgt: 0,
                imp_l30: 0,
                spend_l30: 0,
                clks_l30: 0,
                ad_sls_l30: 0,
                ad_sld_l30: 0,
                acos_l30: 0,
                cvr_l30: 0,
                status: ''
            };
            
            // Add row to table
            table.addRow(newRowData, true).then(function() {
                // Reorganize groups after adding new row
                setTimeout(function() {
                    if (window.organizeGroups) {
                        window.organizeGroups();
                    }
                }, 100);
            }).catch(function(error) {
                console.error('Error adding row:', error);
            });
        };

        document.body.style.zoom = "80%";
        
        // Add hover effects for toggle buttons using event delegation
        $(document).on('mouseenter', '.group-toggle-icon', function() {
            if (!$(this).closest('.group-header-row').hasClass('expanded')) {
                $(this).css({
                    'backgroundColor': '#e9ecef',
                    'borderColor': '#007bff',
                    'boxShadow': '0 2px 6px rgba(0, 123, 255, 0.2)'
                });
                $(this).find('i').css('color', '#007bff');
            }
        });
        
        $(document).on('mouseleave', '.group-toggle-icon', function() {
            const isExpanded = $(this).closest('.group-header-row').hasClass('expanded');
            if (!isExpanded) {
                $(this).css({
                    'backgroundColor': '#f8f9fa',
                    'borderColor': '#dee2e6',
                    'boxShadow': '0 2px 4px rgba(0,0,0,0.05)'
                });
                $(this).find('i').css('color', '#495057');
            }
        });
        
        // Toggle group dropdown
        window.toggleGroupDropdown = function(dropdownId) {
            // Close all other dropdowns
            $('.group-dropdown-menu').not('#' + dropdownId).hide();
            
            // Toggle current dropdown
            $('#' + dropdownId).toggle();
        };
        
        // Handle dropdown item click
        $(document).on('click', '.group-dropdown-item', function(e) {
            // If click was on delete button, don't process this click
            if ($(e.target).closest('.btn-delete-group').length > 0) {
                return;
            }
            
            e.stopPropagation();
            const item = $(this);
            const value = item.data('value');
            const dropdown = item.closest('.group-dropdown-menu');
            const dropdownId = dropdown.attr('id');
            const btn = $('[data-dropdown-id="' + dropdownId + '"]');
            const currentGroup = btn.data('group');
            
            if (value === '__add_new__') {
                dropdown.hide();
                const newGroupName = prompt('Enter new group name:');
                if (newGroupName && newGroupName.trim()) {
                    // Create new group via API
                    $.ajax({
                        url: "{{ route('meta.ads.group.store') }}",
                        method: 'POST',
                        data: {
                            group_name: newGroupName.trim(),
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Reload groups and update table
                                loadGroups();
                                loadGroupsInForm();
                                
                                // Update group for all rows
                                if (window.updateGroupForAllRows) {
                                    window.updateGroupForAllRows(currentGroup, newGroupName.trim());
                                }
                                
                                // Refresh table
                                setTimeout(function() {
                                    table.replaceData();
                                }, 500);
                            } else {
                                alert('Error: ' + (response.message || 'Failed to create group'));
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'Error creating group';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMsg = xhr.responseJSON.message;
                            }
                            alert(errorMsg);
                        }
                    });
                }
            } else {
                // Update group
                dropdown.hide();
                if (value !== currentGroup) {
                    if (window.updateGroupForAllRows) {
                        window.updateGroupForAllRows(currentGroup, value);
                    }
                }
            }
        });
        
        // Close dropdowns when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.group-dropdown-wrapper').length) {
                $('.group-dropdown-menu').hide();
            }
        });
        
        // Delete group function
        window.deleteGroup = function(groupName, deleteBtn) {
            if (!confirm('Are you sure you want to delete the group "' + groupName + '"? All campaigns in this group will be ungrouped.')) {
                return;
            }
            
            // Disable delete button
            $(deleteBtn).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: "{{ route('meta.ads.group.delete') }}",
                method: 'DELETE',
                data: {
                    group_name: groupName,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Reload groups
                        loadGroups();
                        loadGroupsInForm();
                        
                        // Update rows with this group - set group to empty/null
                        const allRows = table.getRows();
                        const rowsToUpdate = [];
                        
                        allRows.forEach(function(row) {
                            const data = row.getData();
                            if ((data.group || '').trim() === groupName.trim()) {
                                rowsToUpdate.push(row);
                            }
                        });
                        
                        // Update rows to remove group
                        rowsToUpdate.forEach(function(row) {
                            const rowData = row.getData();
                            rowData.group = '';
                            row.update(rowData);
                        });
                        
                        // Refresh table and reorganize
                        setTimeout(function() {
                            if (window.organizeGroups) {
                                window.organizeGroups();
                            }
                            // Reload table data to reflect changes
                            table.replaceData();
                        }, 200);
                        
                        alert('Group deleted successfully! All campaigns have been ungrouped.');
                    } else {
                        alert('Error: ' + (response.message || 'Failed to delete group'));
                        $(deleteBtn).prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Error deleting group';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    alert(errorMsg);
                    $(deleteBtn).prop('disabled', false).html('<i class="fas fa-times"></i>');
                }
            });
        };
        
        // Add hover effect for dropdown items
        $(document).on('mouseenter', '.group-dropdown-item', function() {
            if (!$(this).data('value') || $(this).data('value') !== '__add_new__') {
                $(this).css('background-color', '#f8f9fa');
            }
        });
        
        $(document).on('mouseleave', '.group-dropdown-item', function() {
            if (!$(this).hasClass('active')) {
                $(this).css('background-color', 'white');
            }
        });
    });
</script>

