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
            --sp-soft: #fdecec;
            --sp-white: #ffffff;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
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
        .sp-top-actions { display: flex; align-items: center; gap: 22px; font-size: 14px; color: #333; }
        .sp-top-actions a:hover { color: var(--sp-red); }
        .sp-hero {
            background: linear-gradient(105deg, #111 0%, #1c1c1c 55%, #2a2a2a 100%);
            color: #fff;
            display: grid;
            grid-template-columns: 1.15fr .85fr;
            gap: 40px;
            padding: 64px 7% 56px;
            align-items: center;
        }
        .sp-hero h1 {
            margin: 0 0 14px;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.15;
            font-weight: 800;
        }
        .sp-hero h1 em { color: var(--sp-red); font-style: normal; }
        .sp-hero p { margin: 0 0 22px; color: #cfcfcf; max-width: 520px; font-size: 16px; }
        .sp-lock {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #bdbdbd;
            font-size: 13px;
        }
        .sp-lock i { color: var(--sp-red); }
        .sp-hero-visual {
            min-height: 240px;
            border-radius: 10px;
            overflow: hidden;
            background: #0d0d0d;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sp-hero-visual img { width: 100%; height: 320px; object-fit: cover; display: block; }
        .sp-hero-fallback {
            color: #888;
            text-align: center;
            padding: 40px;
            font-size: 14px;
        }
        .sp-wrap { padding: 42px 7% 20px; }
        .sp-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 8px 0 18px;
        }
        .sp-head h2 { margin: 0; font-size: 22px; font-weight: 800; }
        .sp-viewall { color: var(--sp-red); font-weight: 600; font-size: 14px; }
        .sp-viewall:hover { color: var(--sp-red-dark); }
        .sp-quick {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 36px;
        }
        .sp-quick a {
            border: 1px solid var(--sp-line);
            border-radius: 10px;
            padding: 18px 16px 16px;
            min-height: 112px;
            position: relative;
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        .sp-quick a:hover { border-color: #f0b7b9; box-shadow: 0 8px 22px rgba(227,28,35,.08); }
        .sp-quick i { color: var(--sp-red); font-size: 22px; }
        .sp-quick strong { display: block; margin: 10px 0 4px; font-size: 15px; }
        .sp-quick span { color: var(--sp-muted); font-size: 12px; }
        .sp-quick .ri-arrow-right-s-line { position: absolute; right: 10px; bottom: 8px; font-size: 20px; color: #bbb; }
        .sp-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 40px;
        }
        .sp-card {
            border: 1px solid var(--sp-line);
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            display: flex;
            flex-direction: column;
        }
        .sp-thumb {
            height: 150px;
            background: #f6f6f6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .sp-thumb img { max-width: 86%; max-height: 130px; object-fit: contain; }
        .sp-pdf {
            width: 72px;
            height: 90px;
            background: var(--sp-red);
            color: #fff;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: .04em;
        }
        .sp-card-body { padding: 14px 14px 16px; }
        .sp-card-body h3 { margin: 0 0 6px; font-size: 14px; font-weight: 700; }
        .sp-meta { color: var(--sp-muted); font-size: 12px; margin-bottom: 10px; }
        .sp-dl {
            color: var(--sp-red);
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .sp-dl:hover { color: var(--sp-red-dark); }
        .sp-empty { color: var(--sp-muted); font-size: 14px; padding: 8px 0 28px; }
        .sp-announce {
            margin: 10px 7% 0;
            background: var(--sp-soft);
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .sp-announce-left { display: flex; align-items: flex-start; gap: 12px; }
        .sp-announce i { color: var(--sp-red); font-size: 22px; margin-top: 1px; }
        .sp-announce strong { display: block; font-size: 14px; }
        .sp-announce p { margin: 3px 0 0; font-size: 13px; color: #5a5a5a; }
        .sp-footer {
            margin-top: 36px;
            background: #141414;
            color: #c8c8c8;
            padding: 28px 7%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            font-size: 13px;
        }
        .sp-footer strong { color: #fff; }
        .sp-footer em { color: var(--sp-red); font-style: normal; }
        .sp-footer a:hover { color: #fff; }
        @media (max-width: 980px) {
            .sp-hero, .sp-quick, .sp-grid { grid-template-columns: 1fr 1fr; }
            .sp-footer, .sp-announce { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 640px) {
            .sp-hero, .sp-quick, .sp-grid { grid-template-columns: 1fr; }
            .sp-top, .sp-hero, .sp-wrap, .sp-announce, .sp-footer { padding-left: 18px; padding-right: 18px; }
        }
    </style>
</head>
<body>
    <header class="sp-top">
        <div class="sp-brand">
            <i class="ri-shield-check-line"></i>
            Supplier Portal
        </div>
        <div class="sp-top-actions">
            @if($settings->contact_email)
                <a href="mailto:{{ $settings->contact_email }}"><i class="ri-question-line"></i> Help</a>
            @endif
        </div>
    </header>

    <section class="sp-hero">
        <div>
            <h1>
                @php
                    $hero = (string) $settings->hero_title;
                    $hero = preg_replace('/5 Core/i', '<em>5 Core</em>', e($hero), 1);
                @endphp
                {!! $hero !!}
            </h1>
            <p>{{ $settings->hero_subtitle }}</p>
            <div class="sp-lock">
                <i class="ri-lock-2-line"></i>
                Authorized suppliers — download official logos and packaging files only.
            </div>
        </div>
        <div class="sp-hero-visual">
            @if($settings->hero_image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->hero_image_path) }}" alt="{{ $settings->company_name }}">
            @else
                <div class="sp-hero-fallback">Upload a hero image from Supplier Portal admin</div>
            @endif
        </div>
    </section>

    <main class="sp-wrap">
        @if($section === null)
            <div class="sp-head"><h2>Quick Access</h2></div>
            <div class="sp-quick">
                <a href="#logos">
                    <i class="ri-palette-line"></i>
                    <strong>Brand Assets</strong>
                    <span>Logos, icons, brand files</span>
                    <i class="ri-arrow-right-s-line"></i>
                </a>
                <a href="#packaging">
                    <i class="ri-box-3-line"></i>
                    <strong>Packaging Designs</strong>
                    <span>Dielines and box artwork</span>
                    <i class="ri-arrow-right-s-line"></i>
                </a>
                <a href="#marketing">
                    <i class="ri-image-line"></i>
                    <strong>Marketing Materials</strong>
                    <span>Catalogs, banners, kits</span>
                    <i class="ri-arrow-right-s-line"></i>
                </a>
                <a href="#documents">
                    <i class="ri-file-text-line"></i>
                    <strong>Guidelines</strong>
                    <span>Brand and compliance PDFs</span>
                    <i class="ri-arrow-right-s-line"></i>
                </a>
            </div>
        @endif

        @foreach(\App\Models\SupplierPortalAsset::CATEGORIES as $key => $label)
            @if($section !== null && $section !== $key)
                @continue
            @endif
            @php $items = $grouped[$key] ?? collect(); $preview = $section ? $items : $items->take(4); @endphp
            <section id="{{ $key }}">
                <div class="sp-head">
                    <h2>{{ $label }}</h2>
                    @if($section === null && $items->count() > 4)
                        <a class="sp-viewall" href="{{ url('/supplier-portal/'.$key) }}">View All &gt;</a>
                    @elseif($section !== null)
                        <a class="sp-viewall" href="{{ url('/supplier-portal') }}">&lt; Back to portal</a>
                    @endif
                </div>
                @if($preview->isEmpty())
                    <p class="sp-empty">Files for this section will appear here after they are uploaded.</p>
                @else
                    <div class="sp-grid">
                        @foreach($preview as $asset)
                            <article class="sp-card">
                                <div class="sp-thumb">
                                    @if($asset->isImage())
                                        <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->title }}">
                                    @else
                                        <div class="sp-pdf">{{ $asset->extensionLabel() }}</div>
                                    @endif
                                </div>
                                <div class="sp-card-body">
                                    <h3>{{ $asset->title }}</h3>
                                    <div class="sp-meta">{{ $asset->extensionLabel() }} · {{ $asset->sizeLabel() }}</div>
                                    <a class="sp-dl" href="{{ route('supplier-portal.download', $asset) }}">
                                        Download <i class="ri-download-line"></i>
                                    </a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </main>

    @if($settings->announcement)
        <div class="sp-announce">
            <div class="sp-announce-left">
                <i class="ri-notification-3-line"></i>
                <div>
                    <strong>Latest Announcement</strong>
                    <p>{{ $settings->announcement }}</p>
                </div>
            </div>
        </div>
    @endif

    <footer class="sp-footer">
        <div>
            <strong>{{ $settings->company_name }}</strong>
            @if($settings->footer_tagline)
                <em> · {{ $settings->footer_tagline }}</em>
            @endif
            <div style="margin-top:6px">© {{ date('Y') }} {{ $settings->company_name }} Inc. All rights reserved.</div>
        </div>
        <div>
            This portal is for authorized suppliers only.
            @if($settings->contact_email)
                · <a href="mailto:{{ $settings->contact_email }}">Contact</a>
            @endif
        </div>
    </footer>
</body>
</html>
