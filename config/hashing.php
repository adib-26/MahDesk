<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password hashing
    |--------------------------------------------------------------------------
    |
    | Passwords are hashed with Argon2id, OWASP's first recommendation. It is
    | memory-hard (expensive to brute-force on GPUs) and, unlike bcrypt, uses
    | the whole password instead of only the first 72 bytes.
    |
    | The "argon2id" driver is App\Hashing\UpgradingArgon2IdHasher. It also
    | accepts bcrypt hashes created before the switch and upgrades them to
    | Argon2id when the user next signs in (App\Auth\PasswordVerifier).
    |
    | Costs below are Laravel's defaults (64 MiB, 4 passes, 1 thread), above
    | OWASP's minimum (19 MiB, 2 passes). Hashes made with lower costs are
    | upgraded on sign-in too. Tests use cheaper values from phpunit.xml.
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        // Refuse to hash input longer than bcrypt can use instead of silently truncating it.
        'limit' => env('BCRYPT_LIMIT', 72),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    'rehash_on_login' => true,

];
