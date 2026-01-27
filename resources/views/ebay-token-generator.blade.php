<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eBay Refresh Token Generator</title>
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
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }
        .info-item {
            margin: 8px 0;
            font-size: 14px;
            color: #555;
        }
        .info-label {
            font-weight: 600;
            color: #333;
        }
        .select-box {
            margin-bottom: 25px;
        }
        .select-box label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .select-box select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            background: white;
            cursor: pointer;
        }
        .select-box select:focus {
            outline: none;
            border-color: #667eea;
        }
        .auth-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            text-align: center;
            width: 100%;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }
        .auth-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .error-box {
            background: #fee;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
            color: #721c24;
        }
        .instructions {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-top: 25px;
            border-radius: 4px;
            font-size: 14px;
            color: #555;
        }
        .instructions ol {
            margin-left: 20px;
            margin-top: 10px;
        }
        .instructions li {
            margin: 8px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 eBay Refresh Token Generator</h1>
        <p class="subtitle">Generate refresh token for eBay1, eBay2, or eBay3</p>

        @if(isset($error))
            <div class="error-box">
                <strong>Error:</strong> {{ $error }}
            </div>
        @endif

        <form method="GET" action="{{ route('ebay.token.generate') }}" id="accountForm">
            <div class="select-box">
                <label for="account">Select eBay Account:</label>
                <select name="account" id="account" onchange="document.getElementById('accountForm').submit();">
                    <option value="ebay1" {{ ($selectedAccount ?? 'ebay1') == 'ebay1' ? 'selected' : '' }}>eBay1 (Amarjit Kalra)</option>
                    <option value="ebay2" {{ ($selectedAccount ?? 'ebay1') == 'ebay2' ? 'selected' : '' }}>eBay2 (Pro Light Sound)</option>
                    <option value="ebay3" {{ ($selectedAccount ?? 'ebay1') == 'ebay3' ? 'selected' : '' }}>eBay3 (Kaneer Kaur Kal)</option>
                </select>
            </div>
        </form>

        @if(!isset($error) && isset($authUrl))
            <div class="info-box">
                <div class="info-item">
                    <span class="info-label">Account:</span> {{ strtoupper($selectedAccount ?? 'ebay1') }}
                </div>
                <div class="info-item">
                    <span class="info-label">Client ID:</span> {{ $clientId ?? 'N/A' }}
                </div>
                <div class="info-item">
                    <span class="info-label">RuName:</span> {{ $ruName ?? 'N/A' }}
                </div>
            </div>

            <a href="{{ $authUrl }}" class="auth-button" target="_blank">
                🔐 Authorize with eBay
            </a>

            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px; font-size: 12px;">
                <strong>Debug - Authorization URL:</strong><br>
                <code style="word-break: break-all; display: block; margin-top: 8px;">{{ $authUrl }}</code>
            </div>

            <div class="instructions">
                <strong>Instructions:</strong>
                <ol>
                    <li>Select the eBay account from dropdown above</li>
                    <li>Click the "Authorize with eBay" button</li>
                    <li>You will be redirected to eBay's authorization page</li>
                    <li>Log in and grant the requested permissions</li>
                    <li>After authorization, eBay will redirect you to 5core.com with a code in the URL</li>
                    <li>Copy the authorization code and paste it below</li>
                </ol>
            </div>

            <form method="POST" action="{{ route('ebay.token.callback') }}" style="margin-top: 25px;">
                @csrf
                <input type="hidden" name="account" value="{{ $selectedAccount ?? 'ebay1' }}">
                <div style="margin-bottom: 15px;">
                    <label for="code" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Authorization Code:</label>
                    <input type="text" 
                           id="code" 
                           name="code" 
                           placeholder="Paste the authorization code here (e.g., v^1.1#i^1#I^3#p^3#r^1#f^0#t^...)"
                           required
                           style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px; font-family: monospace;">
                    <small style="color: #666; font-size: 12px; margin-top: 5px; display: block;">
                        💡 Tip: If you were redirected to 5core.com, copy the entire code value from the URL parameter (?code=...)
                    </small>
                </div>
                <button type="submit" class="auth-button" style="margin-top: 0;">
                    🔄 Generate Refresh Token
                </button>
            </form>
            
            @if(request()->has('code'))
                <div style="margin-top: 20px; padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">
                    <strong>✅ Authorization code detected in URL!</strong>
                    <p style="margin-top: 8px; margin-bottom: 0;">Code: <code style="word-break: break-all;">{{ request()->get('code') }}</code></p>
                    <p style="margin-top: 8px; color: #155724;">Click the button below to automatically generate your refresh token.</p>
                    <form method="POST" action="{{ route('ebay.token.callback') }}" style="margin-top: 10px;">
                        @csrf
                        <input type="hidden" name="account" value="{{ $selectedAccount ?? 'ebay1' }}">
                        <input type="hidden" name="code" value="{{ request()->get('code') }}">
                        <button type="submit" class="auth-button" style="margin-top: 0; background: #28a745;">
                            ✅ Generate Refresh Token Now
                        </button>
                    </form>
                </div>
            @endif
        @endif
    </div>
</body>
</html>
