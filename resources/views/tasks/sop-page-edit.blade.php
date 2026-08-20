@extends('layouts.vertical', ['title' => 'Edit SOP Page'])

@section('css')
<style>
    .sop-page-wrap { max-width: 960px; margin: 0 auto; }
    #sop-page-editor {
        min-height: 60vh;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 16px;
        background: #fff;
    }
    #sop-page-editor img { max-width: 100%; height: auto; }
    #sop-page-editor table { width: 100%; }
</style>
@endsection

@section('content')
<div class="container-fluid py-3">
    <div class="sop-page-wrap">
        <form method="POST" action="{{ route('tasks.automatedSopPage.update', $task->id) }}" id="sop-page-edit-form">
            @csrf
            @method('PUT')
            <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                <div>
                    <div class="text-muted small">Edit SOP page (assignor only)</div>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $page->title) }}" placeholder="Page title">
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('tasks.automatedSopPage.show', $task->id) }}" class="btn btn-light btn-sm">View</a>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="mdi mdi-content-save-outline me-1"></i>Save
                    </button>
                </div>
            </div>
            <textarea name="body" id="sop-page-body" class="d-none">{{ old('body', $page->body) }}</textarea>
            <div id="sop-page-editor" contenteditable="true">{!! old('body', $page->body) !!}</div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
    document.getElementById('sop-page-edit-form').addEventListener('submit', function () {
        document.getElementById('sop-page-body').value = document.getElementById('sop-page-editor').innerHTML;
    });
</script>
@endsection
