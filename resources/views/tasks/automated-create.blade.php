@extends('layouts.vertical', ['title' => 'Create Automated Task', 'sidenav' => 'condensed'])

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        .auto-task-mode-opt:has(input:checked) {
            border-color: #667eea !important;
            background: #eef0ff;
            box-shadow: inset 0 0 0 1px #667eea;
        }
        #auto-task-mode-wrap.is-invalid {
            border-color: #dc3545 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.15);
        }
    </style>
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
                            <li class="breadcrumb-item active">Create Automated Task</li>
                        </ol>
                    </div>
                    <h4 class="page-title">Create Automated Task</h4>
                </div>
            </div>
        </div>     
        <!-- end page title --> 

        <div class="row">
            <div class="col-12">
                <div class="card" style="border: 2px solid #fd7e14; box-shadow: 0 4px 15px rgba(253, 126, 20, 0.15);">
                    <div class="card-header position-relative" style="background: linear-gradient(135deg, #f7b733 0%, #fc4a1a 100%); color: white;">
                        <h5 class="mb-0">
                            <i class="mdi mdi-robot me-2"></i>Automated Task Information
                        </h5>
                        <button type="button" 
                                onclick="window.location.href='{{ route('tasks.automated') }}'" 
                                class="btn-close btn-close-white position-absolute" 
                                style="top: 50%; right: 15px; transform: translateY(-50%);" 
                                aria-label="Close"></button>
                    </div>
                    <div class="card-body" style="padding: 30px;">
                        @if(session('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-check-circle me-2"></i>{{ session('success') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="mdi mdi-alert-circle me-2"></i>{{ session('error') }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        <form action="{{ route('tasks.automatedStore') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="schedule_type" id="schedule_type" value="daily">
                            
                            <!-- Action Buttons at Top Right -->
                            <div class="row mb-4">
                                <div class="col-12 text-end">
                                    <button type="button" id="auto-create-channel-btn" class="btn btn-outline-primary me-2" title="Create this task once for every active channel. Group becomes the channel name.">
                                        <i class="mdi mdi-plus me-1"></i> Channel Task
                                    </button>
                                    <button type="submit" name="after_create" value="list" class="btn btn-danger">
                                        <i class="mdi mdi-check-circle me-1"></i> Create
                                    </button>
                                    <button type="submit" name="after_create" value="another" class="btn btn-outline-danger ms-2" title="Save this task and open a blank form for another">
                                        <i class="mdi mdi-plus-circle-multiple-outline me-1"></i> Save and create more
                                    </button>
                                </div>
                            </div>
                            
                            <input type="hidden" name="priority" value="Normal">
                            
                            <div class="row">
                                <div class="col-md-3 mb-2">
                                    <label for="group" class="form-label fw-bold" style="font-size: 13px;">Group</label>
                                    <input type="text" class="form-control form-control-sm @error('group') is-invalid @enderror" 
                                           id="group" name="group" placeholder="Enter Group" value="{{ old('group') }}">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="title" class="form-label fw-bold" style="font-size: 13px;">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('title') is-invalid @enderror" 
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title') }}" required>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label for="etc_minutes" class="form-label fw-bold" style="font-size: 13px;">ETC (Min) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm @error('etc_minutes') is-invalid @enderror" 
                                           id="etc_minutes" name="etc_minutes" placeholder="10" value="{{ old('etc_minutes', 10) }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="assignor_id" class="form-label fw-bold" style="font-size: 13px;">Assignor</label>
                                    @if(strtolower(Auth::user()->role ?? '') === 'admin')
                                        <select class="form-select form-select-sm select2 @error('assignor_id') is-invalid @enderror" 
                                                id="assignor_id" name="assignor_id">
                                            @foreach($users as $user)
                                                <option value="{{ $user->id }}" {{ old('assignor_id', Auth::id()) == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" class="form-control form-control-sm" value="{{ Auth::user()->name }}" readonly>
                                        <input type="hidden" name="assignor_id" value="{{ Auth::id() }}">
                                    @endif
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <label for="assignee_ids" class="form-label fw-bold mb-0" style="font-size: 13px;">Assignee(s) <span class="text-danger">*</span></label>
                                        <button type="button" id="auto-assignee-select-all" class="btn btn-link btn-sm p-0" style="font-size: 12px; font-weight: 600; text-decoration: none;">Select all</button>
                                    </div>
                                    <select class="form-select form-select-sm select2-assignees @error('assignee_ids') is-invalid @enderror"
                                            id="assignee_ids" name="assignee_ids[]" multiple required>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ collect(old('assignee_ids', []))->contains($user->id) ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div id="auto-task-mode-wrap" class="border rounded p-2 d-none" style="background:#f7f8ff; border-color:#d6d9f5 !important;" role="group" aria-label="Joint or split task">
                                        <div style="font-size:12px; font-weight:600; margin-bottom:6px;">More than one assignee. Is this task joint or split? <span class="text-danger">*</span></div>
                                        <div class="d-flex gap-2">
                                            <label class="auto-task-mode-opt flex-fill border rounded px-2 py-1 mb-0" style="cursor:pointer; font-size:12px;">
                                                <input type="radio" name="auto_task_mode" value="joint" class="me-1">
                                                <strong>Joint</strong> <span class="text-muted">— one shared task</span>
                                            </label>
                                            <label class="auto-task-mode-opt flex-fill border rounded px-2 py-1 mb-0" style="cursor:pointer; font-size:12px;">
                                                <input type="radio" name="auto_task_mode" value="split" class="me-1">
                                                <strong>Split</strong> <span class="text-muted">— each gets a copy</span>
                                            </label>
                                        </div>
                                        <input type="hidden" name="split_tasks" id="auto_split_tasks" value="1" disabled>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2 d-flex align-items-end">
                                    <label for="auto_create_for_all_channels" class="d-flex align-items-center gap-2 mb-0 border rounded px-2 w-100" style="min-height: 38px; cursor: pointer;">
                                        <input type="checkbox" class="form-check-input m-0" id="auto_create_for_all_channels" name="create_for_all_channels" value="1" style="width: 18px; height: 18px;">
                                        <i class="mdi mdi-storefront-outline"></i>
                                        <span style="font-size: 13px;">Create for every active channel <span class="text-muted">(Group = channel name)</span></span>
                                    </label>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <label for="l1" class="form-label fw-bold" style="font-size: 13px;">L1</label>
                                    <input type="text" class="form-control form-control-sm @error('l1') is-invalid @enderror" 
                                           id="l1" name="l1" placeholder="L1" value="{{ old('l1') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="l2" class="form-label fw-bold" style="font-size: 13px;">L2</label>
                                    <input type="text" class="form-control form-control-sm @error('l2') is-invalid @enderror" 
                                           id="l2" name="l2" placeholder="L2" value="{{ old('l2') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="training_link" class="form-label fw-bold" style="font-size: 13px;">Training</label>
                                    <input type="text" class="form-control form-control-sm @error('training_link') is-invalid @enderror" 
                                           id="training_link" name="training_link" placeholder="Training Link" value="{{ old('training_link') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="video_link" class="form-label fw-bold" style="font-size: 13px;">Video</label>
                                    <input type="text" class="form-control form-control-sm @error('video_link') is-invalid @enderror" 
                                           id="video_link" name="video_link" placeholder="Video Link" value="{{ old('video_link') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="form_link" class="form-label fw-bold" style="font-size: 13px;">Form</label>
                                    <input type="text" class="form-control form-control-sm @error('form_link') is-invalid @enderror" 
                                           id="form_link" name="form_link" placeholder="Form Link" value="{{ old('form_link') }}">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label for="form_report_link" class="form-label fw-bold" style="font-size: 13px;">Form Report</label>
                                    <input type="text" class="form-control form-control-sm @error('form_report_link') is-invalid @enderror" 
                                           id="form_report_link" name="form_report_link" placeholder="Report Link" value="{{ old('form_report_link') }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <label for="checklist_link" class="form-label fw-bold" style="font-size: 13px;">Checklist</label>
                                    <input type="text" class="form-control form-control-sm @error('checklist_link') is-invalid @enderror" 
                                           id="checklist_link" name="checklist_link" placeholder="Checklist Link" value="{{ old('checklist_link') }}">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label for="image" class="form-label fw-bold" style="font-size: 13px;">Image</label>
                                    <input type="file" class="form-control form-control-sm @error('image') is-invalid @enderror" 
                                           id="image" name="image" accept="image/*">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label for="schedule_time" class="form-label fw-bold" style="font-size: 13px;">Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control form-control-sm" id="schedule_time" name="schedule_time" value="12:00" required>
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
                                        <input type="hidden" name="schedule_days" id="schedule_days" value="">
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
            $('.select2').select2({
                theme: 'bootstrap-5',
                placeholder: 'Please Select'
            });

            var $assignees = $('#assignee_ids');
            $assignees.select2({
                theme: 'bootstrap-5',
                placeholder: 'Select assignees',
                closeOnSelect: false,
                width: '100%'
            });

            function syncAutoTaskMode() {
                var count = ($assignees.val() || []).length;
                var $wrap = $('#auto-task-mode-wrap');
                if (count > 1) {
                    $wrap.removeClass('d-none');
                    var mode = $wrap.find('input[name="auto_task_mode"]:checked').val();
                    $('#auto_split_tasks').prop('disabled', mode !== 'split');
                } else {
                    $wrap.addClass('d-none').removeClass('is-invalid');
                    $wrap.find('input[name="auto_task_mode"]').prop('checked', false);
                    $('#auto_split_tasks').prop('disabled', true);
                }
            }

            $assignees.on('change', syncAutoTaskMode);

            $('#auto-task-mode-wrap').on('change', 'input[name="auto_task_mode"]', function () {
                $('#auto-task-mode-wrap').removeClass('is-invalid');
                $('#auto_split_tasks').prop('disabled', $(this).val() !== 'split');
            });

            $('#auto-assignee-select-all').on('click', function () {
                var all = $assignees.find('option').map(function () { return this.value; }).get();
                $assignees.val(all).trigger('change');
            });

            // When creating for every channel, Group is set per-channel on the server.
            $('#auto_create_for_all_channels').on('change', function () {
                var on = $(this).is(':checked');
                $('#group').prop('disabled', on);
                $('#group').attr('placeholder', on ? 'Group = channel name' : 'Enter Group');
            });

            // Channel Task button: same as ticking "Create for every active channel" and creating.
            $('#auto-create-channel-btn').on('click', function () {
                var form = $(this).closest('form')[0];
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                if (!confirm('Create this task for every active channel? Group will be each channel name.')) {
                    return;
                }
                $('#auto_create_for_all_channels').prop('checked', true).trigger('change');
                form.requestSubmit();
            });

            // Require a Joint/Split choice when more than one assignee is selected.
            $('form').on('submit', function (e) {
                var count = ($assignees.val() || []).length;
                if (count > 1 && !$('input[name="auto_task_mode"]:checked').length) {
                    e.preventDefault();
                    alert('More than one assignee is selected. Choose whether this task is Joint or Split.');
                    $('#auto-task-mode-wrap').addClass('is-invalid');
                    return false;
                }
            });

            syncAutoTaskMode();

            var selectedDays = [];
            var selectedDates = [];

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
                selectedDays = [];
                selectedDates = [];
                $('.day-btn').css('background', '#6c757d');
                $('.date-btn').css('background', '#e9ecef');
                $('#schedule_days').val('');

                if (scheduleType === 'weekly') {
                    $('#weekly-options').show();
                    selectedDays = ['Mon'];
                    $('.day-btn[data-day="Mon"]').css('background', '#0d6efd');
                    $('#schedule_days').val('Mon');
                } else if (scheduleType === 'monthly') {
                    $('#monthly-options').show();
                }
            });

            // Weekday Selection (for Weekly)
            $('.day-btn').on('click', function() {
                var day = $(this).data('day');
                var index = selectedDays.indexOf(day);
                
                if (index > -1) {
                    // Already selected - remove it
                    selectedDays.splice(index, 1);
                    $(this).css('background', '#6c757d');
                } else {
                    // Not selected - add it
                    selectedDays.push(day);
                    $(this).css('background', '#0d6efd'); // Blue when selected
                }
                
                // Update hidden field
                $('#schedule_days').val(selectedDays.join(','));
            });

            // Date Selection (for Monthly)
            $('.date-btn').on('click', function() {
                var date = $(this).data('date');
                var index = selectedDates.indexOf(date);
                
                if (index > -1) {
                    // Already selected - remove it
                    selectedDates.splice(index, 1);
                    $(this).css('background', '#e9ecef');
                } else {
                    // Not selected - add it
                    selectedDates.push(date);
                    $(this).css('background', '#fd7e14'); // Orange when selected
                }
                
                // Update hidden field
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
