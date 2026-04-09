@extends('layouts.vertical', ['title' => 'R&R — '.$user->name])

@section('content')
    <div class="container py-4">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="{{ route('users.add') }}">Team Management</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
            </ol>
        </nav>

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="h3 text-primary fw-bold mb-1">R&amp;R portfolio</h1>
                <p class="text-muted mb-0">{{ $user->name }} @if($user->email)<span class="mx-1">·</span> {{ $user->email }} @endif</p>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($canUpload)
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-semibold mb-3">Upload file (CSV, TXT, HTML, Excel)</h2>
                    <p class="small text-muted mb-3">The file is converted to HTML. One upload can be <strong>linked to this user only</strong> or to <strong>several users</strong> (same document for everyone selected). Supported: <strong>.csv</strong>, <strong>.txt</strong>, <strong>.htm / .html</strong>, <strong>.xls / .xlsx</strong> (max 10&nbsp;MB).</p>

                    <form action="{{ route('users.rr-portfolio.upload', $user) }}" method="post" enctype="multipart/form-data" id="rrPortfolioUploadForm">
                        @csrf

                        <div class="mb-3">
                            <span class="form-label d-block mb-2">Assign this document to</span>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assign_scope" id="assign_single" value="single" {{ old('assign_scope', 'single') === 'single' ? 'checked' : '' }}>
                                <label class="form-check-label" for="assign_single"><strong>This user only</strong> ({{ $user->name }})</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assign_scope" id="assign_multiple" value="multiple" {{ old('assign_scope') === 'multiple' ? 'checked' : '' }}>
                                <label class="form-check-label" for="assign_multiple"><strong>Selected users</strong> (same file for each)</label>
                            </div>
                        </div>

                        <div id="user-checkboxes-panel" class="border rounded p-3 mb-3 bg-light {{ old('assign_scope') === 'multiple' ? '' : 'd-none' }}">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-semibold text-muted">Team members (active)</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="rrSelectAllUsers">Select all</button>
                            </div>
                            <div class="row g-2" style="max-height: 220px; overflow-y: auto;">
                                @foreach($teamUsers as $tu)
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check">
                                            <input class="form-check-input rr-user-cb" type="checkbox" name="user_ids[]" value="{{ $tu->id }}" id="uid-{{ $tu->id }}"
                                                {{ (is_array(old('user_ids')) && in_array($tu->id, old('user_ids', []))) || (int)$tu->id === (int)$user->id ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="uid-{{ $tu->id }}">{{ $tu->name }} <span class="text-muted">({{ $tu->email }})</span></label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('user_ids')
                                <div class="text-danger small mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-md-8">
                                <label for="portfolio_file" class="form-label">Choose file</label>
                                <input type="file" name="portfolio_file" id="portfolio_file" class="form-control @error('portfolio_file') is-invalid @enderror" accept=".csv,.txt,.htm,.html,.xls,.xlsx" required>
                                @error('portfolio_file')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100">Convert &amp; assign</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const single = document.getElementById('assign_single');
                    const multiple = document.getElementById('assign_multiple');
                    const panel = document.getElementById('user-checkboxes-panel');
                    const selectAll = document.getElementById('rrSelectAllUsers');
                    function syncPanel() {
                        if (!panel) return;
                        panel.classList.toggle('d-none', multiple && !multiple.checked);
                    }
                    if (single && multiple && panel) {
                        single.addEventListener('change', syncPanel);
                        multiple.addEventListener('change', syncPanel);
                        syncPanel();
                    }
                    if (selectAll) {
                        selectAll.addEventListener('click', function() {
                            document.querySelectorAll('.rr-user-cb').forEach(function(cb) { cb.checked = true; });
                        });
                    }
                });
            </script>
        @endif

        @forelse($assignments as $assignment)
            @php $portfolio = $assignment->portfolio; @endphp
            @if($portfolio)
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3 pb-3 border-bottom">
                            <div>
                                @if($portfolio->original_filename)
                                    <p class="small text-muted mb-1">
                                        File: <strong>{{ $portfolio->original_filename }}</strong>
                                        @if($portfolio->source_format)
                                            <span class="badge bg-light text-dark border ms-1">{{ strtoupper($portfolio->source_format) }}</span>
                                        @endif
                                    </p>
                                @endif
                                <p class="small text-muted mb-0">Assigned {{ $assignment->created_at?->format('Y-m-d H:i') }} · updated {{ $assignment->updated_at?->format('Y-m-d H:i') }}</p>
                            </div>
                            @if($canUpload)
                                <form method="post" action="{{ route('users.rr-portfolio.assignment.fits', [$user, $assignment]) }}" class="d-flex align-items-center gap-2 flex-wrap">
                                    @csrf
                                    <div class="form-check form-switch m-0">
                                        <input class="form-check-input" type="checkbox" role="switch" name="fits" value="1" id="fits-{{ $assignment->id }}"
                                            {{ $assignment->fits ? 'checked' : '' }}
                                            onchange="this.form.submit()">
                                        <label class="form-check-label small fw-semibold" for="fits-{{ $assignment->id }}">User fits this R&amp;R</label>
                                    </div>
                                    <noscript><button type="submit" class="btn btn-sm btn-outline-primary">Save</button></noscript>
                                </form>
                            @else
                                @if($assignment->fits)
                                    <span class="badge bg-success">Fits this R&amp;R</span>
                                @else
                                    <span class="badge bg-secondary">Fit not confirmed</span>
                                @endif
                            @endif
                        </div>
                        <div class="rr-portfolio-content">
                            {!! $portfolio->html_content !!}
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center text-muted">
                    <i class="ri-file-list-3-line fs-1 d-block mb-2 opacity-50"></i>
                    <p class="mb-0">No portfolio document assigned yet.@if($canUpload) Upload a file above.@else Check back later.@endif</p>
                </div>
            </div>
        @endforelse
    </div>
@endsection

@section('css')
    <style>
        .rr-portfolio-content .rr-portfolio-table { font-size: 0.95rem; }
        .rr-portfolio-content .rr-portfolio-plain { white-space: pre-wrap; word-break: break-word; line-height: 1.6; }
        .rr-portfolio-content .rr-portfolio-html { line-height: 1.6; }
        .rr-portfolio-content .rr-portfolio-html table { max-width: 100%; }
    </style>
@endsection
