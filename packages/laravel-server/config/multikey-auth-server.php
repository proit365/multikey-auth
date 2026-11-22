<?php

declare(strict_types=1);

return [
    'database_connection' => env('MKAP_DATABASE_CONNECTION'),
    'master_key_provider' => env('MKAP_MASTER_KEY_PROVIDER'),
    'client_pool_provider' => null,
    'challenge_lifetime_seconds' => 30,
    'maximum_challenge_body_bytes' => 65_536,
    'maximum_authenticated_body_bytes' => 1_048_576,
    'rate_limits' => [
        'challenge' => 60,
        'authenticate' => 120,
        'decay_seconds' => 60,
    ],
    'routes' => [
        'enabled' => false,
        'challenge_path' => 'v1/auth/challenges',
        'middleware' => ['api'],
    ],
];
