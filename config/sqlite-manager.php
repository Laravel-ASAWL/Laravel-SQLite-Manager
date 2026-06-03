<?php

declare(strict_types=1);

return [
    'database_path' => env('SQLITE_MANAGER_DATABASE_PATH', database_path('database.sqlite')),

    'connections' => [
        'default' => env('SQLITE_MANAGER_DATABASE_PATH', database_path('database.sqlite')),
    ],

    'routes' => [
        'enabled' => env('SQLITE_MANAGER_ROUTES_ENABLED', true),
        'prefix' => env('SQLITE_MANAGER_ROUTE_PREFIX', 'sqlite-manager'),
        'middleware' => ['web'],
    ],

    'security' => [
        'allowed_environments' => ['local', 'testing'],
        'authorization_gate' => null,
        'read_only' => env('SQLITE_MANAGER_READ_ONLY', false),
        'gates' => [
            'access' => null,
            'view' => null,
            'create' => null,
            'update' => null,
            'delete' => null,
            'bulk_delete' => null,
            'export' => null,
            'import' => null,
        ],
        'limits' => [
            'max_delete_rows' => 100,
            'max_page_size' => 100,
        ],
    ],

    'tables' => [
        'show_laravel_tables' => env('SQLITE_MANAGER_SHOW_LARAVEL_TABLES', false),
        'show_test_tables' => env('SQLITE_MANAGER_SHOW_TEST_TABLES', false),
        'show_soft_deleted' => false,
        'allow' => [],
        'deny' => [],
        'laravel_table_patterns' => [
            'cache',
            'cache_locks',
            'failed_jobs',
            'job_batches',
            'jobs',
            'migrations',
            'password_reset_tokens',
            'sessions',
            'telescope_*',
        ],
        'test_table_patterns' => [
            '_lsm_test_*',
        ],
    ],

    'validation' => [
        'rules' => [],
    ],

    'audit' => [
        'enabled' => env('SQLITE_MANAGER_AUDIT_ENABLED', true),
        'table' => '_lsm_audit_log',
    ],

    'exports' => [
        'max_rows' => 5000,
    ],

    'imports' => [
        'max_rows' => 500,
    ],

    'ui' => [
        'theme' => 'auto',
    ],

    'pagination' => [
        'per_page_options' => [5, 10, 25, 50, 100],
        'default_per_page' => 10,
    ],
];
