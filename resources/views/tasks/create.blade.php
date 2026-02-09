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
                <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0;">
                    <h5 class="mb-0">
                        <i class="mdi mdi-plus-circle me-2"></i>Create New Task
                    </h5>
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

                        <!-- Assign To -->
                        <div class="mb-3">
                            <label for="mobile_assignee_id" class="form-label fw-bold">
                                Assign To
                            </label>
                            <select class="form-select form-select-lg @error('assignee_id') is-invalid @enderror" 
                                    id="mobile_assignee_id" 
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
                        </div>

                        <!-- Image Upload -->
                        <div class="mb-3">
                            <label class="form-label fw-bold">
                                <i class="mdi mdi-image me-1"></i>Image (Optional)
                            </label>
                            
                            <div class="border rounded p-3 text-center" style="border: 2px dashed #dee2e6; background-color: #f8f9fa;">
                                <input type="file" 
                                       class="form-control @error('image') is-invalid @enderror" 
                                       id="mobile_image" 
                                       name="image" 
                                       accept="image/*"
                                       style="display: none;">
                                
                                <div class="mb-2">
                                    <i class="mdi mdi-camera" style="font-size: 36px; color: #667eea;"></i>
                                </div>
                                
                                <button type="button" 
                                        class="btn btn-outline-primary btn-sm mb-2 w-100" 
                                        onclick="document.getElementById('mobile_image').click()">
                                    <i class="mdi mdi-folder-open me-1"></i> Choose Image
                                </button>
                                
                                <div class="text-muted small">
                                    or press Ctrl+V to paste
                                </div>
                                
                                <div id="mobile-image-preview" class="mt-2"></div>
                            </div>
                            
                            @error('image')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-lg btn-danger">
                                <i class="mdi mdi-check-circle me-1"></i> Save Task
                            </button>
                            <a href="{{ route('tasks.index') }}" class="btn btn-lg btn-outline-secondary">
                                <i class="mdi mdi-close-circle me-1"></i> Cancel
                            </a>
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

            <div class="row">
            <div class="col-12">
                <div class="card" style="border: 2px solid #667eea; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-0">
                            <i class="mdi mdi-form-textbox me-2"></i>Task Information
                        </h5>
                    </div>
                    <div class="card-body" style="padding: 30px;">
                        <div class="mb-3">
                            <a href="{{ route('tasks.index') }}" class="btn btn-outline-primary">
                                <i class="mdi mdi-format-list-bulleted me-1"></i> View Task List
                            </a>
                        </div>
                        
                        <form action="{{ route('tasks.store') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="group" class="form-label">Group</label>
                                    <input type="text" class="form-control @error('group') is-invalid @enderror" 
                                           id="group" name="group" placeholder="Enter Group" value="{{ old('group') }}">
                                    @error('group')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="title" class="form-label">Task <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" placeholder="Enter Task" value="{{ old('title') }}" required>
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
                                        <option value="normal" {{ old('priority', 'normal') == 'normal' ? 'selected' : '' }}>Normal</option>
                                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
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
                                                <option value="{{ $user->id }}" {{ old('assignor_id', Auth::id()) == $user->id ? 'selected' : '' }}>
                                                    {{ $user->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" class="form-control" value="{{ Auth::user()->name }}" readonly>
                                        <input type="hidden" name="assignor_id" value="{{ Auth::id() }}">
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
                                            <option value="{{ $user->id }}" {{ old('assignee_id') == $user->id ? 'selected' : '' }}>
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
                                           id="etc_minutes" name="etc_minutes" placeholder="10" value="{{ old('etc_minutes', 10) }}">
                                    @error('etc_minutes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="tid" class="form-label">TID (Task Initiation Date) <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control @error('tid') is-invalid @enderror" 
                                           id="tid" name="tid" value="{{ old('tid', now()->format('Y-m-d\TH:i')) }}">
                                    @error('tid')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="l1" class="form-label">L1</label>
                                    <input type="text" class="form-control @error('l1') is-invalid @enderror" 
                                           id="l1" name="l1" placeholder="Enter L1" value="{{ old('l1') }}">
                                    @error('l1')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="l2" class="form-label">L2</label>
                                    <input type="text" class="form-control @error('l2') is-invalid @enderror" 
                                           id="l2" name="l2" placeholder="Enter L2" value="{{ old('l2') }}">
                                    @error('l2')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="training_link" class="form-label">Training Link</label>
                                    <input type="text" class="form-control @error('training_link') is-invalid @enderror" 
                                           id="training_link" name="training_link" placeholder="Enter training Note" value="{{ old('training_link') }}">
                                    @error('training_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="video_link" class="form-label">Video Link</label>
                                    <input type="text" class="form-control @error('video_link') is-invalid @enderror" 
                                           id="video_link" name="video_link" placeholder="Enter video Note" value="{{ old('video_link') }}">
                                    @error('video_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="form_link" class="form-label">Form Link</label>
                                    <input type="text" class="form-control @error('form_link') is-invalid @enderror" 
                                           id="form_link" name="form_link" placeholder="Enter form Note" value="{{ old('form_link') }}">
                                    @error('form_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="form_report_link" class="form-label">Form Report Link</label>
                                    <input type="text" class="form-control @error('form_report_link') is-invalid @enderror" 
                                           id="form_report_link" name="form_report_link" placeholder="Enter form Note" value="{{ old('form_report_link') }}">
                                    @error('form_report_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="checklist_link" class="form-label">Checklist Link</label>
                                    <input type="text" class="form-control @error('checklist_link') is-invalid @enderror" 
                                           id="checklist_link" name="checklist_link" placeholder="Enter checklist link" value="{{ old('checklist_link') }}">
                                    @error('checklist_link')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="pl" class="form-label">PL</label>
                                    <input type="text" class="form-control @error('pl') is-invalid @enderror" 
                                           id="pl" name="pl" placeholder="Enter PL link" value="{{ old('pl') }}">
                                    @error('pl')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="process" class="form-label">PROCESS</label>
                                    <input type="text" class="form-control @error('process') is-invalid @enderror" 
                                           id="process" name="process" placeholder="Enter form Note" value="{{ old('process') }}">
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
                                              placeholder="Enter Description">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="image" class="form-label">Image</label>
                                    
                                    <div class="border rounded p-4 text-center" id="paste-zone" style="border: 2px dashed #dee2e6; background-color: #f8f9fa;">
                                        <input type="file" class="form-control @error('image') is-invalid @enderror" 
                                               id="image" name="image" accept="image/*" style="display: none;">
                                        
                                        <div class="mb-3">
                                            <i class="mdi mdi-cloud-upload" style="font-size: 48px; color: #667eea;"></i>
                                        </div>
                                        
                                        <!-- Upload Buttons -->
                                        <div class="d-flex gap-2 justify-content-center flex-wrap mb-3">
                                            <button type="button" class="btn btn-danger" onclick="document.getElementById('image').click()">
                                                <i class="mdi mdi-folder-open me-1"></i> Choose File
                                            </button>
                                            <button type="button" class="btn btn-success" id="pasteButton">
                                                <i class="mdi mdi-content-paste me-1"></i> Paste Screenshot
                                            </button>
                                        </div>
                                        
                                        <div class="text-muted mb-2">
                                            or drag & drop image here • or press Ctrl+V
                                        </div>
                                        
                                        <div id="paste-status" class="text-success small">
                                            <i class="mdi mdi-check-circle"></i> Ready to paste
                                        </div>
                                        
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
                                        Create
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
            // Initialize Select2 only on desktop
            if (window.innerWidth >= 768) {
                $('.select2').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Please Select'
                });
            }

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
