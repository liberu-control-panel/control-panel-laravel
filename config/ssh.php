<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSH Connection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SSH connections to remote servers
    |
    */

    'timeout' => env('SSH_TIMEOUT', 30),

    'retry_attempts' => env('SSH_RETRY_ATTEMPTS', 3),

    'retry_delay' => env('SSH_RETRY_DELAY', 5),

    'connection_pool_size' => env('SSH_CONNECTION_POOL_SIZE', 10),

    'keepalive_interval' => env('SSH_KEEPALIVE_INTERVAL', 60),

    /*
    |--------------------------------------------------------------------------
    | SSH Key Storage
    |--------------------------------------------------------------------------
    |
    | Location for storing SSH keys
    |
    */

    'keys_storage_path' => env('SSH_KEYS_STORAGE_PATH', storage_path('app/ssh-keys')),

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        'allowed_ciphers' => [
            'aes256-ctr',
            'aes192-ctr',
            'aes128-ctr',
        ],
        'allowed_macs' => [
            'hmac-sha2-256',
            'hmac-sha2-512',
        ],
        'allowed_key_types' => [
            'ssh-rsa',
            'ssh-ed25519',
            'ecdsa-sha2-nistp256',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sudo Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for running commands with limited privileges
    |
    */

    'sudo' => [
        'enabled' => env('SSH_SUDO_ENABLED', false),
        'password_required' => env('SSH_SUDO_PASSWORD_REQUIRED', true),
        'allowed_commands' => [
            'kubectl',
            'docker',
            'systemctl',
        ],
    ],
];
