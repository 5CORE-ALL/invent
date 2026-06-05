@extends('layouts.vertical', ['title' => 'Scope of Improvement'])

@section('css')
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    #scope-editor {
        min-height: 220px;
        background: #fff;
    }

    #scope-editor.editor-readonly .ql-editor {
        background: #f8f9fa;
        cursor: not-allowed;
    }
</style>
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Scope of Improvement', 'sub_title' => 'Scope of Improvement'])

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="header-title mb-0">Scope of Improvement</h4>
            </div>
            <div class="card-body">
                <form id="scopeForm">
                    @csrf
                    <div class="row g-3">
                        {{-- 1st input: user select --}}
                        <div class="col-md-6">
                            <label for="scope_user_id" class="form-label">User</label>
                            <select name="user_id" id="scope_user_id" class="form-select">
                                <option value="">Select user</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- 2nd input: rich text editor (only editable by president@5core.com) --}}
                        <div class="col-12">
                            <label class="form-label">
                                Details
                                @unless ($canEditEditor)
                                    <span class="badge bg-soft-secondary text-secondary ms-1">Read only</span>
                                @endunless
                            </label>
                            <div id="scope-editor" class="{{ $canEditEditor ? '' : 'editor-readonly' }}"></div>
                            <input type="hidden" name="details" id="scope_details">
                            @unless ($canEditEditor)
                                <small class="text-muted">Only <code>president@5core.com</code> can edit this field.</small>
                            @endunless
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
    (function () {
        var canEdit = @json($canEditEditor);

        var quill = new Quill('#scope-editor', {
            theme: 'snow',
            readOnly: !canEdit,
            modules: {
                toolbar: canEdit ? [
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean']
                ] : false
            }
        });

        var hidden = document.getElementById('scope_details');
        if (hidden) {
            quill.on('text-change', function () {
                hidden.value = quill.root.innerHTML;
            });
        }
    })();
</script>
@endsection
