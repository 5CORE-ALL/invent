<?php

return [

    /*
    | Emails allowed to open Team Management (/users/add).
    */
    'viewer_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software@5core.com',
        'tech-support@5core.com',
    ],

    /*
    | Emails that can view the Salary tab (not granted to tech-support).
    */
    'salary_viewer_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software@5core.com',
    ],

    /*
    | Emails that can edit users, import/export salary, and manage inactive users.
    */
    'editor_emails' => [
        'president@5core.com',
        'hr@5core.com',
    ],

];
