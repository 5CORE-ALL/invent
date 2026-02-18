<!DOCTYPE html>
<html lang="en" data-sidenav-size="{{ $sidenav ?? 'default' }}" data-layout-mode="{{ $layoutMode ?? 'fluid' }}"
    data-layout-position="{{ $position ?? 'fixed' }}" data-menu-color="{{ $menuColor ?? 'dark' }}"
    data-topbar-color="{{ $topbarColor ?? 'light' }}">

<head>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://code.highcharts.com/highcharts.js"></script>
    <script src="https://code.highcharts.com/modules/exporting.js"></script>
    <script src="https://code.highcharts.com/modules/export-data.js"></script>
    <script src="https://code.highcharts.com/modules/accessibility.js"></script>
    <script src="https://code.highcharts.com/themes/adaptive.js"></script>
    @include('layouts.shared/title-meta', ['title' => $title])
    @yield('css')
    @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#667eea">
    <link rel="apple-touch-icon" href="/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Invent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
</head>

<body>
    <!-- Mobile Splash Screen -->
    @include('layouts.mobile-splash')
    
    <!-- Begin page -->
    <div class="wrapper">

        <!-- Desktop Navigation -->
        @include('layouts.shared/topbar')
        @include('layouts.shared/left-sidebar')
        
        <!-- Mobile Header -->
        @include('layouts.mobile-header')

        <div class="content-page">
            <div class="content">

                <!-- Start Content-->
                <div class="container-fluid">
                    @yield('content')
                </div>
                <!-- container -->

            </div>
            <!-- content -->

            @include('layouts.shared/footer')
        </div>

    </div>
    <!-- END wrapper -->

    <!-- Floating Add Task Button -->
    <button type="button" 
            class="btn floating-task-btn" 
            id="open-task-form-btn"
            style="position: fixed; 
                   top: 80px; 
                   right: 20px; 
                   z-index: 1000; 
                   border-radius: 50%; 
                   padding: 0;
                   width: 56px;
                   height: 56px;
                   display: flex;
                   align-items: center;
                   justify-content: center;
                   box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
                   border: none;
                   background: white;
                   transition: all 0.3s ease;">
        <img src="{{ asset('assets/css/icondes.jpeg') }}" 
             alt="Add Task" 
             style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
    </button>

    <!-- Floating Task Form Sidebar -->
    <div id="floating-task-form" style="position: fixed; 
                                         top: 0; 
                                         right: -25%; 
                                         width: 25%; 
                                         min-width: 250px;
                                         height: auto;
                                         max-height: 100vh;
                                         background: white; 
                                         box-shadow: -5px 0 25px rgba(0,0,0,0.3); 
                                         z-index: 1050; 
                                         overflow-y: auto; 
                                         transition: right 0.3s ease;
                                         padding: 8px;">
        
        <button type="button" 
                id="close-task-form-btn"
                style="position: absolute; 
                       top: 5px; 
                       right: 5px; 
                       background: #dc3545; 
                       border: none; 
                       font-size: 14px; 
                       color: white; 
                       cursor: pointer; 
                       z-index: 10;
                       padding: 2px;
                       width: 22px;
                       height: 22px;
                       line-height: 1;
                       border-radius: 50%;
                       transition: all 0.2s ease;">
            <i class="mdi mdi-close"></i>
        </button>
        
        <div style="font-size: 11px; font-weight: bold; margin-bottom: 6px; padding-right: 25px;">
            <i class="mdi mdi-plus-circle" style="font-size: 12px;"></i> Create Task
        </div>

        <form id="quick-task-form" action="{{ route('tasks.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div style="margin-bottom: 6px;">
                <label for="quick_group" class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Group</label>
                <input type="text" class="form-control" id="quick_group" name="group" placeholder="Group" style="font-size: 10px; padding: 3px 6px; height: 26px;">
            </div>
            
            <div style="margin-bottom: 6px;">
                <label for="quick_title" class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Task <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="quick_title" name="title" placeholder="Enter Task" required style="font-size: 10px; padding: 3px 6px; height: 26px;">
            </div>
            
            <div style="margin-bottom: 6px;">
                <label for="quick_assignee_id" class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Assignee</label>
                <select class="form-select" id="quick_assignee_id" name="assignee_id" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                    <option value="">Select</option>
                    @php
                        $users = \App\Models\User::select('id', 'name')->orderBy('name')->get();
                    @endphp
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            
            <div style="margin-bottom: 6px;">
                <label for="quick_etc_minutes" class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">ETC <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="quick_etc_minutes" name="etc_minutes" placeholder="10" value="10" style="font-size: 10px; padding: 3px 6px; height: 26px;">
            </div>

            <!-- Hidden Fields with Defaults -->
            <input type="hidden" name="priority" value="normal">
            <input type="hidden" name="assignor_id" value="{{ Auth::id() }}">
            <input type="hidden" name="tid" value="{{ now()->format('Y-m-d\TH:i') }}">

            <!-- More Fields Toggle -->
            <button type="button" class="btn btn-outline-secondary w-100" id="toggle-quick-more-fields" style="font-size: 8px; padding: 2px 4px; height: 22px; margin-bottom: 6px;">
                <i class="mdi mdi-chevron-down" id="quick-toggle-icon" style="font-size: 9px;"></i> More
            </button>

            <!-- Additional Fields -->
            <div id="quick-additional-fields" style="display: none;">
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Priority</label>
                    <select class="form-select" name="priority_override" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                        <option value="normal" selected>Normal</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">TID</label>
                    <input type="datetime-local" class="form-control" name="tid_override" value="{{ now()->format('Y-m-d\TH:i') }}" style="font-size: 9px; padding: 2px 4px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">L1</label>
                    <input type="text" class="form-control" name="l1" placeholder="L1" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">L2</label>
                    <input type="text" class="form-control" name="l2" placeholder="L2" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Training</label>
                    <input type="text" class="form-control" name="training_link" placeholder="Training" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Video</label>
                    <input type="text" class="form-control" name="video_link" placeholder="Video" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Form</label>
                    <input type="text" class="form-control" name="form_link" placeholder="Form" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Report</label>
                    <input type="text" class="form-control" name="form_report_link" placeholder="Report" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Checklist</label>
                    <input type="text" class="form-control" name="checklist_link" placeholder="Checklist" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">PL</label>
                    <input type="text" class="form-control" name="pl" placeholder="PL" style="font-size: 10px; padding: 3px 6px; height: 26px;">
                </div>
                <div style="margin-bottom: 6px;">
                    <label class="form-label" style="font-size: 9px; font-weight: 600; margin-bottom: 1px; display: block;">Image</label>
                    <input type="file" class="form-control" name="image" accept="image/*" style="font-size: 9px; padding: 2px 4px; height: 26px;">
                </div>
            </div>

            <button type="submit" class="btn btn-danger w-100" style="font-size: 10px; padding: 4px 8px; height: 28px; margin-top: 6px;">
                <i class="mdi mdi-check-circle me-1" style="font-size: 11px;"></i> Create
            </button>
        </form>
    </div>

    <!-- Backdrop Overlay -->
    <div id="task-form-backdrop" style="position: fixed; 
                                         top: 0; 
                                         left: 0; 
                                         width: 100%; 
                                         height: 100%; 
                                         background: rgba(0,0,0,0.5); 
                                         z-index: 1040; 
                                         display: none;"></div>

    <style>
        .floating-task-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.6);
        }
        
        #floating-task-form::-webkit-scrollbar {
            width: 6px;
        }
        
        #floating-task-form::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        #floating-task-form::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        @media (max-width: 768px) {
            .floating-task-btn {
                display: none;
            }
            #floating-task-form {
                width: 100%;
                right: -100%;
            }
        }
    </style>

    <script>
        $(document).ready(function() {
            // Open floating task form
            $('#open-task-form-btn').on('click', function() {
                $('#floating-task-form').css('right', '0');
                $('#task-form-backdrop').fadeIn(300);
                $('body').css('overflow', 'hidden');
            });

            // Close floating task form
            function closeTaskForm() {
                $('#floating-task-form').css('right', '-25%');
                $('#task-form-backdrop').fadeOut(300);
                $('body').css('overflow', 'auto');
            }

            $('#close-task-form-btn').on('click', closeTaskForm);
            $('#task-form-backdrop').on('click', closeTaskForm);
            
            // Close on Escape key
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#floating-task-form').css('right') === '0px') {
                    closeTaskForm();
                }
            });

            // Toggle more fields in quick form
            $('#toggle-quick-more-fields').on('click', function() {
                $('#quick-additional-fields').slideToggle(200);
                const icon = $('#quick-toggle-icon');
                const btn = $(this);
                
                if (icon.hasClass('mdi-chevron-down')) {
                    icon.removeClass('mdi-chevron-down').addClass('mdi-chevron-up');
                    btn.html('<i class="mdi mdi-chevron-up" id="quick-toggle-icon"></i> Hide Fields');
                } else {
                    icon.removeClass('mdi-chevron-up').addClass('mdi-chevron-down');
                    btn.html('<i class="mdi mdi-chevron-down" id="quick-toggle-icon"></i> More Fields');
                }
            });

            // Handle form submission
            $('#quick-task-form').on('submit', function(e) {
                // Override hidden fields if more fields values are provided
                const priorityOverride = $('select[name="priority_override"]').val();
                if (priorityOverride) {
                    $('input[name="priority"]').val(priorityOverride);
                }
                
                const tidOverride = $('input[name="tid_override"]').val();
                if (tidOverride) {
                    $('input[name="tid"]').val(tidOverride);
                }
            });
        });
    </script>

    @yield('modal')

    @include('layouts.shared/right-sidebar')
    
    <!-- Mobile Bottom Navigation -->
    @include('layouts.mobile-bottom-nav')

    @include('layouts.shared/footer-scripts')

    @vite(['resources/js/layout.js', 'resources/js/main.js'])

    {{-- @include('components.ai-chat-widget') --}}
    
    <!-- PWA Service Worker Registration with Error Handling -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                // Unregister old service workers first
                navigator.serviceWorker.getRegistrations().then(function(registrations) {
                    for(let registration of registrations) {
                        if (registration.active && registration.active.scriptURL.includes('/sw.js')) {
                            console.log('Updating existing service worker...');
                        }
                    }
                });
                
                // Register new service worker
                navigator.serviceWorker.register('/sw.js')
                    .then(function(registration) {
                        console.log('✓ ServiceWorker registered successfully');
                        
                        // Update on new version
                        registration.addEventListener('updatefound', function() {
                            console.log('ServiceWorker update found!');
                        });
                    })
                    .catch(function(error) {
                        console.warn('⚠️ ServiceWorker registration failed (non-critical):', error);
                        // Don't block app if service worker fails
                    });
            });
        } else {
            console.log('Service Worker not supported in this browser');
        }

        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            console.log('✓ PWA Install prompt ready');
        });

        // Function to trigger PWA install
        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('✓ User accepted PWA install');
                    }
                    deferredPrompt = null;
                });
            }
        }
    </script>
</body>

</html>
