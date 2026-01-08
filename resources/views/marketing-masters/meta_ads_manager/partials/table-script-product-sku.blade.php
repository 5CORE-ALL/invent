<script>
    $(document).ready(function() {
        var table = new Tabulator("#budget-under-table", {
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
                        
                        if (isGroupHeader) {
                            return `
                                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                    <span style="flex: 1; text-align: left;">${value}</span>
                                    <span class="group-toggle-icon" style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 6px; background-color: #f8f9fa; border: 1.5px solid #dee2e6; transition: all 0.3s ease; margin-left: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <i class="fas fa-chevron-down" style="font-size: 11px; color: #495057; transition: all 0.3s ease; font-weight: 600;"></i>
                                    </span>
                                </div>
                            `;
                        }
                        
                        return `<span style="padding-left: 20px;">${value}</span>`;
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
                    title: "SKU",
                    field: "sku",
                    minWidth: 200,
                    headerSort: true
                },
                {
                    title: "INV",
                    field: "inv",
                    minWidth: 100,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    }
                },
                {
                    title: "OV L30",
                    field: "ov_l30",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value ? parseInt(value).toLocaleString() : '0';
                    }
                },
                {
                    title: "DIL%",
                    field: "dil_percent",
                    minWidth: 120,
                    headerSort: true,
                    formatter: function(cell) {
                        let value = parseInt(cell.getValue()) || 0;
                        let dil = value;
                        let color = "";

                        if (value < 50) {
                            color = "red";
                        } else if (value >= 50 && value <= 80) {
                            color = "green";
                        } else if (value > 80) {
                            color = "pink";
                        }

                        if (color == "pink") {
                            return `
                                <span class="dil-percent-value ${color}">
                                    ${dil}%
                                </span>
                            `;
                        } else {
                            return `
                                <span style="font-weight:600; color:${color};">
                                    ${dil}%
                                </span>
                            `;
                        }
                    }
                },
                {
                    title: "CAMPAIGN",
                    field: "campaign",
                    minWidth: 200,
                    headerSort: true,
                    formatter: function(cell) {
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
                return response.data;
            }
        });

        // Function to organize groups after data is loaded
        function organizeGroups() {
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
                
                // Second pass: mark first row of each group and hide others
                Object.keys(groupMap).forEach(group => {
                    const groupRows = groupMap[group];
                    
                    if (groupRows.length > 1) {
                        // Sort by index to ensure proper order
                        groupRows.sort((a, b) => a.index - b.index);
                        
                        // Mark first row as header
                        groupRows[0].element.classList.add('group-header-row');
                        groupRows[0].element.classList.remove('group-child-row');
                        groupRows[0].element.classList.remove('expanded');
                        
                        // Mark and hide other rows
                        for (let i = 1; i < groupRows.length; i++) {
                            groupRows[i].element.classList.add('group-child-row');
                            groupRows[i].element.classList.remove('group-header-row');
                            groupRows[i].element.style.display = 'none';
                        }
                        
                        // Redraw the first row to update the formatter (show arrow icon)
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
            organizeGroups();
            
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
                setTimeout(organizeGroups, 100);
            });
            table.on("pageLoaded", function() {
                updateCampaignStats();
                setTimeout(organizeGroups, 100);
            });
            table.on("dataProcessed", function() {
                updateCampaignStats();
                setTimeout(organizeGroups, 100);
            });
            table.on("dataLoaded", function() {
                setTimeout(organizeGroups, 100);
            });

            $("#global-search").on("keyup", function() {
                table.setFilter(combinedFilter);
            });

            $("#inv-filter").on("change", function() {
                table.setFilter(combinedFilter);
            });

            updateCampaignStats();
        });

        document.body.style.zoom = "70%";
        
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
    });
</script>

