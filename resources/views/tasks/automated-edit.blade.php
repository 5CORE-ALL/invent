@extends('layouts.vertical', ['title' => 'Edit Automated Task', 'sidenav' => 'condensed'])

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
                            <li class="breadcrumb-item"><a href="{{ route('tasks.automated') }}">Automated Tasks</a></li>
                            <li class="breadcrumb-item active">Edit Automated Task</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Edit Automated Task</h4>
                </div>
            </div>
        </div>     
        <!-- end page title --> 

        <div class="row">
            <div class="col-12">
                <div class="card" style="border: 2px solid #6610f2; box-shadow: 0 4px 15px rgba(102, 16, 242, 0.15);">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-0">
                            <i class="mdi mdi-pencil me-2"></i>Edit Automated Task
                        </h5>
                    </div>
                    <div class="card-body" style="padding: 30px;">
                        <div class="mb-3">
                            <a href="{{ route('tasks.automated') }}" class="btn btn-outline-primary">
                                <i class="mdi mdi-format-list-bulleted me-1"></i> View Automated Task List
                            </a>
                        </div>
                        
                        <form action="{{ route('tasks.automatedUpdate', $task->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="schedule_type" id="schedule_type" value="{{ old('schedule_type', $task->schedule_type ?? 'daily') }}">
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="group" class="form-label">Group</label>
                                    <input type="text" class="form-control @error('group') is-invalid @enderror" 
                                           id="group" name="group" placeholder="Enter Group" value="{{ old('group', $task->group) }}">
                                    @error('group')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="title" class="form-label">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title', $task->title) }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                                    <select class="form-select @error('priority') is-invalid @enderror" 
                                            id="priority" name="priority" required 
                                            style="display: block !important; visibility: visible !important; height: auto !important;">
                                        <option value="">Select Priority</option>
                                        <option value="Low" {{ old('priority', $task->priority) == 'Low' ? 'selected' : '' }}>Low</option>
                                        <option value="Normal" {{ old('priority', $task->priority) == 'Normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="High" {{ old('priority', $task->priority) == 'High' ? 'selected' : '' }}>High</option>
                                        <option value="Urgent" {{ old('priority', $task->priority) == 'Urgent' ? 'selected' : '' }}>Urgent</option>
                                    </select>
                                    @error('priority')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                            </div>

                            <div class="row">
                             

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
                                        <input type="text" class="form-control" value="{{ Auth::user()->name }}" readonly>
                                        <input type="hidden" name="assignor_id" value="{{ $task->assignor_id }}">
                                    @endif
                                    @error('assignor_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
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
                              
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Task Distribution</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="split_tasks" name="split_tasks" value="1" {{ old('split_tasks') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="split_tasks">
                                            Split tasks between assignees
                                        </label>
                                    </div>
                                    <small class="text-muted">When enabled, each assignee will get their own copy of this task</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Flag Raise</label>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="flag_raise" name="flag_raise" value="1" {{ old('flag_raise') ? 'checked' : '' }}>
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
                                           id="tid" name="tid" value="{{ old('tid', $task->tid ? \Carbon\Carbon::parse($task->tid)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
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
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Schedule Type <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-3 justify-content-center">
                                        <button type="button" class="btn btn-lg schedule-btn active" data-schedule="daily" 
                                                style="background: #fd7e14; color: white; width: 100px; height: 80px; font-size: 24px; font-weight: bold; border-radius: 12px;">
                                            D
                                            <small style="display: block; font-size: 11px; font-weight: normal;">Daily</small>
                                        </button>
                                        <button type="button" class="btn btn-lg schedule-btn" data-schedule="weekly" 
                                                style="background: #6c757d; color: white; width: 100px; height: 80px; font-size: 24px; font-weight: bold; border-radius: 12px;">
                                            W
                                            <small style="display: block; font-size: 11px; font-weight: normal;">Weekly</small>
                                        </button>
                                        <button type="button" class="btn btn-lg schedule-btn" data-schedule="monthly" 
                                                style="background: #6c757d; color: white; width: 100px; height: 80px; font-size: 24px; font-weight: bold; border-radius: 12px;">
                                            M
                                            <small style="display: block; font-size: 11px; font-weight: normal;">Monthly</small>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Weekly Selection (Days of Week) -->
                            <div class="row schedule-options" id="weekly-options" style="display: none;">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Select Days of Week:</label>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        <button type="button" class="btn btn-lg day-btn" data-day="Sun" style="background: #6c757d; color: white; width: 80px; height: 80px; font-size: 14px; font-weight: 600; border-radius: 50%;">Sun</button>
                                        <button type="button" class="btn btn-lg day-btn" data-day="Mon" style="background: #6c757d; color: white; width: 80px; height: 80px; font-size: 14px; font-weight: 600; border-radius: 50%;">Mon</button>
                                        <button type="button" class="btn btn-lg day-btn" data-day="Tue" style="background: #6c757d; color: white; width: 80px; height: 80px; font-size: 14px; font-weight: 600; border-radius: 50%;">Tue</button>
                                        <button type="button" class="btn btn-lg day-btn" data-day="Wed" style="background: #6c757d; color: white; width: 80px; height: 80px; font-size: 14px; font-weight: 600; border-radius: 50%;">Wed</button>
                                        <button type="button" class="btn btn-lg day-btn" data-day="Thu" style="background: #6c757d; color: white; width: 80px; height: 80px; font-size: 14px; font-weight: 600; border-radius: 50%;">Thu</button>
                                        <button type="button" class="btn btn-lg day-btn" data-day="Fri" style="background: #6c757d; color: white; width: 80px; height: 80px; font-size: 14px; font-weight: 600; border-radius: 50%;">Fri</button>
                                        <button type="button" class="btn btn-lg day-btn" data-day="Sat" style="background: #6c757d; color: white; width: 80px; height: 80px; font-size: 14px; font-weight: 600; border-radius: 50%;">Sat</button>
                                    </div>
                                    <input type="hidden" name="schedule_days" id="schedule_days" value="{{ $task->schedule_days ?? '' }}">
                                    <small class="text-muted d-block mt-2">Select multiple days for task execution</small>
                                </div>
                            </div>

                            <!-- Monthly Selection (Dates) -->
                            <div class="row schedule-options" id="monthly-options" style="display: none;">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Select Dates of Month:</label>
                                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                                        @for($i = 1; $i <= 31; $i++)
                                            <button type="button" class="btn date-btn" data-date="{{ $i }}" style="background: #e9ecef; color: #495057; width: 60px; height: 60px; font-size: 14px; font-weight: 600; border-radius: 50%;">{{ $i }}</button>
                                        @endfor
                                        <button type="button" class="btn date-btn" data-date="EOM" style="background: #e9ecef; color: #495057; width: 90px; height: 60px; font-size: 11px; font-weight: 600; border-radius: 12px;">End of<br>Month</button>
                                    </div>
                                    <small class="text-muted d-block mt-2">Select multiple dates for task execution</small>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="schedule_time" class="form-label fw-bold">Select Time: <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control form-control-lg" id="schedule_time" name="schedule_time" value="{{ old('schedule_time', $task->schedule_time ?? '12:01') }}" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <button type="button" class="btn btn-secondary" onclick="window.location.href='{{ route('tasks.automated') }}'">
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-danger">
                                        Update Automated Task
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
            // Initialize Select2
            $('#assignor_id').select2({
                theme: 'bootstrap-5',
                placeholder: 'Please Select'
            });
            
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

            var selectedDays = [];
            var selectedDates = [];
            
            // Initialize with current schedule type
            var currentSchedule = '{{ $task->schedule_type ?? "daily" }}';
            var currentDays = '{{ $task->schedule_days ?? "" }}';
            
            // Show correct options on load
            if (currentSchedule === 'weekly') {
                $('#weekly-options').show();
                if (currentDays) {
                    selectedDays = currentDays.split(',');
                    selectedDays.forEach(function(day) {
                        $('.day-btn[data-day="' + day + '"]').css('background', '#0d6efd');
                    });
                }
            } else if (currentSchedule === 'monthly') {
                $('#monthly-options').show();
                if (currentDays) {
                    selectedDates = currentDays.split(',');
                    selectedDates.forEach(function(date) {
                        $('.date-btn[data-date="' + date + '"]').css('background', '#fd7e14');
                    });
                }
            }
            
            // Highlight current schedule button
            $('.schedule-btn[data-schedule="' + currentSchedule + '"]').css('background', '#fd7e14').addClass('active');
            $('.schedule-btn').not('[data-schedule="' + currentSchedule + '"]').css('background', '#6c757d').removeClass('active');

            // Schedule Type Selection (D/W/M buttons)
            $('.schedule-btn').on('click', function() {
                // Remove active class from all
                $('.schedule-btn').removeClass('active').css('background', '#6c757d');
                
                // Add active class to clicked button
                $(this).addClass('active').css('background', '#fd7e14');
                
                // Update hidden input
                var scheduleType = $(this).data('schedule');
                $('#schedule_type').val(scheduleType);
                
                // Show/hide day/date selection
                $('.schedule-options').hide();
                if (scheduleType === 'weekly') {
                    $('#weekly-options').show();
                } else if (scheduleType === 'monthly') {
                    $('#monthly-options').show();
                }
                
                // Reset selections
                selectedDays = [];
                selectedDates = [];
                $('.day-btn').css('background', '#6c757d');
                $('.date-btn').css('background', '#e9ecef');
                $('#schedule_days').val('');
            });

            // Weekday Selection (for Weekly)
            $('.day-btn').on('click', function() {
                var day = $(this).data('day');
                var index = selectedDays.indexOf(day);
                
                if (index > -1) {
                    selectedDays.splice(index, 1);
                    $(this).css('background', '#6c757d');
                } else {
                    selectedDays.push(day);
                    $(this).css('background', '#0d6efd');
                }
                
                $('#schedule_days').val(selectedDays.join(','));
            });

            // Date Selection (for Monthly)
            $('.date-btn').on('click', function() {
                var date = $(this).data('date');
                var index = selectedDates.indexOf(String(date));
                
                if (index > -1) {
                    selectedDates.splice(index, 1);
                    $(this).css('background', '#e9ecef');
                } else {
                    selectedDates.push(String(date));
                    $(this).css('background', '#fd7e14');
                }
                
                $('#schedule_days').val(selectedDates.join(','));
            });

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
