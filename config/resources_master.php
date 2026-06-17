<?php

return [

    'disk' => env('RESOURCES_MASTER_DISK', 'resources_master'),

    /*
    | Max upload size in kilobytes (server php.ini may still cap lower).
    */
    'max_upload_kb' => (int) env('RESOURCES_MASTER_MAX_KB', 102400), // 100 MB default

    'categories' => [
        'rr_files' => 'R&R Files',
        'training_resources' => 'Training Resources',
        'checklist_forms' => 'Checklist Forms',
        'media_gallery' => 'Media Gallery',
        'links_videos' => 'Links / Videos',
    ],

    /*
    | Emails allowed to permanently delete (force delete) resources.
    */
    'force_delete_emails' => [
        'president@5core.com',
        'software5@5core.com',
        'software@5core.com',
    ],

    /*
    | Emails that can upload, edit, restore, and manage metadata (in addition to policy rules).
    */
    'manager_emails' => [
        'president@5core.com',
        'software5@5core.com',
        'software@5core.com',
    ],

    'blocked_extensions' => [
        'php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'com', 'scr', 'dll', 'sh', 'ps1',
        'vbs', 'js', 'jar', 'app', 'deb', 'rpm', 'msi',
    ],

];
