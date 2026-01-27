<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eBay Token Generation Result</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 700px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .error-box {
            background: #fee;
            border-left: 4px solid #dc3545;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
            color: #721c24;
        }
        .token-box {
            background: #f8f9fa;
            border: 2px dashed #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        .token-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .copy-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
            transition: background 0.2s;
        }
        .copy-button:hover {
            background: #5568d3;
        }
        .env-instruction {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
            color: #856404;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .account-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 Token Generation Result</h1>
        
        @if(isset($account))
            <div class="account-badge">{{ strtoupper($account) }}</div>
        @endif

        @if(isset($success) && $success)
            <div class="success-box">
                <strong style="font-size: 18px; color: #155724;">✅ Success!</strong>
                <p style="margin-top: 10px; color: #155724;">Refresh token generated successfully!</p>
            </div>

            <div class="token-box">
                <div class="token-label">Refresh Token:</div>
                <div id="refreshToken">{{ $refreshToken }}</div>
                <button class="copy-button" onclick="copyToken('refreshToken')">📋 Copy Refresh Token</button>
            </div>

            @if(isset($accessToken))
                <div class="token-box">
                    <div class="token-label">Access Token (expires in {{ $expiresIn ?? 'unknown' }} seconds):</div>
                    <div>{{ substr($accessToken, 0, 50) }}...</div>
                </div>
            @endif

            <div class="env-instruction">
                <strong>⚠️ Important:</strong> Update your <code>.env</code> file with the following:
                <div style="margin-top: 10px; font-family: 'Courier New', monospace; background: white; padding: 10px; border-radius: 4px;">
                    {{ $envKey ?? 'EBAY_REFRESH_TOKEN' }}={{ $refreshToken }}
                </div>
            </div>

        @else
            <div class="error-box">
                <strong style="font-size: 18px;">❌ Error</strong>
                <p style="margin-top: 10px;">{{ $error ?? 'Unknown error occurred' }}</p>
                
                @if(isset($errorDescription))
                    <p style="margin-top: 10px;"><strong>Details:</strong> {{ $errorDescription }}</p>
                @endif

                @if(isset($status))
                    <p style="margin-top: 10px;"><strong>HTTP Status:</strong> {{ $status }}</p>
                @endif

                @if(isset($response))
                    <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.5); border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 200px; overflow-y: auto;">
                        <strong>Response:</strong><br>
                        {{ is_string($response) ? $response : json_encode($response, JSON_PRETTY_PRINT) }}
                    </div>
                @endif
            </div>
        @endif

        <a href="{{ route('ebay.token.generate', ['account' => $account ?? 'ebay1']) }}" class="back-link">← Try Again</a>
    </div>

    <script>
        function copyToken(elementId) {
            const tokenElement = document.getElementById(elementId);
            const token = tokenElement.textContent.trim();
            
            navigator.clipboard.writeText(token).then(function() {
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '✅ Copied!';
                button.style.background = '#28a745';
                
                setTimeout(function() {
                    button.textContent = originalText;
                    button.style.background = '#667eea';
                }, 2000);
            }, function(err) {
                alert('Failed to copy: ' + err);
            });
        }
    </script>
</body>
</html>
