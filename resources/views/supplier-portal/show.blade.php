<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --sp-red: #e31c23;
            --sp-red-dark: #c4161c;
            --sp-ink: #141414;
            --sp-muted: #6b6b6b;
            --sp-line: #e8e8e8;
            --sp-white: #ffffff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, "Segoe UI", sans-serif;
            color: var(--sp-ink);
            background: var(--sp-white);
        }
        a { color: inherit; text-decoration: none; }
        .sp-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 7%;
            border-bottom: 1px solid var(--sp-line);
            background: #fff;
        }
        .sp-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 15px;
        }
        .sp-brand i { color: var(--sp-red); font-size: 20px; }
        .sp-wrap { padding: 28px 7% 48px; max-width: 1100px; }
        .sp-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--sp-red);
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 18px;
        }
        .sp-back:hover { color: var(--sp-red-dark); }
        .sp-detail {
            border: 1px solid var(--sp-line);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }
        .sp-preview {
            min-height: 360px;
            background: #f6f6f6;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px;
        }
        .sp-preview img { max-width: 100%; max-height: 620px; object-fit: contain; }
        .sp-preview-inner { text-align: center; }
        .sp-file-code {
            margin-top: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            color: var(--sp-red);
        }
        .sp-pdf {
            width: 110px;
            height: 140px;
            background: var(--sp-red);
            color: #fff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 22px;
        }
        .sp-info { padding: 22px 24px 26px; }
        .sp-cat {
            color: var(--sp-muted);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .sp-info h1 { margin: 0 0 8px; font-size: 26px; }
        .sp-product-row { display: flex; flex-wrap: wrap; gap: 16px; margin: 0 0 10px; font-size: 14px; }
        .sp-product-row span { color: var(--sp-muted); }
        .sp-product-row strong { color: var(--sp-ink); }
        .sp-meta { color: var(--sp-muted); font-size: 14px; margin-bottom: 18px; }
        .sp-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .sp-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            padding: 11px 16px;
            font-weight: 700;
            font-size: 14px;
        }
        .sp-btn-red { background: var(--sp-red); color: #fff; }
        .sp-btn-red:hover { background: var(--sp-red-dark); }
        .sp-btn-line { border: 1px solid var(--sp-line); color: #333; }
        .sp-btn-line:hover { border-color: #ccc; }
        .sp-footer {
            background: #141414;
            color: #c8c8c8;
            padding: 28px 7%;
            font-size: 13px;
        }
        .sp-footer strong { color: #fff; }
        @media (max-width: 640px) {
            .sp-top, .sp-wrap, .sp-footer { padding-left: 18px; padding-right: 18px; }
            .sp-preview { min-height: 240px; }
        }
    </style>
</head>
<body>
    <header class="sp-top">
        <a class="sp-brand" href="{{ url('/supplier-portal') }}">
            <i class="ri-shield-check-line"></i>
            Supplier Portal
        </a>
    </header>

    <main class="sp-wrap">
        <a class="sp-back" href="{{ url('/supplier-portal#'.$categoryKey) }}">
            <i class="ri-arrow-left-line"></i> Back to {{ $categoryLabel }}
        </a>
        <article class="sp-detail">
            <div class="sp-preview">
                <div class="sp-preview-inner">
                    @if($asset->isImage())
                        <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->title }}">
                    @else
                        <div class="sp-pdf">{{ $asset->extensionLabel() }}</div>
                    @endif
                    <div class="sp-file-code">{{ $asset->codeLabel($fileNumber ?? null) }}</div>
                </div>
            </div>
            <div class="sp-info">
                <div class="sp-cat">{{ $categoryLabel }}</div>
                <h1>{{ $asset->title }}</h1>
                @if(trim((string) ($asset->parent ?? '')) !== '' || trim((string) ($asset->sku ?? '')) !== '')
                    <div class="sp-product-row">
                        @if(trim((string) ($asset->parent ?? '')) !== '')
                            <div><span>Parent</span> <strong>{{ $asset->parent }}</strong></div>
                        @endif
                        @if(trim((string) ($asset->sku ?? '')) !== '')
                            <div><span>SKU</span> <strong>{{ $asset->sku }}</strong></div>
                        @endif
                    </div>
                @endif
                <div class="sp-meta">{{ $asset->file_name }} · {{ $asset->extensionLabel() }} · {{ $asset->sizeLabel() }}</div>
                <div class="sp-actions">
                    <a class="sp-btn sp-btn-red" href="{{ route('supplier-portal.download', $asset) }}">
                        Download <i class="ri-download-line"></i>
                    </a>
                    @if($asset->isImage())
                        <a class="sp-btn sp-btn-line" href="{{ $asset->publicUrl() }}" target="_blank" rel="noopener">
                            Open image <i class="ri-external-link-line"></i>
                        </a>
                    @endif
                </div>
            </div>
        </article>
    </main>

    <footer class="sp-footer">
        <strong>{{ $settings->company_name }}</strong>
        <div style="margin-top:6px">© {{ date('Y') }} {{ $settings->company_name }} Inc. All rights reserved.</div>
    </footer>
</body>
</html>
