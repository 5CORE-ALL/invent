@php
    $icons = [
        'folder' => ['ri-folder-3-fill', '#f5b400'],
        'image' => ['ri-image-2-fill', '#ef4444'],
        'video' => ['ri-movie-2-fill', '#8b5cf6'],
        'audio' => ['ri-music-2-fill', '#ec4899'],
        'pdf' => ['ri-file-pdf-2-fill', '#dc2626'],
        'doc' => ['ri-file-word-2-fill', '#2563eb'],
        'sheet' => ['ri-file-excel-2-fill', '#16a34a'],
        'slide' => ['ri-file-ppt-2-fill', '#f97316'],
        'archive' => ['ri-file-zip-fill', '#a16207'],
        'text' => ['ri-file-text-fill', '#64748b'],
        'other' => ['ri-file-3-fill', '#94a3b8'],
    ];
    $fmt = function ($b) {
        $b = (int) $b;
        if ($b < 1024) return $b.' B';
        $u = ['KB', 'MB', 'GB', 'TB'];
        $i = -1;
        do { $b /= 1024; $i++; } while ($b >= 1024 && $i < 3);
        return round($b, $b >= 100 ? 0 : 1).' '.$u[$i];
    };
    $cat = $item->category();
    [$icon, $color] = $icons[$cat] ?? $icons['other'];
    $isFolder = $item->isFolder();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $item->name }} · 5Core Drive</title>
    @if (! $isFolder && $cat === 'image' && $rawUrl)
        <meta property="og:image" content="{{ $rawUrl }}">
    @endif
    <meta property="og:title" content="{{ $item->name }}">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #0f0f1e; color: #e2e8f0; min-height: 100vh; }
        .bg { position: fixed; inset: 0; background: radial-gradient(60% 50% at 10% 0%, rgba(79,70,229,.35), transparent 60%), radial-gradient(50% 50% at 100% 100%, rgba(6,182,212,.25), transparent 60%); pointer-events: none; }
        header { position: relative; display: flex; align-items: center; gap: 14px; padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,.08); backdrop-filter: blur(8px); }
        .logo { width: 42px; height: 42px; border-radius: 50%; object-fit: contain; background: #fff; }
        .brand { font-weight: 700; }
        .brand small { display: block; font-weight: 400; color: #94a3b8; font-size: 12px; }
        .title { flex: 1; min-width: 0; display: flex; align-items: center; gap: 10px; font-weight: 600; font-size: 16px; margin-left: 18px; }
        .title span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .title i { font-size: 22px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; border-radius: 10px; border: 0; font-weight: 600; font-size: 14px; cursor: pointer; text-decoration: none; color: #fff; background: rgba(255,255,255,.1); }
        .btn:hover { background: rgba(255,255,255,.18); }
        .btn.primary { background: linear-gradient(135deg,#4f46e5,#7c3aed 50%,#06b6d4); }
        .btn.primary:hover { filter: brightness(1.08); }
        main { position: relative; padding: 28px 24px 40px; max-width: 1280px; margin: 0 auto; }
        .stage { display: grid; place-items: center; min-height: calc(100vh - 220px); }
        .stage img, .stage video { max-width: 100%; max-height: calc(100vh - 220px); border-radius: 12px; box-shadow: 0 30px 70px rgba(0,0,0,.6); }
        .stage iframe { width: 100%; height: calc(100vh - 200px); border: 0; border-radius: 12px; background: #fff; }
        .none { text-align: center; color: #94a3b8; }
        .none i { font-size: 110px; display: block; }
        .meta { text-align: center; color: #94a3b8; font-size: 13px; margin-top: 16px; }
        .link { display: flex; gap: 8px; max-width: 760px; margin: 18px auto 0; }
        .link input { flex: 1; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); color: #e2e8f0; border-radius: 10px; padding: 9px 12px; font-family: ui-monospace, Consolas, monospace; font-size: 12.5px; }
        .crumbs { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 18px; font-size: 18px; font-weight: 600; }
        .crumbs a { color: #a5b4fc; text-decoration: none; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
        .card { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; overflow: hidden; text-decoration: none; color: inherit; transition: transform .15s, background .15s; display: block; }
        .card:hover { transform: translateY(-2px); background: rgba(255,255,255,.09); }
        .thumb { height: 140px; display: grid; place-items: center; background: rgba(0,0,0,.25); }
        .thumb img { width: 100%; height: 100%; object-fit: cover; }
        .thumb i { font-size: 56px; }
        .cap { display: flex; align-items: center; gap: 8px; padding: 10px 12px; font-size: 13.5px; }
        .cap span { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .cap a { color: #94a3b8; font-size: 18px; }
        .empty { text-align: center; color: #94a3b8; padding: 60px 0; }
        footer { position: relative; text-align: center; color: #64748b; font-size: 12px; padding: 20px; }
        @media (max-width: 640px) { .title { display: none; } }
    </style>
</head>
<body>
<div class="bg"></div>
<header>
    <img class="logo" src="{{ asset('images/5core-drive-logo.png') }}" alt="5Core">
    <div class="brand">5Core Drive<small>Shared by {{ $item->owner?->name ?? '5Core' }}</small></div>
    <div class="title"><i class="{{ $icon }}" style="color: {{ $isFolder && $item->color ? $item->color : $color }}"></i><span>{{ $item->name }}</span></div>
    <a class="btn primary" href="{{ route('drive.public.download', $token) }}"><i class="ri-download-2-line"></i> {{ $isFolder ? 'Download all' : 'Download' }}</a>
</header>

<main>
    @if ($isFolder)
        <div class="crumbs">
            <a href="{{ route('drive.public.show', $token) }}">{{ $item->name }}</a>
            @foreach ($crumbs as $c)
                @if ($c->id !== $item->id)
                    <i class="ri-arrow-right-s-line" style="color:#64748b"></i>
                    <a href="{{ route('drive.public.show', $token) }}?f={{ $c->uuid }}">{{ $c->name }}</a>
                @endif
            @endforeach
        </div>
        @if ($children->isEmpty())
            <div class="empty"><i class="ri-folder-open-line" style="font-size:72px"></i><p>This folder is empty.</p></div>
        @else
            <div class="grid">
                @foreach ($children as $child)
                    @php
                        $cc = $child->category();
                        [$ci, $ccol] = $icons[$cc] ?? $icons['other'];
                        $childUrl = $child->isFolder()
                            ? route('drive.public.show', $token).'?f='.$child->uuid
                            : route('drive.public.child', ['token' => $token, 'uuid' => $child->uuid, 'filename' => $drive->urlFilename($child->name)]);
                    @endphp
                    <a class="card" href="{{ $childUrl }}" @unless($child->isFolder()) target="_blank" rel="noopener" @endunless>
                        <div class="thumb">
                            @if ($cc === 'image')
                                <img src="{{ $childUrl }}" loading="lazy" alt="">
                            @else
                                <i class="{{ $ci }}" style="color: {{ $child->isFolder() && $child->color ? $child->color : $ccol }}"></i>
                            @endif
                        </div>
                        <div class="cap">
                            <span title="{{ $child->name }}">{{ $child->name }}</span>
                            @unless ($child->isFolder())
                                <small style="color:#64748b">{{ $fmt($child->size) }}</small>
                            @endunless
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @else
        <div class="stage">
            @if ($cat === 'image')
                <img src="{{ $rawUrl }}" alt="{{ $item->name }}">
            @elseif ($cat === 'video')
                <video src="{{ $rawUrl }}" controls playsinline></video>
            @elseif ($cat === 'audio')
                <div class="none"><i class="ri-music-2-fill" style="color:#ec4899"></i><audio src="{{ $rawUrl }}" controls></audio></div>
            @elseif ($cat === 'pdf')
                <iframe src="{{ $rawUrl }}" title="{{ $item->name }}"></iframe>
            @else
                <div class="none">
                    <i class="{{ $icon }}" style="color: {{ $color }}"></i>
                    <h2 style="color:#e2e8f0">{{ $item->name }}</h2>
                    <p>No preview available for this file type.</p>
                    <a class="btn primary" href="{{ route('drive.public.download', $token) }}"><i class="ri-download-2-line"></i> Download</a>
                </div>
            @endif
        </div>
        <div class="meta">{{ $item->name }} · {{ $fmt($item->size) }} · Updated {{ $item->updated_at?->format('M j, Y') }}</div>
        <div class="link">
            <input id="direct" readonly value="{{ $rawUrl }}">
            <button class="btn" onclick="navigator.clipboard.writeText(document.getElementById('direct').value).then(()=>{this.innerHTML='<i class=&quot;ri-check-line&quot;></i> Copied'})"><i class="ri-file-copy-line"></i> Copy direct link</button>
        </div>
    @endif
</main>
<footer>Shared securely with 5Core Drive</footer>
</body>
</html>
