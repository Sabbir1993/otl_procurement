<?php

return [
    // Local-only conveniences. NEVER enable in production (bypasses passwords / allows role hopping).
    'demo_login' => env('DEMO_LOGIN', env('APP_ENV') === 'local'),

    // Case opened by default for a new session.
    'default_case' => 'IC-2026-10',
];
