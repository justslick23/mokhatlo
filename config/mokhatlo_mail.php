<?php

return [
    // Keep disabled until SMTP, PDF dependencies, and trial runs have been verified.
    'enabled' => (bool) env('MOKHATLO_MAIL_ENABLED', true),
    // UTC timestamp when automatic transaction capture may start; never back-email legacy imports.
    'started_at' => env('MOKHATLO_MAIL_STARTED_AT'),
    'max_attempts' => (int) env('MOKHATLO_MAIL_MAX_ATTEMPTS', 5),
    'process_limit' => (int) env('MOKHATLO_MAIL_BATCH_SIZE', 40),
];
