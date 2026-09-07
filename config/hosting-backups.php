<?php

return [
    // Pre-create private directories outside all website source trees.
    'archive_root' => env('HOSTING_BACKUP_ROOT'),
    'restore_root' => env('HOSTING_RESTORE_ROOT'),

    // Keep old keys when rotating: existing snapshots refer to their key ID.
    'active_key' => 'v1',
    'keys' => ['v1' => env('HOSTING_BACKUP_KEY')],

    // Operator-controlled aliases, never paths supplied by panel users.
    // 'example-site' => ['team_id' => '123', 'policy_id' => 'uuid', 'path' => '/srv/example'],
    'sources' => [],

    'max_files' => 10000,
    'max_bytes' => 1024 * 1024 * 1024,
    'max_manifest_bytes' => 4 * 1024 * 1024,
];
