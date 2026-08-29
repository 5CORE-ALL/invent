@extends('layouts.vertical', ['title' => $title])

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">Supplier Portal</h4>
            <div class="text-muted">Upload logos and packaging files. Suppliers open the public link — no login.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-dark" href="{{ $publicUrl }}" target="_blank" rel="noopener">Open public page</a>
            <button type="button" class="btn btn-danger" id="spCopyLink">Copy public link</button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Page content</div>
        <div class="card-body">
            <form method="post" action="{{ route('supplier-portal.admin.settings') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Company name</label>
                        <input class="form-control" name="company_name" value="{{ old('company_name', $settings->company_name) }}" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Hero title</label>
                        <input class="form-control" name="hero_title" value="{{ old('hero_title', $settings->hero_title) }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Hero subtitle</label>
                        <textarea class="form-control" name="hero_subtitle" rows="2">{{ old('hero_subtitle', $settings->hero_subtitle) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Latest announcement</label>
                        <textarea class="form-control" name="announcement" rows="2">{{ old('announcement', $settings->announcement) }}</textarea>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Help / contact email</label>
                        <input class="form-control" type="email" name="contact_email" value="{{ old('contact_email', $settings->contact_email) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Footer tagline</label>
                        <input class="form-control" name="footer_tagline" value="{{ old('footer_tagline', $settings->footer_tagline) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Hero image</label>
                        <input class="form-control" type="file" name="hero_image" accept="image/*">
                        @if($settings->hero_image_path)
                            <div class="mt-2 d-flex align-items-center gap-3">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($settings->hero_image_path) }}" alt="" style="height:64px;border-radius:6px;object-fit:cover;">
                                <button form="spHeroDelete" class="btn btn-sm btn-outline-danger">Remove hero image</button>
                            </div>
                        @endif
                    </div>
                </div>
                <button class="btn btn-danger mt-3" type="submit">Save page content</button>
            </form>
            <form id="spHeroDelete" method="post" action="{{ route('supplier-portal.admin.hero.destroy') }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    @foreach($categories as $key => $label)
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ $label }}</div>
            <div class="card-body">
                <form class="row g-2 align-items-end mb-3" method="post" action="{{ route('supplier-portal.admin.assets.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="category" value="{{ $key }}">
                    <div class="col-md-4">
                        <label class="form-label">Title <span class="text-muted fw-normal">(optional for multi-upload)</span></label>
                        <input class="form-control" name="title" placeholder="e.g. 5 Core Logo – Full Color">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Files</label>
                        <input class="form-control" type="file" name="files[]" multiple required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sort</label>
                        <input class="form-control" type="number" name="sort_order" value="0" min="0">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-danger w-100" type="submit">Upload</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Preview</th>
                                <th>Title</th>
                                <th>File</th>
                                <th>Sort</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($grouped[$key] as $asset)
                                <tr>
                                    <td style="width:80px">
                                        @if($asset->isImage())
                                            <img src="{{ $asset->publicUrl() }}" alt="" style="height:44px;max-width:70px;object-fit:contain;">
                                        @else
                                            <span class="badge bg-danger">{{ $asset->extensionLabel() }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form method="post" action="{{ route('supplier-portal.admin.assets.update', $asset) }}" class="d-flex gap-2">
                                            @csrf
                                            @method('PUT')
                                            <input class="form-control form-control-sm" name="title" value="{{ $asset->title }}">
                                            <input class="form-control form-control-sm" style="width:80px" type="number" name="sort_order" value="{{ $asset->sort_order }}">
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">Save</button>
                                        </form>
                                    </td>
                                    <td class="text-muted small">{{ $asset->file_name }} · {{ $asset->sizeLabel() }}</td>
                                    <td>{{ $asset->sort_order }}</td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-dark" href="{{ route('supplier-portal.download', $asset) }}">Download</a>
                                        <form method="post" action="{{ route('supplier-portal.admin.assets.destroy', $asset) }}" class="d-inline" onsubmit="return confirm('Remove this file from the supplier page?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">No files in this section yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

@section('script')
<script>
document.getElementById('spCopyLink')?.addEventListener('click', async function () {
    try {
        await navigator.clipboard.writeText(@json($publicUrl));
        this.textContent = 'Copied';
        setTimeout(() => { this.textContent = 'Copy public link'; }, 1600);
    } catch (e) {
        prompt('Copy this link', @json($publicUrl));
    }
});
</script>
@endsection
