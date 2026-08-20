@extends('layouts.vertical', ['title' => $page->title ?: 'SOP Page'])

@section('css')
<style>
    .sop-page-wrap { max-width: 880px; margin: 0 auto; }
    .sop-page-body { font-size: 15.5px; line-height: 1.65; color: #212529; }
    .sop-page-body h2 { font-size: 1.25rem; margin-top: 1.6rem; margin-bottom: .6rem; }
    .sop-page-body h3 { font-size: 1.05rem; margin-top: 1.2rem; }
    .sop-page-body img { max-width: 100%; height: auto; }
    .sop-page-body table { width: 100%; }
    .sop-page-body ol, .sop-page-body ul { padding-left: 1.3rem; }
    .sop-source-data { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e9ecef; }
    .sop-source-table { background: #fff; }
    .sop-source-pre { white-space: pre-wrap; background: #f8f9fa; padding: 12px; border-radius: 8px; }
    .sop-ai-reel { margin: 0 0 1.5rem; border-radius: 14px; overflow: hidden; background: #0b1b2b; color: #fff; }
    .sop-ai-reel-label { padding: 8px 14px; font-size: 12px; letter-spacing: .04em; text-transform: uppercase; opacity: .85; }
    .sop-ai-reel-stage { position: relative; height: 320px; }
    .sop-ai-slide { position: absolute; inset: 0; margin: 0; opacity: 0; transition: opacity .7s ease; }
    .sop-ai-slide.is-active { opacity: 1; }
    .sop-ai-slide img {
        width: 100%; height: 100%; object-fit: cover;
        transform: scale(1.08);
        animation: sopKenBurns 8s ease-in-out infinite alternate;
    }
    .sop-ai-slide figcaption, .sop-ai-inline figcaption {
        position: absolute; left: 0; right: 0; bottom: 0;
        margin: 0; padding: 10px 14px;
        background: linear-gradient(transparent, rgba(0,0,0,.65));
        font-size: 13px;
    }
    .sop-ai-inline { position: relative; margin: 1rem 0 1.4rem; border-radius: 12px; overflow: hidden; }
    .sop-ai-inline img { width: 100%; max-height: 280px; object-fit: cover; display: block; }
    .sop-ai-motion { position: relative; height: 220px; overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 24px; }
    .sop-ai-orb { position: absolute; width: 180px; height: 180px; border-radius: 50%; background: radial-gradient(circle, #3dd6d0, #1a6cff 70%); filter: blur(8px); opacity: .7; animation: sopOrb 6s ease-in-out infinite; }
    .sop-ai-orb-2 { width: 120px; height: 120px; right: 18%; top: 18%; animation-delay: -2s; background: radial-gradient(circle, #ffd36e, #ff6b6b 70%); }
    .sop-ai-motion-title, .sop-ai-motion-sub { position: relative; z-index: 1; }
    .sop-ai-motion-title { font-weight: 700; font-size: 1.15rem; margin-bottom: 6px; }
    .sop-ai-motion-sub { opacity: .85; margin: 0; }
    @keyframes sopKenBurns {
        from { transform: scale(1.05) translate3d(0,0,0); }
        to { transform: scale(1.18) translate3d(-2%, -2%, 0); }
    }
    @keyframes sopOrb {
        0%, 100% { transform: translate(-40px, 10px) scale(1); }
        50% { transform: translate(50px, -16px) scale(1.15); }
    }
</style>
@endsection

@section('content')
<div class="container-fluid py-3">
    <div class="sop-page-wrap">
        <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
            <div>
                <div class="text-muted small">Automated Task SOP</div>
                <h4 class="mb-0">{{ $page->title ?: ($task->title ?? 'SOP') }}</h4>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('tasks.automated') }}" class="btn btn-light btn-sm">Back</a>
                @if($canEdit)
                    <a href="{{ route('tasks.automatedSopPage.edit', $task->id) }}" class="btn btn-primary btn-sm">
                        <i class="mdi mdi-pencil me-1"></i>Edit
                    </a>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-body sop-page-body">
                {!! $page->body !!}
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    (function () {
        var reel = document.querySelector('[data-sop-reel] .sop-ai-reel-stage');
        if (!reel) return;
        var slides = reel.querySelectorAll('.sop-ai-slide');
        if (slides.length < 2) return;
        var i = 0;
        setInterval(function () {
            slides[i].classList.remove('is-active');
            i = (i + 1) % slides.length;
            slides[i].classList.add('is-active');
        }, 4500);
    })();
</script>
@endsection
