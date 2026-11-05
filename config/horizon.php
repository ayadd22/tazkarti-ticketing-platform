<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    */

    'name' => env('HORIZON_NAME', env('APP_NAME', 'Tazkarti')),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |
    | The named Redis connection (defined in config/database.php) that Horizon
    | uses to store its meta-information. Must be a Redis connection, not the
    | database queue driver. The application queue driver may differ.
    |--------------------------------------------------------------------------
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'tazkarti'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds (seconds)
    |--------------------------------------------------------------------------
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times (minutes)
    |--------------------------------------------------------------------------
    */

    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,   // 1 week
        'failed'        => 10080,   // 1 week
        'monitored'     => 10080,   // 1 week
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |
    | Maximum memory the Horizon master supervisor may use before restart.
    |--------------------------------------------------------------------------
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |
    | 'defaults' defines the base supervisor template shared across all
    | environments. Each environment entry overrides only the keys it needs.
    |
    | Queues monitored:
    |   - default   : general jobs (SendReservationConfirmation, etc.)
    |
    | tries and timeout are left at defaults here; retry policies belong to
    | Task 11.
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'supervisor-1' => [
            'connection'           => 'redis',
            'queue' => ['notifications', 'ticket-files', 'default'],
            'balance'              => 'auto',
            'autoScalingStrategy'  => 'time',
            'maxProcesses'         => 1,
            'maxTime'              => 0,
            'maxJobs'              => 0,
            'memory'               => 128,
            'tries'                => 1,
            'timeout'              => 60,
            'nice'                 => 0,
        ],
    ],

    'environments' => [

        // ── Local development ─────────────────────────────────────────────────
        // Single worker process; fast feedback without resource overhead.
        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 1,
            ],
        ],

        // ── Staging / testing ─────────────────────────────────────────────────
        // Light multi-process setup suitable for pre-production verification.
        'staging' => [
            'supervisor-1' => [
                'maxProcesses'     => 3,
                'balanceMaxShift'  => 1,
                'balanceCooldown'  => 3,
            ],
        ],

        // ── Production ────────────────────────────────────────────────────────
        // Higher concurrency with gradual auto-scaling.
        'production' => [
            'supervisor-1' => [
                'maxProcesses'    => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher (used with `horizon:listen`)
    |--------------------------------------------------------------------------
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],

];
