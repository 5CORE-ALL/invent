<script>
    $(document).ready(function() {
        var table = new Tabulator("#budget-under-table", {
            ajaxURL: dataUrl,
            layout: "fitDataStretch",
            pagination: false,
            paginationMode: "local",
            height: "auto",
            placeholder: "No Data Available",
            columns: [
                {
                    title: "Group",
                    field: "group_name",
                    minWidth: 150,
                    headerSort: true,
                    editor: "input",
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value || '<span class="text-muted">No Group</span>';
                    }
                },
                {
                    title: "Campaign Name",
                    field: "campaign_name",
                    minWidth: 320,
                    headerSort: true
                },
                {
                    title: "Campaign ID",
                    field: "campaign_id",
                    minWidth: 200,
                    headerSort: true
                },
                {
                    title: "BGT",
                    field: "budget",
                    minWidth: 100,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    }
                },
                {
                    title: "IMP L30",
                    field: "impressions_l30",
                    minWidth: 135,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const formatted = value ? parseInt(value).toLocaleString() : '0';
                        return `
                            <span>${formatted}</span>
                            <i class="fa fa-info-circle text-primary toggle-imp-cols-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: "IMP L60",
                    field: "impressions_l60",
                    minWidth: 135,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    },
                    visible: false
                },
                {
                    title: "IMP L7",
                    field: "impressions_l7",
                    minWidth: 135,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    },
                    visible: false
                },
                {
                    title: "SPENT L30",
                    field: "spend_l30",
                    width: 155,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const formatted = value ? parseFloat(value).toFixed(2) : '0.00';
                        return `
                            <span>${formatted}</span>
                            <i class="fa fa-info-circle text-primary toggle-spent-cols-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: "SPENT L60",
                    field: "spend_l60",
                    width: 155,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    },
                    visible: false
                },
                {
                    title: "SPENT L7",
                    field: "spend_l7",
                    width: 155,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    },
                    visible: false
                },
                {
                    title: "CLKS L30",
                    field: "clicks_l30",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const formatted = value ? parseInt(value).toLocaleString() : '0';
                        return `
                            <span>${formatted}</span>
                            <i class="fa fa-info-circle text-primary toggle-clks-cols-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: "CLKS L60",
                    field: "clicks_l60",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    },
                    visible: false
                },
                {
                    title: "CLKS L7",
                    field: "clicks_l7",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    },
                    visible: false
                },
                {
                    title: "AD SLS L30",
                    field: "sales_l30",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const formatted = value ? parseFloat(value).toFixed(2) : '0.00';
                        return `
                            <span>${formatted}</span>
                            <i class="fa fa-info-circle text-primary toggle-sales-cols-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: "AD SLS L60",
                    field: "sales_l60",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    },
                    visible: false
                },
                {
                    title: "AD SLS L7",
                    field: "sales_l7",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    },
                    visible: false
                },
                {
                    title: "AD SLD L30",
                    field: "sales_delivered_l30",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const formatted = value ? parseFloat(value).toFixed(2) : '0.00';
                        return `
                            <span>${formatted}</span>
                            <i class="fa fa-info-circle text-primary toggle-sld-cols-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: "AD SLD L60",
                    field: "sales_delivered_l60",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    },
                    visible: false
                },
                {
                    title: "AD SLD L7",
                    field: "sales_delivered_l7",
                    width: 160,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) : '0.00';
                    },
                    visible: false
                },
                {
                    title: "ACOS L30",
                    field: "acos_l30",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        const formatted = value ? parseFloat(value).toFixed(2) + '%' : '0.00%';
                        return `
                            <span>${formatted}</span>
                            <i class="fa fa-info-circle text-primary toggle-acos-cols-btn" 
                            style="cursor:pointer; margin-left:8px;"></i>
                        `;
                    }
                },
                {
                    title: "ACOS L60",
                    field: "acos_l60",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) + '%' : '0.00%';
                    },
                    visible: false
                },
                {
                    title: "ACOS L7",
                    field: "acos_l7",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseFloat(value).toFixed(2) + '%' : '0.00%';
                    },
                    visible: false
                },
                {
                    title: "CVR L30",
                    field: "cvr_l30",
                    width: 145,
                    headerSort: true,
                    formatter: function(cell) {
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
                                <i class="fa fa-info-circle text-primary toggle-cvr-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        } else {
                            return `
                                <span style="font-weight:600; color:${color};">
                                    ${cvr}%
                                </span>
                                <i class="fa fa-info-circle text-primary toggle-cvr-cols-btn" 
                                style="cursor:pointer; margin-left:8px;"></i>
                            `;
                        }
                    }
                },
                {
                    title: "CVR L60",
                    field: "cvr_l60",
                    width: 145,
                    headerSort: true,
                    formatter: function(cell) {
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
                    },
                    visible: false
                },
                {
                    title: "CVR L7",
                    field: "cvr_l7",
                    width: 145,
                    headerSort: true,
                    formatter: function(cell) {
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
                    },
                    visible: false
                },
                {
                    title: "Status",
                    field: "status",
                    width: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue() || '';
                        let bgColor = '#6c757d';
                        let displayText = value;
                        
                        if (value === 'ACTIVE') {
                            bgColor = '#28a745';
                            displayText = 'Active';
                        } else if (value === 'INACTIVE') {
                            bgColor = '#dc3545';
                            displayText = 'Inactive';
                        } else if (value === 'NOT_DELIVERING') {
                            bgColor = '#ffc107';
                            displayText = 'Not Delivering';
                        }
                        
                        return `<span class="badge" style="background-color: ${bgColor}; color: white; font-size: 0.85rem; padding: 6px 12px;">${displayText}</span>`;
                    }
                }
            ],
            ajaxResponse: function(url, params, response) {
                return response.data;
            }
        });

        table.on("tableBuilt", function() {
            function combinedFilter(data) {
                let searchVal = $("#global-search").val()?.toLowerCase() || "";
                if (searchVal && !(data.campaign_name?.toLowerCase().includes(searchVal))) {
                    return false;
                }

                let statusVal = $("#status-filter").val();
                if (statusVal && data.status !== statusVal) {
                    return false;
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

            table.on("dataFiltered", updateCampaignStats);
            table.on("pageLoaded", updateCampaignStats);
            table.on("dataProcessed", updateCampaignStats);

            $("#global-search").on("keyup", function() {
                table.setFilter(combinedFilter);
            });

            $("#status-filter").on("change", function() {
                table.setFilter(combinedFilter);
            });

            updateCampaignStats();
        });

        // Sync from Meta API
        $('#sync-btn').on('click', function () {
            if (!confirm('This will sync campaign data from Meta API (Facebook & Instagram). Continue?')) {
                return;
            }

            // Show loading state
            $('#sync-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Syncing from Meta API...');

            $.ajax({
                url: "{{ route('meta.ads.sync') }}",
                type: "POST",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function (response) {
                    $('#sync-btn').prop('disabled', false).html('<i class="fa fa-sync me-1"></i>Sync from Meta API');
                    
                    alert('Sync successful!\nL30 synced: ' + response.l30_synced + ' campaigns\nL7 synced: ' + response.l7_synced + ' campaigns');
                    table.replaceData();
                },
                error: function (xhr) {
                    $('#sync-btn').prop('disabled', false).html('<i class="fa fa-sync me-1"></i>Sync from Meta API');
                    
                    let message = 'Sync failed';

                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        message = xhr.responseJSON.error;
                    }

                    alert(message);
                }
            });
        });

        // Toggle handlers for column visibility
        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-imp-cols-btn")) {
                let colsToToggle = ["impressions_l60", "impressions_l7"];
                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-spent-cols-btn")) {
                let colsToToggle = ["spend_l60", "spend_l7"];
                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-clks-cols-btn")) {
                let colsToToggle = ["clicks_l60", "clicks_l7"];
                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-sales-cols-btn")) {
                let colsToToggle = ["sales_l60", "sales_l7"];
                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-sld-cols-btn")) {
                let colsToToggle = ["sales_delivered_l60", "sales_delivered_l7"];
                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-acos-cols-btn")) {
                let colsToToggle = ["acos_l60", "acos_l7"];
                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.addEventListener("click", function(e) {
            if (e.target.classList.contains("toggle-cvr-cols-btn")) {
                let colsToToggle = ["cvr_l60", "cvr_l7"];
                colsToToggle.forEach(colName => {
                    let col = table.getColumn(colName);
                    if (col) {
                        col.toggle();
                    }
                });
            }
        });

        document.body.style.zoom = "70%";

        // Add New Group Modal Handler
        $('#add-group-btn').on('click', function() {
            $('#addGroupModal').modal('show');
        });

        // Submit Group Form
        $('#submitGroupBtn').on('click', function() {
            const groupName = $('#groupName').val().trim();
            
            if (!groupName) {
                alert('Please enter a group name');
                return;
            }

            // Here you can add AJAX call to save the group
            $.ajax({
                url: "{{ route('meta.ads.group.store') }}",
                type: "POST",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: { group_name: groupName },
                success: function(response) {
                    alert('Group created successfully!');
                    $('#addGroupModal').modal('hide');
                    $('#groupName').val('');
                    // Optionally refresh the table or update group dropdown
                },
                error: function(xhr) {
                    let message = 'Failed to create group';
                    if (xhr.responseJSON && xhr.responseJSON.error) {
                        message = xhr.responseJSON.error;
                    }
                    alert(message);
                }
            });
        });

        // Import Button Handler
        $('#import-btn').on('click', function() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.csv,.xlsx,.xls';
            
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                const formData = new FormData();
                formData.append('file', file);
                
                // Show loading state
                $('#import-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Importing...');
                
                $.ajax({
                    url: "{{ route('meta.ads.import') }}",
                    type: "POST",
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $('#import-btn').prop('disabled', false).html('<i class="fa fa-upload me-1"></i>Import');
                        alert('Import successful!');
                        table.replaceData();
                    },
                    error: function(xhr) {
                        $('#import-btn').prop('disabled', false).html('<i class="fa fa-upload me-1"></i>Import');
                        let message = 'Import failed';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            message = xhr.responseJSON.error;
                        }
                        alert(message);
                    }
                });
            };
            
            input.click();
        });

        // Export Button Handler
        $('#export-btn').on('click', function() {
            const data = table.getData();
            
            if (data.length === 0) {
                alert('No data to export');
                return;
            }
            
            // Show loading state
            $('#export-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Exporting...');
            
            $.ajax({
                url: "{{ route('meta.ads.export') }}",
                type: "POST",
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: {},
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(response) {
                    $('#export-btn').prop('disabled', false).html('<i class="fa fa-download me-1"></i>Export');
                    
                    // Create download link
                    const url = window.URL.createObjectURL(response);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'meta_ads_export_' + new Date().toISOString().split('T')[0] + '.xlsx';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    a.remove();
                },
                error: function(xhr) {
                    $('#export-btn').prop('disabled', false).html('<i class="fa fa-download me-1"></i>Export');
                    alert('Export failed');
                }
            });
        });
    });
</script>
