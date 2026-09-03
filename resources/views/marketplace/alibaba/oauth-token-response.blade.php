<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Alibaba token response</title>
    <style>
        body { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; margin: 0; padding: 24px; background: #0f172a; color: #e2e8f0; }
        h1 { font-size: 18px; font-weight: 600; margin: 0 0 8px; }
        p { color: #94a3b8; margin: 0 0 16px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: #7dd3fc; margin: 24px 0 8px; }
        pre { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 16px; overflow: auto; white-space: pre-wrap; word-break: break-all; }
        a { color: #7dd3fc; }
        .ok { color: #4ade80; }
        .err { color: #f87171; }
    </style>
</head>
<body>
    <h1>Alibaba OAuth token response</h1>
    <p class="{{ !empty($result['success']) ? 'ok' : 'err' }}">{{ $result['message'] ?? (!empty($result['success']) ? 'Token received.' : 'Token exchange failed.') }}</p>

    <h2>Redirect URL query</h2>
    <pre>{{ json_encode($redirectQuery ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

    <h2>Token API response</h2>
    <pre>{{ json_encode($result['raw'] ?? $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

    <p><a href="{{ route('marketplace.manager.alibaba.connect') }}">Back to Alibaba Connect</a></p>
</body>
</html>
