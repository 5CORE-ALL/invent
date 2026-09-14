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
            position: relative;
            color: #fff;
            min-height: 380px;
            padding: 72px 7% 64px;
            display: flex;
            align-items: center;
            background: #141414 center / cover no-repeat;
        }
        .sp-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(10,10,10,.78) 0%, rgba(10,10,10,.42) 55%, rgba(10,10,10,.18) 100%);
        }
        .sp-hero-copy {
            position: relative;
            z-index: 1;
            max-width: 620px;
        }
        .sp-hero h1 {
            margin: 0 0 14px;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.15;
            font-weight: 800;
        }
        .sp-hero h1 em { color: var(--sp-red); font-style: normal; }
        .sp-hero p { margin: 0 0 22px; color: #f0f0f0; max-width: 520px; font-size: 16px; }
        .sp-lock {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #e8e8e8;
            font-size: 13px;
        }
        .sp-lock i { color: var(--sp-red); }
        .sp-wrap { padding: 42px 7% 20px; }
        .sp-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 0;
            margin: 0 0 24px;
            padding: 0;
            border: 0;
            border-bottom: 1px solid var(--sp-line);
            background: transparent;
        }
        .sp-tab {
            border: 0;
            border-bottom: 2px solid transparent;
            margin-bottom: -1px;
            background: transparent;
            color: #6b6b6b;
            font: inherit;
            font-weight: 600;
            font-size: 13px;
            padding: 11px 14px;
            border-radius: 0;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .sp-tab:hover { color: var(--sp-ink); background: transparent; }
        .sp-tab.is-active {
            color: var(--sp-ink);
            background: transparent;
            border-bottom-color: var(--sp-red);
            box-shadow: none;
        }
        .sp-tab-count {
            min-width: 18px;
            height: 18px;
            padding: 0 6px;
            border-radius: 4px;
            background: #f1f1f1;
            color: #555;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .sp-tab.is-active .sp-tab-count { background: var(--sp-soft); color: var(--sp-red-dark); }
        .sp-panel { display: none; }
        .sp-panel.is-active { display: block; }
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
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        a.sp-card:hover { border-color: #f0b7b9; box-shadow: 0 8px 22px rgba(227,28,35,.08); }
        .sp-thumb {
            height: 150px;
            background: #f6f6f6;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .sp-thumb img { max-width: 86%; max-height: 130px; object-fit: contain; }
        .sp-file-code {
            text-align: center;
            padding: 8px 10px 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            color: var(--sp-red);
        }
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
        .sp-product { color: var(--sp-ink); font-size: 12px; font-weight: 600; margin-bottom: 4px; }
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
        .sp-page-headers { display: grid; gap: 12px; margin: 0 0 20px; }
        .sp-page-header {
            border: 1px solid var(--sp-line);
            border-left: 4px solid var(--sp-red);
            border-radius: 8px;
            padding: 14px 16px;
            background: #fffaf8;
        }
        .sp-page-header h3 { margin: 0 0 6px; font-size: 16px; font-weight: 800; }
        .sp-page-header p { margin: 0; color: #555; font-size: 14px; white-space: pre-wrap; }
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
            .sp-hero { min-height: 300px; }
            .sp-grid { grid-template-columns: 1fr 1fr; }
            .sp-footer { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 640px) {
            .sp-grid { grid-template-columns: 1fr; }
            .sp-tabs { overflow-x: auto; flex-wrap: nowrap; }
            .sp-hero { min-height: 260px; padding-top: 48px; padding-bottom: 48px; }
            .sp-top, .sp-hero, .sp-wrap, .sp-footer { padding-left: 18px; padding-right: 18px; }
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

    <section class="sp-hero"@if($settings->hero_image_path) style="background-image: url('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->hero_image_path) }}')"@endif>
        <div class="sp-hero-copy">
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
    </section>

    <main class="sp-wrap" id="spCatalog">
        @php
            $categories = \App\Models\SupplierPortalAsset::CATEGORIES;
            $headers = $headers ?? [];
            $activeTab = $section && isset($categories[$section])
                ? $section
                : array_key_first($categories);
        @endphp
        <div class="sp-tabs" role="tablist" aria-label="Supplier file categories">
            @foreach($categories as $key => $label)
                @php $count = ($grouped[$key] ?? collect())->count(); @endphp
                <button
                    type="button"
                    class="sp-tab{{ $key === $activeTab ? ' is-active' : '' }}"
                    role="tab"
                    id="sp-tab-{{ $key }}"
                    data-tab="{{ $key }}"
                    aria-selected="{{ $key === $activeTab ? 'true' : 'false' }}"
                    aria-controls="sp-panel-{{ $key }}"
                >
                    <span>{{ $label }}</span>
                    <span class="sp-tab-count">{{ $count }}</span>
                </button>
            @endforeach
        </div>

        @foreach($categories as $key => $label)
            @php $items = $grouped[$key] ?? collect(); @endphp
            <section
                class="sp-panel{{ $key === $activeTab ? ' is-active' : '' }}"
                id="sp-panel-{{ $key }}"
                role="tabpanel"
                aria-labelledby="sp-tab-{{ $key }}"
                data-panel="{{ $key }}"
            >
                @php $pageHeaders = ($headers[$key] ?? collect()); @endphp
                @if($pageHeaders->isNotEmpty())
                    <div class="sp-page-headers">
                        @foreach($pageHeaders as $header)
                            <article class="sp-page-header">
                                <h3>{{ $header->title }}</h3>
                                @if(trim((string) ($header->instructions ?? '')) !== '')
                                    <p>{{ $header->instructions }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
                @if(\App\Support\SupplierPortalPackingData::usesPackingGrid($key))
                    @include('supplier-portal.partials.packing-inner-grid', ['prefix' => 'spPiPublic_'.$key, 'editable' => false])
                @endif
                @if(\App\Support\SupplierPortalDimWtData::usesDimWtGrid($key))
                    @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwPublic_'.$key, 'editable' => false, 'variant' => 'pkg'])
                @endif
                @if(\App\Support\SupplierPortalDimWtData::usesDimWtCoverGrid($key))
                    @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwCoverPublic_'.$key, 'editable' => false, 'variant' => 'cover'])
                @endif
                @if(\App\Support\SupplierPortalDimWtData::usesDimWtSkuGrid($key))
                    @include('supplier-portal.partials.dim-wt-item-grid', ['prefix' => 'spDwSkuPublic_'.$key, 'editable' => false, 'variant' => 'sku', 'category' => $key])
                @endif
                @if($items->isEmpty())
                    <p class="sp-empty">No {{ strtolower($label) }} files uploaded yet.</p>
                @else
                    <div class="sp-grid">
                        @foreach($items as $asset)
                            <a class="sp-card" href="{{ route('supplier-portal.show', $asset) }}">
                                <div class="sp-thumb">
                                    @if($asset->isImage())
                                        <img src="{{ $asset->publicUrl() }}" alt="{{ $asset->title }}">
                                    @else
                                        <div class="sp-pdf">{{ $asset->extensionLabel() }}</div>
                                    @endif
                                </div>
                                <div class="sp-file-code">{{ $asset->codeLabel($loop->iteration) }}</div>
                                <div class="sp-card-body">
                                    <h3>{{ $asset->title }}</h3>
                                    @if($asset->productMeta() !== '')
                                        <div class="sp-product">{{ $asset->productMeta() }}</div>
                                    @endif
                                    <div class="sp-meta">{{ $asset->extensionLabel() }} · {{ $asset->sizeLabel() }}</div>
                                    <span class="sp-dl">
                                        Open <i class="ri-arrow-right-up-line"></i>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </main>

    <footer class="sp-footer">
        <div>
            <strong>{{ $settings->company_name }}</strong>
            @if($settings->footer_tagline)
                <em> · {{ $settings->footer_tagline }}</em>
            @endif
            <div style="margin-top:6px">© {{ date('Y') }} {{ $settings->company_name }} Inc. All rights reserved.</div>
        </div>
    </footer>
    <script>
    (function () {
        var root = document.getElementById('spCatalog');
        if (!root) return;
        var tabs = Array.prototype.slice.call(root.querySelectorAll('.sp-tab'));
        var panels = Array.prototype.slice.call(root.querySelectorAll('.sp-panel'));
        var known = {};
        tabs.forEach(function (tab) { known[tab.getAttribute('data-tab')] = true; });

        function show(key, updateHash) {
            if (!known[key]) return;
            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-tab') === key;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('is-active', panel.getAttribute('data-panel') === key);
            });
            if (updateHash) {
                history.replaceState(null, '', '#' + key);
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                show(tab.getAttribute('data-tab'), true);
            });
        });

        var fromHash = (location.hash || '').replace('#', '');
        if (known[fromHash]) {
            show(fromHash, false);
        }
    })();
    </script>
</body>
</html>
