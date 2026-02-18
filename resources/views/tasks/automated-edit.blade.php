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
                        <form action="{{ route('tasks.automatedUpdate', $task->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="schedule_type" id="schedule_type" value="{{ old('schedule_type', $task->schedule_type ?? 'daily') }}">
                            
                            <!-- Action Buttons at Top Right -->
                            <div class="row mb-4">
                                <div class="col-12 text-end">
                                    <button type="button" class="btn btn-secondary" onclick="window.location.href='{{ route('tasks.automated') }}'">
                                        <i class="mdi mdi-arrow-left me-1"></i> Cancel
                                    </button>
                                    <button type="submit" class="btn btn-danger ms-2">
                                        <i class="mdi mdi-check-circle me-1"></i> Update
                                    </button>
                                </div>
                            </div>
                            
                            <input type="hidden" name="priority" value="Normal">
                            
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label for="group" class="form-label fw-bold" style="font-size: 13px;">Group</label>
                                    <input type="text" class="form-control form-control-sm @error('group') is-invalid @enderror" 
                                           id="group" name="group" placeholder="Enter Group" value="{{ old('group', $task->group) }}">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="title" class="form-label fw-bold" style="font-size: 13px;">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('title') is-invalid @enderror" 
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title', $task->title) }}" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="etc_minutes" class="form-label fw-bold" style="font-size: 13px;">ETC (Min) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm @error('etc_minutes') is-invalid @enderror" 
                                           id="etc_minutes" name="etc_minutes" placeholder="10" value="{{ old('etc_minutes', $task->etc_minutes) }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="assignor_id" class="form-label fw-bold" style="font-size: 13px;">Assignor</label>
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
                                        <input type="text" class="form-control form-control-sm" value="{{ Auth::user()->name }}" readonly>
                                        <input type="hidden" name="assignor_id" value="{{ $task->assignor_id }}">
                                    @endif
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="assignee_id" class="form-label fw-bold" style="font-size: 13px;">Assignee</label>
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
                                <div class="col-md-2 mb-2">
                                    <label for="l1" class="form-label fw-bold" style="font-size: 13px;">L1</label>
                                    <input type="text" class="form-control form-control-sm @error('l1') is-invalid @enderror" 
                                           id="l1" name="l1" placeholder="L1" value="{{ old('l1', $task->l1) }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="l2" class="form-label fw-bold" style="font-size: 13px;">L2</label>
                                    <input type="text" class="form-control form-control-sm @error('l2') is-invalid @enderror" 
                                           id="l2" name="l2" placeholder="L2" value="{{ old('l2', $task->l2) }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="training_link" class="form-label fw-bold" style="font-size: 13px;">Training</label>
                                    <input type="text" class="form-control form-control-sm @error('training_link') is-invalid @enderror" 
                                           id="training_link" name="training_link" placeholder="Training Link" value="{{ old('training_link', $task->training_link) }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="video_link" class="form-label fw-bold" style="font-size: 13px;">Video</label>
                                    <input type="text" class="form-control form-control-sm @error('video_link') is-invalid @enderror" 
                                           id="video_link" name="video_link" placeholder="Video Link" value="{{ old('video_link', $task->video_link) }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="form_link" class="form-label fw-bold" style="font-size: 13px;">Form</label>
                                    <input type="text" class="form-control form-control-sm @error('form_link') is-invalid @enderror" 
                                           id="form_link" name="form_link" placeholder="Form Link" value="{{ old('form_link', $task->form_link) }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="form_report_link" class="form-label fw-bold" style="font-size: 13px;">Form Report</label>
                                    <input type="text" class="form-control form-control-sm @error('form_report_link') is-invalid @enderror" 
                                           id="form_report_link" name="form_report_link" placeholder="Report Link" value="{{ old('form_report_link', $task->form_report_link) }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label for="checklist_link" class="form-label fw-bold" style="font-size: 13px;">Checklist</label>
                                    <input type="text" class="form-control form-control-sm @error('checklist_link') is-invalid @enderror" 
                                           id="checklist_link" name="checklist_link" placeholder="Checklist Link" value="{{ old('checklist_link', $task->checklist_link) }}">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="pl" class="form-label fw-bold" style="font-size: 13px;">PL</label>
                                    <input type="text" class="form-control form-control-sm @error('pl') is-invalid @enderror" 
                                           id="pl" name="pl" placeholder="PL Link" value="{{ old('pl', $task->pl) }}">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="image" class="form-label fw-bold" style="font-size: 13px;">Image</label>
                                    <input type="file" class="form-control form-control-sm @error('image') is-invalid @enderror" 
                                           id="image" name="image" accept="image/*">
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="schedule_time" class="form-label fw-bold" style="font-size: 13px;">Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control form-control-sm" id="schedule_time" name="schedule_time" value="{{ old('schedule_time', $task->schedule_time ?? '12:01') }}" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-2">
                                    <label class="form-label fw-bold" style="font-size: 13px;">Frequency <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button type="button" class="btn schedule-btn active" data-schedule="daily" 
                                                style="background: #fd7e14; color: white; width: 40px; height: 32px; font-size: 11px; font-weight: bold; border-radius: 4px; padding: 0;">D</button>
                                        <button type="button" class="btn schedule-btn" data-schedule="weekly" 
                                                style="background: #6c757d; color: white; width: 40px; height: 32px; font-size: 11px; font-weight: bold; border-radius: 4px; padding: 0;">W</button>
                                        <button type="button" class="btn schedule-btn" data-schedule="monthly" 
                                                style="background: #6c757d; color: white; width: 40px; height: 32px; font-size: 11px; font-weight: bold; border-radius: 4px; padding: 0;">M</button>
                                        
                                        <!-- Weekly Days Inline -->
                                        <div class="schedule-options d-flex gap-1 ms-3" id="weekly-options" style="display: none !important;">
                                            <button type="button" class="btn day-btn" data-day="Sun" style="background: #6c757d; color: white; width: 35px; height: 32px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">S</button>
                                            <button type="button" class="btn day-btn" data-day="Mon" style="background: #6c757d; color: white; width: 35px; height: 32px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">M</button>
                                            <button type="button" class="btn day-btn" data-day="Tue" style="background: #6c757d; color: white; width: 35px; height: 32px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">T</button>
                                            <button type="button" class="btn day-btn" data-day="Wed" style="background: #6c757d; color: white; width: 35px; height: 32px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">W</button>
                                            <button type="button" class="btn day-btn" data-day="Thu" style="background: #6c757d; color: white; width: 35px; height: 32px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">T</button>
                                            <button type="button" class="btn day-btn" data-day="Fri" style="background: #6c757d; color: white; width: 35px; height: 32px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">F</button>
                                            <button type="button" class="btn day-btn" data-day="Sat" style="background: #6c757d; color: white; width: 35px; height: 32px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">S</button>
                                        </div>
                                        
                                        <!-- Monthly Dates Inline -->
                                        <div class="schedule-options d-flex gap-1 flex-wrap ms-3" id="monthly-options" style="display: none !important; max-width: 500px;">
                                            @for($i = 1; $i <= 31; $i++)
                                                <button type="button" class="btn date-btn" data-date="{{ $i }}" style="background: #e9ecef; color: #495057; width: 28px; height: 28px; font-size: 10px; font-weight: 600; border-radius: 4px; padding: 0;">{{ $i }}</button>
                                            @endfor
                                            <button type="button" class="btn date-btn" data-date="EOM" style="background: #e9ecef; color: #495057; width: 40px; height: 28px; font-size: 8px; font-weight: 600; border-radius: 4px; padding: 0;">EOM</button>
                                        </div>
                                        <input type="hidden" name="schedule_days" id="schedule_days" value="{{ $task->schedule_days ?? '' }}">
                                    </div>
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
