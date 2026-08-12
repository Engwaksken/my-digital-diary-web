<?php

/**
 * NOT a real config file — a documentation snippet, same pattern as
 * routes/auth-otp-additions.php. Your app already has its own
 * config/services.php (with mailgun/postmark/ses/slack entries etc.) —
 * don't overwrite it. Just add this ONE array entry into the existing
 * array Laravel's config/services.php returns:
 */

return [
    // ... your existing entries (mailgun, postmark, ses, slack, etc.) stay exactly as they are ...

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
    ],
];
