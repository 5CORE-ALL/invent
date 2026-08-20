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
    .sop-ai-box {
        border: 1px solid #c5d4ff;
        background: linear-gradient(180deg, #f4f7ff 0%, #fff 55%);
        box-shadow: 0 8px 22px rgba(26, 61, 140, 0.08);
    }
    .sop-ai-box-label {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #1a3d8c;
    }
    .sop-ai-box textarea {
        min-height: 86px;
        resize: vertical;
        font-size: 14px;
    }
    .sop-ai-box-hint { font-size: 12px; color: #6c757d; }
    .sop-ai-box-status { font-size: 13px; min-height: 1.2em; }
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
        @if(!empty($canUseAiBox))
            <div class="card sop-ai-box mb-3" id="sop-ai-box">
                <div class="card-body py-3">
                    <div class="sop-ai-box-label mb-1">
                        <i class="mdi mdi-auto-fix me-1"></i> Ask AI to change this page
                    </div>
                    <p class="sop-ai-box-hint mb-2">Visible only to the assignor, president@5core.com, and Directors. Tell AI what to add, edit, or delete.</p>
                    <textarea id="sop-ai-instruction" class="form-control mb-2" maxlength="4000" placeholder="Examples: Add a step after #3 to confirm the listing is live. Edit the Who section to name the marketplace team. Delete the Checks list. Change ETC wording to 15 minutes."></textarea>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-primary btn-sm" id="sop-ai-apply">
                            <i class="mdi mdi-auto-fix me-1"></i> Apply with AI
                        </button>
                        <span class="sop-ai-box-status text-muted" id="sop-ai-status"></span>
                    </div>
                </div>
            </div>
        @endif
        <div class="card">
            <div class="card-body sop-page-body" id="sop-page-body">
                {!! $page->body !!}
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    (function () {
        var reelTimer = null;

        function startSopReel() {
            if (reelTimer) {
                clearInterval(reelTimer);
                reelTimer = null;
            }
            var reel = document.querySelector('[data-sop-reel] .sop-ai-reel-stage');
            if (!reel) return;
            var slides = reel.querySelectorAll('.sop-ai-slide');
            if (slides.length < 2) return;
            var i = 0;
            reelTimer = setInterval(function () {
                slides[i].classList.remove('is-active');
                i = (i + 1) % slides.length;
                slides[i].classList.add('is-active');
            }, 4500);
        }

        startSopReel();

        var applyBtn = document.getElementById('sop-ai-apply');
        var instructionEl = document.getElementById('sop-ai-instruction');
        var statusEl = document.getElementById('sop-ai-status');
        if (!applyBtn || !instructionEl) return;

        instructionEl.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                applyBtn.click();
            }
        });

        applyBtn.addEventListener('click', function () {
            var instruction = (instructionEl.value || '').trim();
            if (instruction.length < 3) {
                statusEl.className = 'sop-ai-box-status text-danger';
                statusEl.textContent = 'Enter what you want AI to add, edit, or delete.';
                instructionEl.focus();
                return;
            }

            applyBtn.disabled = true;
            instructionEl.disabled = true;
            statusEl.className = 'sop-ai-box-status text-muted';
            statusEl.innerHTML = '<i class="mdi mdi-loading mdi-spin me-1"></i> Updating the page…';

            fetch(@json(route('tasks.automatedSopPage.revise', $task->id)), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': @json(csrf_token())
                },
                body: JSON.stringify({ instruction: instruction })
            }).then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data };
                });
            }).then(function (result) {
                if (!result.ok) {
                    var msg = (result.data && result.data.message) || 'Could not update the page.';
                    if (result.data && result.data.errors && result.data.errors.instruction) {
                        msg = result.data.errors.instruction[0];
                    }
                    throw new Error(msg);
                }
                var bodyEl = document.getElementById('sop-page-body');
                if (bodyEl && result.data.body) {
                    bodyEl.innerHTML = result.data.body;
                    startSopReel();
                }
                if (result.data.title) {
                    var titleEl = document.querySelector('.sop-page-wrap h4');
                    if (titleEl) titleEl.textContent = result.data.title;
                    document.title = result.data.title;
                }
                instructionEl.value = '';
                statusEl.className = 'sop-ai-box-status text-success';
                statusEl.textContent = result.data.message || 'SOP page updated.';
            }).catch(function (err) {
                statusEl.className = 'sop-ai-box-status text-danger';
                statusEl.textContent = err.message || 'Could not update the page.';
            }).finally(function () {
                applyBtn.disabled = false;
                instructionEl.disabled = false;
            });
        });
    })();
</script>
@endsection
