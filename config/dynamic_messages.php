<?php

return [

    /*
    | Only this account can create / edit / delete / run message rules.
    | (Exact, case-insensitive email match.)
    */
    'editor_email' => 'president@5core.com',

    /*
    | Account that receives the optional replies from recipients (Amarjit).
    | Replies are shown to this user under /dynamic-messages -> Replies.
    */
    'reply_to_email' => 'president@5core.com',
    'reply_to_label' => 'Amarjit',

    /*
    | Assignor stamped on every task created by a dynamic task rule (Amarjit).
    | Stored as an email, same as tasks created from the Tasks page.
    */
    'task_assignor_email' => 'president@5core.com',

    /*
    | Timezone used for "once a day / week" de-duplication and the date placeholders.
    */
    'timezone' => 'Asia/Kolkata',
];
