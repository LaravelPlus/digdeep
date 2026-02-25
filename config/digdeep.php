<?php

return [

    'enabled' => env('DIGDEEP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto Profile
    |--------------------------------------------------------------------------
    |
    | When true, every web request is automatically profiled via middleware.
    | When false, only manually-triggered profiles are captured.
    |
    */

    'auto_profile' => env('DIGDEEP_AUTO_PROFILE', true),

    'max_profiles' => 200,

    /*
    |--------------------------------------------------------------------------
    | Threshold Alerts
    |--------------------------------------------------------------------------
    |
    | Profiles exceeding these thresholds are auto-tagged and fire
    | a ThresholdExceeded event for notification hooks.
    |
    */

    'thresholds' => [
        'duration_ms' => env('DIGDEEP_THRESHOLD_DURATION', 500),
        'query_count' => env('DIGDEEP_THRESHOLD_QUERIES', 20),
        'memory_peak_mb' => env('DIGDEEP_THRESHOLD_MEMORY', 64),
        'query_time_ms' => env('DIGDEEP_THRESHOLD_QUERY_TIME', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Profile Size (KB)
    |--------------------------------------------------------------------------
    |
    | Maximum JSON payload size for a single profile. Profiles exceeding
    | this limit have their request/response bodies and query bindings
    | truncated to stay within bounds.
    |
    */

    'max_profile_size_kb' => env('DIGDEEP_MAX_PROFILE_SIZE', 512),

    'ignored_paths' => [
        'digdeep',
        '_debugbar',
        'telescope',
        'horizon',
        'livewire',
        'favicon.ico',
    ],

];
