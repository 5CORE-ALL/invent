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

        <div class="row">
            <div class="col-12">
                <div class="card" style="border: 2px solid #28a745; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.15);">
                    <div class="card-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white;">
                        <h5 class="mb-0">
                            <i class="mdi mdi-pencil me-2"></i>Edit Task
                        </h5>
                    </div>
                    <div class="card-body" style="padding: 30px;">
                        <div class="mb-3">
                            <a href="{{ route('tasks.index') }}" class="btn btn-outline-success">
                                <i class="mdi mdi-format-list-bulleted me-1"></i> View Task List
                            </a>
                        </div>
                        
                        <form action="{{ route('tasks.update', $task->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="group" class="form-label">Group</label>
                                    <input type="text" class="form-control @error('group') is-invalid @enderror" 
                                           id="group" name="group" placeholder="Enter Group" value="{{ old('group', $task->group) }}">
                                    @error('group')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select class="form-select @error('priority') is-invalid @enderror" 
                                            id="priority" name="priority" required 
                                            style="display: block !important; visibility: visible !important; height: auto !important;">
                                        <option value="normal" {{ old('priority', $task->priority) == 'normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="high" {{ old('priority', $task->priority) == 'high' ? 'selected' : '' }}>High</option>
                                        <option value="low" {{ old('priority', $task->priority) == 'low' ? 'selected' : '' }}>Low</option>
                                    </select>
                                    @error('priority')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="title" class="form-label">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title', $task->title) }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="assignor_id" class="form-label">Assignor (Task Creator) <span class="text-danger">*</span></label>
                                    @if(strtolower(Auth::user()->role ?? '') === 'admin')
                                        <select class="form-select select2 @error('assignor_id') is-invalid @enderror" 
                                                id="assignor_id" name="assignor_id">
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ old('assignor_id', $task->assignor_id) == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" class="form-control" value="{{ $task->assignor->name ?? Auth::user()->name }}" readonly>
                                        <input type="hidden" name="assignor_id" value="{{ $task->assignor_id }}">
                                    @endif
                                    @error('assignor_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="assignee_id" class="form-label">Assign To (Assignee)</label>
                                    <select class="form-select select2 @error('assignee_id') is-invalid @enderror" 
                                            id="assignee_id" name="assignee_id">
                                        <option value="">Please Select</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ old('assignee_id', $task->assignee_id) == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('assignee_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Task Distribution</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="split_tasks" name="split_tasks" value="1" {{ old('split_tasks', $task->split_tasks) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="split_tasks">
                                            Split tasks between assignees
                                        </label>
                                    </div>
                                    <small class="text-muted">When enabled, each assignee will get their own copy of this task</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Flag Raise</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="flag_raise" name="flag_raise" value="1" {{ old('flag_raise', $task->flag_raise) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="flag_raise">
                                            Create flag for this task
                                        </label>
                                    </div>
                                    <small class="text-muted">When enabled, this task will be synced to flag management</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="etc_minutes" class="form-label">ETC (Min) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control @error('etc_minutes') is-invalid @enderror" 
                                           id="etc_minutes" name="etc_minutes" placeholder="10" value="{{ old('etc_minutes', $task->etc_minutes) }}">
                                    @error('etc_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tid" class="form-label">TID (Task Initiation Date) <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('tid') is-invalid @enderror" 
                                           id="tid" name="tid" value="{{ old('tid', $task->tid ? $task->tid->format('Y-m-d\TH:i') : '') }}">
                                    @error('tid')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="l1" class="form-label">L1</label>
                                    <input type="text" class="form-control @error('l1') is-invalid @enderror" 
                                           id="l1" name="l1" placeholder="Enter L1" value="{{ old('l1', $task->l1) }}">
                                    @error('l1')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="l2" class="form-label">L2</label>
                                    <input type="text" class="form-control @error('l2') is-invalid @enderror" 
                                           id="l2" name="l2" placeholder="Enter L2" value="{{ old('l2', $task->l2) }}">
                                    @error('l2')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="training_link" class="form-label">Training Link</label>
                                    <input type="text" class="form-control @error('training_link') is-invalid @enderror" 
                                           id="training_link" name="training_link" placeholder="Enter training Note" value="{{ old('training_link', $task->training_link) }}">
                                    @error('training_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="video_link" class="form-label">Video Link</label>
                                    <input type="text" class="form-control @error('video_link') is-invalid @enderror" 
                                           id="video_link" name="video_link" placeholder="Enter video Note" value="{{ old('video_link', $task->video_link) }}">
                                    @error('video_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="form_link" class="form-label">Form Link</label>
                                    <input type="text" class="form-control @error('form_link') is-invalid @enderror" 
                                           id="form_link" name="form_link" placeholder="Enter form Note" value="{{ old('form_link', $task->form_link) }}">
                                    @error('form_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="form_report_link" class="form-label">Form Report Link</label>
                                    <input type="text" class="form-control @error('form_report_link') is-invalid @enderror" 
                                           id="form_report_link" name="form_report_link" placeholder="Enter form Note" value="{{ old('form_report_link', $task->form_report_link) }}">
                                    @error('form_report_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="checklist_link" class="form-label">Checklist Link</label>
                                    <input type="text" class="form-control @error('checklist_link') is-invalid @enderror" 
                                           id="checklist_link" name="checklist_link" placeholder="Enter checklist link" value="{{ old('checklist_link', $task->checklist_link) }}">
                                    @error('checklist_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="pl" class="form-label">PL</label>
                                    <input type="text" class="form-control @error('pl') is-invalid @enderror" 
                                           id="pl" name="pl" placeholder="Enter PL link" value="{{ old('pl', $task->pl) }}">
                                    @error('pl')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="process" class="form-label">PROCESS</label>
                                    <input type="text" class="form-control @error('process') is-invalid @enderror" 
                                           id="process" name="process" placeholder="Enter form Note" value="{{ old('process', $task->process) }}">
                                    @error('process')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3" 
                                              placeholder="Enter Description">{{ old('description', $task->description) }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="image" class="form-label">Image</label>
                                    @if($task->image)
                                        <div class="mb-2">
                                            <img src="{{ asset('uploads/tasks/' . $task->image) }}" class="img-thumbnail" style="max-width: 200px;">
                                            <p class="text-muted small">Current Image</p>
                                        </div>
                                    @endif
                                    <div class="border rounded p-3 text-center" style="border: 2px dashed #dee2e6;">
                                        <input type="file" class="form-control @error('image') is-invalid @enderror" 
                                               id="image" name="image" accept="image/*" style="display: none;">
                                        <button type="button" class="btn btn-danger" onclick="document.getElementById('image').click()">
                                            Choose File
                                        </button>
                                        <span class="ms-2">or drag & drop, or paste image</span>
                                        <div id="image-preview" class="mt-3"></div>
                                    </div>
                                    @error('image')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="button" class="btn btn-secondary" onclick="window.location.href='{{ route('tasks.index') }}'">
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-danger">
                                        Update
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
