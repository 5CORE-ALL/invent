<script>
document.addEventListener("DOMContentLoaded", function() {
    var table = new Tabulator("#budget-under-table", {
        index: "campaign_id",
        ajaxURL: "{{ $dataUrl }}",
        layout: "fitColumns",
        pagination: "local",
        paginationSize: 25,
        movableColumns: true,
        resizableColumns: true,
        columns: @include('marketing-masters.meta_ads_manager.partials.table-columns')
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

    // Sync from Google Sheets
    $('#sync-btn').on('click', function () {
        if (!confirm('This will sync data from Google Sheets. Continue?')) {
            return;
        }

        $('#sync-btn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Syncing...');

        $.ajax({
            url: "{{ route('meta.ads.sync') }}",
            type: "POST",
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            success: function (response) {
                $('#sync-btn').prop('disabled', false).html('<i class="fa fa-sync me-1"></i>Sync from Google Sheets');
                alert('Sync successful!\nL30 synced: ' + response.l30_synced + ' campaigns\nL7 synced: ' + response.l7_synced + ' campaigns');
                table.replaceData();
            },
            error: function (xhr) {
                $('#sync-btn').prop('disabled', false).html('<i class="fa fa-sync me-1"></i>Sync from Google Sheets');
                let message = 'Sync failed';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    message = xhr.responseJSON.error;
                }
                alert(message);
            }
        });
    });

    // Toggle handlers for column visibility
    @include('marketing-masters.meta_ads_manager.partials.toggle-handlers')

    document.body.style.zoom = "70%";
});
</script>
