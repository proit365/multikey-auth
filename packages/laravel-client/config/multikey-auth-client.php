<?php

declare(strict_types=1);

return [
    'base_uri' => env('MKAP_BASE_URI'),
    'client_id' => env('MKAP_CLIENT_ID'),
    'pool_version' => (int) env('MKAP_POOL_VERSION', 1),
    'identifier_length' => (int) env('MKAP_IDENTIFIER_LENGTH', 6),
    'identifier_alphabet' => env(
        'MKAP_IDENTIFIER_ALPHABET',
        'abcdefghijklmnopqrstuvwxyz0123456789'
    ),
    'export_path' => env('MKAP_EXPORT_PATH'),
    'require_https' => (bool) env('MKAP_REQUIRE_HTTPS', true),
    'maximum_challenge_response_bytes' => 65_536,
];
