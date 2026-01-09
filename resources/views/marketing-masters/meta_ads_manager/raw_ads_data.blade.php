@extends('layouts.vertical', ['title' => 'Raw Facebook Ads Data', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])
@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .json-container {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            max-height: 80vh;
            overflow: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        .json-key {
            color: #9cdcfe;
        }
        .json-string {
            color: #ce9178;
        }
        .json-number {
            color: #b5cea8;
        }
        .json-boolean {
            color: #569cd6;
        }
        .json-null {
            color: #808080;
        }
        .loading-spinner {
            display: none;
        }
        .loading-spinner.active {
            display: inline-block;
        }
    </style>
@endsection
@section('content')
    @include('layouts.shared.page-title', [
        'page_title' => 'Raw Facebook Ads Data',
        'sub_title' => 'Fetch and display raw data from Facebook/Meta API',
    ])
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold text-primary mb-0">
                                <i class="fa-solid fa-code me-2"></i>
                                Raw Facebook Ads Data from API
                            </h4>
                            <div class="d-flex gap-2">
                                <select id="data-type" class="form-select form-select-md" style="max-width: 200px;">
                                    <option value="ads">Ads</option>
                                    <option value="campaigns">Campaigns</option>
                                    <option value="insights">Insights</option>
                                </select>
                                <select id="date-preset" class="form-select form-select-md" style="max-width: 200px;">
                                    <option value="last_7d">Last 7 Days</option>
                                    <option value="last_30d" selected>Last 30 Days</option>
                                    <option value="last_90d">Last 90 Days</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                                <select id="insight-level" class="form-select form-select-md" style="max-width: 200px;">
                                    <option value="account">Account</option>
                                    <option value="campaign" selected>Campaign</option>
                                    <option value="adset">Ad Set</option>
                                    <option value="ad">Ad</option>
                                </select>
                                <button type="button" class="btn btn-primary" id="fetch-btn">
                                    <i class="fa fa-sync me-2"></i>
                                    <span class="spinner-border spinner-border-sm loading-spinner me-2" role="status" aria-hidden="true"></span>
                                    Fetch Data
                                </button>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle me-2"></i>
                            <strong>Info:</strong> This page fetches raw data directly from the Facebook/Meta API. 
                            The data is displayed in JSON format exactly as returned by the API.
                            <button type="button" class="btn btn-sm btn-outline-info ms-2" id="test-connection-btn">
                                <i class="fa fa-plug me-1"></i>Test API Connection
                            </button>
                        </div>
                    </div>

                    <div id="data-container">
                        <div class="text-center text-muted py-5">
                            <i class="fa fa-database fa-3x mb-3"></i>
                            <p>Click "Fetch Data" to load raw data from Facebook API</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Test API connection
            $('#test-connection-btn').on('click', function() {
                const btn = $(this);
                const originalHtml = btn.html();
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i>Testing...');
                
                $.ajax({
                    url: "{{ route('meta.ads.test.connection') }}",
                    method: 'GET',
                    success: function(response) {
                        if (response.success) {
                            alert('✓ API Connection Successful!\n\nAd Accounts Found: ' + response.ad_accounts_count);
                        } else {
                            alert('✗ API Connection Failed:\n\n' + (response.message || response.error));
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'Connection test failed';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        }
                        alert('✗ API Connection Failed:\n\n' + errorMsg);
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });
            
            // Toggle insight level visibility
            $('#data-type').on('change', function() {
                if ($(this).val() === 'insights') {
                    $('#date-preset, #insight-level').show();
                } else {
                    $('#date-preset, #insight-level').hide();
                }
            });
            
            // Initially hide date preset and level if not insights
            if ($('#data-type').val() !== 'insights') {
                $('#date-preset, #insight-level').hide();
            }

            $('#fetch-btn').on('click', function() {
                const btn = $(this);
                const spinner = btn.find('.loading-spinner');
                const icon = btn.find('.fa-sync');
                
                // Disable button and show spinner
                btn.prop('disabled', true);
                spinner.addClass('active');
                icon.hide();
                
                // Show loading message
                $('#data-container').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="text-muted">Fetching data from Facebook API...</p>
                    </div>
                `);
                
                const type = $('#data-type').val();
                const datePreset = $('#date-preset').val();
                const level = $('#insight-level').val();
                
                $.ajax({
                    url: "{{ route('meta.ads.raw.data') }}",
                    method: 'GET',
                    data: {
                        type: type,
                        date_preset: datePreset,
                        level: level
                    },
                    success: function(response) {
                        if (response.success) {
                            displayRawData(response);
                        } else {
                            $('#data-container').html(`
                                <div class="alert alert-danger">
                                    <i class="fa fa-exclamation-triangle me-2"></i>
                                    <strong>Error:</strong> ${response.message || response.error}
                                </div>
                            `);
                        }
                    },
                    error: function(xhr) {
                        let errorMsg = 'An error occurred while fetching data';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        } else if (xhr.responseText) {
                            try {
                                const errorResponse = JSON.parse(xhr.responseText);
                                errorMsg = errorResponse.message || errorResponse.error || errorMsg;
                            } catch(e) {
                                errorMsg = xhr.responseText.substring(0, 200);
                            }
                        }
                        
                        let errorHtml = `
                            <div class="alert alert-danger">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                <strong>Error:</strong> ${errorMsg}
                            </div>
                        `;
                        
                        // Add helpful tips if it's a credentials error
                        if (errorMsg.includes('not configured') || errorMsg.includes('credentials')) {
                            errorHtml += `
                                <div class="alert alert-info mt-3">
                                    <h6><i class="fa fa-info-circle me-2"></i>Setup Instructions:</h6>
                                    <ul class="mb-0">
                                        <li>Make sure your <code>.env</code> file has the following variables:
                                            <ul>
                                                <li><code>META_ACCESS_TOKEN</code> - Your Facebook API access token</li>
                                                <li><code>META_AD_ACCOUNT_ID</code> - Your ad account ID (numeric, without "act_" prefix)</li>
                                            </ul>
                                        </li>
                                        <li>After updating <code>.env</code>, clear your config cache: <code>php artisan config:clear</code></li>
                                    </ul>
                                </div>
                            `;
                        }
                        
                        $('#data-container').html(errorHtml);
                    },
                    complete: function() {
                        // Re-enable button and hide spinner
                        btn.prop('disabled', false);
                        spinner.removeClass('active');
                        icon.show();
                    }
                });
            });
            
            let currentResponseData = null;
            
            function displayRawData(response) {
                currentResponseData = response.data;
                const jsonString = JSON.stringify(response.data, null, 2);
                const html = `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-success me-2">Type: ${response.type}</span>
                                <span class="badge bg-info me-2">Count: ${response.count}</span>
                                <span class="badge bg-secondary">Fetched: ${response.fetched_at}</span>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="copy-btn">
                                <i class="fa fa-copy me-1"></i>Copy JSON
                            </button>
                        </div>
                    </div>
                    <div class="json-container" id="json-display">
                        <pre id="json-content">${syntaxHighlight(jsonString)}</pre>
                    </div>
                `;
                $('#data-container').html(html);
                
                // Attach copy button event
                $('#copy-btn').on('click', function() {
                    copyToClipboard();
                });
            }
            
            function syntaxHighlight(json) {
                json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
                    let cls = 'json-number';
                    if (/^"/.test(match)) {
                        if (/:$/.test(match)) {
                            cls = 'json-key';
                        } else {
                            cls = 'json-string';
                        }
                    } else if (/true|false/.test(match)) {
                        cls = 'json-boolean';
                    } else if (/null/.test(match)) {
                        cls = 'json-null';
                    }
                    return '<span class="' + cls + '">' + match + '</span>';
                });
            }
            
            function copyToClipboard() {
                if (!currentResponseData) return;
                
                const jsonContent = JSON.stringify(currentResponseData, null, 2);
                navigator.clipboard.writeText(jsonContent).then(function() {
                    const btn = $('#copy-btn');
                    const originalHtml = btn.html();
                    btn.html('<i class="fa fa-check me-1"></i>Copied!');
                    btn.removeClass('btn-outline-primary').addClass('btn-success');
                    setTimeout(function() {
                        btn.html(originalHtml);
                        btn.removeClass('btn-success').addClass('btn-outline-primary');
                    }, 2000);
                }, function(err) {
                    console.error('Failed to copy: ', err);
                    alert('Failed to copy to clipboard');
                });
            }
        });
    </script>
@endsection

