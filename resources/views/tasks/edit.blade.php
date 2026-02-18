@extends('layouts.vertical', ['title' => 'Edit Task', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
@endsection

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
                            <li class="breadcrumb-item active">Edit Task</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Edit Task</h4>
                </div>
            </div>
        </div>     
        <!-- end page title --> 

        <div class="row justify-content-end">
            <div class="col-md-4">
                <div class="card" style="border: 2px solid #28a745; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.15); position: sticky; top: 20px;">
                    <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 10px 15px;">
                        <h6 class="mb-0">
                            <i class="mdi mdi-pencil me-1"></i>Edit Task
                        </h6>
                    </div>
                    <div class="card-body" style="padding: 15px;">
                        <form action="{{ route('tasks.update', $task->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            
                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="group" class="form-label fw-bold" style="font-size: 12px;">Group</label>
                                    <input type="text" class="form-control form-control-sm @error('group') is-invalid @enderror" 
                                           id="group" name="group" placeholder="Group" value="{{ old('group', $task->group) }}">
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="title" class="form-label fw-bold" style="font-size: 12px;">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('title') is-invalid @enderror" 
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title', $task->title) }}" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="assignee_id" class="form-label fw-bold" style="font-size: 12px;">Assignee</label>
                                    <select class="form-select form-select-sm select2 @error('assignee_id') is-invalid @enderror" 
                                            id="assignee_id" name="assignee_id">
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
                                           id="etc_minutes" name="etc_minutes" placeholder="10" value="{{ old('etc_minutes', $task->etc_minutes) }}">
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

                            <!-- Additional Fields (Hidden by Default) -->
                            <div id="additional-fields" style="display: none;">
                                <div class="row">
                                    <div class="col-12 mb-2">
                                        <label for="priority" class="form-label fw-bold" style="font-size: 12px;">Priority <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm @error('priority') is-invalid @enderror" 
                                                id="priority" name="priority" required>
                                            <option value="normal" {{ old('priority', $task->priority ?? 'normal') == 'normal' ? 'selected' : '' }}>Normal</option>
                                            <option value="high" {{ old('priority', $task->priority) == 'high' ? 'selected' : '' }}>High</option>
                                            <option value="low" {{ old('priority', $task->priority) == 'low' ? 'selected' : '' }}>Low</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="assignor_id" class="form-label fw-bold" style="font-size: 12px;">Assignor <span class="text-danger">*</span></label>
                                        @if(strtolower(Auth::user()->role ?? '') === 'admin')
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
                                            <input type="hidden" name="assignor_id" value="{{ $task->assignor_id }}">
                                        @endif
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="tid" class="form-label fw-bold" style="font-size: 12px;">TID <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control form-control-sm @error('tid') is-invalid @enderror" 
                                               id="tid" name="tid" value="{{ old('tid', $task->tid ? $task->tid->format('Y-m-d\TH:i') : '') }}">
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
                                        <label for="image" class="form-label fw-bold" style="font-size: 12px;">Image</label>
                                        @if($task->image)
                                            <img src="{{ asset('uploads/tasks/' . $task->image) }}" class="img-thumbnail mb-1" style="max-width: 80px;">
                                        @endif
                                        <input type="file" class="form-control form-control-sm @error('image') is-invalid @enderror" 
                                               id="image" name="image" accept="image/*">
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="button" class="btn btn-sm btn-secondary w-100 mb-1" onclick="window.location.href='{{ route('tasks.index') }}'">
                                        <i class="mdi mdi-arrow-left me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-sm btn-success w-100">
                                        <i class="mdi mdi-check-circle me-1"></i> Update Task
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
