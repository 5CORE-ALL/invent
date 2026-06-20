@extends('layouts.vertical', ['title' => 'Edit Task', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endsection

@php
    // True for assignor / admin / president override; false when an assignee
    // opens this page only to attach links.
    $canEditAll = $canEditAll ?? false;
    $lockedAttr = $canEditAll ? '' : 'readonly disabled';
    $lockedTitle = $canEditAll ? '' : 'Only the task creator can change this. You can still add reference links below.';
@endphp

@section('content')
    <!-- Start Content-->
    <div class="container-fluid">
        
        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Task Manager</a></li>
                            <li class="breadcrumb-item active">{{ $canEditAll ? 'Edit Task' : 'Add Links' }}</li>
                        </ol>
                    </div>
                    <h4 class="page-title">{{ $canEditAll ? 'Edit Task' : 'Add Reference Links' }}</h4>
                </div>
            </div>
        </div>     
        <!-- end page title --> 

        <div class="row justify-content-end">
            <div class="col-md-4">
                <div class="card" style="border: 2px solid #28a745; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.15); position: sticky; top: 20px;">
                    <div class="card-header position-relative" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 10px 15px;">
                        <h6 class="mb-0">
                            <i class="mdi {{ $canEditAll ? 'mdi-pencil' : 'mdi-link-variant-plus' }} me-1"></i>{{ $canEditAll ? 'Edit Task' : 'Add Links' }}
                        </h6>
                        <button type="button" 
                                onclick="window.location.href='{{ route('tasks.index') }}'" 
                                class="btn-close btn-close-white position-absolute" 
                                style="top: 50%; right: 15px; transform: translateY(-50%);" 
                                aria-label="Close"></button>
                    </div>
                    <div class="card-body" style="padding: 15px;">
                        @unless($canEditAll)
                            <div class="alert alert-info py-2 px-2 mb-2" style="font-size: 11px;">
                                <i class="mdi mdi-information-outline me-1"></i>
                                You're an assignee on this task. Group, task title, date and assignee
                                stay locked — but you can attach reference / SOP / proof links below
                                to make review easier.
                            </div>
                        @endunless
                        <form action="{{ route('tasks.update', $task->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            
                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="group" class="form-label fw-bold" style="font-size: 12px;">Group</label>
                                    <input type="text" class="form-control form-control-sm @error('group') is-invalid @enderror"
                                           id="group" name="group" placeholder="Group" value="{{ old('group', $task->group) }}"
                                           {!! $lockedAttr !!} title="{{ $lockedTitle }}">
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="title" class="form-label fw-bold" style="font-size: 12px;">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('title') is-invalid @enderror"
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title', $task->title) }}"
                                           {{ $canEditAll ? 'required' : '' }} {!! $lockedAttr !!} title="{{ $lockedTitle }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="assignee_id" class="form-label fw-bold" style="font-size: 12px;">Assignee</label>
                                    <select class="form-select form-select-sm select2 @error('assignee_id') is-invalid @enderror"
                                            id="assignee_id" name="assignee_id" {!! $lockedAttr !!} title="{{ $lockedTitle }}">
                                        <option value="">Please Select</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ old('assignee_id', $task->assignee_id) == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="etc_minutes" class="form-label fw-bold" style="font-size: 12px;">ETC (Min) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm @error('etc_minutes') is-invalid @enderror"
                                           id="etc_minutes" name="etc_minutes" placeholder="10" value="{{ old('etc_minutes', $task->etc_minutes) }}"
                                           {!! $lockedAttr !!} title="{{ $lockedTitle }}">
                                </div>
                            </div>

                            <!-- Toggle Button for Additional Fields -->
                            <div class="row">
                                <div class="col-12 mb-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="toggle-additional-fields" style="font-size: 11px;">
                                        <i class="mdi mdi-chevron-down" id="toggle-icon"></i> More Fields
                                    </button>
                                </div>
                            </div>

                            <!-- Additional Fields (Hidden by Default; auto-opens for assignees so links are visible) -->
                            <div id="additional-fields" style="display: {{ $canEditAll ? 'none' : 'block' }};">
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label for="priority" class="form-label fw-bold" style="font-size: 12px;">Priority <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm @error('priority') is-invalid @enderror"
                                                id="priority" name="priority" {{ $canEditAll ? 'required' : '' }} {!! $lockedAttr !!} title="{{ $lockedTitle }}">
                                            <option value="normal" {{ old('priority', $task->priority ?? 'normal') == 'normal' ? 'selected' : '' }}>Normal</option>
                                            <option value="high" {{ old('priority', $task->priority) == 'high' ? 'selected' : '' }}>Urgent</option>
                                            <option value="low" {{ old('priority', $task->priority) == 'low' ? 'selected' : '' }}>Low</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="assignor_id" class="form-label fw-bold" style="font-size: 12px;">Assignor <span class="text-danger">*</span></label>
                                        @if($canEditAll && strtolower(Auth::user()->role ?? '') === 'admin')
                                            <select class="form-select form-select-sm select2 @error('assignor_id') is-invalid @enderror" 
                                                    id="assignor_id" name="assignor_id">
                                                @foreach($users as $user)
                                                    <option value="{{ $user->id }}" {{ old('assignor_id', $task->assignor_id) == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="text" class="form-control form-control-sm" value="{{ $task->assignor->name ?? Auth::user()->name }}" readonly>
                                            @if($canEditAll)
                                                <input type="hidden" name="assignor_id" value="{{ $task->assignor_id }}">
                                            @endif
                                        @endif
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="tid" class="form-label fw-bold" style="font-size: 12px;">TID <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control form-control-sm @error('tid') is-invalid @enderror"
                                               id="tid" name="tid" value="{{ old('tid', $task->tid ? $task->tid->format('Y-m-d\TH:i') : '') }}"
                                               {!! $lockedAttr !!} title="{{ $lockedTitle }}">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label for="l1" class="form-label fw-bold" style="font-size: 12px;">L1</label>
                                        <input type="text" class="form-control form-control-sm @error('l1') is-invalid @enderror" 
                                               id="l1" name="l1" placeholder="L1" value="{{ old('l1', $task->l1) }}">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label for="l2" class="form-label fw-bold" style="font-size: 12px;">L2</label>
                                        <input type="text" class="form-control form-control-sm @error('l2') is-invalid @enderror" 
                                               id="l2" name="l2" placeholder="L2" value="{{ old('l2', $task->l2) }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="training_link" class="form-label fw-bold" style="font-size: 12px;">Training</label>
                                        <input type="text" class="form-control form-control-sm @error('training_link') is-invalid @enderror" 
                                               id="training_link" name="training_link" placeholder="Training Link" value="{{ old('training_link', $task->training_link) }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="video_link" class="form-label fw-bold" style="font-size: 12px;">Video</label>
                                        <input type="text" class="form-control form-control-sm @error('video_link') is-invalid @enderror" 
                                               id="video_link" name="video_link" placeholder="Video Link" value="{{ old('video_link', $task->video_link) }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="form_link" class="form-label fw-bold" style="font-size: 12px;">Form</label>
                                        <input type="text" class="form-control form-control-sm @error('form_link') is-invalid @enderror" 
                                               id="form_link" name="form_link" placeholder="Form Link" value="{{ old('form_link', $task->form_link) }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="form_report_link" class="form-label fw-bold" style="font-size: 12px;">Report</label>
                                        <input type="text" class="form-control form-control-sm @error('form_report_link') is-invalid @enderror" 
                                               id="form_report_link" name="form_report_link" placeholder="Report Link" value="{{ old('form_report_link', $task->form_report_link) }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="checklist_link" class="form-label fw-bold" style="font-size: 12px;">Checklist</label>
                                        <input type="text" class="form-control form-control-sm @error('checklist_link') is-invalid @enderror" 
                                               id="checklist_link" name="checklist_link" placeholder="Checklist" value="{{ old('checklist_link', $task->checklist_link) }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="pl" class="form-label fw-bold" style="font-size: 12px;">PL</label>
                                        <input type="text" class="form-control form-control-sm @error('pl') is-invalid @enderror" 
                                               id="pl" name="pl" placeholder="PL" value="{{ old('pl', $task->pl) }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label class="form-label fw-bold" style="font-size: 12px;">Image</label>
                                        @if($task->image)
                                            <div class="mb-2 d-flex align-items-start gap-2">
                                                <a href="{{ asset('uploads/tasks/' . $task->image) }}" target="_blank"
                                                   rel="noopener" title="Open full-size image in a new tab">
                                                    <img src="{{ asset('uploads/tasks/' . $task->image) }}"
                                                         alt="Task attachment"
                                                         class="img-thumbnail"
                                                         style="max-width: 220px; cursor: zoom-in;">
                                                </a>
                                                <a href="{{ asset('uploads/tasks/' . $task->image) }}" target="_blank"
                                                   rel="noopener"
                                                   class="btn btn-sm btn-outline-primary"
                                                   style="font-size: 11px; white-space: nowrap;">
                                                    <i class="mdi mdi-open-in-new"></i> View full
                                                </a>
                                            </div>
                                        @else
                                            <div class="text-muted small mb-1" style="font-size: 11px;">
                                                <i class="mdi mdi-image-off-outline"></i> No image attached.
                                            </div>
                                        @endif
                                        @if($canEditAll)
                                            <input type="file" class="form-control form-control-sm @error('image') is-invalid @enderror"
                                                   id="image" name="image" accept="image/*">
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-sm btn-success w-100">
                                        <i class="mdi mdi-check-circle me-1"></i> {{ $canEditAll ? 'Update Task' : 'Save Links' }}
                                    </button>
                                </div>
                            </div>

                        </form>

                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
        </div>
        <!-- end row -->

    </div> <!-- container -->
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // For assignees the additional-fields panel is open on load (so links are visible),
            // so the toggle button must start in the "Hide" state to match.
            @unless($canEditAll)
                $('#toggle-additional-fields').html('<i class="mdi mdi-chevron-up" id="toggle-icon"></i> Hide Fields');
            @endunless

            // Toggle Additional Fields
            $('#toggle-additional-fields').on('click', function() {
                $('#additional-fields').slideToggle(200);
                const icon = $('#toggle-icon');
                const btn = $(this);
                
                if (icon.hasClass('mdi-chevron-down')) {
                    icon.removeClass('mdi-chevron-down').addClass('mdi-chevron-up');
                    btn.html('<i class="mdi mdi-chevron-up" id="toggle-icon"></i> Hide Fields');
                } else {
                    icon.removeClass('mdi-chevron-up').addClass('mdi-chevron-down');
                    btn.html('<i class="mdi mdi-chevron-down" id="toggle-icon"></i> More Fields');
                }
            });

            // Initialize Select2 for assignor
            $('#assignor_id').select2({
                theme: 'bootstrap-5',
                placeholder: 'Please Select'
            });

            // Initialize Select2 for assignee with proper handling
            $('#assignee_id').select2({
                theme: 'bootstrap-5',
                placeholder: 'Please Select',
                allowClear: true
            });

            // Ensure the selected values are preserved
            @if(isset($task->assignor_id) && $task->assignor_id)
                $('#assignor_id').val('{{ $task->assignor_id }}').trigger('change');
            @endif

            @if(isset($task->assignee_id) && $task->assignee_id)
                $('#assignee_id').val('{{ $task->assignee_id }}').trigger('change');
            @endif

            // Image preview
            $('#image').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#image-preview').html('<img src="' + e.target.result + '" class="img-thumbnail" style="max-width: 300px;">');
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
@endsection
