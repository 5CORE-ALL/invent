@extends('layouts.vertical', ['title' => 'Create Task', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        /* Mobile View Styles */
        @media (max-width: 767.98px) {
            .mobile-view {
                padding: 10px;
            }
            
            .mobile-view .card {
                border-radius: 15px;
                overflow: hidden;
            }
            
            .mobile-view .form-control,
            .mobile-view .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
                border-radius: 8px;
            }
            
            .mobile-view .btn {
                border-radius: 8px;
                font-weight: 500;
            }
            
            .mobile-view .card-header h5 {
                font-size: 18px;
            }
            
            /* Container fluid padding adjustment for mobile */
            .container-fluid {
                padding-left: 5px;
                padding-right: 5px;
            }
        }
        
        /* Desktop View - ensure it's hidden on mobile */
        @media (max-width: 767.98px) {
            .desktop-view {
                display: none !important;
            }
        }
        
        /* Ensure mobile view is hidden on desktop */
        @media (min-width: 768px) {
            .mobile-view {
                display: none !important;
            }
        }
    </style>
@endsection

@section('content')
    <!-- Start Content-->
    <div class="container-fluid">
        
        <!-- Mobile View - Simple Form (Only visible on phones) -->
        <div class="mobile-view d-md-none" style="padding: 0;">
            <div class="card border-0 shadow-sm" style="border-radius: 0; margin: 0;">
                <div class="card-header bg-gradient text-white position-relative" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0;">
                    <h5 class="mb-0">
                        <i class="mdi mdi-plus-circle me-2"></i>Create New Task
                    </h5>
                    <a href="{{ route('tasks.index') }}" 
                       class="btn-close btn-close-white position-absolute" 
                       style="top: 50%; right: 15px; transform: translateY(-50%);" 
                       aria-label="Close"></a>
                </div>
                <div class="card-body p-3">
                    <form action="{{ route('tasks.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- Hidden default values for mobile -->
                        <input type="hidden" name="priority" value="normal">
                        <input type="hidden" name="assignor_id" value="{{ Auth::id() }}">
                        <input type="hidden" name="etc_minutes" value="10">
                        <input type="hidden" name="tid" value="{{ now()->format('Y-m-d\TH:i') }}">
                        
                        <!-- Task Input -->
                        <div class="mb-3">
                            <label for="mobile_title" class="form-label fw-bold">
                                Task <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg @error('title') is-invalid @enderror" 
                                   id="mobile_title" 
                                   name="title" 
                                   placeholder="Enter task description" 
                                   value="{{ old('title') }}" 
                                   required
                                   autofocus>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Assign To (Required on Mobile) -->
                        <div class="mb-3">
                            <label for="assignee_id" class="form-label fw-bold">
                                Assign To <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg @error('assignee_id') is-invalid @enderror" 
                                    id="assignee_id" 
                                    name="assignee_id">
                                <option value="">Select Person</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" {{ old('assignee_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('assignee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            
                            <!-- Multiple Assignment Option -->
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="enable_multiple_assign" style="width: 18px; height: 18px;">
                                <label class="form-check-label" for="enable_multiple_assign" style="margin-left: 8px;">
                                    <small>Or assign to multiple users</small>
                                </label>
                            </div>
                            
                            <!-- Multiple Assignees (Hidden by default) -->
                            <div id="multiple-assignees-section" style="display: none; margin-top: 10px;">
                                <label class="form-label fw-bold">Select Multiple Users:</label>
                                <div class="border rounded p-3" style="background: #f8f9fa; max-height: 200px; overflow-y: auto;">
                                    @foreach($users as $user)
                                        <div class="form-check mb-2">
                                            <input class="form-check-input multi-assignee-check" 
                                                   type="checkbox" 
                                                   name="assignee_ids[]" 
                                                   value="{{ $user->id }}" 
                                                   id="multi_assignee_{{ $user->id }}"
                                                   style="width: 18px; height: 18px;">
                                            <label class="form-check-label" for="multi_assignee_{{ $user->id }}" style="font-size: 14px; margin-left: 8px; cursor: pointer;">
                                                {{ $user->name }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div id="multi-selected-count" class="mt-2"></div>
                            </div>
                        </div>

                        <!-- Image Upload with Camera & Paste -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="mdi mdi-image me-1"></i>Image (Optional)
                            </label>
                            
                            <!-- Hidden file inputs -->
                            <input type="file" 
                                   id="task_image_input" 
                                   name="image" 
                                   accept="image/*"
                                   class="@error('image') is-invalid @enderror"
                                   style="display: none;">
                            
                            <input type="file" 
                                   id="task_camera_input" 
                                   accept="image/*"
                                   capture="environment"
                                   style="display: none;">
                            
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 mb-2">
                                <button type="button" class="btn btn-success btn-lg" id="take-photo-btn">
                                    <i class="mdi mdi-camera me-2"></i>📷 Take Photo with Camera
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg" id="choose-file-btn">
                                    <i class="mdi mdi-folder-open me-2"></i>📁 Choose from Files
                                </button>
                            </div>
                            
                            <!-- Paste Area - SIMPLE & RELIABLE -->
                            <div id="paste-box" 
                                 class="border rounded p-4 text-center" 
                                 style="border: 2px dashed #667eea; background-color: #f8f9fa; cursor: pointer; min-height: 150px;"
                                 contenteditable="true">
                                <div id="paste-instructions" style="pointer-events: none;">
                                    <i class="mdi mdi-content-paste" style="font-size: 48px; color: #667eea;"></i>
                                    <p class="mb-1 mt-2" style="color: #667eea; font-weight: 600; font-size: 16px;">
                                        Click here, then press Ctrl+V
                                    </p>
                                    <div style="background: #e7f3ff; padding: 10px; border-radius: 8px; margin-top: 10px;">
                                        <small style="color: #0d6efd; font-weight: 600;">
                                            <i class="mdi mdi-information"></i> How to take screenshot:
                                        </small><br>
                                        <small class="text-muted">
                                            <strong>Windows:</strong> Win+Shift+S (auto-copies!)<br>
                                            <strong>Mac:</strong> Cmd+Shift+4, then Cmd+C to copy<br>
                                            <strong>Then:</strong> Click this box and press Ctrl+V
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Image Preview -->
                            <div id="task-preview-area" style="display: none; margin-top: 10px;"></div>
                            
                            @error('image')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-lg btn-danger">
                                <i class="mdi mdi-check-circle me-1"></i> Save Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- End Mobile View -->
        
        <!-- Desktop View (Hidden on phones) -->
        <div class="desktop-view d-none d-md-block">
            <!-- start page title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box">
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                                <li class="breadcrumb-item"><a href="{{ route('tasks.index') }}">Task Manager</a></li>
                                <li class="breadcrumb-item active">Create Task</li>
                            </ol>
                        </div>
                        <h4 class="page-title">Create Single Task</h4>
                    </div>
                </div>
            </div>     
            <!-- end page title --> 

            <div class="row justify-content-end">
            <div class="col-md-4">
                <div class="card" style="border: 2px solid #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15); position: sticky; top: 20px;">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 15px;">
                        <h6 class="mb-0">
                            <i class="mdi mdi-form-textbox me-1"></i>Create Task
                        </h6>
                    </div>
                    <div class="card-body" style="padding: 15px;">
                        <form action="{{ route('tasks.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="group" class="form-label fw-bold" style="font-size: 12px;">Group</label>
                                    <input type="text" class="form-control form-control-sm @error('group') is-invalid @enderror" 
                                           id="group" name="group" placeholder="Group" value="{{ old('group') }}">
                                </div>
                                <div class="col-12 mb-2">
                                    <label for="title" class="form-label fw-bold" style="font-size: 12px;">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm @error('title') is-invalid @enderror" 
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title') }}" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="desktop_assignee_id" class="form-label fw-bold" style="font-size: 12px;">Assignee</label>
                                    <select class="form-select form-select-sm select2 @error('assignee_id') is-invalid @enderror" 
                                            id="desktop_assignee_id" 
                                            name="assignee_id">
                                        <option value="">Please Select</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ old('assignee_id') == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="desktop_enable_multiple_assign" style="font-size: 10px;">
                                        <label class="form-check-label" for="desktop_enable_multiple_assign" style="font-size: 10px;">
                                            Multiple users
                                        </label>
                                    </div>
                                    <div id="desktop-multiple-assignees-section" style="display: none; margin-top: 5px;">
                                        <div class="border rounded p-2" style="background: #f8f9fa; max-height: 150px; overflow-y: auto; font-size: 11px;">
                                            @foreach($users as $user)
                                                <div class="form-check">
                                                    <input class="form-check-input desktop-multi-assignee-check" 
                                                           type="checkbox" 
                                                           name="assignee_ids[]" 
                                                           value="{{ $user->id }}" 
                                                           id="desktop_multi_assignee_{{ $user->id }}">
                                                    <label class="form-check-label" for="desktop_multi_assignee_{{ $user->id }}">
                                                        {{ $user->name }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-2">
                                    <label for="etc_minutes" class="form-label fw-bold" style="font-size: 12px;">ETC (Min) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm @error('etc_minutes') is-invalid @enderror" 
                                           id="etc_minutes" name="etc_minutes" placeholder="10" value="{{ old('etc_minutes', 10) }}">
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
                                            <option value="normal" {{ old('priority', 'normal') == 'normal' ? 'selected' : '' }}>Normal</option>
                                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                                            <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="assignor_id" class="form-label fw-bold" style="font-size: 12px;">Assignor <span class="text-danger">*</span></label>
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
                                    <div class="col-12 mb-2">
                                        <label for="tid" class="form-label fw-bold" style="font-size: 12px;">TID <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control form-control-sm @error('tid') is-invalid @enderror" 
                                               id="tid" name="tid" value="{{ old('tid', now()->format('Y-m-d\TH:i')) }}">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label for="l1" class="form-label fw-bold" style="font-size: 12px;">L1</label>
                                        <input type="text" class="form-control form-control-sm @error('l1') is-invalid @enderror" 
                                               id="l1" name="l1" placeholder="L1" value="{{ old('l1') }}">
                                    </div>
                                    <div class="col-6 mb-2">
                                        <label for="l2" class="form-label fw-bold" style="font-size: 12px;">L2</label>
                                        <input type="text" class="form-control form-control-sm @error('l2') is-invalid @enderror" 
                                               id="l2" name="l2" placeholder="L2" value="{{ old('l2') }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="training_link" class="form-label fw-bold" style="font-size: 12px;">Training</label>
                                        <input type="text" class="form-control form-control-sm @error('training_link') is-invalid @enderror" 
                                               id="training_link" name="training_link" placeholder="Training Link" value="{{ old('training_link') }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="video_link" class="form-label fw-bold" style="font-size: 12px;">Video</label>
                                        <input type="text" class="form-control form-control-sm @error('video_link') is-invalid @enderror" 
                                               id="video_link" name="video_link" placeholder="Video Link" value="{{ old('video_link') }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="form_link" class="form-label fw-bold" style="font-size: 12px;">Form</label>
                                        <input type="text" class="form-control form-control-sm @error('form_link') is-invalid @enderror" 
                                               id="form_link" name="form_link" placeholder="Form Link" value="{{ old('form_link') }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="form_report_link" class="form-label fw-bold" style="font-size: 12px;">Form Report</label>
                                        <input type="text" class="form-control form-control-sm @error('form_report_link') is-invalid @enderror" 
                                               id="form_report_link" name="form_report_link" placeholder="Report Link" value="{{ old('form_report_link') }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="checklist_link" class="form-label fw-bold" style="font-size: 12px;">Checklist</label>
                                        <input type="text" class="form-control form-control-sm @error('checklist_link') is-invalid @enderror" 
                                               id="checklist_link" name="checklist_link" placeholder="Checklist Link" value="{{ old('checklist_link') }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="pl" class="form-label fw-bold" style="font-size: 12px;">PL</label>
                                        <input type="text" class="form-control form-control-sm @error('pl') is-invalid @enderror" 
                                               id="pl" name="pl" placeholder="PL Link" value="{{ old('pl') }}">
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="image" class="form-label fw-bold" style="font-size: 12px;">Image</label>
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
                                    <button type="submit" class="btn btn-sm btn-danger w-100">
                                        <i class="mdi mdi-check-circle me-1"></i> Create Task
                                    </button>
                                </div>
                            </div>

                        </form>

                    </div> <!-- end card-body-->
                </div> <!-- end card-->
            </div> <!-- end col -->
            </div>
            <!-- end row -->
        </div>
        <!-- End Desktop View -->

    </div> <!-- container -->
@endsection

@section('script')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                theme: 'bootstrap-5',
                placeholder: 'Please Select'
            });

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

            // ==========================================
            // SIMPLE IMAGE UPLOAD: CAMERA + PASTE + FILE
            // ==========================================
            
            const taskImageInput = document.getElementById('task_image_input');
            const taskCameraInput = document.getElementById('task_camera_input');
            const previewArea = document.getElementById('task-image-preview');
            const previewPlaceholder = document.getElementById('preview-placeholder');
            const previewImage = document.getElementById('preview-image');
            
            // Check if elements exist (mobile view)
            if (taskImageInput && taskCameraInput) {
                console.log('✓ Image upload initialized');
                
                const pasteBox = document.getElementById('paste-box');
                const pasteInstructions = document.getElementById('paste-instructions');
                const previewArea = document.getElementById('task-preview-area');
                
                // Take Photo Button - Opens Camera
                $('#take-photo-btn').on('click', function() {
                    console.log('📷 Camera button clicked!');
                    const btn = $(this);
                    const originalHtml = btn.html();
                    
                    // Show loading state
                    btn.html('<i class="mdi mdi-loading mdi-spin me-2"></i>Opening camera...').prop('disabled', true);
                    
                    // Trigger camera input
                    console.log('✓ Triggering camera input...');
                    taskCameraInput.click();
                    
                    // Reset button after 2 seconds (in case user cancels)
                    setTimeout(() => {
                        btn.html(originalHtml).prop('disabled', false);
                    }, 2000);
                });
                
                // Paste Box - Click to activate
                pasteBox.addEventListener('click', function() {
                    this.style.borderColor = '#28a745';
                    this.style.backgroundColor = '#f0fff4';
                    console.log('✓ Paste box activated - Press Ctrl+V now');
                });
                
                pasteBox.addEventListener('blur', function() {
                    if (!taskImageInput.files.length) {
                        this.style.borderColor = '#667eea';
                        this.style.backgroundColor = '#f8f9fa';
                    }
                });
                
                // Paste Box - Handle paste event (SIMPLE & WORKING!)
                pasteBox.addEventListener('paste', function(e) {
                    console.log('📋 PASTE EVENT DETECTED IN BOX!');
                    e.preventDefault();
                    
                    const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                    console.log('Items:', items.length);
                    
                    for (let i = 0; i < items.length; i++) {
                        console.log(`Item ${i}: ${items[i].type} (${items[i].kind})`);
                        
                        if (items[i].type.indexOf('image') !== -1) {
                            console.log('✓ IMAGE FOUND!');
                            
                            const blob = items[i].getAsFile();
                            const file = new File([blob], `screenshot_${Date.now()}.png`, { type: 'image/png' });
                            
                            console.log('✓ File created:', file.name, file.size, 'bytes');
                            
                            // Set to file input
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            taskImageInput.files = dt.files;
                            
                            // Show preview
                            showTaskPreview(file);
                            
                            // Success notification
                            const notification = $('<div class="alert alert-success alert-dismissible position-fixed" style="top: 70px; left: 20px; right: 20px; z-index: 9999;">')
                                .html('<i class="mdi mdi-check-circle me-2"></i><strong>✓ Screenshot Pasted!</strong><button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                            $('body').append(notification);
                            setTimeout(() => notification.fadeOut(() => notification.remove()), 2000);
                            
                            console.log('✓ PASTE COMPLETE!');
                            return;
                        }
                    }
                    
                    // No image found - show helpful guidance
                    const foundTypes = Array.from(items).map(i => i.type).join(', ');
                    console.log('⚠️ No image in paste - only found:', foundTypes);
                    
                    alert(`⚠️ NO IMAGE IN CLIPBOARD\n\nYour clipboard has: ${foundTypes}\n\n✅ HOW TO FIX:\n\n` +
                          `WINDOWS:\n` +
                          `• Press Win + Shift + S\n` +
                          `• Select area\n` +
                          `• Image copies to clipboard automatically\n\n` +
                          `MAC:\n` +
                          `• Press Cmd + Shift + 4\n` +
                          `• Select area\n` +
                          `• Then press Cmd + C to copy\n\n` +
                          `OR USE:\n` +
                          `• "Take Photo" button (opens camera)\n` +
                          `• "Choose File" button (select from files)\n\n` +
                          `Then try pasting again!`);
                    
                    // Reset paste box
                    pasteBox.blur();
                });
                
                // Choose File Button
                $('#choose-file-btn').on('click', function() {
                    console.log('📁 Choose file clicked');
                    taskImageInput.click();
                });
                
                // Handle camera photo
                taskCameraInput.addEventListener('change', function(e) {
                    console.log('📷 Camera input changed!');
                    const file = e.target.files[0];
                    if (file) {
                        console.log('✓ Photo captured:', file.name, 'Size:', file.size, 'bytes');
                        console.log('✓ File type:', file.type);
                        
                        // Transfer to main input
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        taskImageInput.files = dt.files;
                        console.log('✓ File transferred to main input');
                        
                        // Show preview
                        showTaskPreview(file);
                        
                        // Success notification
                        const notification = $('<div class="alert alert-success alert-dismissible position-fixed" style="top: 70px; left: 20px; right: 20px; z-index: 9999;">')
                            .html('<i class="mdi mdi-check-circle me-2"></i><strong>✓ Photo Captured!</strong><button type="button" class="btn-close" data-bs-dismiss="alert"></button>');
                        $('body').append(notification);
                        setTimeout(() => notification.fadeOut(() => notification.remove()), 2000);
                    } else {
                        console.log('⚠️ No file selected from camera');
                    }
                });
                
                // Handle file selection
                taskImageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        console.log('✓ File selected:', file.name);
                        showTaskPreview(file);
                    }
                });
                
                // Show image preview
                function showTaskPreview(file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        // Update paste box
                        pasteBox.contentEditable = 'false';
                        pasteBox.style.borderColor = '#28a745';
                        pasteBox.style.backgroundColor = '#f0fff4';
                        pasteBox.style.minHeight = 'auto';
                        pasteBox.style.cursor = 'default';
                        pasteBox.innerHTML = `
                            <div class="text-center">
                                <img src="${e.target.result}" class="img-thumbnail mb-3" style="max-width: 100%; max-height: 300px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                <div class="text-success mb-2">
                                    <i class="mdi mdi-check-circle-outline"></i> <strong>${file.name}</strong>
                                    <br><small>${formatBytes(file.size)}</small>
                                </div>
                                <button type="button" class="btn btn-danger btn-sm" onclick="clearTaskImage()">
                                    <i class="mdi mdi-delete me-1"></i>Remove Image
                                </button>
                            </div>
                        `;
                    };
                    reader.readAsDataURL(file);
                }
                
                // Clear image
                window.clearTaskImage = function() {
                    taskImageInput.value = '';
                    taskCameraInput.value = '';
                    pasteBox.contentEditable = 'true';
                    pasteBox.style.borderColor = '#667eea';
                    pasteBox.style.backgroundColor = '#f8f9fa';
                    pasteBox.style.minHeight = '150px';
                    pasteBox.style.cursor = 'pointer';
                    pasteBox.innerHTML = `
                        <div id="paste-instructions" style="pointer-events: none;">
                            <i class="mdi mdi-content-paste" style="font-size: 48px; color: #667eea;"></i>
                            <p class="mb-1 mt-2" style="color: #667eea; font-weight: 600; font-size: 15px;">
                                Click here, then press Ctrl+V to paste screenshot
                            </p>
                            <small class="text-muted">
                                Take screenshot (Win+Shift+S / Cmd+Shift+4) then paste here
                            </small>
                        </div>
                    `;
                    console.log('✓ Image cleared, paste box reset');
                };
                
                // Format bytes helper
                function formatBytes(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
                }
                
                console.log('✓ Camera, Paste, and File upload ready!');
            }
            
            // ==========================================
            // TOGGLE MULTIPLE ASSIGNEE MODE (MOBILE)
            // ==========================================
            $('#enable_multiple_assign').on('change', function() {
                if ($(this).is(':checked')) {
                    // Show multiple selection, make single optional
                    $('#multiple-assignees-section').slideDown();
                    $('#assignee_id').prop('disabled', true).prop('required', false).val('');
                    console.log('✓ Multiple assignee mode - single dropdown not required');
                } else {
                    // Show single selection, make it required again
                    $('#multiple-assignees-section').slideUp();
                    $('#assignee_id').prop('disabled', false).prop('required', true);
                    $('.multi-assignee-check').prop('checked', false);
                    $('#multi-selected-count').html('');
                    console.log('✓ Single assignee mode - dropdown required');
                }
            });
            
            // Form validation - MOBILE ONLY - ensure at least one assignee selected
            $('form').on('submit', function(e) {
                // Only validate on mobile view
                if (window.innerWidth >= 768) {
                    console.log('Desktop view - skipping mobile validation');
                    return true; // Skip validation for desktop
                }
                
                const multipleEnabled = $('#enable_multiple_assign').is(':checked');
                
                if (multipleEnabled) {
                    // Check if at least one checkbox is checked
                    const selectedCount = $('.multi-assignee-check:checked').length;
                    if (selectedCount === 0) {
                        e.preventDefault();
                        alert('❌ Please select at least one assignee!\n\nCheck the boxes next to user names.');
                        return false;
                    }
                } else {
                    // Check if single dropdown has value
                    const singleAssignee = $('#assignee_id').val();
                    if (!singleAssignee) {
                        e.preventDefault();
                        alert('❌ Please select an assignee!\n\nOr check "Assign to multiple users" to select multiple people.');
                        return false;
                    }
                }
                
                console.log('✅ Mobile form validation passed - has assignee(s)');
                return true;
            });
            
            // Multiple assignee selection counter
            $('.multi-assignee-check').on('change', function() {
                const selected = $('.multi-assignee-check:checked');
                const count = selected.length;
                const names = selected.map(function() {
                    return $(this).next('label').text().trim();
                }).get();
                
                if (count > 0) {
                    $('#multi-selected-count').html(`
                        <div class="alert alert-success p-2 mb-0">
                            <small>
                                <i class="mdi mdi-account-multiple-check"></i> 
                                <strong>${count} user(s) selected:</strong> ${names.join(', ')}
                                <br><em>One task assigned to all ${count} users</em>
                            </small>
                        </div>
                    `);
                } else {
                    $('#multi-selected-count').html('');
                }
                
                console.log('✓ Multiple assignees:', names, '| IDs:', selected.map(function() { return $(this).val(); }).get());
            });
            
            // ==========================================
            // TOGGLE MULTIPLE ASSIGNEE MODE (DESKTOP)
            // ==========================================
            $('#desktop_enable_multiple_assign').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#desktop-multiple-assignees-section').slideDown();
                    $('#desktop_assignee_id').prop('disabled', true).val('').trigger('change');
                    console.log('✓ Desktop multiple assignee mode enabled');
                } else {
                    $('#desktop-multiple-assignees-section').slideUp();
                    $('#desktop_assignee_id').prop('disabled', false);
                    $('.desktop-multi-assignee-check').prop('checked', false);
                    $('#desktop-multi-selected-count').html('');
                    console.log('✓ Desktop single assignee mode');
                }
            });
            
            // Desktop multiple assignee selection counter
            $('.desktop-multi-assignee-check').on('change', function() {
                const selected = $('.desktop-multi-assignee-check:checked');
                const count = selected.length;
                const names = selected.map(function() {
                    return $(this).next('label').text().trim();
                }).get();
                
                if (count > 0) {
                    $('#desktop-multi-selected-count').html(`
                        <div class="alert alert-success p-2 mb-0">
                            <small>
                                <i class="mdi mdi-account-multiple-check"></i> 
                                <strong>${count} user(s) selected:</strong> ${names.join(', ')}
                                <br><em>One task assigned to all ${count} users (comma-separated)</em>
                            </small>
                        </div>
                    `);
                } else {
                    $('#desktop-multi-selected-count').html('');
                }
                
                console.log('✓ Desktop multiple assignees:', names);
            });
            
            // Form submission logging
            $('form').on('submit', function(e) {
                const singleAssignee = $('#assignee_id').val() || $('#desktop_assignee_id').val();
                const multipleAssignees = $('.multi-assignee-check:checked, .desktop-multi-assignee-check:checked')
                    .map(function() { return $(this).val(); }).get();
                
                console.log('📤 FORM SUBMITTING...');
                console.log('Single assignee:', singleAssignee);
                console.log('Multiple assignees:', multipleAssignees);
                console.log('Form data:', $(this).serialize());
            });

            // Store pasted file globally
            let pastedFile = null;

            // Helper function to format bytes
            function formatBytes(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
            }

            // Function to show image preview
            function showImagePreview(blob, fileName) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#image-preview').html(`
                        <img src="${e.target.result}" class="img-thumbnail" style="max-width: 300px; max-height: 300px;">
                        <div class="mt-2 text-success">
                            <i class="mdi mdi-check-circle"></i> Image loaded successfully!
                            <br>
                            <small>${fileName} (${formatBytes(blob.size)})</small>
                        </div>
                    `);
                    $('#paste-status').html('<i class="mdi mdi-check-circle text-success"></i> Image ready to upload!');
                };
                reader.readAsDataURL(blob);
            }

            // Function to set file to input
            function setFileToInput(file) {
                try {
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    document.getElementById('image').files = dataTransfer.files;
                    console.log('File set to input:', file.name);
                    return true;
                } catch (error) {
                    console.warn('DataTransfer not supported:', error);
                    pastedFile = file; // Store for form submission
                    return false;
                }
            }

            // Image preview from file input
            $('#image').on('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    showImagePreview(file, file.name);
                }
            });

            // ==========================================
            // PASTE BUTTON CLICK HANDLER
            // ==========================================
            $('#pasteButton').on('click', async function() {
                console.log('Paste button clicked!');
                const btn = $(this);
                const originalHtml = btn.html();
                
                btn.html('<i class="mdi mdi-loading mdi-spin me-1"></i> Reading clipboard...').prop('disabled', true);
                $('#paste-status').html('<i class="mdi mdi-loading mdi-spin text-primary"></i> Reading clipboard...');
                
                try {
                    // Check if Clipboard API is supported
                    if (!navigator.clipboard || !navigator.clipboard.read) {
                        throw new Error('Clipboard API not supported. Please use Ctrl+V or upgrade your browser.');
                    }
                    
                    console.log('Reading clipboard...');
                    const clipboardItems = await navigator.clipboard.read();
                    console.log('Clipboard items found:', clipboardItems.length);
                    
                    let imageFound = false;
                    
                    for (const clipboardItem of clipboardItems) {
                        for (const type of clipboardItem.types) {
                            console.log('Clipboard type:', type);
                            
                            if (type.startsWith('image/')) {
                                console.log('Image found!');
                                imageFound = true;
                                
                                const blob = await clipboardItem.getType(type);
                                const timestamp = new Date().getTime();
                                const fileName = `screenshot_${timestamp}.png`;
                                
                                pastedFile = new File([blob], fileName, { type: blob.type || 'image/png' });
                                console.log('File created:', fileName, blob.size, 'bytes');
                                
                                setFileToInput(pastedFile);
                                showImagePreview(blob, fileName);
                                
                                btn.html(originalHtml).prop('disabled', false);
                                
                                // Show success notification
                                const notification = $('<div class="alert alert-success alert-dismissible position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">')
                                    .html(`
                                        <i class="mdi mdi-check-circle me-2"></i>
                                        <strong>Screenshot Pasted!</strong>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    `);
                                $('body').append(notification);
                                setTimeout(() => notification.fadeOut(() => notification.remove()), 3000);
                                
                                return;
                            }
                        }
                    }
                    
                    if (!imageFound) {
                        throw new Error('No image found in clipboard. Please take a screenshot first!');
                    }
                    
                } catch (error) {
                    console.error('Clipboard error:', error);
                    btn.html(originalHtml).prop('disabled', false);
                    $('#paste-status').html('<i class="mdi mdi-alert-circle text-danger"></i> Failed to read clipboard');
                    
                    let errorMsg = 'Failed to paste screenshot!\n\n';
                    
                    if (error.name === 'NotAllowedError') {
                        errorMsg += 'Permission denied. Please allow clipboard access when prompted.\n\nOr try pressing Ctrl+V instead.';
                    } else if (error.message.includes('not supported')) {
                        errorMsg += error.message + '\n\nTry:\n• Press Ctrl+V instead\n• Or use "Choose File" button';
                    } else if (error.message.includes('No image')) {
                        errorMsg += error.message + '\n\nHow to take screenshot:\n• Windows: Win+Shift+S\n• Mac: Cmd+Shift+4';
                    } else {
                        errorMsg += error.message;
                    }
                    
                    alert(errorMsg);
                }
            });

            // ==========================================
            // CTRL+V KEYBOARD PASTE HANDLER
            // ==========================================
            window.addEventListener('paste', function(e) {
                console.log('Paste event detected!');
                
                const clipboardData = e.clipboardData || window.clipboardData;
                if (!clipboardData) {
                    console.log('No clipboard data');
                    return;
                }
                
                const items = clipboardData.items;
                console.log('Clipboard items:', items.length);
                
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        console.log('Image found in paste event!');
                        e.preventDefault();
                        
                        const blob = items[i].getAsFile();
                        const timestamp = new Date().getTime();
                        const fileName = `screenshot_${timestamp}.png`;
                        
                        pastedFile = new File([blob], fileName, { type: blob.type || 'image/png' });
                        
                        setFileToInput(pastedFile);
                        showImagePreview(blob, fileName);
                        
                        // Show notification
                        const notification = $('<div class="alert alert-success alert-dismissible position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">')
                            .html(`
                                <i class="mdi mdi-check-circle me-2"></i>
                                <strong>Screenshot Pasted (Ctrl+V)!</strong>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            `);
                        $('body').append(notification);
                        setTimeout(() => notification.fadeOut(() => notification.remove()), 3000);
                        
                        break;
                    }
                }
            }, true);

            // Drag and drop
            const dropZone = $('#paste-zone');
            
            dropZone.on('dragover', function(e) {
                e.preventDefault();
                $(this).css('background-color', '#e7f3ff');
            });
            
            dropZone.on('dragleave', function(e) {
                $(this).css('background-color', '#f8f9fa');
            });
            
            dropZone.on('drop', function(e) {
                e.preventDefault();
                $(this).css('background-color', '#f8f9fa');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0 && files[0].type.indexOf('image') !== -1) {
                    document.getElementById('image').files = files;
                    pastedFile = files[0];
                    showImagePreview(files[0], files[0].name);
                }
            });

            // Ensure pasted file is included in form submission
            $('form').on('submit', function(e) {
                if (pastedFile && (!document.getElementById('image').files || document.getElementById('image').files.length === 0)) {
                    try {
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(pastedFile);
                        document.getElementById('image').files = dataTransfer.files;
                    } catch (error) {
                        console.error('Could not add file:', error);
                    }
                }
            });

            console.log('Paste functionality ready! Use the green button or press Ctrl+V');

            // ==========================================
            // MOBILE IMAGE PREVIEW AND PASTE
            // ==========================================
            let mobilePastedFile = null;

            // Mobile image file input preview
            $('#mobile_image').on('change', function(e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#mobile-image-preview').html(`
                            <img src="${e.target.result}" class="img-thumbnail" style="max-width: 100%; max-height: 200px;">
                            <div class="mt-2 text-success small">
                                <i class="mdi mdi-check-circle"></i> ${file.name}
                            </div>
                        `);
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Mobile paste handler
            if (window.innerWidth < 768) {
                window.addEventListener('paste', function(e) {
                    const clipboardData = e.clipboardData || window.clipboardData;
                    if (!clipboardData) return;
                    
                    const items = clipboardData.items;
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            
                            const blob = items[i].getAsFile();
                            const timestamp = new Date().getTime();
                            const fileName = `screenshot_${timestamp}.png`;
                            
                            mobilePastedFile = new File([blob], fileName, { type: blob.type || 'image/png' });
                            
                            // Set file to input
                            try {
                                const dataTransfer = new DataTransfer();
                                dataTransfer.items.add(mobilePastedFile);
                                document.getElementById('mobile_image').files = dataTransfer.files;
                            } catch (error) {
                                console.log('DataTransfer not supported');
                            }
                            
                            // Show preview
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                $('#mobile-image-preview').html(`
                                    <img src="${e.target.result}" class="img-thumbnail" style="max-width: 100%; max-height: 200px;">
                                    <div class="mt-2 text-success small">
                                        <i class="mdi mdi-check-circle"></i> Screenshot pasted!
                                    </div>
                                `);
                            };
                            reader.readAsDataURL(blob);
                            
                            break;
                        }
                    }
                }, true);
            }
        });
    </script>
@endsection
