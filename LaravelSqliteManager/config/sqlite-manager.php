<?php

declare(strict_types=1);

return [
    'database_path' => env('SQLITE_MANAGER_DATABASE_PATH', database_path('database.sqlite')),

    'routes' => [
        'enabled' => env('SQLITE_MANAGER_ROUTES_ENABLED', true),
        'prefix' => env('SQLITE_MANAGER_ROUTE_PREFIX', 'sqlite-manager'),
        'middleware' => ['web'],
    ],

    'tables' => [
        'show_laravel_tables' => env('SQLITE_MANAGER_SHOW_LARAVEL_TABLES', false),
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
    ],

    'pagination' => [
        'per_page_options' => [5, 10, 25, 50, 100],
        'default_per_page' => 10,
    ],
];
