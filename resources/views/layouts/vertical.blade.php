<!DOCTYPE html>
<html lang="en" data-sidenav-size="{{ $sidenav ?? 'default' }}" data-layout-mode="{{ $layoutMode ?? 'fluid' }}"
    data-layout-position="{{ $position ?? 'fixed' }}" data-menu-color="{{ $menuColor ?? 'dark' }}"
    data-topbar-color="{{ $topbarColor ?? 'light' }}">

<head>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highcharts@11/highcharts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highcharts@11/modules/exporting.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highcharts@11/modules/export-data.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highcharts@11/modules/accessibility.js"></script>
    <!-- Highcharts default theme used; optional: https://code.highcharts.com/themes/adaptive.js -->
    @include('layouts.shared/title-meta', ['title' => $title])
    @yield('css')
    @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#667eea">
    <link rel="apple-touch-icon" href="/images/chat-icon.png">
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

    <!-- Floating Add Task Button (Chat bot / Help desk icon) -->
    <button type="button" 
            class="btn floating-task-btn" 
            id="open-task-form-btn"
            style="position: fixed; 
                   top: 80px; 
                   right: 20px; 
                   z-index: 1000; 
                   border-radius: 50%; 
                   padding: 0;
                   width: 42px;
                   height: 42px;
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
                                         right: -420px; 
                                         width: 30%; 
                                         min-width: 280px;
                                         height: 100vh;
                                         max-height: 100vh;
                                         background: white; 
                                         box-shadow: -5px 0 25px rgba(0,0,0,0.3); 
                                         z-index: 1050; 
                                         overflow-y: auto; 
                                         transition: right 0.3s ease;
                                         padding: 0;">
        
        <!-- Header: title doubles as submit; close stays separate -->
        <div class="position-relative" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 10px 15px;">
            <button type="submit"
                    form="quick-task-form"
                    id="quick-task-header-submit"
                    class="quick-task-header-submit-btn">
                <i class="mdi mdi-plus-circle me-1"></i> Create Task
            </button>
            <button type="button" 
                    id="close-task-form-btn"
                    class="btn-close btn-close-white position-absolute"
                    style="top: 50%; 
                           right: 10px; 
                           transform: translateY(-50%);"
                    aria-label="Close">
            </button>
        </div>
        
        <!-- Form body with padding -->
        <div style="padding: 8px;">

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
        </form>
        </div>
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
        .quick-task-header-submit-btn {
            font-size: 13px;
            font-weight: bold;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            text-align: left;
            display: block;
            width: calc(100% - 36px);
            transition: background 0.2s ease, color 0.2s ease;
        }
        .quick-task-header-submit-btn:hover {
            background: rgba(255, 255, 255, 0.28);
            color: #fff;
        }
        .quick-task-header-submit-btn:focus-visible {
            outline: 2px solid rgba(255, 255, 255, 0.9);
            outline-offset: 2px;
        }

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
        
        @media (max-width: 991.98px) {
            .floating-task-btn {
                display: none;
            }
            #floating-task-form {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                right: -100%;
                height: 100vh;
                max-height: 100vh;
            }

            #quick-task-form .form-label {
                font-size: 12px !important;
                margin-bottom: 4px !important;
            }

            #quick-task-form .form-control,
            #quick-task-form .form-select {
                font-size: 14px !important;
                padding: 8px 10px !important;
                height: 40px !important;
            }

            #quick-task-form input[type="file"] {
                height: auto !important;
                min-height: 40px;
            }

            #toggle-quick-more-fields {
                font-size: 12px !important;
                height: 36px !important;
                padding: 6px 10px !important;
            }

            .quick-task-header-submit-btn {
                font-size: 16px;
                padding: 10px 12px;
            }
        }
    </style>

    <script>
        $(document).ready(function() {
            function getTaskFormClosedOffset() {
                return window.matchMedia('(max-width: 991.98px)').matches ? '-100%' : '-420px';
            }

            // Open floating task form
            $('#open-task-form-btn').on('click', function() {
                $('#floating-task-form').css('right', '0');
                $('#task-form-backdrop').fadeIn(300);
                $('body').css('overflow', 'hidden');
            });

            // Close floating task form
            function closeTaskForm() {
                $('#floating-task-form').css('right', getTaskFormClosedOffset());
                $('#task-form-backdrop').fadeOut(300);
                $('body').css('overflow', 'auto');
            }

            $('#close-task-form-btn').on('click', closeTaskForm);
            $('#task-form-backdrop').on('click', closeTaskForm);

            // Keep closed position in sync when viewport changes.
            $(window).on('resize', function() {
                if ($('#floating-task-form').css('right') !== '0px') {
                    $('#floating-task-form').css('right', getTaskFormClosedOffset());
                }
            });
            
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

    @include('components.ai-chat-widget')
    
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
