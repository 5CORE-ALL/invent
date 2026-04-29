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
    @include('layouts.shared/title-meta', [
        'title' => $title,
        'favicon' => $favicon ?? null,
        'faviconType' => $faviconType ?? null,
    ])
    @yield('css')
    @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
    
    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#667eea">
    <link rel="apple-touch-icon" href="{{ $appleTouchIcon ?? '/images/chat-icon.png' }}">
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

    @unless($hideFloatingTaskButton ?? false)
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
                                         padding: 0;
                                         display: none;">
        
        <!-- Header: compact actions + close -->
        <div class="position-relative d-flex align-items-center flex-wrap gap-2"
             style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 63px 12px 18px;">
            <button type="submit"
                    form="quick-task-form"
                    id="quick-task-header-submit"
                    class="btn btn-success btn-sm px-2 py-1 shadow-sm quick-task-header-primary-btn">
                <i class="mdi mdi-plus-circle me-1"></i>Create Task
            </button>
            <button type="button"
                    id="quick-task-save-create-more-btn"
                    class="btn btn-warning btn-sm px-2 py-1 text-dark shadow-sm border border-dark border-opacity-10"
                    title="Save this task and clear the form for another">
                <i class="mdi mdi-content-save-outline me-1"></i>Save &amp; Create More
            </button>
            <button type="button"
                    id="close-task-form-btn"
                    class="btn-close btn-close-white position-absolute"
                    style="top: 50%;
                           right: 15px;
                           transform: translateY(-50%);"
                    aria-label="Close">
            </button>
        </div>
        
        <!-- Form body with padding -->
        <div style="padding: 12px;">
        <div id="quick-task-success-alert" class="alert alert-success py-1 px-2 mb-2 d-none" style="font-size: 15px; line-height: 1.35;" role="status" aria-live="polite"></div>

        <form id="quick-task-form" action="{{ route('tasks.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <div style="margin-bottom: 9px;">
                <label for="quick_group" class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Group</label>
                <input type="text" class="form-control" id="quick_group" name="group" placeholder="Group" style="font-size: 15px; padding: 5px 9px; height: 39px;">
            </div>
            
            <div style="margin-bottom: 9px;">
                <label for="quick_title" class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Task <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="quick_title" name="title" placeholder="Enter Task" required style="font-size: 15px; padding: 5px 9px; height: 39px;">
            </div>
            
            @php
                $quickTaskUsers = \App\Models\User::where('is_active', true)->select('id', 'name')->orderBy('name')->get();
            @endphp
            <script type="application/json" id="quick-assignee-users-data">{!! json_encode($quickTaskUsers->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])->values()) !!}</script>
            <div style="margin-bottom: 9px;">
                <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Assignee(s)</label>
                <div class="quick-assignee-ms position-relative" id="quick_assignee_ms" title="Select one or more people; one shared task is created with all assignees">
                    <button type="button" class="quick-assignee-ms-trigger" id="quick_assignee_ms_trigger" aria-haspopup="listbox" aria-expanded="false">
                        <span class="quick-assignee-ms-label text-truncate">Select assignees</span>
                        <i class="mdi mdi-menu-down quick-assignee-ms-chevron"></i>
                    </button>
                    <div class="quick-assignee-ms-panel" id="quick_assignee_ms_panel" role="listbox" aria-multiselectable="true">
                        <input type="text" class="form-control form-control-sm quick-assignee-ms-search" id="quick_assignee_ms_search" placeholder="Search…" autocomplete="off" aria-label="Search assignees">
                        <ul class="quick-assignee-ms-list list-unstyled mb-0" id="quick_assignee_ms_list"></ul>
                    </div>
                    <div id="quick_assignee_hidden_wrap" class="quick-assignee-hidden-wrap"></div>
                </div>
                <small class="text-muted" style="font-size: 12px; display: block; margin-top: 3px;">One task; all selected users are assignees.</small>
            </div>
            
            <div style="margin-bottom: 9px;">
                <label for="quick_etc_minutes" class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">ETC <span class="text-danger">*</span></label>
                <input type="number" class="form-control" id="quick_etc_minutes" name="etc_minutes" placeholder="10" value="10" style="font-size: 15px; padding: 5px 9px; height: 39px;">
            </div>

            <!-- Hidden Fields with Defaults -->
            <input type="hidden" name="priority" value="normal">
            <input type="hidden" name="assignor_id" value="{{ Auth::id() }}">
            <input type="hidden" name="tid" value="{{ now()->format('Y-m-d\TH:i') }}">

            <!-- More Fields Toggle -->
            <button type="button" class="btn btn-outline-secondary w-100" id="toggle-quick-more-fields" style="font-size: 12px; padding: 3px 6px; height: 33px; margin-bottom: 9px;">
                <i class="mdi mdi-chevron-down" id="quick-toggle-icon" style="font-size: 13.5px;"></i> More
            </button>

            <!-- Additional Fields -->
            <div id="quick-additional-fields" style="display: none;">
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Priority</label>
                    <select class="form-select" name="priority_override" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                        <option value="normal" selected>Normal</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">TID</label>
                    <input type="datetime-local" class="form-control" name="tid_override" value="{{ now()->format('Y-m-d\TH:i') }}" style="font-size: 13.5px; padding: 3px 6px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">L1</label>
                    <input type="text" class="form-control" name="l1" placeholder="L1" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">L2</label>
                    <input type="text" class="form-control" name="l2" placeholder="L2" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Training</label>
                    <input type="text" class="form-control" name="training_link" placeholder="Training" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Video</label>
                    <input type="text" class="form-control" name="video_link" placeholder="Video" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Form</label>
                    <input type="text" class="form-control" name="form_link" placeholder="Form" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Report</label>
                    <input type="text" class="form-control" name="form_report_link" placeholder="Report" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Checklist</label>
                    <input type="text" class="form-control" name="checklist_link" placeholder="Checklist" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">PL</label>
                    <input type="text" class="form-control" name="pl" placeholder="PL" style="font-size: 15px; padding: 5px 9px; height: 39px;">
                </div>
                <div style="margin-bottom: 9px;">
                    <label class="form-label" style="font-size: 13.5px; font-weight: 600; margin-bottom: 2px; display: block;">Image</label>
                    <input type="file" class="form-control" name="image" accept="image/*" style="font-size: 13.5px; padding: 3px 6px; height: 39px;">
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
        .quick-task-header-primary-btn {
            font-size: 16.5px;
            font-weight: 600;
        }
        #quick-task-save-create-more-btn {
            font-size: 16.5px;
            font-weight: 600;
        }

        .floating-task-btn {
            display: flex !important;
            z-index: 1035 !important;
        }

        body.task-form-open .floating-task-btn {
            opacity: 0 !important;
            pointer-events: none !important;
            transform: scale(0.9);
            transition: opacity 0.2s ease, transform 0.2s ease;
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

        .quick-assignee-hidden-wrap {
            display: contents;
        }

        .quick-assignee-ms-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 9px;
            width: 100%;
            height: 39px;
            padding: 5px 12px 5px 9px;
            font-size: 15px;
            line-height: 1.2;
            text-align: left;
            color: #212529;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            cursor: pointer;
        }
        .quick-assignee-ms-trigger:hover {
            border-color: #adb5bd;
        }
        .quick-assignee-ms-trigger:focus {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
        }
        .quick-assignee-ms-trigger.is-open .quick-assignee-ms-chevron {
            transform: rotate(180deg);
        }
        .quick-assignee-ms-label {
            flex: 1;
            min-width: 0;
        }
        .quick-assignee-ms-label.has-value {
            color: #212529;
        }
        .quick-assignee-ms-chevron {
            flex-shrink: 0;
            font-size: 24px;
            line-height: 1;
            opacity: 0.65;
            transition: transform 0.15s ease;
        }
        .quick-assignee-ms-panel {
            position: fixed;
            z-index: 1080;
            max-height: 330px;
            display: none;
            flex-direction: column;
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }
        .quick-assignee-ms-panel.is-open {
            display: flex;
        }
        .quick-assignee-ms-search {
            font-size: 15px !important;
            padding: 6px 12px !important;
            border-radius: 0 !important;
            border-left: none !important;
            border-right: none !important;
            border-top: none !important;
        }
        .quick-assignee-ms-list {
            overflow-y: auto;
            max-height: 264px;
            padding: 6px 0;
        }
        .quick-assignee-ms-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            font-size: 15px;
            line-height: 1.3;
            cursor: pointer;
            user-select: none;
        }
        .quick-assignee-ms-item:hover,
        .quick-assignee-ms-item:focus {
            background: #f8f9fa;
            outline: none;
        }
        .quick-assignee-ms-item.is-selected {
            background: #e7f1ff;
        }
        .quick-assignee-ms-item.is-hidden {
            display: none;
        }
        .quick-assignee-ms-box {
            width: 21px;
            height: 21px;
            border: 1px solid #adb5bd;
            border-radius: 3px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            line-height: 1;
            color: transparent;
        }
        .quick-assignee-ms-item.is-selected .quick-assignee-ms-box {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        
        @media (max-width: 991.98px) {
            .floating-task-btn {
                top: auto !important;
                bottom: 84px !important;
                right: 12px !important;
                width: 50px !important;
                height: 50px !important;
                display: flex !important;
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
                font-size: 18px !important;
                margin-bottom: 6px !important;
            }

            #quick-task-form .form-control,
            #quick-task-form .form-select {
                font-size: 21px !important;
                padding: 12px 15px !important;
                height: 60px !important;
            }

            .quick-assignee-ms-trigger {
                height: 60px !important;
                font-size: 21px !important;
                padding: 12px 15px !important;
            }
            .quick-assignee-ms-search {
                font-size: 21px !important;
                padding: 12px 15px !important;
            }
            .quick-assignee-ms-item {
                font-size: 21px !important;
                padding: 12px 18px !important;
            }
            .quick-assignee-ms-panel {
                max-height: 420px;
            }
            .quick-assignee-ms-list {
                max-height: 330px;
            }

            #quick-task-form input[type="file"] {
                height: auto !important;
                min-height: 60px;
            }

            #toggle-quick-more-fields {
                font-size: 18px !important;
                height: 54px !important;
                padding: 9px 15px !important;
            }

            .quick-task-header-primary-btn,
            #quick-task-save-create-more-btn {
                font-size: 19.5px;
                padding: 9px 15px;
            }
        }

        @media all and (display-mode: standalone) and (max-width: 991.98px) {
            .floating-task-btn {
                bottom: calc(84px + env(safe-area-inset-bottom)) !important;
            }
        }
    </style>

    <script>
        $(document).ready(function() {
            let hideTaskFormTimer = null;

            function getTaskFormClosedOffset() {
                return window.matchMedia('(max-width: 991.98px)').matches ? '-100%' : '-420px';
            }

            function ensureTaskFormClosed() {
                clearTimeout(hideTaskFormTimer);
                $('#floating-task-form')
                    .css('right', getTaskFormClosedOffset())
                    .hide();
                $('#task-form-backdrop').hide();
                $('body').removeClass('task-form-open').css('overflow', 'auto');
            }

            // Open floating task form
            $('#open-task-form-btn').on('click', function() {
                clearTimeout(hideTaskFormTimer);
                $('#quick-task-success-alert').addClass('d-none').empty();
                clearTimeout(window.__quickTaskAlertTimer);
                $('#floating-task-form').show();
                requestAnimationFrame(function () {
                    $('#floating-task-form').css('right', '0');
                });
                $('#task-form-backdrop').fadeIn(300);
                $('body').addClass('task-form-open');
                $('body').css('overflow', 'hidden');
            });

            function closeQuickAssigneePanel() {
                var $panel = $('#quick_assignee_ms_panel');
                if (!$panel.length || !$panel.hasClass('is-open')) return;
                $panel.removeClass('is-open');
                $('#quick_assignee_ms_trigger').removeClass('is-open').attr('aria-expanded', 'false');
                $('#quick_assignee_ms_search').val('');
                $('#quick_assignee_ms_list .quick-assignee-ms-item').removeClass('is-hidden');
            }

            function positionQuickAssigneePanel() {
                var $btn = $('#quick_assignee_ms_trigger');
                var $panel = $('#quick_assignee_ms_panel');
                if (!$btn.length || !$panel.length || !$panel.hasClass('is-open')) return;
                var r = $btn[0].getBoundingClientRect();
                var spaceBelow = window.innerHeight - r.bottom - 8;
                var maxH = Math.min(330, Math.max(180, spaceBelow));
                $panel.css({
                    top: (r.bottom + 3) + 'px',
                    left: r.left + 'px',
                    width: r.width + 'px',
                    maxHeight: maxH + 'px'
                });
                $panel.find('.quick-assignee-ms-list').css('max-height', Math.max(120, maxH - 66) + 'px');
            }

            function openQuickAssigneePanel() {
                var $panel = $('#quick_assignee_ms_panel');
                if (!$panel.length) return;
                $panel.addClass('is-open');
                $('#quick_assignee_ms_trigger').addClass('is-open').attr('aria-expanded', 'true');
                positionQuickAssigneePanel();
                setTimeout(function () {
                    $('#quick_assignee_ms_search').trigger('focus');
                }, 0);
            }

            (function initQuickAssigneeMultiselect() {
                var $dataEl = $('#quick-assignee-users-data');
                var $list = $('#quick_assignee_ms_list');
                var $wrap = $('#quick_assignee_hidden_wrap');
                if (!$dataEl.length || !$list.length) return;

                var users = [];
                try {
                    users = JSON.parse($dataEl.text());
                } catch (e) {
                    return;
                }
                if (!Array.isArray(users)) return;

                var selected = new Map();

                users.forEach(function (u) {
                    var id = u.id;
                    var $li = $('<li/>', {
                        'class': 'quick-assignee-ms-item',
                        'role': 'option',
                        'tabindex': -1,
                        'data-id': id,
                        'aria-selected': 'false'
                    });
                    $li.append(
                        $('<span/>', { 'class': 'quick-assignee-ms-box', 'aria-hidden': 'true' }).text('✓'),
                        $('<span/>').text(u.name)
                    );
                    $list.append($li);
                });

                function syncHidden() {
                    $wrap.empty();
                    selected.forEach(function (_name, id) {
                        $wrap.append($('<input/>', { type: 'hidden', name: 'assignee_ids[]', value: id }));
                    });
                }

                function updateLabel() {
                    var $lab = $('#quick_assignee_ms .quick-assignee-ms-label');
                    var $btn = $('#quick_assignee_ms_trigger');
                    if (selected.size === 0) {
                        $lab.text('Select assignees').removeClass('has-value').addClass('text-muted');
                        $btn.attr('title', 'Select one or more people; one shared task is created with all assignees');
                        return;
                    }
                    var text = Array.from(selected.values()).join(', ');
                    $lab.text(text).addClass('has-value').removeClass('text-muted');
                    $btn.attr('title', text);
                }

                $list.on('click', '.quick-assignee-ms-item', function () {
                    var id = parseInt($(this).data('id'), 10);
                    var u = users.find(function (x) { return x.id === id; });
                    if (!u) return;
                    if (selected.has(id)) {
                        selected.delete(id);
                        $(this).removeClass('is-selected').attr('aria-selected', 'false');
                    } else {
                        selected.set(id, u.name);
                        $(this).addClass('is-selected').attr('aria-selected', 'true');
                    }
                    syncHidden();
                    updateLabel();
                });

                $('#quick_assignee_ms_search').on('input', function () {
                    var q = $(this).val().toLowerCase().trim();
                    $list.find('.quick-assignee-ms-item').each(function () {
                        var text = $(this).text().toLowerCase();
                        $(this).toggleClass('is-hidden', q.length > 0 && text.indexOf(q) === -1);
                    });
                });

                $('#quick_assignee_ms_trigger').on('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var $panel = $('#quick_assignee_ms_panel');
                    if ($panel.hasClass('is-open')) {
                        closeQuickAssigneePanel();
                    } else {
                        openQuickAssigneePanel();
                    }
                });

                $(document).on('mousedown.quickAssigneeMs', function (e) {
                    if (!$('#quick_assignee_ms_panel').hasClass('is-open')) return;
                    if ($(e.target).closest('#quick_assignee_ms').length) return;
                    closeQuickAssigneePanel();
                });

                $(window).on('resize.quickAssigneeMs scroll.quickAssigneeMs', function () {
                    if ($('#quick_assignee_ms_panel').hasClass('is-open')) {
                        positionQuickAssigneePanel();
                    }
                });

                $('#floating-task-form').on('scroll.quickAssigneeMs', function () {
                    if ($('#quick_assignee_ms_panel').hasClass('is-open')) {
                        positionQuickAssigneePanel();
                    }
                });

                window.resetQuickTaskAssignees = function () {
                    selected.clear();
                    $list.find('.quick-assignee-ms-item').removeClass('is-selected').attr('aria-selected', 'false');
                    syncHidden();
                    updateLabel();
                };
            })();

            // Close floating task form
            function closeTaskForm() {
                closeQuickAssigneePanel();
                $('#floating-task-form').css('right', getTaskFormClosedOffset());
                $('#task-form-backdrop').fadeOut(300);
                $('body').removeClass('task-form-open');
                $('body').css('overflow', 'auto');
                clearTimeout(hideTaskFormTimer);
                hideTaskFormTimer = setTimeout(function () {
                    $('#floating-task-form').hide();
                }, 320);
            }

            $('#close-task-form-btn').on('click', closeTaskForm);
            $('#task-form-backdrop').on('click', closeTaskForm);

            // Keep closed position in sync when viewport changes.
            $(window).on('resize', function() {
                if (!$('body').hasClass('task-form-open')) {
                    $('#floating-task-form').css('right', getTaskFormClosedOffset());
                }
            });
            
            // Close on Escape key (assignee panel first, then whole form)
            $(document).on('keydown', function(e) {
                if (e.key !== 'Escape' || !$('body').hasClass('task-form-open')) return;
                if ($('#quick_assignee_ms_panel').hasClass('is-open')) {
                    closeQuickAssigneePanel();
                    return;
                }
                closeTaskForm();
            });

            // Keep modal closed on first load and browser page restore.
            ensureTaskFormClosed();
            window.addEventListener('pageshow', ensureTaskFormClosed);

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

            function resetQuickTaskFormAfterSave() {
                var form = document.getElementById('quick-task-form');
                if (!form) {
                    return;
                }
                form.reset();
                $('input[name="priority"]').val('normal');
                var now = new Date();
                function pad(n) { return n < 10 ? '0' + n : '' + n; }
                var tidVal = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                $('input[name="tid"]').val(tidVal);
                $('input[name="tid_override"]').val(tidVal);
                $('select[name="priority_override"]').val('normal');
                $('#quick_etc_minutes').val('10');
                if (typeof window.resetQuickTaskAssignees === 'function') {
                    window.resetQuickTaskAssignees();
                }
                $('#quick-additional-fields').hide();
                $('#toggle-quick-more-fields').html('<i class="mdi mdi-chevron-down" id="quick-toggle-icon"></i> More');
                closeQuickAssigneePanel();
            }

            $('#quick-task-save-create-more-btn').on('click', function () {
                var $form = $('#quick-task-form');
                var title = $.trim($('#quick_title').val() || '');
                if (!title) {
                    alert('Please enter a task title.');
                    $('#quick_title').trigger('focus');
                    return;
                }
                var priorityOverride = $('select[name="priority_override"]').val();
                if (priorityOverride) {
                    $('input[name="priority"]').val(priorityOverride);
                }
                var tidOverride = $('input[name="tid_override"]').val();
                if (tidOverride) {
                    $('input[name="tid"]').val(tidOverride);
                }

                var formData = new FormData($form[0]);
                formData.append('quick_create_more', '1');

                $('#quick-task-success-alert').addClass('d-none').empty();
                clearTimeout(window.__quickTaskAlertTimer);

                var $btn = $(this);
                $btn.prop('disabled', true);
                fetch($form.attr('action'), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                }).then(function (res) {
                    var ct = res.headers.get('content-type') || '';
                    if (res.status === 422) {
                        return res.json().then(function (err) {
                            var msg = 'Validation failed.';
                            if (err.errors) {
                                msg = Object.values(err.errors).flat().join('\n');
                            }
                            alert(msg);
                        });
                    }
                    if (!res.ok) {
                        alert('Could not save task. Please try again.');
                        return;
                    }
                    if (ct.indexOf('application/json') === -1) {
                        window.location.reload();
                        return;
                    }
                    return res.json().then(function (data) {
                        var msg = (data && data.message) ? data.message : 'Task generated successfully!';
                        var $alert = $('#quick-task-success-alert');
                        $alert.text(msg).removeClass('d-none');
                        resetQuickTaskFormAfterSave();
                        if (window.toastr && typeof toastr.success === 'function') {
                            toastr.success(msg);
                        }
                        clearTimeout(window.__quickTaskAlertTimer);
                        window.__quickTaskAlertTimer = setTimeout(function () {
                            $alert.addClass('d-none').empty();
                        }, 8000);
                    });
                }).catch(function () {
                    alert('Network error. Please try again.');
                }).finally(function () {
                    $btn.prop('disabled', false);
                });
            });
        });
    </script>
    @endunless

    @yield('modal')

    @include('layouts.shared/right-sidebar')
    
    <!-- Mobile Bottom Navigation -->
    @include('layouts.mobile-bottom-nav')

    @include('layouts.shared/footer-scripts')

    @vite(['resources/js/layout.js', 'resources/js/main.js'])

    {{-- Runs after Vite so jQuery matches head.js; DataTables and similar plugins attach here --}}
    @yield('script-after-vite')

    @include('components.ai-chat-widget')
    
    <!-- PWA Service Worker Registration with Error Handling -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                @if (config('app.debug'))
                // Local dev: SW caches HTML and causes stale CSRF meta → 419 on POST. Disable SW when APP_DEBUG=true.
                navigator.serviceWorker.getRegistrations().then(function (registrations) {
                    registrations.forEach(function (r) { r.unregister(); });
                });
                return;
                @endif
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
