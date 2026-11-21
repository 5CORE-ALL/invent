<?php

/*
 * Personal sending mailboxes for forwarder / compliance / QC mails, one per user, all driven by .env.
 * List the users in QUOTE_MAIL_USERS (comma separated, default "CANDY"); for each KEY add:
 *   QUOTE_MAIL_<KEY>_USERNAME  mailbox address (Google Workspace)            - required
 *   QUOTE_MAIL_<KEY>_PASSWORD  Google app password for that mailbox          - required (without it the user falls back to sourcing1@5core.com)
 *   QUOTE_MAIL_<KEY>_NAME      sender display name                           - optional
 *   QUOTE_MAIL_<KEY>_MATCH     comma separated, matched against the logged-in user's name / login email - optional (default: the key and the mailbox address)
 * Users that are not listed (or have no password yet) send from sourcing1@5core.com.
 */
$quoteSenders = [];
$quoteUserMailers = [];
foreach (array_filter(array_map('trim', explode(',', (string) env('QUOTE_MAIL_USERS', 'CANDY')))) as $quoteKey) {
    $K = strtoupper(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $quoteKey), '_'));
    if ($K === '') {
        continue;
    }
    $isCandy = $K === 'CANDY'; // keeps the original Candy defaults
    $qUser = trim((string) env("QUOTE_MAIL_{$K}_USERNAME", $isCandy ? 'purchase@5core.com' : ''));
    if ($qUser === '') {
        continue;
    }
    $qName = trim((string) env("QUOTE_MAIL_{$K}_NAME", $isCandy ? 'Candy - 5Core Purchase' : ucfirst(strtolower($K)).' - 5Core'));
    $qMatch = array_values(array_filter(array_map(
        fn ($m) => mb_strtolower(trim($m)),
        explode(',', (string) env("QUOTE_MAIL_{$K}_MATCH", strtolower($K).','.$qUser))
    )));
    $qMailer = 'quotes_'.strtolower($K);
    $quoteSenders[strtolower($K)] = ['match' => $qMatch, 'mailer' => $qMailer, 'from_email' => $qUser, 'from_name' => $qName];
    $quoteUserMailers[$qMailer] = [
        'transport' => 'smtp',
        'host' => env('QUOTE_MAIL_HOST', 'smtp.gmail.com'),
        'port' => env('QUOTE_MAIL_PORT', 587),
        'encryption' => env('QUOTE_MAIL_ENCRYPTION', 'tls'),
        'username' => $qUser,
        'password' => env("QUOTE_MAIL_{$K}_PASSWORD"),
        'timeout' => env('MAIL_TIMEOUT', 15),
        'local_domain' => env('MAIL_EHLO_DOMAIN'),
    ];
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    // Mailer used for forwarder quote requests (see the 'quotes' mailer below).
    'quote_mailer' => env('QUOTE_MAILER', 'quotes'),

    // Users who send from their own mailbox instead of sourcing1@5core.com (built from .env above).
    // A user without a password in .env, or not listed, falls back to the sourcing mailbox.
    'quote_senders' => $quoteSenders,

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "log", "array", "failover"
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => env('MAIL_TIMEOUT', 15),
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        // Used for forwarder quote requests, sent as sourcing1@5core.com.
        // Needs QUOTE_MAIL_PASSWORD (a Google app password for that mailbox) in .env.
        'quotes' => [
            'transport' => 'smtp',
            'host' => env('QUOTE_MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('QUOTE_MAIL_PORT', 587),
            'encryption' => env('QUOTE_MAIL_ENCRYPTION', 'tls'),
            'username' => env('QUOTE_MAIL_USERNAME', 'sourcing1@5core.com'),
            'password' => env('QUOTE_MAIL_PASSWORD'),
            'timeout' => env('MAIL_TIMEOUT', 15),
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ],

        // Per-user mailboxes (quotes_<key>) are added below from .env, see the top of this file.

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],
    ] + $quoteUserMailers,

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'tech-support@5core.com'),
        'name' => env('MAIL_FROM_NAME', '5Core AI Assistant'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
