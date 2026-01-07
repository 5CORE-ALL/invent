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
                    field: "group",
                    minWidth: 150,
                    headerSort: true,
                    formatter: function(cell) {
                        const value = cell.getValue();
                        return value || '<span class="text-muted">No Group</span>';
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
                }
            ],
            ajaxResponse: function(url, params, response) {
                return response.data;
            }
        });

        table.on("tableBuilt", function() {
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

            table.on("dataFiltered", updateCampaignStats);
            table.on("pageLoaded", updateCampaignStats);
            table.on("dataProcessed", updateCampaignStats);

            $("#global-search").on("keyup", function() {
                table.setFilter(combinedFilter);
            });

            $("#inv-filter").on("change", function() {
                table.setFilter(combinedFilter);
            });

            updateCampaignStats();
        });

        document.body.style.zoom = "70%";
    });
</script>

