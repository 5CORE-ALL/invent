<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Amazon Ads Push Report Viewer</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        .summary-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .summary-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .summary-item.success {
            border-left-color: #28a745;
        }
        .summary-item.warning {
            border-left-color: #ffc107;
        }
        .summary-item.error {
            border-left-color: #dc3545;
        }
        .summary-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .summary-value {
            font-size: 28px;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .reason-cell {
            max-width: 300px;
            word-wrap: break-word;
        }
        .error-reason {
            color: #dc3545;
            font-weight: 500;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
            transition: background-color 0.3s;
        }
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background-color: #0056b3;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-group {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        .alert-danger {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #333;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        #reportContainer {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Amazon Ads Push Report Viewer</h1>
        
        <div id="alertContainer"></div>
        
        <div id="reportContainer">
            <div class="summary-box">
                <div class="summary-item">
                    <div class="summary-label">Total Submitted</div>
                    <div class="summary-value" id="totalSubmitted">0</div>
                </div>
                <div class="summary-item success">
                    <div class="summary-label">Successfully Processed</div>
                    <div class="summary-value" id="totalProcessed">0</div>
                </div>
                <div class="summary-item warning">
                    <div class="summary-label">Skipped</div>
                    <div class="summary-value" id="totalSkipped">0</div>
                </div>
            </div>

            <div class="btn-group">
                <button class="btn btn-success" onclick="exportToCSV()">📥 Export Skipped to CSV</button>
                <button class="btn btn-primary" onclick="copyToClipboard()">📋 Copy Campaign IDs</button>
            </div>

            <h2>Skipped Campaigns Report</h2>
            <div id="tableContainer">
                <p class="no-data">No skipped campaigns to display.</p>
            </div>
        </div>
    </div>

    <script>
        let currentReport = null;

        // Example: Call this after receiving response from push API
        function displayReport(apiResponse) {
            currentReport = apiResponse;
            
            // Show report container
            document.getElementById('reportContainer').style.display = 'block';
            
            // Update summary
            document.getElementById('totalSubmitted').textContent = apiResponse.total_submitted || 0;
            document.getElementById('totalProcessed').textContent = apiResponse.total_processed || 0;
            document.getElementById('totalSkipped').textContent = apiResponse.total_skipped || 0;
            
            // Show alert
            showAlert(apiResponse);
            
            // Display skipped rows table
            if (apiResponse.skipped_rows && apiResponse.skipped_rows.length > 0) {
                displaySkippedTable(apiResponse.skipped_rows);
            } else {
                document.getElementById('tableContainer').innerHTML = '<p class="no-data">✅ No campaigns were skipped! All data pushed successfully.</p>';
            }
        }

        function showAlert(response) {
            const alertContainer = document.getElementById('alertContainer');
            let alertClass = 'alert-success';
            let message = '';
            
            if (response.ok && response.total_skipped === 0) {
                alertClass = 'alert-success';
                message = `🎉 <strong>Success!</strong> All ${response.total_processed} campaigns were updated successfully.`;
            } else if (response.total_skipped > 0) {
                alertClass = 'alert-warning';
                message = `⚠️ <strong>Partial Success:</strong> ${response.total_processed} campaigns updated, but ${response.total_skipped} were skipped. See details below.`;
            } else if (!response.ok) {
                alertClass = 'alert-danger';
                message = `❌ <strong>Error:</strong> ${response.message || 'Push operation failed.'}`;
            }
            
            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
        }

        function displaySkippedTable(skippedRows) {
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Row #</th>
                            <th>Campaign ID</th>
                            <th>Campaign Name</th>
                            <th>Value</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            skippedRows.forEach(row => {
                const value = row.bid || row.sbgt || 'N/A';
                html += `
                    <tr>
                        <td>${row.index + 1}</td>
                        <td><code>${row.campaign_id || '(empty)'}</code></td>
                        <td>${row.campaign_name || '(not provided)'}</td>
                        <td>${value}</td>
                        <td class="reason-cell"><span class="error-reason">${row.reason}</span></td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            document.getElementById('tableContainer').innerHTML = html;
        }

        function exportToCSV() {
            if (!currentReport || !currentReport.skipped_rows || currentReport.skipped_rows.length === 0) {
                alert('No skipped rows to export');
                return;
            }
            
            // Create CSV content
            let csv = 'Row Index,Campaign ID,Campaign Name,Bid/Budget Value,Reason\n';
            
            currentReport.skipped_rows.forEach(row => {
                const value = row.bid || row.sbgt || '';
                csv += `${row.index + 1},`;
                csv += `"${(row.campaign_id || '').replace(/"/g, '""')}",`;
                csv += `"${(row.campaign_name || '').replace(/"/g, '""')}",`;
                csv += `"${value}",`;
                csv += `"${row.reason.replace(/"/g, '""')}"\n`;
            });
            
            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `amazon_ads_skipped_campaigns_${new Date().toISOString().slice(0,10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            alert('CSV file downloaded successfully!');
        }

        function copyToClipboard() {
            if (!currentReport || !currentReport.skipped_rows || currentReport.skipped_rows.length === 0) {
                alert('No campaign IDs to copy');
                return;
            }
            
            const campaignIds = currentReport.skipped_rows
                .map(row => row.campaign_id)
                .filter(id => id && id.trim() !== '')
                .join('\n');
            
            navigator.clipboard.writeText(campaignIds).then(() => {
                alert(`Copied ${currentReport.skipped_rows.length} campaign IDs to clipboard!`);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy to clipboard');
            });
        }

        // Example usage - simulate API response
        // In your actual implementation, call displayReport() after receiving the API response
        
        // Demo with sample data:
        window.addEventListener('load', function() {
            // Uncomment to test with sample data:
            /*
            const sampleResponse = {
                ok: false,
                message: "SBID push finished with one or more non-success responses",
                total_submitted: 10,
                total_processed: 7,
                total_skipped: 3,
                skipped_rows: [
                    {
                        index: 2,
                        campaign_id: "",
                        campaign_name: "Summer Sale Campaign",
                        bid: 0,
                        reason: "Missing or empty campaign_id"
                    },
                    {
                        index: 5,
                        campaign_id: "123456789",
                        campaign_name: "Winter Products",
                        bid: 0,
                        reason: "Invalid bid (must be positive number > 0)"
                    },
                    {
                        index: 8,
                        campaign_id: "987654321",
                        campaign_name: "Black Friday PT",
                        bid: -1.5,
                        reason: "Invalid bid (must be positive number > 0)"
                    }
                ]
            };
            displayReport(sampleResponse);
            */
        });

        // Integration example for actual API calls:
        function pushAmazonAdsBids(rows, pushType = 'sp-sbids') {
            const endpoints = {
                'sp-sbids': '/amazon-ads/push-sp-sbids',
                'sb-sbids': '/amazon-ads/push-sb-sbids',
                'sp-sbgts': '/amazon-ads/push-sp-sbgts',
                'sb-sbgts': '/amazon-ads/push-sb-sbgts'
            };

            fetch(endpoints[pushType], {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ rows: rows })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Push response:', data);
                displayReport(data);
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('alertContainer').innerHTML = 
                    '<div class="alert alert-danger">❌ <strong>Error:</strong> Failed to push data. ' + error.message + '</div>';
            });
        }

        // Expose function to window for easy integration
        window.displayAmazonAdsReport = displayReport;
        window.pushAmazonAdsBids = pushAmazonAdsBids;
    </script>
</body>
</html>
