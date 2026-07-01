<?php

return [

    /*
    | Emails allowed to open Team Management (/users/add).
    */
    'viewer_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software@5core.com',
        'software5@5core.com',
    ],

    /*
    | Emails that can view the Salary tab.
    */
    'salary_viewer_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software@5core.com',
        'software5@5core.com',
    ],

    /*
    | Full access: edit users on Users tab (not salary tab — see salary_viewer_emails).
    */
    'editor_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software5@5core.com',
    ],

    /*
    | Emails that can SEE the Resume column on the Users table.
    */
    'resume_viewer_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software5@5core.com',
    ],

    /*
    | Emails that can ADD/EDIT (upload, replace, delete) a user's resume file and docs.
    */
    'resume_editor_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software5@5core.com',
    ],

];
