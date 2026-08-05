<?php

return [

    /*
    | Emails allowed to manage payroll (create months, edit, lock, release).
    */
    'manager_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software5@5core.com',
    ],

    /*
    | Emails allowed to sync employees from Team and download the month sheet.
    | Restricted to HR and President only (subset of manager_emails).
    */
    'sheet_admin_emails' => [
        'president@5core.com',
        'hr@5core.com',
        'software5@5core.com',
    ],

    /*
    | Directors omitted from the bank payout / salary sheet download.
    | Matched by email or by full/first name (case-insensitive), in addition to
    | users whose org_level is "director".
    */
    'payout_sheet_exclude_emails' => [
        'president@5core.com',       // Amarjit
        'ritu.kaur013@gmail.com',    // RITU
        'inventory@5core.com',       // Ritu
        'ineetkalra@5core.com',      // Ineet
        'sr.manager@5core.com',      // Jasmine
    ],

    'payout_sheet_exclude_names' => [
        'amarjit',
        'ritu',
        'ineet',
        'priyanka',
        'jasmine',
    ],

    /*
    | Map user email → TeamLogger email when they differ.
    */
    'email_mapping' => [
        'adexec1@5core.com' => 'support@prolightsounds.com',
        // Hritiksha Deb (Manager Operation): app email differs from TeamLogger.
        'mgr-operations@5core.com' => 'mrg-operations@5core.com',
    ],

    /*
    | Standard hours divisor for pro-rata salary (matches Team Management export).
    */
    'hours_divisor' => 200,

    /*
    | Round net pay to nearest N rupees (0 = no rounding).
    */
    'round_net_to' => 100,

    'payslip_formats' => [
        'standard' => 'Standard Payslip',
        'detailed' => 'Detailed (with components)',
        'compact' => 'Compact Summary',
    ],

    'company' => [
        'name' => '5 CORE INC.',
        'tagline' => 'OHIO, USA.',
        'address' => '1221 W Sandusky Ave Suite C, Bellefontaine OH 43311',
        'email' => 'hr@5core.com',
        'website' => 'www.5core.com',
        'logo' => '/images/payroll/5core-logo.png',
        'watermark' => '/images/payroll/5core-stamp-red.png',
    ],

    'month_statuses' => [
        'draft' => 'Draft',
        'processing' => 'Processing',
        'processed' => 'Processed',
        'released' => 'Released',
    ],

];
